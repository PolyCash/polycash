<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['block_id'])) $_REQUEST['block_id'] = $cmd_vars['block_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (empty($_REQUEST['blockchain_id'])) die("Please specify a blockchain_id.\n");
	else $blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	
	if (!empty($_REQUEST['block_id'])) $from_block_id = (int) $_REQUEST['block_id'];
	else $from_block_id = false;
	
	echo $blockchain->sync_initial($from_block_id);
}
else {
	echo "Error: you supplied the wrong key for scripts/sync_coind_initial.php\n";
}
?>
