<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	$invitation = false;
	$success = try_capture_giveaway($game, $thisuser, $invitation);
	
	if ($success) {
		$qq = "UPDATE user_games SET show_planned_votes=1 WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game['game_id']."';";
		$rr = run_query($qq);
		
		echo "1";
	}
	else echo "0";
}
?>