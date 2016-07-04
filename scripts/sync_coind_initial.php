<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$start_time = microtime(true);
	
	$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

	$game_id = $app->get_site_constant('primary_game_id');
	$game = new Game($app, $game_id);
	$game->delete_reset_game('reset');

	$q = "DELETE FROM addresses WHERE game_id='".$game->db_game['game_id']."';";
	$app->run_query($q);
	
	$blocks = array();
	$transactions = array();
	$block_height = 0;

	$genesis_hash = $coin_rpc->getblockhash(0);
	echo "genesis hash: ".$genesis_hash."<br/>\n";

	$current_hash = $genesis_hash;
	$keep_looping = true;
	
	$new_transaction_count = 0;

	$blocks[$block_height] = new block($coin_rpc->getblock($current_hash), $block_height, $current_hash);
	$tx_hash = $blocks[$block_height]->json_obj['tx'][0];
	$transactions[0] = new transaction($tx_hash, "", false, $block_height);
	
	$output_address = $game->create_or_fetch_address("genesis_address", true, false, false, false);
	
	$q = "INSERT INTO transactions SET game_id='".$game->db_game['game_id']."', amount='".$game->db_game['pow_reward']."', transaction_desc='coinbase', tx_hash='".$tx_hash."', address_id=".$output_address['address_id'].", block_id='".$block_height."', time_created='".time()."';";
	$r = $app->run_query($q);
	$transaction_id = $app->last_insert_id();
	
	$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$game->db_game['game_id']."', user_id=NULL, address_id='".$output_address['address_id']."'";
	$q .= ", create_transaction_id='".$transaction_id."', amount='".$game->db_game['pow_reward']."', create_block_id='".$block_height."';";
	$r = $app->run_query($q);
	
	$q = "INSERT INTO blocks SET game_id='".$game->db_game['game_id']."', block_hash='".$genesis_hash."', block_id='".$block_height."', time_created='".time()."';";
	$r = $app->run_query($q);
	
	echo "Added the genesis transaction!<br/>\n";
	
	$current_hash = $blocks[$block_height]->json_obj['nextblockhash'];
	
	do {
		$block_height = count($blocks);
		
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
	
	echo "Looping through rounds 1 to $completed_rounds.<br/>\n";
	for ($round_id=1; $round_id<=$completed_rounds; $round_id++) {
		$game->add_round_from_rpc($round_id);
	}
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
