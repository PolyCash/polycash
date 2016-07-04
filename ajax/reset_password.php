<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($GLOBALS['outbound_email_enabled']) {
	$success_msg = "A password reset link has been sent to that email address, please open your inbox and click the link to reset your password.";

	$email = urldecode($_REQUEST['email']);
	safe_email($email);
	
	if ($email != "") {
		$q = "SELECT * FROM users WHERE notification_email='".$email."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$user = mysql_fetch_array($r);
			
			$token_key = random_string(32);
			
			$q = "INSERT INTO user_resettokens SET user_id='".$user['user_id']."', token_key='".$token_key."', token2_key='".random_string(32)."', create_time='".time()."', expire_time='".(time()+3600*36)."'";
			if ($GLOBALS['pageview_tracking_enabled']) $q .= ", request_viewer_id='".$viewer_id."', requester_ip='".$_SERVER['REMOTE_ADDR']."'";
			$q .= ";";
			$r = run_query($q);
			$token_id = mysql_insert_id();
			
			$reset_link = $GLOBALS['base_url']."/reset_password/?do=reset&tid=".$token_id."&reset_key=".$token_key;
			
			$subject = $GLOBALS['site_name']." - Please reset your password";
			$message = "<p>Someone requested a password reset for your ".$GLOBALS['site_name']." web wallet.  If you did not request a password reset, please delete this email.</p>";
			$message .= "<p>If you did request the reset and you would like to reset your password, please follow this link:</p>";
			$message .= "<p><a href=\"".$reset_link."\">".$reset_link."</a></p>";
			$message .= "<p>Sent by <a href=\"".$GLOBALS['base_url']."\">".$GLOBALS['site_name_short']."</a></p>";
			
			$res = mail_async($user['notification_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
			
			echo $success_msg;
		}
		else {
			echo $success_msg;
		}
	}
}
else echo "Sorry, this server cannot deliver emails.";
?>