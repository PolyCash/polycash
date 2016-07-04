<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

$success_msg = "A password reset link has been sent to that email address, please open your inbox and click the link to reset your password.";

$email = urldecode($_REQUEST['email']);

if ($email != "") {
	$q = "SELECT * FROM users WHERE username='".mysql_real_escape_string($email)."';";
	$r = run_query($q);
	
	if (mysql_numrows($r) == 1) {
		$user = mysql_fetch_array($r);
		
		$token_key = random_string(32);
		
		$q = "INSERT INTO user_resettokens SET user_id='".$user['user_id']."', token_key='".$token_key."', token2_key='".random_string(32)."', create_time='".time()."', expire_time='".(time()+3600*36)."', request_viewer_id='".$viewer_id."', requester_ip='".$_SERVER['REMOTE_ADDR']."';";
		$r = run_query($q);
		$token_id = mysql_insert_id();
		
		$reset_link = "http://empireco.in/reset_password/?do=reset&tid=".$token_id."&reset_key=".$token_key;
		
		$subject = "EmpireCo.in - Please reset your password";
		$message = "<p>Someone requested a password reset for your EmpireCo.in web wallet.  If you did not request a password reset, please delete this email.</p>";
		$message .= "<p>If you did request the reset and you would like to reset your password, please follow this link:</p>";
		$message .= "<p><a href=\"".$reset_link."\">".$reset_link."</a></p>";
		$message .= "<p>Sent by <a href=\"http://empireco.in\">EmpireCo.in</a></p>";
		
		$res = mail_async($user['username'], "EmpireCo.in", "no-reply@empireco.in", $subject, $message, "", "");
		
		echo $success_msg;
	}
	else {
		echo $success_msg;
	}
}
?>