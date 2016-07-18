<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser) {
	$app->output_message(2, "You're already logged in.", false);
}
else {
	$alias = $app->make_alphanumeric(strip_tags($_REQUEST['alias']));
	$password = strip_tags($_REQUEST['password']);
	$email = strip_tags($_REQUEST['email']);
	
	$q = "SELECT * FROM users WHERE username=".$app->quote_escape($alias).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 0) {
		$verify_code = $app->random_string(32);
		
		$q = "INSERT INTO users SET username=".$app->quote_escape($alias).", notification_email=".$app->quote_escape($email).", api_access_code=".$app->quote_escape($app->random_string(32)).", password=".$app->quote_escape($password);
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q .= ", ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']);
		}
		if ($GLOBALS['new_games_per_user'] != "unlimited" && $GLOBALS['new_games_per_user'] > 0) {
			$q .= ", authorized_games=".$app->quote_escape($GLOBALS['new_games_per_user']);
		}
		$q .= ", time_created='".time()."', verify_code='".$verify_code."';";
		$r = $app->run_query($q);
		$user_id = $app->last_insert_id();
		
		$bitcoin_address = $_REQUEST['bitcoin_address'];
		
		if (!empty($bitcoin_address)) {
			$qq = "INSERT INTO external_addresses SET user_id='".$user_id."', currency_id=2, address=".$app->quote_escape($bitcoin_address).", time_created='".time()."';";
			$rr = $app->run_query($qq);
			$address_id = $app->last_insert_id();
			$app->run_query("UPDATE users SET bitcoin_address_id='".$address_id."' WHERE user_id=".$user_id.";");
		}
		
		$thisuser = new User($app, $user_id);
		
		$session_key = session_id();
		$expire_time = time()+3600*24;
		
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
			if ($r->rowCount() == 0) {
				$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$thisuser->db_user['user_id']."';";
				$r = $app->run_query($q);
			}
			
			$q = "UPDATE users SET ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR'])." WHERE user_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
		}
		
		// Send an email if the username includes
		if ($GLOBALS['outbound_email_enabled'] && strpos($notification_email, '@')) {
			$email_message = "<p>A new ".$GLOBALS['site_name_short']." web wallet has been created for <b>".$alias."</b>.</p>";
			$email_message .= "<p>Thanks for signing up!</p>";
			$email_message .= "<p>To log in any time please visit ".$GLOBALS['base_url']."/wallet/</p>";
			$email_message .= "<p>This message was sent to you by ".$GLOBALS['base_url']."</p>";
			
			$email_id = $app->mail_async($email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], "New account created", $email_message, "", "");
		}
		
		if ($primary_game['giveaway_status'] == "public_free") {
			$q = "SELECT * FROM games WHERE game_id='".$app->get_site_constant('primary_game_id')."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$db_primary_game = $r->fetch();
				$primary_game = new Game($app, $db_primary_game['game_id']);
				
				$thisuser->ensure_user_in_game($primary_game->db_game['game_id']);
				$giveaway = $primary_game->new_game_giveaway($user_id, 'initial_purchase', false);
			}
		}
		
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
			
			$redir_game = $app->fetch_game_from_url();
			if ($redir_game) {
				$header_loc = "/wallet/".$redir_game['url_identifier']."/";
			}
			else $header_loc = "/wallet/";
			
			$app->output_message(1, $header_loc, false);
		}
		die();
	}
	else {
		$app->output_message(3, "Error: the alias that you entered matches more than one account.", false);
	}
}
?>