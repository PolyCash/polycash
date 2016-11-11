<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$alias = $app->normalize_username($_REQUEST['alias']);
	
	if (strlen($alias) >= 4) {
		$q = "SELECT * FROM users WHERE username=".$app->quote_escape($alias).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$app->output_message(1, "Thanks for joining ".$GLOBALS['coin_brand_name']."!", false);
		}
		else if ($r->rowCount() == 1) {
			$matched_user = $r->fetch();
			
			if (!empty($GLOBALS['login_by_email_enabled']) && $matched_user['login_method'] == "email") {
				$app->output_message(4, "We have sent a login link to your inbox. Please open that email to log in.", false);
			}
			else {
				$app->output_message(5, "To log in, please enter your password.", false);
			}
		}
		else {
			$app->output_message(3, "Error: the alias that you entered matches more than one account.", false);
		}
	}
	else {
		$app->output_message(6, "Error: the alias that you entered is too short. Aliases must be at least 6 characters.", false);
	}
}
?>