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
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$session = mysql_fetch_array($r);
		
		$q = "SELECT * FROM users WHERE user_id='".$session['user_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$thisuser = mysql_fetch_array($r);
			
			$q = "SELECT * FROM games WHERE game_id='".$thisuser['game_id']."';";
			$r = run_query($q);
			if (mysql_numrows($r) == 1) {
				$game = mysql_fetch_array($r);
			}
			else die("Invalid game selected.");
		}
	}
}
?>