<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] != "" && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$real_games = array();
	$coin_rpcs = array();
	$game_id2real_game_i = array();
	
	$q = "SELECT * FROM games WHERE game_type='real';";
	$r = $app->run_query($q);
	$real_game_i = 0;
	
	while ($db_real_game = $r->fetch()) {
		$game_id2real_game_i[$db_real_game['game_id']] = $real_game_i;
		$real_games[$real_game_i] = new Game($app, $db_real_game['game_id']);
		$coin_rpcs[$real_game_i] = new jsonRPCClient('http://'.$real_games[$real_game_i]->db_game['rpc_username'].':'.$real_games[$real_game_i]->db_game['rpc_password'].'@127.0.0.1:'.$real_games[$real_game_i]->db_game['rpc_port'].'/');
		echo 'connecting '.$real_games[$real_game_i]->db_game['rpc_username'].':'.$real_games[$real_game_i]->db_game['rpc_password'].'@127.0.0.1:'.$real_games[$real_game_i]->db_game['rpc_port'].' for '.$real_games[$real_game_i]->db_game['name']."<br/>\n";
		$real_game_i++;
	}
	
	for ($real_game_i=0; $real_game_i<count($real_games); $real_game_i++) {
		if ($real_games[$real_game_i]->db_game['min_unallocated_addresses'] > 0) {
			$need_addresses = false;
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$real_games[$real_game_i]->db_game['game_id']."' ORDER BY option_id ASC;";
			$r = $app->run_query($q);
			while ($option = $r->fetch()) {
				$qq = "SELECT * FROM addresses WHERE game_id='".$real_games[$real_game_i]->db_game['game_id']."' AND option_id='".$option['option_id']."' AND user_id IS NULL AND is_mine=1;";
				$rr = $app->run_query($qq);
				$num_addr = $rr->rowCount();
				
				if ($num_addr < $real_games[$real_game_i]->db_game['min_unallocated_addresses']) {
					echo "Generate ".($real_games[$real_game_i]->db_game['min_unallocated_addresses']-$num_addr)." unallocated ".$option['name']." addresses in \"".$real_games[$real_game_i]->db_game['name']."\"<br/>\n";
					try {
						for ($i=0; $i<($real_games[$real_game_i]->db_game['min_unallocated_addresses']-$num_addr); $i++) {
							$new_addr_str = $coin_rpcs[$real_game_i]->getnewvotingaddress($option['name']);
							$new_addr_db = $real_games[$real_game_i]->create_or_fetch_address($new_addr_str, false, $coin_rpcs[$real_game_i], true, false);
						}
					}
					catch (Exception $e) {
						$new_voting_addr_count = 0;
						do {
							$temp_address = $coin_rpcs[$real_game_i]->getnewaddress();
							$new_addr_db = $real_games[$real_game_i]->create_or_fetch_address($temp_address, false, $coin_rpcs[$real_game_i], true, false);
							if ($new_addr_db['option_id'] == $option['option_id']) $new_voting_addr_count++;
						}
						while ($new_voting_addr_count < ($real_games[$real_game_i]->db_game['min_unallocated_addresses']-$num_addr));
					}
				}
			}
		}
	}
	echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
}
else echo "Error: incorrect key supplied in cron/address_miner.php\n";
?>
