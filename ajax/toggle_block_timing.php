<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

if ($thisuser && $game) {
	if ($game->db_game['game_type'] == "simulation" && $game->db_game['creator_id'] == $thisuser->db_user['user_id']) {
		if ($game->db_game['block_timing'] == "user_controlled") $toggle_value = "realistic";
		else $toggle_value = "user_controlled";
		
		$q = "UPDATE games SET block_timing='".$toggle_value."' WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		echo "1";
	}
	else echo "Error, permission denied.";
}
else echo "Please log in.";
?>