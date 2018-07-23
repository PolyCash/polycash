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
	$log_text = "";
	
	$q = "SELECT * FROM blockchains b JOIN games g ON b.blockchain_id=g.blockchain_id WHERE g.game_status='running'";
	if (!empty($_REQUEST['game_id'])) $q .= " AND g.game_id=".((int)$_REQUEST['game_id']);
	$q .= " GROUP BY b.blockchain_id ORDER BY b.blockchain_id ASC;";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$last_block_id = $blockchain->last_block_id();
		
		$game_q = "SELECT * FROM games WHERE game_status='running' AND blockchain_id='".$db_blockchain['blockchain_id']."'";
		if (!empty($_REQUEST['game_id'])) $game_q .= " AND game_id=".((int)$_REQUEST['game_id']);
		$game_q .= ";";
		$game_r = $app->run_query($game_q);
		
		while ($db_game = $game_r->fetch()) {
			$game = new Game($blockchain, $db_game['game_id']);
			$log_text .= $game->set_event_blocks(false);
		}
	}
	echo $log_text;
}
else echo "Incorrect key supplied.\n";
?>