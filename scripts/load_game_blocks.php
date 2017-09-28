<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = (int) $_REQUEST['game_id'];
	$from_block_height = (int) $_REQUEST['from_block_height'];
	$to_block_height = (int) $_REQUEST['to_block_height'];
	
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	for ($block_height=$from_block_height; $block_height<=$to_block_height; $block_height++) {
		$log_text = "";
		list($successful, $log_text) = $game->add_block($block_height);
		echo "$log_text<br/>\n";
	}
}
?>