<?php
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games";
	$game_id = intval($_REQUEST['game_id']);
	if ($game_id) $q .= " WHERE game_id='".$game_id."'";
	$q .= ";";
	$r = $app->run_query($q);

	while ($db_game = $r->fetch()) {
		$mandatory_game = new Game($app, $db_game['game_id']);
		$mandatory_game->ensure_game_options();
		
		if ($mandatory_game->db_game['creator_id'] > 0) {}
		else {
			$qq = "SELECT * FROM users;";
			$rr = $app->run_query($qq);
			
			while ($db_user = $rr->fetch()) {
				$user = new User($app, $db_user['user_id']);
				$user->ensure_user_in_game($mandatory_game->db_game['game_id']);
				$invitation = false;
				$success = $mandatory_game->try_capture_giveaway($user, $invitation);
			}
		}
		
		$mandatory_game->update_option_scores();
		
		echo $mandatory_game->db_game['name']."<br/>\n";
		
		$qq = "SELECT s.voting_strategy, COUNT(*) FROM user_strategies s JOIN user_games ug ON s.strategy_id=ug.strategy_id JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$mandatory_game->db_game['game_id']."' GROUP BY s.voting_strategy;";
		$rr = $app->run_query($qq);
		
		while ($strategy_count = $rr->fetch()) {
			echo "&nbsp;&nbsp;".$strategy_count['COUNT(*)']."&nbsp;".$strategy_count['voting_strategy']."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>