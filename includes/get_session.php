<?php
if (!isset($session_key)) {
	$session_key = session_id();
}

if ($session_key == "") {
	session_start();
	$session_key = session_id();
}

$thisuser = FALSE;
$game = FALSE;

if (strlen($session_key) > 0) {
	$q = "SELECT * FROM user_sessions WHERE session_key=".$app->quote_escape($session_key)." AND expire_time > '".time()."' AND logout_time=0;";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$session = $r->fetch();
		
		$thisuser = new User($app, $session['user_id']);
		
		if ($thisuser->db_user) {
			if (!empty($_REQUEST['game_id'])) {
				$game_id = intval($_REQUEST['game_id']);
				
				$q = "SELECT g.* FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game_id."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$db_game = $r->fetch();
					
					$blockchain = new Blockchain($app, $db_game['blockchain_id']);
					$game = new Game($blockchain, $db_game['game_id']);
				}
			}
		}
		else $thisuser = false;
	}
	else {
		while ($session = $r->fetch()) {
			$qq = "UPDATE user_sessions SET logout_time='".time()."' WHERE session_id='".$session['session_id']."';";
			$rr = $app->run_query($qq);
		}
		$session = false;
	}
	
	$q = "SELECT * FROM cards c JOIN card_users u ON c.card_id=u.card_id JOIN card_sessions s ON s.card_user_id=u.card_user_id WHERE s.session_key=".$app->quote_escape($session_key);
	if ($GLOBALS['pageview_tracking_enabled']) $q .= " AND s.ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']);
	$q .= " AND ".time()." < s.expire_time AND s.logout_time IS NULL GROUP BY c.card_id;";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$j=0;
		while($card_session = $r->fetch()) {
			if ($j == 0) $this_card_session = $card_session;
			
			// Make sure the user has a maximum of 1 active gift card session
			if ($j > 0) {
				$qq = "UPDATE card_sessions SET logout_time='".(time()-1)."' WHERE session_id='".$card_session['session_id']."';";
				$rr = $app->run_query($qq);
			}
			
			if (empty($thisuser) && !empty($card_session['user_id'])) {
				$thisuser = new User($app, $card_session['user_id']);
			}
			
			$j++;
		}
	}
}
?>