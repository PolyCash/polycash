<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$script_start_time = microtime(true);

// Release the lock for this script whenever it terminates
declare(ticks = 1);
pcntl_signal(SIGINT, 'shutdown');
pcntl_signal(SIGTERM, 'shutdown');
function shutdown($app){
	$app->set_site_constant("loading_blocks", 0);
}

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_blocks = (int) $app->get_site_constant("loading_blocks");
	
	if ($loading_blocks == 0) {
		$app->set_site_constant("loading_blocks", 1);
		register_shutdown_function('shutdown', $app);
		
		$real_games = array();
		$q = "SELECT * FROM games WHERE game_type='real' AND game_status='running';";
		$r = $app->run_query($q);
		while ($real_game = $r->fetch()) {
			$real_games[count($real_games)] = new Game($app, $real_game['game_id']);
			echo "Including game: ".$real_game['name']."<br/>\n";
		}
	
		for ($real_game_i=0; $real_game_i<count($real_games); $real_game_i++) {
			$coin_rpc = new jsonRPCClient('http://'.$real_games[$real_game_i]->db_game['rpc_username'].':'.$real_games[$real_game_i]->db_game['rpc_password'].'@127.0.0.1:'.$real_games[$real_game_i]->db_game['rpc_port'].'/');
			$real_games[$real_game_i]->load_all_block_headers($coin_rpc, TRUE);
			$real_games[$real_game_i]->load_all_blocks($coin_rpc, TRUE);
			//$real_games[$real_game_i]->load_all_block_headers($coin_rpc, FALSE);
			//$real_games[$real_game_i]->load_all_blocks($coin_rpc, FALSE);
		}
		
		$app->set_site_constant("loading_blocks", 0);
	}
	else echo "Block loading script is already running, skip...\n";
}
?>
