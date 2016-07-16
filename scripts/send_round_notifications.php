<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['round_id'] = $cmd_vars['round_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$round_id = intval($_REQUEST['round_id']);
	$game = new Game($app, $game_id);
	
	if ($game) {
		$round_voting_stats = false;
		$game->send_round_notifications($round_id, $round_voting_stats);
	}
	else echo "Error identifying the game.";
}
else echo "Incorrect key.";
?>