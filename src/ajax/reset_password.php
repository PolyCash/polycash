<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

die("This function is disabled.");

$success_msg = "A password reset link has been sent to that email address, please open your inbox and click the link to reset your password.";

$email = $app->normalize_username($_REQUEST['email']);

if ($email != "") {
	$users_by_email = $app->run_query("SELECT * FROM users WHERE username=:email OR notification_email=:email;", [
		'email' => $email
	])->fetchAll();
	
	if (count($users_by_email) == 1) {
		$db_user = $users_by_email[0];
		
		$token_key = $app->random_string(32);
		
		$resettoken_params = [
			'user_id' => $db_user['user_id'],
			'token_key' => $token_key,
			'token2_key' => $app->random_string(32),
			'create_time' => time(),
			'expire_time' => (time()+3600*36)
		];
		if (AppSettings::getParam('pageview_tracking_enabled')) {
			$resettoken_params['request_viewer_id'] = $viewer_id;
			$resettoken_params['requester_ip'] = $_SERVER['REMOTE_ADDR'];
		}
		$app->run_insert_query("user_resettokens", $resettoken_params);
		$token_id = $app->last_insert_id();
		
		$reset_link = AppSettings::getParam('base_url')."/reset_password/?action=reset&tid=".$token_id."&reset_key=".$token_key;
		
		$subject = AppSettings::getParam('site_name')." - Please reset your password";
		$message = "<p>Someone requested a password reset for your ".AppSettings::getParam('site_name')." web wallet.  If you did not request a password reset, please delete this email.</p>";
		$message .= "<p>If you did request the reset and you would like to reset your password, please follow this link:</p>";
		$message .= "<p><a href=\"".$reset_link."\">".$reset_link."</a></p>";
		$message .= "<p>Sent by <a href=\"".AppSettings::getParam('base_url')."\">".AppSettings::getParam('site_name')."</a></p>";
		
		$res = $app->mail_async($db_user['notification_email'], AppSettings::getParam('site_name'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message, "", "", "");
		
		echo $success_msg;
	}
	else {
		echo "Your password cannot be reset. Please contact your server administrator to recover access to your account.";
	}
}
?>