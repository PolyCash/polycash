<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/classes/CoinbaseClient.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "check_peers_in_sync";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$db_running_games = $app->fetch_running_games()->fetchAll();
		
		if ($print_debug) $app->print_debug("Checking sync status for ".count($db_running_games)." games.");
		
		$sec_between_sync_checks = 10*60;
		$sec_between_reset_checksums = 60*60*3;
		$txos_per_partition = PeerVerifier::txosPerPartition();
		
		foreach ($db_running_games as $db_running_game) {
			$blockchain = new Blockchain($app, $db_running_game['blockchain_id']);
			$running_game = new Game($blockchain, $db_running_game['game_id']);
			
			if ($running_game->last_block_id() == $blockchain->last_block_id()) {
				if (empty($running_game->db_game['last_reset_checksums_at']) || $running_game->db_game['last_reset_checksums_at'] <= time()-$sec_between_reset_checksums) {
					$running_game->reset_partition_checksums($print_debug);
				}
				
				if ($print_debug) $app->print_debug("Setting partition checksums for ".$running_game->db_game['name']);
				
				$running_game->set_partition_checksums($txos_per_partition, $print_debug);
				
				$game_peers = $running_game->fetch_all_peers();
				$app->print_debug("Checking ".$running_game->db_game['name']." synchronization status against ".count($game_peers)." peers.");
				
				foreach ($game_peers as $game_peer) {
					if (empty($game_peer['last_sync_check_at']) || $game_peer['last_sync_check_at'] <= time()-$sec_between_sync_checks) {
						$last_block_id = $running_game->blockchain->last_block_id();
						$last_block = $running_game->fetch_game_block_by_height($last_block_id);
						
						$peer_info = PeerVerifier::peerApiCall($game_peer, "/api/".$running_game->db_game['url_identifier']."/info");
						
						if (array_key_exists('last_block_id', $peer_info) && $peer_info['last_block_id'] == $last_block_id) {
							$out_of_sync_block = $running_game->peer_out_of_sync_block($game_peer, $txos_per_partition, $last_block, $print_debug);
							
							if ($out_of_sync_block !== null) {
								$app->run_query("UPDATE game_peers SET last_sync_check_at=:last_sync_check_at, last_check_in_sync=:last_check_in_sync, out_of_sync_since=:out_of_sync_since, out_of_sync_block=:out_of_sync_block WHERE game_peer_id=:game_peer_id;", [
									'game_peer_id' => $game_peer['game_peer_id'],
									'last_sync_check_at' => time(),
									'last_check_in_sync' => $out_of_sync_block === false ? 1 : 0,
									'out_of_sync_since' => $out_of_sync_block === false ? null : (empty($game->db_game['out_of_sync_since']) ? time() : $game->db_game['out_of_sync_since']),
									'out_of_sync_block' => $out_of_sync_block === false ? null : $out_of_sync_block,
								]);
							}
						}
						else if ($print_debug) $app->print_debug($running_game->db_game['name']." is not fully loaded locally or on peer ".$game_peer['peer_name'].", skipping sync check.");
					}
					else if ($print_debug) $app->print_debug("Sync check ran recently for ".$game_peer['peer_name'].", skipping sync check.");
				}
			}
			else if ($print_debug) $app->print_debug("Skipping ".$running_game->db_game['name'].", it's not in fully loaded.");
		}
	}
	else echo "This process is already running.\n";
}
else echo "You don't have permission to run this script.\n";
