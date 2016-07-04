<?php
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/jsonRPCClient.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = get_site_constant('primary_game_id');
	if ($_REQUEST['game_id'] > 0) $game_id = intval($_REQUEST['game_id']);
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	echo apply_user_strategies($game);
}
else echo "Please supply the correct key.";
?>