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
	
	$q = "SELECT * FROM card_sessions s JOIN card_users u ON s.card_user_id=u.card_user_id WHERE s.session_key=".$app->quote_escape($session_key);
	if ($GLOBALS['pageview_tracking_enabled']) $q .= " AND s.ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']);
	$q .= " AND ".time()." < s.expire_time AND s.logout_time IS NULL GROUP BY s.card_user_id;";
	$r = $app->run_query($q);
	$distinct_cards = array();
	
	if ($r->rowCount() > 0) {
		$j=0;
		while($card_session = $r->fetch()) {
			if ($j == 0) $this_card_session = $card_session;
			$distinct_cards[$j] = $card_session['card_id'];
			
			// Make sure the user has a maximum of 1 active gift card session
			if ($j > 0) {
				$qq = "UPDATE card_sessions SET logout_time='".(time()-1)."' WHERE session_id='".$card_session['session_id']."';";
				$rr = $app->run_query($qq);
			}
			$j++;
		}
		
		if ($j > 0) {
			$q = "SELECT * FROM cards c JOIN card_groups g ON c.card_group_id=g.card_group_id WHERE c.card_id IN (".implode(",", $distinct_cards).");";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) { // Move to existing group
				$card_group = $r->fetch();
				
				while ($extra_group = $r->fetch()) {
					$qq = "UPDATE cards SET card_group_id='".$card_group['card_group_id']."' WHERE card_group_id='".$extra_group['card_group_id']."';";
					$rr = $app->run_query($qq);
					
					$qq = "UPDATE card_group_withdrawals SET card_group_id='".$card_group['card_group_id']."' WHERE card_group_id='".$extra_group['card_group_id']."';";
					$rr = $app->run_query($qq);
				}
			}
			else { // Create a new group
				$q = "INSERT INTO card_groups () VALUES ();";
				$r = $app->run_query($q);
				$card_group_id = $app->last_insert_id();
				
				$q = "SELECT * FROM card_groups WHERE card_group_id='".$card_group_id."';";
				$r = $app->run_query($q);
				$card_group = $r->fetch();
			}
			$q = "UPDATE cards SET card_group_id='".$card_group['card_group_id']."' WHERE card_id IN (".implode(",", $distinct_cards).");";
			$r = $app->run_query($q);
		}
		
		if (empty($card_group['user_id'])) {
			if (empty($thisuser)) {
				$alias = $app->random_string(16);
				$password = $app->random_string(16);
				$verify_code = $app->random_string(32);
				$salt = $app->random_string(16);
				
				$thisuser = $app->create_new_user($verify_code, $salt, $alias, "", $password);
			}
			$q = "UPDATE card_groups SET user_id='".$thisuser->db_user['user_id']."' WHERE card_group_id='".$card_group['card_group_id']."';";
			$r = $app->run_query($q);
			
			$card_group['user_id'] = $thisuser->db_user['user_id'];
		}
		else {
			$thisuser = new User($app, $card_group['user_id']);
		}
		
		$q = "SELECT c.*, u.*, w.*, curr.*, c.amount AS amount FROM cards c JOIN card_users u ON c.card_id=u.card_id JOIN card_withdrawals w ON u.card_user_id=w.card_user_id JOIN currencies curr ON c.fv_currency_id=curr.currency_id WHERE w.withdraw_method='card_account' AND (c.card_id IN (".implode(",", $distinct_cards).")";
		if ($card_group) $q .= " OR c.card_group_id='".$card_group['card_group_id']."'";
		$q .= ");";
		$r = $app->run_query($q);
		$i = 0;
		while ($my_card = $r->fetch()) {
			$my_cards[$i] = $my_card;
			$i++;
		}
	}
}
?>