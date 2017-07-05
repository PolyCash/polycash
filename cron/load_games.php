<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
if ($GLOBALS['process_lock_method'] == "db") {
	include(realpath(dirname(dirname(__FILE__)))."/includes/handle_script_shutdown.php");
}

$script_target_time = 59;
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_games = $app->check_process_running("loading_games");
	
	if (!$loading_games) {
		if ($GLOBALS['process_lock_method'] == "db") {
			$GLOBALS['app'] = $app;
			$GLOBALS['shutdown_lock_name'] = "loading_games";
			$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
			register_shutdown_function("script_shutdown");
		}
		
		$blockchains = array();
		
		$loop_target_time = $app->get_site_constant("loop_target_time");
		do {
			$loop_start_time = microtime(true);
			
			$real_game_q = "SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.p2p_mode='rpc' AND g.game_status IN ('published','running')";
			if (!empty($_REQUEST['game_id'])) $real_game_q .= " AND g.game_id='".(int)$_REQUEST['game_id']."'";
			$real_game_q .= " AND b.online=1;";
			$real_game_r = $app->run_query($real_game_q);
			echo "Looping through ".$real_game_r->rowCount()." games.\n";
			
			while ($db_real_game = $real_game_r->fetch()) {
				if (empty($blockchains[$db_real_game['blockchain_id']])) $blockchains[$db_real_game['blockchain_id']] = new Blockchain($app, $db_real_game['blockchain_id']);
				$real_game = new Game($blockchains[$db_real_game['blockchain_id']], $db_real_game['game_id']);
				$real_game->sync();
			}
			
			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			$loop_target_time = max(1, $loop_time);
			$sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			echo "script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_target_time));
	}
	echo "Game load script is already running...\n";
}
else echo "Please supply the correct key.\n";
?>