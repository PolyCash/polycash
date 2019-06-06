<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$q = "SELECT * FROM games";
	if (!empty($_REQUEST['game_id'])) $q .= " WHERE game_id='".(int)$_REQUEST['game_id']."'";
	$q .= ";";
	$r = $app->run_query($q);

	$show_internal_params = true;
	
	while ($db_game = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$game->check_set_game_definition("defined", $show_internal_params);
		$game->check_set_game_definition("actual", $show_internal_params);
		
		/*if ($game->db_game['creator_id'] > 0) {}
		else {
			$qq = "SELECT * FROM users;";
			$rr = $app->run_query($qq);
			
			while ($db_user = $rr->fetch()) {
				$user = new User($app, $db_user['user_id']);
				$user_game = $user->ensure_user_in_game($game, false);
				$invitation = false;
				$success = $game->try_capture_giveaway($user, $invitation);
			}
		}*/
		
		/*$game->update_option_votes();
		
		echo $game->db_game['name']."<br/>\n";
		
		$qq = "SELECT s.voting_strategy, COUNT(*) FROM user_strategies s JOIN user_games ug ON s.strategy_id=ug.strategy_id JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$game->db_game['game_id']."' GROUP BY s.voting_strategy;";
		$rr = $app->run_query($qq);
		
		while ($strategy_count = $rr->fetch()) {
			echo "&nbsp;&nbsp;".$strategy_count['COUNT(*)']."&nbsp;".$strategy_count['voting_strategy']."<br/>\n";
		}
		*/
		$ensure_block = $blockchain->last_block_id()+1;
		if ($game->db_game['finite_events'] == 1) $ensure_block = max($ensure_block, $game->max_gde_starting_block());
		$debug_text = $game->ensure_events_until_block($ensure_block);
		echo $debug_text."\n";
		$game->update_option_votes();
		echo "Ensured events until ".$ensure_block."<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>
