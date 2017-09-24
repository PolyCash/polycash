<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games;";
	$r = $app->run_query($q);

	while ($db_game = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$qq = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' ORDER BY u.user_id ASC;";
		$rr = $app->run_query($qq);
		
		while ($db_user = $rr->fetch()) {
			$user = new User($app, $db_user['user_id']);
			$account_value = $user->account_coin_value($game, $db_user)/pow(10,8);
			
			$qqq = "UPDATE user_games SET account_value='".$account_value."' WHERE user_game_id='".$db_user['user_game_id']."';";
			$rrr = $app->run_query($qqq);
			
			echo $game->db_game['name']." &rarr; ".$app->format_bignum($account_value).", ".$user->db_user['username']."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>
