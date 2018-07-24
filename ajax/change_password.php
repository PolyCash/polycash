<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$username = $app->normalize_username($_REQUEST['username']);
	$existing = $app->strong_strip_tags($_REQUEST['existing']);
	$new = $app->strong_strip_tags($_REQUEST['new']);
	if ($new == hash("sha256", "")) $new = "";
	
	$existing_password = $app->normalize_password($existing, $thisuser->db_user['salt']);
	$new_password = $app->normalize_password($new, $thisuser->db_user['salt']);
	
	if ($username == $thisuser->db_user['username']) {
		if ($thisuser->db_user['password'] == $existing_password) {
			if (!empty($new)) {
				$update_q = "UPDATE users SET password=".$app->quote_escape($new_password)." WHERE user_id='".$thisuser->db_user['user_id']."';";
				$update_r = $app->run_query($update_q);
				
				$app->output_message(1, "Your password has been changed.", false);
			}
			else $app->output_message(5, "Error: you entered a blank password.", false);
		}
		else $app->output_message(4, "You didn't enter your password correctly.", false);
	}
	else $app->output_message(3, "You didn't enter your username correctly.", false);
}
else $app->output_message(2, "You must be logged in for this.", false);
?>