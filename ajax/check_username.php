<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (empty($thisuser)) {
	$username = $app->normalize_username($_REQUEST['username']);
	
	if (strlen($username) >= 4) {
		$q = "SELECT * FROM users WHERE username=".$app->quote_escape($username).";";
		$r = $app->run_query($q);
		
		$message = "";
		
		if ($r->rowCount() == 0) {
			$message = "We just sent you a verification email. Please open your inbox and click the link to finish creating your account.";
			$status_code = 1;
		}
		else if ($r->rowCount() == 1) {
			$matched_user = $r->fetch();
			
			if ($matched_user['login_method'] == "email") {
				$message = "We just sent you a verification email. Please open that email to log in.";
				$status_code = 2;
			}
			else {
				$message = "To log in, please enter your password.";
				$status_code = 3;
			}
		}
		else {
			$message = "Error: the alias that you entered matches more than one account.";
			$status_code = 4;
		}
		
		$app->output_message($status_code, $message, false);
	}
	else {
		$app->output_message(5, "Error: the username you entered is too short. Aliases must be at least 4 characters.", false);
	}
}
else {
	$app->output_message(6, "You're already logged in.", false);
}
?>