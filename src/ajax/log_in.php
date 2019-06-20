<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$noinfo_message = "Incorrect username or password, please try again.";
	$username = $app->normalize_username($_REQUEST['username']);
	$password = $app->strong_strip_tags($_REQUEST['password']);
	if ($password == hash("sha256", "")) $password = "";
	
	if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
	else $redirect_url = false;
	
	$existing_user = $app->fetch_user_by_username($username);
	
	if (!$existing_user) {
		if (empty($password)) {
			$ref_user = false;
			$app->send_login_link($ref_user, $redirect_url, $username);
			$message = "We just sent you a verification email. Please open that email to log in.";
			$error_code = 3;
		}
		else {
			$verify_code = $app->random_string(32);
			$salt = $app->random_string(16);
			
			$thisuser = $app->create_new_user($verify_code, $salt, $username, $password);
			
			if ($thisuser->db_user['login_method'] == "email") {
				$app->send_login_link($thisuser->db_user, $redirect_url, $username);
				$success = true;
			}
			else {
				$success = $thisuser->log_user_in($redirect_url, $viewer_id);
			}
			
			if ($success) {
				$message = $redirect_url['url'];
				$error_code = 1;
			}
			else {
				$message = "Login failed. Please make sure you have cookies enabled.";
				$error_code = 4;
			}
		}
	}
	else {
		if ($existing_user['login_method'] == "password") {
			if ($existing_user['password'] == $app->normalize_password($password, $existing_user['salt'])) {
				$thisuser = new User($app, $existing_user['user_id']);
				
				$success = $thisuser->log_user_in($redirect_url, $viewer_id);
				
				if ($success) {
					$message = $redirect_url['url'];
					$error_code = 1;
				}
				else {
					$message = "Login failed. Please make sure you have cookies enabled.";
					$error_code = 4;
				}
			}
			else {
				$message = $noinfo_message;
				$error_code = 2;
			}
		}
		else {
			$app->send_login_link($existing_user, $redirect_url, $username);
			$message = "We just sent you a verification email. Please open that email to log in.";
			$error_code = 3;
		}
	}
	
	$app->output_message($error_code, $message, false);
}
?>