<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

	if (empty($thisuser)) {
		$username = $app->normalize_username($_REQUEST['username']);
		
		if (strlen($username) >= 6) {
			$matched_user = $app->fetch_user_by_username($username);
			
			$message = "";
			
			if ($matched_user) {
				if ($matched_user['login_method'] == "email") $app->output_message(2, User::email_login_message(), false);
				else $app->output_message(3, "To log in, please enter your password.", false);
			}
			else $app->output_message(4, "That username is already registered.", false);
		}
		else $app->output_message(5, "Error: the username you entered is too short. Usernames must be at least 6 characters.", false);
	}
	else $app->output_message(6, "You're already logged in.", false);
?>