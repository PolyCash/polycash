<?php
include("../includes/connect.php");
include("../includes/jsonRPCClient.php");

$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

$game_id = get_site_constant('primary_game_id');

$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
$r = run_query($q);
$game = mysql_fetch_array($r);

if ($_REQUEST['do'] == "set_nation_ids") {
	$game_q = "SELECT * FROM games;";
	$game_r = run_query($game_q);
	while ($game = mysql_fetch_array($game_r)) {
		$address_q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."';";
		$address_r = run_query($address_q);
		while ($address = mysql_fetch_array($address_r)) {
			$nation_id = addr_text_to_nation_id($address['address']);
			$qq = "UPDATE addresses SET nation_id='".$nation_id."' WHERE address_id='".$address['address_id']."';";
			$rr = run_query($qq);
		}
	}
	echo "Done!";
}
else if ($_REQUEST['do'] == "reset") {
	$address_q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."';";
	$address_r = run_query($address_q);

	$my_addr_count = 0;

	while ($address = mysql_fetch_array($address_r)) {
		$validate_address = $empirecoin_rpc->validateaddress($address['address']);
		
		if ($validate_address['ismine']) $is_mine = 1;
		else $is_mine = 0;
		
		$q = "UPDATE addresses SET is_mine='".$is_mine."' WHERE address_id='".$address['address_id']."';";
		$r = run_query($q);
		
		if ($is_mine == 1) $my_addr_count++;
	}

	echo "My addresses: ".$my_addr_count."<br/>\n";
}
else {
	$my_addresses = $empirecoin_rpc->listaddressgroupings();
	
	for ($i=0; $i<count($my_addresses); $i++) {
		$group_addresses = $my_addresses[$i];
		
		for ($j=0; $j<count($group_addresses); $j++) {
			$rpc_address = $group_addresses[$j];
			
			create_or_fetch_address($game, $rpc_address[0], true, $empirecoin_rpc, false);
		}
	}
	
	$more_addresses = $empirecoin_rpc->listreceivedbyaddress(0, true);
	for ($i=0; $i<count($more_addresses); $i++) {
		$rpc_address = $more_addresses[$i];
		
		create_or_fetch_address($game, $rpc_address['address'], true, $empirecoin_rpc, false);
	}
	
	$q = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game['game_id']."' AND is_mine=1;";
	$r = run_query($q);
	$my_addr_count = mysql_fetch_row($r);
	$my_addr_count = $my_addr_count[0];
	
	$q = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game['game_id']."' AND is_mine=1 AND nation_id > 0;";
	$r = run_query($q);
	$voting_addr_count = mysql_fetch_row($r);
	$voting_addr_count = $voting_addr_count[0];
	
	echo "You have ".$my_addr_count." addresses ($voting_addr_count voting addresses).<br/>\n";
}
?>