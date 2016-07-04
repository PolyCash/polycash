<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	
	$q = "SELECT * FROM games";
	if ($game_id > 0) $q .= " WHERE game_id='".$game_id."'";
	$q .= ";";
	$r = run_query($q);
	
	if (mysql_numrows($r) == 1) {
		$game = mysql_fetch_array($r);
		$action = 'reset';
		if ($_REQUEST['action'] == "delete") $action = "delete";
		delete_reset_game($action, $game['game_id']);
	}
	
	echo "Great, the game has been reset!";
}
else echo "Incorrect key.";
?>