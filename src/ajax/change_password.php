<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

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
				$app->run_query("UPDATE users SET password=".$app->quote_escape($new_password)." WHERE user_id='".$thisuser->db_user['user_id']."';");
				
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