<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['block_id'])) $_REQUEST['block_id'] = $cmd_vars['block_id'];
	if (!empty($cmd_vars['round_id'])) $_REQUEST['round_id'] = $cmd_vars['round_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$start_time = microtime(true);
	
	$game_id = $app->get_site_constant('primary_game_id');
	if (!empty($_REQUEST['game_id'])) $game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');
	
	$start_round_id = false;
	
	$blocks = array();
	$transactions = array();
	$block_height = 0;
	
	$keep_looping = true;
	
	$new_transaction_count = 0;
	
	if (!empty($_REQUEST['block_id']) || !empty($_REQUEST['round_id'])) {
		if (!empty($_REQUEST['block_id'])) {
			$block_height = intval($_REQUEST['block_id'])-1;
		}
		else {
			$start_round_id = intval($_REQUEST['round_id']);
			$block_height = ($start_round_id-1)*$game->db_game['round_length'];
		}
		
		$q = "SELECT * FROM blocks WHERE game_id='".$game->db_game['game_id']."' AND block_id='".$block_height."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$db_prev_block = $r->fetch();
			$temp_block = $coin_rpc->getblock($db_prev_block['block_hash']);
			$current_hash = $temp_block['nextblockhash'];
			$game->delete_blocks_from_height($block_height+1);
		}
		else die("Error, that block was not found (".$r->rowCount().").");
	}
	else {
		$game->delete_reset_game('reset');
		
		$returnvals = $game->add_genesis_block($coin_rpc);
		$current_hash = $returnvals['nextblockhash'];
	}
	
	$game->insert_initial_blocks($coin_rpc);
	
	echo "<br/>Finished inserting blocks at ".(microtime(true) - $start_time)." sec<br/>\n";
	
	echo "Syncing with daemon...<br/>\n";
	echo $game->sync_coind($coin_rpc);
	
	echo "Completed sync at ".(microtime(true)-$start_time)." sec<br/>\n";
}
else {
	echo "Error: you supplied the wrong key for scripts/sync_coind_initial.php\n";
}
?>
