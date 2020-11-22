<?php
if (isset($_COOKIE['my_session_global'])) {
	$session_key = $_COOKIE['my_session_global'];
}
else {
	$session_key = $app->random_string(24);
	setcookie('my_session_global', $session_key, time()+24*3600, "/");
}

$thisuser = FALSE;
$game = FALSE;

if (strlen($session_key) > 0) {
	if (!empty(AppSettings::getParam('only_user_username')) && !empty(AppSettings::getParam('only_user_username'))) {
		$db_only_user = $app->fetch_user_by_username(AppSettings::getParam('only_user_username'));
		
		if (!$db_only_user) {
			$verify_code = $app->random_string(32);
			$salt = $app->random_string(16);
			$redirect_url = false;
			
			$only_user = $app->create_new_user($verify_code, $salt, AppSettings::getParam('only_user_username'), AppSettings::getParam('only_user_password'));
			$only_user->log_user_in($redirect_url, null);
		}
		else {
			$sessions = $app->fetch_sessions_by_key($session_key);
			
			if (count($sessions) == 0) {
				$only_user = new User($app, $db_only_user['user_id']);
				$redirect_url = false;
				$only_user->log_user_in($redirect_url, null);
			}
		}
	}
	
	$sessions = $app->fetch_sessions_by_key($session_key);
	
	if (count($sessions) == 1) {
		$session = $sessions[0];
		
		$thisuser = new User($app, $session['user_id']);
		$thisuser->set_synchronizer_token($session['synchronizer_token']);
	}
	else {
		foreach ($sessions as $session) {
			$app->run_query("UPDATE user_sessions SET logout_time=:logout_time WHERE session_id=:session_id;", [
				'logout_time' => time(),
				'session_id' => $session['session_id']
			]);
		}
		$session = false;
	}
	
	$card_sessions_params = [
		'session_key' => $session_key,
		'current_time' => time()
	];
	$card_sessions_q = "SELECT * FROM cards c JOIN card_users u ON c.card_id=u.card_id JOIN card_sessions s ON s.card_user_id=u.card_user_id WHERE s.session_key=:session_key";
	if (AppSettings::getParam('pageview_tracking_enabled')) {
		$card_sessions_q .= " AND s.ip_address=:ip_address";
		$card_sessions_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
	}
	$card_sessions_q .= " AND s.synchronizer_token IS NOT NULL AND :current_time < s.expire_time AND s.logout_time IS NULL GROUP BY c.card_id;";
	$card_sessions = $app->run_query($card_sessions_q, $card_sessions_params)->fetchAll();
	
	if (count($card_sessions) > 0) {
		$j=0;
		foreach ($card_sessions as $card_session) {
			if ($j == 0) $this_card_session = $card_session;
			
			// Make sure the user has a maximum of 1 active gift card session
			if ($j > 0) {
				$app->run_query("UPDATE card_sessions SET logout_time=:logout_time WHERE session_id=:session_id;", [
					'logout_time' => (time()-1),
					'session_id' => $card_session['session_id']
				]);
			}
			
			if (empty($thisuser) && !empty($card_session['user_id'])) {
				$thisuser = new User($app, $card_session['user_id']);
				$thisuser->set_synchronizer_token($card_session['synchronizer_token']);
			}
			
			$j++;
		}
	}
}

if ($thisuser && !empty($_REQUEST['redirect_key'])) {
	$redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
	
	if ($redirect_url) {
		header("Location: ".$redirect_url['url']);
		die();
	}
}

if ($thisuser && !empty($_REQUEST['game_id'])) {
	if ($thisuser->user_in_game($_REQUEST['game_id'])) {
		$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
	}
}

if (AppSettings::getParam('pageview_tracking_enabled')) $viewer_id = $pageviewController->insert_pageview($thisuser);
else $viewer_id = false;
?>