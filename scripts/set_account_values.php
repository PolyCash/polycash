<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$q = "SELECT * FROM games;";
	$r = $app->run_query($q);

	while ($db_game = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$qq = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' ORDER BY u.user_id ASC;";
		$rr = $app->run_query($qq);
		
		while ($db_user = $rr->fetch()) {
			$account_value = $game->account_balance($db_user['account_id'])/pow(10,$game->db_game['decimal_places']);
			
			$qqq = "UPDATE user_games SET account_value='".$account_value."' WHERE user_game_id='".$db_user['user_game_id']."';";
			$rrr = $app->run_query($qqq);
			
			echo $game->db_game['name']." &rarr; ".$app->format_bignum($account_value).", ".$db_user['username']."<br/>\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>
