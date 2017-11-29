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
		$password = $app->strong_strip_tags($_REQUEST['password']);
		
		if (strlen($password) >= 6) {
			$email = $app->normalize_username($_REQUEST['email']);
			
			$q = "SELECT * FROM users WHERE username=".$app->quote_escape($alias).";";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 0) {
				$verify_code = $app->random_string(32);
				$salt = $app->random_string(16);
				
				$thisuser = $app->create_new_user($verify_code, $salt, $alias, $email, $password);
				
				$redirect_url = false;
				
				if ($GLOBALS['pageview_tracking_enabled']) $thisuser->log_user_in($redirect_url, $viewer_id);
				else $thisuser->log_user_in($redirect_url, false);
				
				if ($redirect_url) {
					$app->output_message(1, $redirect_url['url'], false);
				}
				else {
					if (!empty($_REQUEST['invite_key'])) {
						$invite_game = false;
						$success = $app->try_apply_invite_key($thisuser->db_user['user_id'], $_REQUEST['invite_key'], $invite_game);
						if ($success) {
							$app->output_message(1, "/wallet/".$invite_game['url_identifier'], false);
							die();
						}
					}
					else {
						$app->output_message(1, "/accounts/", false);
					}
				}
				die();
			}
			else {
				$app->output_message(3, "Error: that alias is already registered.", false);
			}
		}
		else {
			$app->output_message(6, "Error: the password you entered is too short. Please enter a password which is at least 6 characters.", false);
		}
	}
	else {
		$app->output_message(6, "Error: the alias that you entered is too short. Aliases must be at least 4 characters.", false);
	}
}
?>
