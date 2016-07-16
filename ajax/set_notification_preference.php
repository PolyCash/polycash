<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);
	$game = new Game($app, $game_id);
	
	if ($game) {
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$user_game = $r->fetch();
			
			$preference = $_REQUEST['preference'];
			if ($preference != "email") $preference = "none";
			
			$q = "UPDATE user_games SET notification_preference='".$preference."' WHERE user_game_id='".$user_game['user_game_id']."';";
			$r = $app->run_query($q);
			
			$email = $_REQUEST['email'];
			if ($email != "" && $email != $thisuser->db_user['notification_email']) {
				$app->run_query("UPDATE users SET notification_email=".$app->quote_escape(strip_tags($email))." WHERE user_id='".$thisuser->db_user['user_id']."';");
			}
			echo "Your notification settings have been saved.";
		}
		else echo "You must be a member of this game to perform this action.";
	}
	else echo "Sorry, that game was not found.";
}
else echo "First, please log in.";
?>