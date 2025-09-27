<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	$user_games = $app->run_query("SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id=:game_id ORDER BY u.user_id ASC;", ['game_id'=>$game->db_game['game_id']])->fetchAll();

	foreach ($user_games as $user_game) {
		$user = new User($app, $user_game['user_id']);
		$joined_any_faucets = Faucet::joinAndRequestAllEligibleFaucetsInGame($app, $user, $game);
		
		echo "User #".$user_game['user_id']." joined any? ".json_encode($joined_any_faucets)."\n";
	}
}
else echo "Please run this script as administrator\n";
