<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	if ($game['game_type'] == "simulation" && $game['creator_id'] == $thisuser['user_id']) {
		if ($game['block_timing'] == "user_controlled") $toggle_value = "realistic";
		else $toggle_value = "user_controlled";
		
		$q = "UPDATE games SET block_timing='".$toggle_value."' WHERE game_id='".$game['game_id']."';";
		$r = run_query($q);
		
		echo "1";
	}
	else echo "Error, permission denied.";
}
else echo "Please log in.";
?>