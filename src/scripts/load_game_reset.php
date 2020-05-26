<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	if ($game) {
		$action = 'reset';
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "delete") $action = "delete";
		$process_lock_name = "load_game_".$game->db_game['game_id'];
		
		echo "Waiting for game loading script to finish";
		do {
			echo ". ";
			$app->flush_buffers();
			sleep(1);
			$process_locked = $app->check_process_running($process_lock_name);
		}
		while ($process_locked);
		
		$app->print_debug("now resetting the game");
		
		$app->set_site_constant($process_lock_name, getmypid());
		
		$game->delete_reset_game($action);
		$game->start_game();
		
		$app->print_debug("Great, ".$game->db_game['name']." has been ".$action."!");
		
		$app->set_site_constant($process_lock_name, 0);
	}
	else echo "Failed to load game #".$game_id."<br/>\n";
}
else echo "You need admin privileges to run this script.\n";
?>
