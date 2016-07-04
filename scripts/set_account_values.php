<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games WHERE creator_id IS NULL;";
	$r = $GLOBALS['app']->run_query($q);

	while ($db_game = mysql_fetch_array($r)) {
		$game = new Game($db_game['game_id']);
		$qq = "SELECT u.* FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' ORDER BY u.user_id ASC;";
		$rr = $GLOBALS['app']->run_query($qq);
		
		while ($db_user = mysql_fetch_array($rr)) {
			$user = new User($db_user['user_id']);
			$account_value = $user->account_coin_value($game)/pow(10,8);
			$qqq = "UPDATE user_games SET account_value='".$account_value."' WHERE user_id='".$user->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$rrr = $GLOBALS['app']->run_query($qqq);
			echo $game['name']." &rarr; ".number_format($account_value).", ".$user->db_user['username']."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>