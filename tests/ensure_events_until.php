<?php
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['block_height'] = $cmd_vars['block_height'];
}

$game_id = (int) $_REQUEST['game_id'];
$game = new Game($app, $game_id);

$block_height = (int) $_REQUEST['block_height'];

$game->ensure_events_until_block($block_height);
echo $game->db_game['name']."->ensure_events_until($block_height);<br/>\nDone!\n";
?>