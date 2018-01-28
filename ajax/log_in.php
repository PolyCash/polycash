<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$noinfo_message = "Incorrect username or password, please try again.";
	$username = $app->normalize_username($_REQUEST['username']);
	$password = $app->strong_strip_tags($_REQUEST['password']);
	if ($password == hash("sha256", "")) $password = "";
	
	$q = "SELECT * FROM users WHERE username=".$app->quote_escape($username).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 0) {
		$verify_code = $app->random_string(32);
		$salt = $app->random_string(16);
		
		$thisuser = $app->create_new_user($verify_code, $salt, $username, "", $password);
		
		$successful = $app->send_login_link($db_thisuser, $username);
	}
	else if ($r->rowCount() == 1) {
		$db_thisuser = $r->fetch();
		
		if ($db_thisuser['login_method'] == "password") {
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
			$successful = $app->send_login_link($db_thisuser, $db_thisuser['username']);
		}
	}
	else {
		$message = "System error, a duplicate user account was found.";
		$error_code = 2;
	}
	
	$app->output_message($error_code, $message, false);
}
?>