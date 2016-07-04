<?php
if ($session_key == "") {
	$session_key = session_id();
}

if ($session_key == "") {
	session_start();
	$session_key = session_id();
}

$game = FALSE;

if (strlen($session_key) > 0) {
	$q = "SELECT * FROM user_sessions WHERE session_key='".$session_key."' AND expire_time > '".time()."' AND logout_time=0;";
	$r = $GLOBALS['app']->run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$session = mysql_fetch_array($r);
		
		$thisuser = new User($session['user_id']);
		
		if ($thisuser->db_user) {
			$game_id = intval($_REQUEST['game_id']);
			
			if ($game_id > 0) {
				$q = "SELECT g.* FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game_id."';";
				$r = $GLOBALS['app']->run_query($q);
				
				if (mysql_numrows($r) > 0) {
					$db_game = mysql_fetch_array($r);
					
					$game = new Game($db_game['game_id']);
				}
			}
		}
		else $thisuser = false;
	}
}
?>