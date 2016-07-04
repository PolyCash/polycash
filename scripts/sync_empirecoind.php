<?php
include("../includes/connect.php");
include("../includes/jsonRPCClient.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

	$game_id = get_site_constant('primary_game_id');
	delete_reset_game('reset', $game_id);

	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);

	$blocks = array();
	$transactions = array();

	$genesis_hash = $empirecoin_rpc->getblockhash(0);
	echo "genesis hash: ".$genesis_hash."<br/>\n";

	$current_hash = $genesis_hash;
	$keep_looping = true;

	echo "<pre>\n";
	$new_transaction_count = 0;

	do {
		$block_id = count($blocks);
		$blocks[$block_id] = new block($empirecoin_rpc->getblock($current_hash), $block_id, $current_hash);
		
		$q = "INSERT INTO blocks SET game_id='".$game_id."', block_hash='".$current_hash."', block_id='".$block_id."', time_created='".time()."';";
		$r = run_query($q);
		$internal_block_id = mysql_insert_id();
		
		$from_transaction_id = false;
		$to_transaction_id = false;
		
		echo "\nAdding ".count($blocks[$block_id]->json_obj['tx'])." transactions in block #".$block_id;
		for ($i=0; $i<count($blocks[$block_id]->json_obj['tx']); $i++) {
			$transaction_id = count($transactions);
			if ($i == 0) $from_transaction_id = $transaction_id;
			if ($i == count($blocks[$block_id]->json_obj['tx'])-1) $to_transaction_id = $transaction_id;
			
			if ($transaction_id > 0) {
				$tx_hash = $blocks[$block_id]->json_obj['tx'][$i];
				
				try {
					$raw_transaction = $empirecoin_rpc->getrawtransaction($tx_hash);
					$transactions[$transaction_id] = new transaction($tx_hash, $raw_transaction, $empirecoin_rpc->decoderawtransaction($raw_transaction), $block_id);
					
					$outputs = $transactions[$transaction_id]->json_obj["vout"];
					$inputs = $transactions[$transaction_id]->json_obj["vin"];
					
					if (count($inputs) == 1 && $inputs[0]['coinbase']) {
						$transactions[$transaction_id]->is_coinbase = true;
						$transaction_type = "coinbase";
						if ($block_id%10 == 0 && $block_id > 0) $transaction_type = "votebase";
					}
					else $transaction_type = "transaction";
					
					$output_sum = 0;
					for ($j=0; $j<count($outputs); $j++) {
						$output_sum += pow(10,8)*$outputs[$j]["value"];
					}
					
					$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."', amount='".$output_sum."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', address_id=NULL, block_id='".$block_id."', time_created='".time()."';";
					$r = run_query($q);
					$db_transaction_id = mysql_insert_id();
					$transactions[$transaction_id]->db_id = $db_transaction_id;
					$transactions[$transaction_id]->output_sum = $output_sum;
					echo ". ";
					
					for ($j=0; $j<count($outputs); $j++) {
						$address = $outputs[$j]["scriptPubKey"]["addresses"][0];
						
						$output_address = create_or_fetch_address($game, $address, true, $empirecoin_rpc, false);
						
						$q = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game_id."', out_index='".$j."', user_id=NULL, address_id='".$output_address['address_id']."'";
						if ($output_address['nation_id'] > 0) $q .= ", nation_id=".$output_address['nation_id'];
						$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."', create_block_id='".$block_id."';";
						$r = run_query($q);
					}
					
					$new_transaction_count++;
				}
				catch (Exception $e) {
					echo "Please make sure that txindex=1 is included in your EmpireCoin.conf<br/>\n";
					echo "Exception Error:<br/>\n";
					var_dump($e);
					die();
				}
			}
			else {
				$tx_hash = $blocks[$block_id]->json_obj['tx'][$i];
				$transactions[0] = new transaction($tx_hash, "", false, $block_id);
				
				$output_address = create_or_fetch_address($game, "genesis_address", true, false, false);
				
				$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."', amount='".(25*pow(10,8))."', transaction_desc='coinbase', tx_hash='".$tx_hash."', address_id=".$output_address['address_id'].", block_id='".$block_id."', time_created='".time()."';";
				$r = run_query($q);
				$transaction_id = mysql_insert_id();
				
				$q = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game_id."', user_id=NULL, address_id='".$output_address['address_id']."'";
				$q .= ", create_transaction_id='".$transaction_id."', amount='".(25*pow(10,8))."', create_block_id='".$block_id."';";
				$r = run_query($q);
				echo "\nAdded the genesis transaction!";
			}
		}
		
		for ($i=$from_transaction_id; $i<=$to_transaction_id; $i++) {
			if ($i > 0) {
				$spend_io_ids = array();
				$input_sum = 0;
				
				if (!$transactions[$i]->is_coinbase) {
					$inputs = $transactions[$i]->json_obj["vin"];
					
					for ($j=0; $j<count($inputs); $j++) {
						$q = "SELECT * FROM webwallet_transactions t JOIN transaction_IOs i ON t.transaction_id=i.create_transaction_id WHERE t.game_id='".$game_id."' AND i.spend_status='unspent' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
						$r = run_query($q);
						if (mysql_numrows($r) > 0) {
							$spend_io = mysql_fetch_array($r);
							$spend_io_ids[$j] = $spend_io['io_id'];
							$input_sum += $spend_io['amount'];
						}
						else {
							$transaction_error = true;
							echo "Error in block $block_id, Nothing found for: ".$q."\n";
							var_dump($inputs[$j]);
							echo "\n\n";
						}
					}
					
					if (!$transaction_error && $input_sum >= $transactions[$i]->output_sum) {
						if (count($spend_io_ids) > 0) {
							$q = "UPDATE transaction_IOs SET spend_status='spent', spend_transaction_id='".$transactions[$i]->db_id."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
							$r = run_query($q);
						}
					}
					else {
						echo "Error in transaction #".$i." (".$input_sum." vs ".$transactions[$i]->output_sum.")\n";
						var_dump($transactions[$i]);
						echo "\n";
					}
				}
			}
		}
		
		$current_hash = $blocks[$block_id]->json_obj['nextblockhash'];
		
		if (!$current_hash || $current_hash == "") $keep_looping = false;
	} while ($keep_looping && $block_id < 902);

	echo "\n$new_transaction_count transactions have been added.\n";

	$q = "SELECT MAX(block_id) FROM blocks WHERE game_id='".$game['game_id']."';";
	$r = run_query($q);
	$max_block = mysql_fetch_row($r);
	$max_block = $max_block[0];
	$completed_rounds = floor($max_block/get_site_constant('round_length'));

	for ($round_id=1; $round_id<=$completed_rounds; $round_id++) {
		add_round_from_rpc($game, $round_id);
	}

	echo "$completed_rounds rounds have been added.";

	echo "</pre>";
}
else {
	echo "Please supply the correct key.";
}
?>