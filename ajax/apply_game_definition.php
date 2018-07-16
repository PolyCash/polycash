<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = (int) $_REQUEST['game_id'];
	
	$game_q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$game_r = $app->run_query($game_q);
	
	if ($game_r->rowCount() == 1) {
		$db_game = $game_r->fetch();
		
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$game->check_set_game_definition("defined");
			$game->check_set_game_definition("actual");
			
			$game_def = $app->fetch_game_definition($game, "defined");
			$game_def_str = $app->game_def_to_text($game_def);
			$game_def_hash = $app->game_def_to_hash($game_def_str);
			
			$actual_game_def = $app->fetch_game_definition($game, "actual");
			$actual_game_def_str = $app->game_def_to_text($actual_game_def);
			$actual_game_def_hash = $app->game_def_to_hash($actual_game_def_str);
			
			if ($game_def_hash != $actual_game_def_hash) {
				$log_message = $app->migrate_game_definitions($game, $actual_game_def_hash, $game_def_hash);
				$app->output_message(1, $log_message, false);
			}
			else $app->output_message(5, "Found no changes to apply.", false);
		}
		else $app->output_message(4, "You don't have permission to edit this game.", false);
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "Please log in.", false);
?>