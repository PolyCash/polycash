<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id','account_id','quantity','fee','runs'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	$account = $app->fetch_account_by_id($_REQUEST['account_id']);
	$quantity = (int) $_REQUEST['quantity'];
	$fee = (float) $_REQUEST['fee'];
	$fee_int = $fee*pow(10, $game->blockchain->db_blockchain['decimal_places']);
	$runs = 1;
	if (!empty($_REQUEST['runs'])) $runs = (int) $_REQUEST['runs'];
	
	for ($run=0; $run<$runs; $run++) {
		$spendable_ios = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], false, false)->fetchAll();
		$quantity = min($quantity, count($spendable_ios));
		
		if (count($spendable_ios) > 10) {
			echo "Account #".$account['account_id']." has ".number_format(count($spendable_ios))." spendable UTXOs in ".$game->db_game['name']."\n";
			echo "Joining ".$quantity." UTXOs with a fee of ".$fee."\n";
			
			$spend_io_ids = [];
			$io_input_sum = 0;
			
			for ($i=0; $i<$quantity; $i++) {
				array_push($spend_io_ids, $spendable_ios[$i]['io_id']);
				$io_input_sum += $spendable_ios[$i]['amount'];
			}
			
			$to_address_key = $app->new_normal_address_key($account['currency_id'], $account);
			
			$to_address_ids = [$to_address_key['address_id']];
			
			$amounts = [
				$io_input_sum - $fee_int
			];
			
			$error_message = null;
			$transaction_id = $game->blockchain->create_transaction('transaction', $amounts, false, $spend_io_ids, $to_address_ids, $fee_int, $error_message);
			
			echo "tx id: ".json_encode($transaction_id).", message: ".json_encode($error_message)."\n";
			
			if ($transaction_id) {
				$transaction = $app->fetch_transaction_by_id($transaction_id);
				echo "tx hash: ".$transaction['tx_hash']."\n";
			}
			else $transaction = null;
			
			echo "\n";
		}
		else $run = $runs;
	}
}
else echo "Please run this script as admin.\n";
