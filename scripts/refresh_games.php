<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games";
	if (!empty($_REQUEST['game_id'])) $q .= " WHERE game_id='".(int)$_REQUEST['game_id']."'";
	$q .= ";";
	$r = $app->run_query($q);

	while ($db_game = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$game->check_set_game_definition("defined");
		$game->check_set_game_definition("actual");
		
		//$game->ensure_options();
		
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
		$ensure_block = $blockchain->last_block_id()+($game->db_game['round_length']*50);
		$debug_text = $game->ensure_events_until_block($ensure_block);
		echo $debug_text."\n";
		$game->update_option_votes();
		echo "Ensured events until ".$ensure_block."<br/>\n";
	}
}
else echo "Incorrect key.";
?>
