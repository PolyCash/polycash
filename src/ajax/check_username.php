<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

if ($action == "generate") {
	$username = $app->random_string(12);
	$password = $app->random_string(12);
	
	$html = "<p>Please write down the following username and password:</p>\n";
	$html .= "<p><b>Username:</b> &nbsp;&nbsp;&nbsp; $username</p>\n";
	$html .= "<p><b>Password:</b> &nbsp;&nbsp;&nbsp; $password</p>\n";
	$html .= "<p><button class=\"btn btn-success\" onclick=\"thisPageManager.login();\" id=\"generate_login_btn\">Continue</button></p>\n";
	$html .= "<input type=\"hidden\" id=\"generate_username\" name=\"generate_username\" value=\"".$username."\" />\n";
	$html .= "<input type=\"hidden\" id=\"generate_password\" name=\"generate_password\" value=\"".$password."\" />\n";
	
	$app->output_message(1, $html, false);
}
else {
	if (empty($thisuser)) {
		$username = $app->normalize_username($_REQUEST['username']);
		
		if (strlen($username) >= 4) {
			$matched_user = $app->fetch_user_by_username($username);
			
			$message = "";
			
			if ($matched_user) {
				if ($matched_user['login_method'] == "email") {
					$message = User::email_login_message();
					$status_code = 2;
				}
				else {
					$message = "To log in, please enter your password.";
					$status_code = 3;
				}
			}
			else {
				if (empty(AppSettings::getParam('sendgrid_api_key')) || strpos($username, '@') === false) {
					$message = "To sign up, please enter your password.";
					$status_code = 4;
				}
				else {
					$message = User::email_login_message();
					$status_code = 1;
				}
			}
			
			$app->output_message($status_code, $message, false);
		}
		else $app->output_message(5, "Error: the username you entered is too short. Usernames must be at least 4 characters.", false);
	}
	else $app->output_message(6, "You're already logged in.", false);
}
?>