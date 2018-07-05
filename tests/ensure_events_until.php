<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['block_height'] = $cmd_vars['block_height'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = (int) $_REQUEST['game_id'];
	$block_height = (int) $_REQUEST['block_height'];

	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);

	$debug_text = $game->ensure_events_until_block($block_height);
	echo $game->db_game['name']."->ensure_events_until($block_height);\n$debug_text\nDone!\n";
}
else echo "Please supply the correct key.\n";
?>