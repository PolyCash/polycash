<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	if ($game['game_type'] == "simulation" && $game['creator_id'] == $thisuser['user_id'] && $game['block_timing'] == "user_controlled") {
		$log_text = new_block($game['game_id']);
		$log_text = apply_user_strategies($game);
		echo "1";
	}
	else echo "2";
}
?>