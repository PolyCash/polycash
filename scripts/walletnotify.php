<?php
include("/var/www/html/includes/connect.php");
include("/var/www/html/includes/jsonRPCClient.php");

$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
$r = run_query($q);
$game = mysql_fetch_array($r);

$q = "SELECT MAX(block_id) FROM blocks WHERE game_id='".$game['game_id']."';";
$r = run_query($q);
$lastblock_id = mysql_fetch_row($r);
$lastblock_id = $lastblock_id[0];

$getinfo = $empirecoin_rpc->getinfo();

if ($getinfo['blocks'] > $lastblock_id) {
	echo "Need to add ".($getinfo['blocks']-$lastblock_id)."<br/>";
	$q = "SELECT * FROM blocks WHERE game_id='".$game['game_id']."' AND block_id='".$lastblock_id."';";
	$r = run_query($q);
	$lastblock = mysql_fetch_array($r);
	
	$lastblock_rpc = $empirecoin_rpc->getblock($lastblock['block_hash']);
	
	for ($block_i=1; $block_i<=$getinfo['blocks']-$lastblock_id; $block_i++) {
		$new_block_id = ($lastblock['block_id']+$block_i);
		$new_hash = $lastblock_rpc['nextblockhash'];
		$lastblock_rpc = $empirecoin_rpc->getblock($new_hash);
		$q = "INSERT INTO blocks SET game_id='".$game['game_id']."', block_hash='".$new_hash."', block_id='".$new_block_id."', time_created='".time()."';";
		$r = run_query($q);
		echo "looping through ".count($lastblock_rpc['tx'])." transactions in block #".$new_block_id."<br/>\n";
		
		for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
			$tx_hash = $lastblock_rpc['tx'][$i];
			
			$q = "SELECT * FROM webwallet_transactions WHERE game_id='".$game['game_id']."' AND tx_hash='".$tx_hash."';";
			$r = run_query($q);
			if (mysql_numrows($r) > 0) {
				$unconfirmed_tx = mysql_fetch_array($r);
				$q = "UPDATE webwallet_transactions SET block_id='".$new_block_id."' WHERE transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$r = run_query($q);
				$q = "UPDATE transaction_IOs SET create_block_id='".$new_block_id."' WHERE create_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$r = run_query($q);
				$q = "UPDATE transaction_IOs SET spend_block_id='".$new_block_id."' WHERE spend_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$r = run_query($q);
			}
			else {
				$raw_transaction = $empirecoin_rpc->getrawtransaction($tx_hash);
				$transaction_rpc = $empirecoin_rpc->decoderawtransaction($raw_transaction);
				
				$outputs = $transaction_rpc["vout"];
				$inputs = $transaction_rpc["vin"];
				
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
				
				$q = "INSERT INTO webwallet_transactions SET game_id='".$game['game_id']."', amount='".$output_sum."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', address_id=NULL, block_id='".$new_block_id."', time_created='".time()."';";
				$r = run_query($q);
				$db_transaction_id = mysql_insert_id();
				echo "just added a transaction: $q\n";
				
				for ($j=0; $j<count($outputs); $j++) {
					$address = $outputs[$j]["scriptPubKey"]["addresses"][0];
					
					$q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."' AND address='".$address."';";
					$r = run_query($q);
					
					if (mysql_numrows($r) > 0) {
						$output_address = mysql_fetch_array($r);
					}
					else {
						$address_nation_id = addr_text_to_nation_id($address);
						$q = "INSERT INTO addresses SET game_id='".$game['game_id']."', address='".$address."', nation_id='".$address_nation_id."', time_created='".time()."';";
						$r = run_query($q);
						echo "qqq: $q\n";
						$output_address_id = mysql_insert_id();
						$q = "SELECT * FROM addresses WHERE address_id='".$output_address_id."';";
						$r = run_query($q);
						$output_address = mysql_fetch_array($r);
					}
					
					$q = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game['game_id']."', out_index='".$j."', user_id=NULL, address_id='".$output_address['address_id']."'";
					if ($output_address['nation_id'] > 0) $q .= ", nation_id=".$output_address['nation_id'];
					$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."', create_block_id='".$new_block_id."';";
					$r = run_query($q);
					echo "qqqq: $q\n";
				}
			}
		}
		
		for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
			$tx_hash = $lastblock_rpc['tx'][$i];
			$q = "SELECT * FROM webwallet_transactions WHERE tx_hash='".$tx_hash."';";
			$r = run_query($q);
			$transaction = mysql_fetch_array($r);
			
			$raw_transaction = $empirecoin_rpc->getrawtransaction($tx_hash);
			$transaction_rpc = $empirecoin_rpc->decoderawtransaction($raw_transaction);
			
			$outputs = $transaction_rpc["vout"];
			$inputs = $transaction_rpc["vin"];
			
			$transaction_error = false;
			
			$output_sum = 0;
			for ($j=0; $j<count($outputs); $j++) {
				$output_sum += pow(10,8)*$outputs[$j]["value"];
			}
			
			$spend_io_ids = array();
			$input_sum = 0;
			
			if ($transaction['transaction_desc'] == "transaction") {
				for ($j=0; $j<count($inputs); $j++) {
					$q = "SELECT * FROM webwallet_transactions t JOIN transaction_IOs i ON t.transaction_id=i.create_transaction_id WHERE t.game_id='".$game['game_id']."' AND i.spend_status='unspent' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
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
				
				if (!$transaction_error && $input_sum >= $output_sum) {
					if (count($spend_io_ids) > 0) {
						$q = "UPDATE transaction_IOs SET spend_status='spent', spend_transaction_id='".$transactions['transaction_id']."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
						$r = run_query($q);
						echo "qqqqq: $q\n";
					}
				}
				else {
					echo "Error in transaction #".$transaction['transaction_id']." (".$input_sum." vs ".$output_sum.")\n";
					var_dump($transaction);
					echo "\n";
				}
			}
		}
		
		if ($new_block_id%get_site_constant('round_length') == 0) add_round_from_rpc($game, $new_block_id/get_site_constant('round_length'));
	}
}

$tx_hash = $argv[1];

$raw_transaction = $empirecoin_rpc->getrawtransaction($tx_hash);
$transaction_obj = $empirecoin_rpc->decoderawtransaction($raw_transaction);

$q = "SELECT * FROM webwallet_transactions WHERE tx_hash='".$tx_hash."';";
$r = run_query($q);
if (mysql_numrows($r) > 0) {
	$transaction = mysql_fetch_array($r);
}
else {
	$outputs = $transaction_obj["vout"];
	$inputs = $transaction_obj["vin"];

	if (count($inputs) == 1 && $inputs[0]['coinbase']) {
		$transaction_type = "coinbase";
	}
	else $transaction_type = "transaction";

	$output_sum = 0;
	for ($j=0; $j<count($outputs); $j++) {
		$output_sum += pow(10,8)*$outputs[$j]["value"];
	}
	
	$q = "INSERT INTO webwallet_transactions SET game_id='".$game['game_id']."', amount='".$output_sum."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', address_id=NULL, block_id=NULL, time_created='".time()."';";
	$r = run_query($q);
	$db_transaction_id = mysql_insert_id();
	$q = "SELECT * FROM webwallet_transactions WHERE transaction_id='".$db_transaction_id."';";
	$r = run_query($q);
	$transaction = mysql_fetch_array($r);
	
	for ($j=0; $j<count($outputs); $j++) {
		$address = $outputs[$j]["scriptPubKey"]["addresses"][0];
		
		$q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."' AND address='".$address."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$output_address = mysql_fetch_array($r);
		}
		else {
			$address_nation_id = addr_text_to_nation_id($address);
			$q = "INSERT INTO addresses SET game_id='".$game['game_id']."', address='".$address."', nation_id='".$address_nation_id."', time_created='".time()."';";
			$r = run_query($q);
			$output_address_id = mysql_insert_id();
			$q = "SELECT * FROM addresses WHERE address_id='".$output_address_id."';";
			$r = run_query($q);
			$output_address = mysql_fetch_array($r);
		}
		
		$q = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game['game_id']."', out_index='".$j."', user_id=NULL, address_id='".$output_address['address_id']."'";
		if ($output_address['nation_id'] > 0) $q .= ", nation_id=".$output_address['nation_id'];
		$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."';";
		$r = run_query($q);
	}
}

set_site_constant('walletnotify', $tx_hash);
?>