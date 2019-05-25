<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

if ($app->running_as_admin()) {
	$process_lock_name = "address_miner";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = array();
		
		$q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.min_unallocated_addresses > 0 AND g.game_status IN ('published','running') AND b.p2p_mode='rpc';";
		$r = $app->run_query($q);
		echo "Looping through ".$r->rowCount()." games.<br/>\n";
		
		while ($db_game = $r->fetch()) {
			if (empty($blockchains[$db_game['blockchain_id']])) {
				$blockchains[$db_game['blockchain_id']] = new Blockchain($app, $db_game['blockchain_id']);
				$blockchains[$db_game['blockchain_id']]->load_coin_rpc();
			}
			$game = new Game($blockchains[$db_game['blockchain_id']], $db_game['game_id']);
			
			if ($game->db_game['min_unallocated_addresses'] > 0) {
				echo $game->generate_voting_addresses(10);
			}
		}
		
		echo "Done generating addresses at ".round(microtime(true)-$script_start_time, 2)." seconds.\n";
	}
	else echo "Address miner is already running.\n";
}
else echo "Error: incorrect key supplied in cron/address_miner.php\n";
?>
