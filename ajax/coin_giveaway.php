<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

if ($thisuser && $game) {
	$invitation = false;
	$success = $game->try_capture_giveaway($thisuser, $invitation);
	
	if ($success) {
		$qq = "UPDATE user_games SET show_planned_votes=1 WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
		$rr = $GLOBALS['app']->run_query($qq);
		
		echo "1";
	}
	else echo "0";
}
?>