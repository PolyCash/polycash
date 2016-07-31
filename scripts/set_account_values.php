<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM game_types WHERE creator_id IS NULL;";
	$r = $app->run_query($q);

	while ($db_game = $r->fetch()) {
		$game = new Game($app, $db_game['game_id']);
		$qq = "SELECT u.* FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' ORDER BY u.user_id ASC;";
		$rr = $app->run_query($qq);
		
		while ($db_user = $rr->fetch()) {
			$user = new User($app, $db_user['user_id']);
			$account_value = $user->account_coin_value($game)/pow(10,8);
			$qqq = "UPDATE user_games SET account_value='".$account_value."' WHERE user_id='".$user->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$rrr = $app->run_query($qqq);
			echo $game['name']." &rarr; ".number_format($account_value).", ".$user->db_user['username']."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>
