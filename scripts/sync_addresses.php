<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

	$game_id =$app->get_site_constant('primary_game_id');
	$game = new Game($app, $game_id);
	
	if ($_REQUEST['do'] == "set_option_ids") {
		$game_q = "SELECT * FROM games;";
		$game_r = $app->run_query($game_q);
		
		while ($db_game = $game_r->fetch()) {
			$game = new Game($app, $db_game['game_id']);
			$address_q = "SELECT * FROM addresses WHERE game_id='".$game->db_game['game_id']."';";
			$address_r = $app->run_query($address_q);
			
			while ($address = $address_r->fetch()) {
				$option_id = $game->addr_text_to_option_id($address['address']);
				$qq = "UPDATE addresses SET option_id='".$option_id."' WHERE address_id='".$address['address_id']."';";
				$rr = $app->run_query($qq);
			}
		}
		echo "Done!";
	}
	else if ($_REQUEST['do'] == "reset") {
		$address_q = "SELECT * FROM addresses WHERE game_id='".$game->db_game['game_id']."';";
		$address_r = $app->run_query($address_q);

		$my_addr_count = 0;

		while ($address = $address_r->fetch()) {
			$validate_address = $coin_rpc->validateaddress($address['address']);
			
			if ($validate_address['ismine']) $is_mine = 1;
			else $is_mine = 0;
			
			$q = "UPDATE addresses SET is_mine='".$is_mine."' WHERE address_id='".$address['address_id']."';";
			$r = $app->run_query($q);
			
			if ($is_mine == 1) $my_addr_count++;
		}

		echo "My addresses: ".$my_addr_count."<br/>\n";
	}
	else {
		if ($_REQUEST['hard'] == "1") {
			$address_q = "DELETE FROM addresses WHERE game_id='".$game->db_game['game_id']."';";
			$address_r = $app->run_query($address_q);
		}
		
		$my_addresses = $coin_rpc->listaddressgroupings();
		
		for ($i=0; $i<count($my_addresses); $i++) {
			$group_addresses = $my_addresses[$i];
			
			for ($j=0; $j<count($group_addresses); $j++) {
				$rpc_address = $group_addresses[$j];
				
				$game->create_or_fetch_address($rpc_address[0], true, $coin_rpc, false, true);
			}
		}
		
		$more_addresses = $coin_rpc->listreceivedbyaddress(0, true);
		for ($i=0; $i<count($more_addresses); $i++) {
			$rpc_address = $more_addresses[$i];
			
			$game->create_or_fetch_address($rpc_address['address'], true, $coin_rpc, false, false);
		}
		
		$q = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game->db_game['game_id']."' AND is_mine=1;";
		$r = $app->run_query($q);
		$my_addr_count = $r->fetch(PDO::FETCH_NUM);
		$my_addr_count = $my_addr_count[0];
		
		$q = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game->db_game['game_id']."' AND is_mine=1 AND option_id > 0;";
		$r = $app->run_query($q);
		$voting_addr_count = $r->fetch(PDO::FETCH_NUM);
		$voting_addr_count = $voting_addr_count[0];
		
		echo "You have ".$my_addr_count." addresses ($voting_addr_count voting addresses).<br/>\n";
	}
}
else {
	echo "Please supply the correct key.";
}
?>
