<?php
$host_not_required = TRUE;
include_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

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
		
		$db_running_games = $app->run_query("SELECT * FROM games WHERE game_status='running';")->fetchAll();
		$running_games = [];
		
		for ($game_i=0; $game_i<count($db_running_games); $game_i++) {
			$blockchain = new Blockchain($app, $db_running_games[$game_i]['blockchain_id']);
			$running_games[$game_i] = new Game($blockchain, $db_running_games[$game_i]['game_id']);
			
			list($from_option_index, $to_option_index) = $running_games[$game_i]->option_index_range();
			
			if ($to_option_index !== false) {
				$q = "SELECT * FROM user_games ug JOIN currency_accounts ca ON ug.account_id=ca.account_id WHERE ug.game_id='".$running_games[$game_i]->db_game['game_id']."' AND ca.has_option_indices_until<".$to_option_index." ORDER BY ca.account_id ASC;";
				$r = $app->run_query($q);
				
				if ($print_debug) echo "Looping through ".$r->rowCount()." users.<br/>\n";
				
				while ($user_game = $r->fetch()) {
					$user = new User($app, $user_game['user_id']);
					$user->generate_user_addresses($running_games[$game_i], $user_game);
					if ($print_debug) {
						echo ". ";
						$app->flush_buffers();
					}
				}
			}
		}
		
		if ($print_debug) echo "Looping through ".count($db_running_games)." games.<br/>\n";
		
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
			}
		}
		
		if ($print_debug) echo "Done!\n";
	}
	else echo "This process is already running.\n";
}
else echo "You need admin privileges to run this script.\n";
?>