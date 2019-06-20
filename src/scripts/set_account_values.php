<?php
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$all_games = $app->run_query("SELECT * FROM games;");

	while ($db_game = $all_games->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$user_games = $app->run_query("SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game->db_game['game_id']."' ORDER BY u.user_id ASC;");
		
		while ($user_game = $user_games->fetch()) {
			$account_value = $game->account_balance($user_game['account_id'])/pow(10,$game->db_game['decimal_places']);
			
			$app->run_query("UPDATE user_games SET account_value='".$account_value."' WHERE user_game_id='".$db_user['user_game_id']."';");
			
			echo $game->db_game['name']." &rarr; ".$app->format_bignum($account_value).", ".$db_user['username']."<br/>\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>
