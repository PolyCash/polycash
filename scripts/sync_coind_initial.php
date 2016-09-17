<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['block_id'])) $_REQUEST['block_id'] = $cmd_vars['block_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = $app->get_site_constant('primary_game_id');
	if (!empty($_REQUEST['game_id'])) $game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	if (!empty($_REQUEST['block_id'])) $from_block_id = (int) $_REQUEST['block_id'];
	else $from_block_id = false;
	
	$game->sync_initial($from_block_id);
}
else {
	echo "Error: you supplied the wrong key for scripts/sync_coind_initial.php\n";
}
?>
