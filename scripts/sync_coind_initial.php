<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$start_time = microtime(true);
	
	$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

	$game_id = $app->get_site_constant('primary_game_id');
	$game = new Game($app, $game_id);

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
			$game->delete_blocks_from_height($block_height);
		}
		else die("Error, that block was not found (".$r->rowCount().").");
	}
	else {
		$r = $app->run_query("SELECT * FROM blocks WHERE block_id='0' AND game_id='".$game->db_game['game_id']."';");
		
		if ($r->rowCount() == 0) {
			$game->delete_reset_game('reset');

			$q = "DELETE FROM addresses WHERE game_id='".$game->db_game['game_id']."';";
			$app->run_query($q);
		
			$returnvals = $game->add_genesis_block($coin_rpc);
			$current_hash = $returnvals['nextblockhash'];
		}
		else {
			$db_block = $r->fetch();
			$current_hash = $db_block['block_hash'];
		}
	}
	
	do {
		$block_height++;
		
		$blocks[$block_height] = new block($coin_rpc->getblock($current_hash), $block_height, $current_hash);
		
		if ($block_height == 1) {
			$q = "UPDATE games SET start_time='".$blocks[$block_height]->json_obj['time']."', start_datetime=FROM_UNIXTIME(".$blocks[$block_height]->json_obj['time'].") WHERE game_id='".$game->db_game['game_id']."';";
			$r = $app->run_query($q);
		}
		
		echo $game->coind_add_block($coin_rpc, $current_hash, $block_height);
		
		if (empty($blocks[$block_height]->json_obj['nextblockhash'])) $current_hash = "";
		else $current_hash = $blocks[$block_height]->json_obj['nextblockhash'];
		
		if (!$current_hash || $current_hash == "") $keep_looping = false;
	} while ($keep_looping);
	
	echo "<br/>Finished adding confirmed transactions at ".(microtime(true) - $start_time)." sec<br/>\n";
	
	$q = "SELECT MAX(block_id) FROM blocks WHERE game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	$max_block = $r->fetch(PDO::FETCH_NUM);
	$max_block = $max_block[0];
	$completed_rounds = floor($max_block/$game->db_game['round_length']);
	
	echo "Finished adding rounds at ".(microtime(true)-$start_time)." sec<br/>\n";
	
	$unconfirmed_txs = $coin_rpc->getrawmempool();
	echo "Looping through ".count($unconfirmed_txs)." unconfirmed transactions.<br/>\n";
	for ($i=0; $i<count($unconfirmed_txs); $i++) {
		$game->walletnotify($coin_rpc, $unconfirmed_txs[$i]);
	}
	
	$app->refresh_utxo_user_ids(false);
	$game->update_option_scores();
	
	echo "Completed sync ($completed_rounds rounds) at ".(microtime(true)-$start_time)." sec<br/>\n";
}
else {
	echo "Please supply the correct key.";
}
?>
