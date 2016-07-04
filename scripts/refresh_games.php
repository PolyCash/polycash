<?php
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games;";
	$r = run_query($q);

	while ($mandatory_game = mysql_fetch_array($r)) {
		ensure_game_options($mandatory_game);
		
		if ($mandatory_game['creator_id'] > 0) {}
		else {
			$qq = "SELECT * FROM users;";
			$rr = run_query($qq);
			
			while ($user = mysql_fetch_array($rr)) {
				ensure_user_in_game($user['user_id'], $mandatory_game['game_id']);
				$invitation = false;
				$success = try_capture_giveaway($mandatory_game, $user, $invitation);
			}
		}
		
		update_option_scores($mandatory_game);
		
		echo $mandatory_game['name']."<br/>\n";
		
		$qq = "SELECT s.voting_strategy, COUNT(*) FROM user_strategies s JOIN user_games ug ON s.strategy_id=ug.strategy_id JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$mandatory_game['game_id']."' GROUP BY s.voting_strategy;";
		$rr = run_query($qq);
		
		while ($strategy_count = mysql_fetch_array($rr)) {
			echo "&nbsp;&nbsp;".$strategy_count['COUNT(*)']."&nbsp;".$strategy_count['voting_strategy']."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>