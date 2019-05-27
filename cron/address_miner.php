<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

if ($app->running_as_admin()) {
	$process_lock_name = "address_miner";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$script_target_time = 49;
		$loop_target_time = 10;
		$blockchains = [];
		$need_address_blockchain_ids = [];
		
		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_status IN ('published','running') AND b.p2p_mode='rpc';";
		$r = $app->run_query($q);
		echo "Looping through ".$r->rowCount()." games.<br/>\n";
		
		while ($db_game = $r->fetch()) {
			$needs_addresses = false;
			
			if (empty($blockchains[$db_game['blockchain_id']])) {
				$blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
			}
			$game = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
			
			list($from_option_index, $to_option_index) = $game->option_index_range();
			
			if ($to_option_index !== false) {
				$addrsets_r = $app->run_query("SELECT * FROM address_sets WHERE game_id='".$game->db_game['game_id']."' AND applied=0 AND (has_option_indices_until IS NULL OR has_option_indices_until<".$to_option_index.");");
				if ($addrsets_r->rowCount() > 0) $needs_addresses = true;
			}
			
			if ($needs_addresses) $need_address_blockchain_ids[$game->db_game['blockchain_id']] = true;
		}
		
		$need_address_blockchain_ids = array_keys($need_address_blockchain_ids);
		
		if (count($need_address_blockchain_ids) > 0) {
			$need_address_db_blockchains = $app->run_query("SELECT * FROM blockchains WHERE blockchain_id IN (".implode(",", $need_address_blockchain_ids).");")->fetchAll();
			$blockchain_loop_i = 0;
			
			do {
				$db_blockchain = $need_address_db_blockchains[$blockchain_loop_i%count($need_address_db_blockchains)];
				echo $blockchains[$db_blockchain['blockchain_id']]->generate_addresses($loop_target_time);
				$app->flush_buffers();
				$blockchain_loop_i++;
			}
			while (microtime(true) < $script_start_time+$script_target_time);
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.\n";
	}
	else echo "Address miner is already running.\n";
}
else echo "Error: incorrect key supplied in cron/address_miner.php\n";
?>
