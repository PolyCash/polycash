<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	if ($game) {
		$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
		
		if ($user_game) {
			$preference = $_REQUEST['preference'];
			if ($preference != "email") $preference = "none";
			
			$app->run_query("UPDATE user_games SET notification_preference=".$app->quote_escape($preference)." WHERE user_game_id='".$user_game['user_game_id']."';");
			
			$email = $app->normalize_username($_REQUEST['email']);
			if ($email != $thisuser->db_user['notification_email']) {
				$app->run_query("UPDATE users SET notification_email=".$app->quote_escape($email)." WHERE user_id='".$thisuser->db_user['user_id']."';");
			}
			echo "Your notification settings have been saved.";
		}
		else echo "You must be a member of this game to perform this action.";
	}
	else echo "Sorry, that game was not found.";
}
else echo "First, please log in.";
?>