<?php
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = $app->get_site_constant('primary_game_id');
	if ($_REQUEST['game_id'] > 0) $game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	echo $game->apply_user_strategies();
}
else echo "Please supply the correct key.";
?>