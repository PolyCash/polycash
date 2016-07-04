<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");
include(realpath(dirname(__FILE__))."/../includes/handle_script_shutdown.php");

$script_start_time = microtime(true);

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$loading_blocks = (int) $app->get_site_constant("loading_blocks");
	
	if ($loading_blocks == 0) {
		$GLOBALS['app'] = $app;
		$GLOBALS['shutdown_lock_name'] = "loading_blocks";
		$app->set_site_constant($GLOBALS['shutdown_lock_name'], 1);
		register_shutdown_function("script_shutdown");
		
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
	}
	else echo "Block loading script is already running, skip...\n";
}
?>
