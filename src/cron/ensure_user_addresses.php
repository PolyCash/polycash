<?php
$host_not_required = TRUE;
include_once(AppSettings::srcPath()."/includes/connect.php");

$script_start_time = microtime(true);
$allowed_params = ['print_debug', 'game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	else $print_debug = false;
	
	$process_lock_name = "ensure_user_addresses";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$buffer_address_sets = 5;
		$script_target_time = 49;
		$loop_target_time = 10;
		
		$db_running_games = $app->run_query("SELECT * FROM games WHERE game_status='running';")->fetchAll();
		$running_games = [];
		$blockchains = [];
		
		if ($print_debug) {
			echo "Giving addresses to users.\n";
			$app->flush_buffers();
		}
		
		for ($game_i=0; $game_i<count($db_running_games); $game_i++) {
			if (empty($blockchains[$db_running_games[$game_i]['blockchain_id']])) $blockchains[$db_running_games[$game_i]['blockchain_id']] = new Blockchain($app, $db_running_games[$game_i]['blockchain_id']);
			$running_games[$game_i] = new Game($blockchains[$db_running_games[$game_i]['blockchain_id']], $db_running_games[$game_i]['game_id']);
			
			list($from_option_index, $to_option_index) = $running_games[$game_i]->option_index_range();
			
			if ($to_option_index !== false) {
				$user_games = $app->run_query("SELECT * FROM user_games ug JOIN currency_accounts ca ON ug.account_id=ca.account_id WHERE ug.game_id='".$running_games[$game_i]->db_game['game_id']."' AND ca.has_option_indices_until<".$to_option_index." ORDER BY ca.account_id ASC;");
				
				if ($print_debug) {
					echo "Looping through ".$user_games->rowCount()." users for ".$running_games[$game_i]->db_game['name'].".<br/>\n";
					$app->flush_buffers();
				}
				
				while ($user_game = $user_games->fetch()) {
					$user = new User($app, $user_game['user_id']);
					$user->generate_user_addresses($running_games[$game_i], $user_game);
					if ($print_debug) {
						echo ". ";
						$app->flush_buffers();
					}
				}
			}
		}
		
		$need_address_blockchain_ids = [];
		if ($print_debug) {
			echo "Now filling address sets for ".count($db_running_games)." games.<br/>\n";
			$app->flush_buffers();
		}
		
		for ($game_i=0; $game_i<count($running_games); $game_i++) {
			list($from_option_index, $to_option_index) = $running_games[$game_i]->option_index_range();
			
			if ($to_option_index !== false) {
				$game_addrsets = $app->run_query("SELECT * FROM address_sets WHERE game_id='".$running_games[$game_i]->db_game['game_id']."' AND applied=0;")->fetchAll();
				
				if (count($game_addrsets) < $buffer_address_sets) {
					$num_sets_needed = $buffer_address_sets-count($game_addrsets);
					
					for ($new_addrset_i=0; $new_addrset_i<$num_sets_needed; $new_addrset_i++) {
						$app->run_query("INSERT INTO address_sets SET game_id='".$running_games[$game_i]->db_game['game_id']."';");
					}
					
					$game_addrsets = $app->run_query("SELECT * FROM address_sets WHERE game_id='".$running_games[$game_i]->db_game['game_id']."' AND applied=0;")->fetchAll();
				}
				
				$gen_sets_successful = $app->finish_address_sets($running_games[$game_i], $game_addrsets, $to_option_index);
				
				if (!$gen_sets_successful) $need_address_blockchain_ids[$running_games[$game_i]->blockchain->db_blockchain['blockchain_id']] = true;
			}
		}
		
		$min_unallocated_separators = 100;
		
		foreach ($blockchains as $blockchain_id => $blockchain) {
			if ($blockchain->db_blockchain['p2p_mode'] == "rpc" && empty($need_address_blockchain_ids[$blockchain_id])) {
				$unallocated_separator_info = $app->run_query("SELECT COUNT(*) FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$blockchain_id."' AND a.option_index=1 AND k.account_id IS NULL AND a.address_set_id IS NULL;")->fetch();
				
				if ((int)$unallocated_separator_info['COUNT(*)'] < $min_unallocated_separators) {
					$need_address_blockchain_ids[$blockchain_id] = true;
					if ($print_debug) {
						echo $blockchain->db_blockchain['blockchain_name']." needs ".$min_unallocated_separators." but only has ".$unallocated_separator_info['COUNT(*)']."\n";
						$app->flush_buffers();
					}
				}
			}
		}
		
		$need_address_blockchain_ids = array_keys($need_address_blockchain_ids);
		
		if ($print_debug) {
			echo "Now generating addresses for ".count($need_address_blockchain_ids)." blockchains.\n";
			$app->flush_buffers();
		}
		
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
		
		if ($print_debug) echo "Done!\n";
	}
	else echo "This process is already running.\n";
}
else echo "You need admin privileges to run this script.\n";
?>