<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);
else $viewer_id = false;

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$noinfo_message = "Incorrect username or password, please try again.";
	$username = $app->normalize_username($_REQUEST['username']);
	$password = $app->strong_strip_tags($_REQUEST['password']);
	if ($password == hash("sha256", "")) $password = "";
	
	$redirect_key = "";
	if (!empty($_REQUEST['redirect_key'])) $redirect_key = $_REQUEST['redirect_key'];
	
	if (!empty($redirect_key)) $redirect_url = $app->check_fetch_redirect_url($redirect_key);
	else $redirect_url = false;
	
	$q = "SELECT * FROM users WHERE username=".$app->quote_escape($username).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 0) {
		if (empty($password)) {
			$db_thisuser = false;
			$app->send_login_link($db_thisuser, $redirect_url, $username);
			$message = "We just sent you a verification email. Please open that email to log in.";
			$error_code = 3;
		}
		else {
			$verify_code = $app->random_string(32);
			$salt = $app->random_string(16);
			
			$thisuser = $app->create_new_user($verify_code, $salt, $username, "", $password);
			
			$thisuser->log_user_in($redirect_url, $viewer_id);
			
			$message = $redirect_url['url'];
			$error_code = 1;
		}
	}
	else if ($r->rowCount() == 1) {
		$db_thisuser = $r->fetch();
		
		if ($db_thisuser['login_method'] == "password") {
			if ($db_thisuser['password'] == $app->normalize_password($password, $db_thisuser['salt'])) {
				$thisuser = new User($app, $db_thisuser['user_id']);
				
				$thisuser->log_user_in($redirect_url, $viewer_id);
				
				$message = $redirect_url['url'];
				$error_code = 1;
			}
			else {
				$message = $noinfo_message;
				$error_code = 2;
			}
		}
		else {
			$app->send_login_link($db_thisuser, $redirect_url, $username);
			$message = "We just sent you a verification email. Please open that email to log in.";
			$error_code = 3;
		}
	}
	else {
		$message = "System error, a duplicate user account was found.";
		$error_code = 2;
	}
	
	$app->output_message($error_code, $message, false);
}
?>