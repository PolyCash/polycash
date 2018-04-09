<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$mode = $_REQUEST['mode'];
	if ($mode != "inflationary") $mode = "principal";
	
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	$q = "UPDATE user_games SET betting_mode='".$mode."' WHERE user_game_id='".$user_game['user_game_id']."';";
	$r = $app->run_query($q);
	
	$app->output_message(1, "Mode has been changed.", false);
}
else $app->output_message(2, "Invalid user or game.", false);
?>