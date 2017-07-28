<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$noinfo_message = "Incorrect username or password, please try again.";
	$alias = $app->normalize_username($_REQUEST['alias']);
	$password = $app->strong_strip_tags($_REQUEST['password']);
	
	$q = "SELECT * FROM users WHERE username=".$app->quote_escape($alias).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 0) {
		$message = $noinfo_message;
		$error_code = 2;
	}
	else if ($r->rowCount() == 1) {
		$db_thisuser = $r->fetch();
		
		if ($db_thisuser['password'] == $app->normalize_password($password, $db_thisuser['salt'])) {
			$thisuser = new User($app, $db_thisuser['user_id']);
			$message = "You have been logged in, redirecting...";
			$error_code = 1;
		}
		else {
			$message = $noinfo_message;
			$error_code = 2;
		}
	}
	else {
		$message = "System error, a duplicate user account was found.";
		$error_code = 2;
	}
	
	if ($error_code == 1) {
		$redirect_url = false;
		
		if ($GLOBALS['pageview_tracking_enabled']) $thisuser->log_user_in($redirect_url, $viewer_id);
		else $thisuser->log_user_in($redirect_url, false);
		
		if (!empty($_REQUEST['invite_key'])) {
			$invite_game = false;
			$success = $app->try_apply_invite_key($thisuser->db_user['user_id'], $_REQUEST['invite_key'], $invite_game);
			if ($success) {
				$app->output_message($error_code, "/wallet/".$invite_game['url_identifier'], false);
				die();
			}
		}
		if ($redirect_url) {
			$app->output_message($error_code, $redirect_url['url'], false);
		}
		else {
			$redir_game = $app->fetch_game_from_url();
			if ($redir_game) {
				$header_loc = "/wallet/".$redir_game['url_identifier']."/";
			}
			else $header_loc = "/accounts/";
			
			$app->output_message($error_code, $header_loc, false);
		}
	}
	else $app->output_message($error_code, $message, false);
}
?>
