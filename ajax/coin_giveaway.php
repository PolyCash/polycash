<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

if ($thisuser && $game) {
	$invitation = false;
	$success = $game->try_capture_giveaway($thisuser, $invitation);
	
	$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$user_game = $r->fetch();
		
		$user_strategy = $game->fetch_user_strategy($user_game);
		
		if ($user_strategy['voting_strategy'] == "by_plan") {
			$qq = "UPDATE user_games SET show_planned_votes=1 WHERE user_game_id='".$user_game['user_game_id']."';";
			$rr = $app->run_query($qq);
		}
		
		if ($success) echo "1";
		else echo "0";
	}
	else {
		die("Error: you're not in this game.");
	}
}
else echo "0";
?>