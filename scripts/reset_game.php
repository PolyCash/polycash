<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_db_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	if ($game) {
		$action = 'reset';
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "delete") $action = "delete";
		
		$game->delete_reset_game($action);
		$game->blockchain->unset_first_required_block();
		$game->update_db_game();
		$game->start_game();
		
		echo "Great, the game has been ".$action."!\n";
	}
	else echo "Failed to load game #".$game_id."<br/>\n";
}
else echo "You need admin privileges to run this script.\n";
?>
