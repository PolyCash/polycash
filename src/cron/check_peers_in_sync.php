<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/models/CoinbaseClient.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug', 'force', 'game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$force = false;
	if (!empty($_REQUEST['force'])) $force=true;
	
	$only_game_id = null;
	if (!empty($_REQUEST['game_id'])) $only_game_id = (int) $_REQUEST['game_id'];
	
	$process_lock_name = "check_peers_in_sync";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked && $app->lock_process($process_lock_name)) {
		if ($only_game_id) $db_running_games = [$app->fetch_game_by_id($only_game_id)];
		else $db_running_games = $app->fetch_running_games()->fetchAll();
		
		if ($print_debug) $app->print_debug("Checking sync status for ".count($db_running_games)." games.");
		
		$sec_between_sync_checks = 10*60;
		$sec_between_reset_checksums = 60*60*3;
		$txos_per_partition = PeerVerifier::txosPerPartition();
		
		foreach ($db_running_games as $db_running_game) {
			$blockchain = new Blockchain($app, $db_running_game['blockchain_id']);
			$running_game = new Game($blockchain, $db_running_game['game_id']);
			$game_extra_info = $running_game->fetch_extra_info();

			if ($running_game->last_block_id() == $blockchain->last_block_id() && empty($game_extra_info['pending_reset'])) {
				if (empty($running_game->db_game['last_reset_checksums_at']) || $running_game->db_game['last_reset_checksums_at'] <= time()-$sec_between_reset_checksums) {
					$running_game->reset_partition_checksums($print_debug);
				}
				
				if ($print_debug) $app->print_debug("Setting partition checksums for ".$running_game->db_game['name']);
				
				list($set_partitions_error, $set_partitions_error_message) = $running_game->set_partition_checksums($txos_per_partition, $print_debug);
				
				if ($print_debug) $app->print_debug("Set partitions error? ".json_encode($set_partitions_error).(!empty($set_partitions_error_message) ? " ".$set_partitions_error_message : ""));
				
				if ($set_partitions_error) {
					$reset_message = "Resetting whole game due to partitions error.";
					$app->log_message($reset_message." ".$set_partitions_error_message, true);
					echo $reset_message."\n";
					
					//$running_game->schedule_game_reset(null, null, null, null);
				}
				else {
					$game_peers = $running_game->fetch_all_peers();
					$app->print_debug("Checking ".$running_game->db_game['name']." synchronization status against ".count($game_peers)." peers.");
					
					foreach ($game_peers as $game_peer) {
						if ($force || empty($game_peer['last_sync_check_at']) || $game_peer['last_sync_check_at'] <= time()-$sec_between_sync_checks) {
							$last_block_id = $running_game->blockchain->last_block_id();
							$last_block = $running_game->fetch_game_block_by_height($last_block_id);
							
							$peer_info = PeerVerifier::peerApiCall($game_peer, "/api/".$running_game->db_game['url_identifier']."/info");
							
							if ($peer_info === null) {
								if ($print_debug) $app->print_debug("API call to peer ".$game_peer['peer_name']." failed.");
							}
							else if (array_key_exists('last_block_id', $peer_info) && $peer_info['last_block_id'] == $last_block_id && array_key_exists('game_definition', $peer_info) && $peer_info['game_definition'] == $running_game->db_game['cached_definition_hash']) {
								list($out_of_sync_block, $out_of_sync_txo_pos) = $running_game->peer_out_of_sync_block($game_peer, $txos_per_partition, $last_block, $print_debug);
								
								$app->run_query("UPDATE game_peers SET last_sync_check_at=:last_sync_check_at, last_check_in_sync=:last_check_in_sync, out_of_sync_since=:out_of_sync_since, out_of_sync_block=:out_of_sync_block, out_of_sync_txo_pos=:out_of_sync_txo_pos WHERE game_peer_id=:game_peer_id;", [
									'game_peer_id' => $game_peer['game_peer_id'],
									'last_sync_check_at' => time(),
									'last_check_in_sync' => $out_of_sync_block === false ? 1 : 0,
									'out_of_sync_since' => $out_of_sync_block === false ? null : (empty($game_peer['out_of_sync_since']) ? time() : $game_peer['out_of_sync_since']),
									'out_of_sync_block' => $out_of_sync_block === false ? null : $out_of_sync_block,
									'out_of_sync_txo_pos' => $out_of_sync_txo_pos,
								]);
							}
							else if ($print_debug) $app->print_debug($running_game->db_game['name']." is not loaded or is on different game definition for peer ".$game_peer['peer_name'].", skipping sync check.");
						}
						else if ($print_debug) $app->print_debug("Sync check ran recently for ".$game_peer['peer_name'].", skipping sync check.");
					}

					$game_peers = $running_game->fetch_all_peers();

					$num_peers_recently_checked = 0;
					$num_peers_out_of_sync = 0;
					$min_out_of_sync_block = null;
					$min_out_of_sync_since = null;

					foreach ($game_peers as $game_peer) {
						if (!empty($game_peer['last_sync_check_at']) && $game_peer['last_sync_check_at'] >= time()-($sec_between_sync_checks*4)) {
							$num_peers_recently_checked++;

							if (!$game_peer['last_check_in_sync']) {
								$num_peers_out_of_sync++;

								if (isset($game_peer['out_of_sync_block']) && ($min_out_of_sync_block === null || $game_peer['out_of_sync_block'] < $min_out_of_sync_block)) $min_out_of_sync_block = (int) $game_peer['out_of_sync_block'];
							}
							
							if ((string)$game_peer['out_of_sync_since'] !== "") {
								if ($min_out_of_sync_since === null || (int)$game_peer['out_of_sync_since'] < $min_out_of_sync_since) {
									$min_out_of_sync_since = (int)$game_peer['out_of_sync_since'];
								}
							}
						}
					}

					if ($print_debug) $app->print_debug($num_peers_out_of_sync."/".$num_peers_recently_checked." peers are out of sync in ".$running_game->db_game['name'].($min_out_of_sync_block ? " since block ".$min_out_of_sync_block : ""));

					if ($num_peers_recently_checked > 0 && $num_peers_out_of_sync/$num_peers_recently_checked > 0.5 && isset($min_out_of_sync_block)) {
						$reset_after_out_of_sync_minutes = 6*60;
						
						if ($min_out_of_sync_since !== null && $min_out_of_sync_since < time()-(60*$reset_after_out_of_sync_minutes)) {
							$game_extra_info = $running_game->fetch_extra_info();

							if (!empty($game_extra_info['pending_reset'])) {
								if ($print_debug) $app->print_debug("Game already has a pending reset");
							}
							else {
								if ($print_debug) $app->print_debug("Resetting game from block #".$min_out_of_sync_block);

								$running_game->schedule_game_reset($min_out_of_sync_block, null, null, time()+$sec_between_sync_checks+60);
							}
						}
						else if ($print_debug) $app->print_debug("Game reset skipped because no peer is out of sync for more than ".$app->format_seconds(60*$reset_after_out_of_sync_minutes).".");
					}
					else if ($print_debug) $app->print_debug("Game does not need to be reset.");
				}
			}
			else if ($print_debug) $app->print_debug("Skipping ".$running_game->db_game['name'].", it's not in fully loaded or has a pending reset.");
		}
	}
	else echo "This process is already running.\n";
}
else echo "You don't have permission to run this script.\n";
