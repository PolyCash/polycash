<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game) {
	$mode = $_REQUEST['mode'];
	if ($mode != "inflationary") $mode = "principal";
	
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	$app->run_query("UPDATE user_games SET betting_mode=:betting_mode WHERE user_game_id=:user_game_id;", [
		'betting_mode' => $mode,
		'user_game_id' => $user_game['user_game_id']
	]);
	
	$app->output_message(1, "Mode has been changed.", false);
}
else $app->output_message(2, "Invalid user or game.", false);
?>