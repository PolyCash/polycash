<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$show_internal_params = true;
			
			list($defined_game_def_hash, $defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
			list($actual_game_def_hash, $actual_game_def) = GameDefinition::fetch_game_definition($game, "actual", $show_internal_params, false);
			
			GameDefinition::check_set_game_definition($app, $defined_game_def_hash, $defined_game_def);
			GameDefinition::check_set_game_definition($app, $actual_game_def_hash, $actual_game_def);
			
			if ($defined_game_def_hash != $actual_game_def_hash) {
				$log_message = GameDefinition::migrate_game_definitions($game, $thisuser->db_user['user_id'], "apply_defined_to_actual", $show_internal_params, $actual_game_def, $defined_game_def);
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