<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$noinfo_message = "Incorrect username or password, please try again.";
	
	if (empty($_REQUEST['username'])) {
		$app->output_message(3, "Please supply a valid username.", false);
	}
	else {
		$username = $app->normalize_username($_REQUEST['username']);
		
		$existing_user = $app->fetch_user_by_username($username);
		
		if (!$existing_user || $existing_user['login_method'] != "email") {
			$app->output_message(4, "User does not exists or is not set up to log in by email.", false);
		}
		else {
			$redirect_url = null;
			if (!empty($_REQUEST['redirect_key']) && empty($redirect_url)) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
			
			$app->send_login_link($existing_user, $redirect_url, $existing_user['username']);
			$app->output_message(1, User::email_login_message(), false);
		}
	}
}