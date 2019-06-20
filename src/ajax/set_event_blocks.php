<?php
$host_not_required = TRUE;
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($app->user_can_edit_game($thisuser, $game)) {
	if (!empty($_REQUEST['game_defined_event_id'])) $game_defined_event_id = $_REQUEST['game_defined_event_id'];
	else $game_defined_event_id = false;
	
	$log_text = $game->set_event_blocks($game_defined_event_id);
	$game->set_cached_definition_hashes();
	
	$app->output_message(1, "Successfully set the event blocks.", false);
}
else $app->output_message(2, "Error: you don't have permission to modify this game.", false);
?>