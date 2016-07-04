<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games;";
	$r = run_query($q);
	
	while ($game = mysql_fetch_array($r)) {
		if ($game['game_id'] == get_site_constant('primary_game_id')) {
			delete_reset_game('reset', $game['game_id']);
		}
		else delete_reset_game('delete', $game['game_id']);
	}
	
	$q = "UPDATE users SET game_id='".get_site_constant('primary_game_id')."';";
	$r = run_query($q);
	
	echo "Great, the game has been reset!";
}
else echo "Incorrect key.";
?>