<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	if ($game) {
		$action = 'reset';
		if ($_REQUEST['action'] == "delete") $action = "delete";
		$game->delete_reset_game($action);
	}
	
	echo "Great, the game has been ".$action."!";
}
else echo "Incorrect key.";
?>
