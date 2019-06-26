<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
		
		echo "<pre>";
		$game->apply_user_strategies(true);
		echo "</pre>\n";
	}
	else echo "Please supply a valid game ID.\n";
}
else echo "You need admin privileges to run this script.\n";
?>