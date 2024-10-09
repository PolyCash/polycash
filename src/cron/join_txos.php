<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','game_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "join_txos";
	$process_locked = $app->check_process_running($process_lock_name);
	
	$next_join_txos_time = $app->get_site_constant("next_join_txos_time");
	
	if ((empty($next_join_txos_time) || time() >= $next_join_txos_time) || !empty($_REQUEST['force'])) {
		if (!empty($_REQUEST['force']) || (!$process_locked && $app->lock_process($process_lock_name))) {
			$app->set_site_constant($process_lock_name, getmypid());
			
			$next_join_txos_time = strtotime(date("Y-m-d H:00:01")." +30 minutes");
			
			$game_accounts = $app->run_query("SELECT ca.*, us.transaction_fee FROM currency_accounts ca JOIN user_games ug ON ca.account_id=ug.account_id JOIN user_strategies us ON ug.strategy_id=us.strategy_id WHERE ca.game_id IS NOT NULL AND ca.join_txos_on_quantity IS NOT NULL ORDER BY ca.account_id ASC;")->fetchAll();
			
			echo "Checking ".count($game_accounts)." game accounts.\n";
			
			$new_tx_count = 0;
			$join_txo_count = 0;
			$games_by_id = [];
			
			foreach ($game_accounts as $account) {
				if (empty($games_by_id[$account['game_id']])) {
					$db_game = $app->fetch_game_by_id($account['game_id']);
					$blockchain = new Blockchain($app, $db_game['blockchain_id']);
					$games_by_id[$account['game_id']] = new Game($blockchain, $db_game['game_id']);
				}
				$game = $games_by_id[$account['game_id']];
				
				$keep_looping = true;
				do {
					$spendable_ios = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], false, false);
					$quantity = min($account['join_txos_on_quantity'], count($spendable_ios));

					echo "Account #".$account['account_id']." has ".number_format(count($spendable_ios))." spendable UTXOs in ".$game->db_game['name']."\n";

					if (count($spendable_ios) >= $account['join_txos_on_quantity']) {
						echo "Joining ".$quantity." UTXOs with a fee of ".$account['transaction_fee']."\n";
						
						$spend_io_ids = [];
						$io_input_sum = 0;
						
						for ($i=0; $i<$quantity; $i++) {
							array_push($spend_io_ids, $spendable_ios[$i]['io_id']);
							$io_input_sum += $spendable_ios[$i]['amount'];
						}
						
						$to_address_key = $app->new_normal_address_key($account['currency_id'], $account);
						
						$to_address_ids = [$to_address_key['address_id']];

						$fee_int = (int)($account['transaction_fee']*pow(10, $game->blockchain->db_blockchain['decimal_places']));
						
						$amounts = [
							$io_input_sum - $fee_int
						];
						
						$error_message = null;
						$transaction_id = $game->blockchain->create_transaction('transaction', $amounts, false, $spend_io_ids, $to_address_ids, $fee_int, $error_message);
						
						echo "Join tx: ".json_encode($transaction_id).", message: ".json_encode($error_message)."\n";
						
						if ($transaction_id) {
							$transaction = $app->fetch_transaction_by_id($transaction_id);
							
							echo "TX hash: ".$transaction['tx_hash']."\n";
							
							$new_tx_count++;
							$join_txo_count += $quantity;
							
							$spendable_ios = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], false, false);
							
							if (count($spendable_ios) < $account['join_txos_on_quantity']) $keep_looping = false;
						}
						else $keep_looping = false;
						
						echo "\n";
					}
					else $keep_looping = false;
				}
				while ($keep_looping);
			}
			
			$gameless_accounts = $app->run_query("SELECT ca.*, c.blockchain_id FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id WHERE ca.game_id IS NULL AND c.blockchain_id IS NOT NULL AND ca.join_txos_on_quantity IS NOT NULL ORDER BY ca.account_id ASC;")->fetchAll();
			
			echo "Checking ".count($game_accounts)." gameless accounts.\n";
			
			$new_tx_count = 0;
			$join_txo_count = 0;
			$games_by_id = [];
			
			foreach ($gameless_accounts as $account) {
				$blockchain = new Blockchain($app, $account['blockchain_id']);

				$keep_looping = true;
				do {
					$spendable_ios = $app->spendable_ios_in_gameless_account($account['account_id'], $account['join_txos_on_quantity']);
					
					$quantity = min($account['join_txos_on_quantity'], count($spendable_ios));
					
					echo "Gameless account #".$account['account_id']." has ".number_format(count($spendable_ios))." spendable TXOs.\n";

					if (count($spendable_ios) >= $account['join_txos_on_quantity']) {
						echo "Joining ".$quantity." UTXOs with a fee of ".$account['account_transaction_fee']."\n";
						
						$spend_io_ids = [];
						$io_input_sum = 0;
						
						for ($i=0; $i<$quantity; $i++) {
							array_push($spend_io_ids, $spendable_ios[$i]['io_id']);
							$io_input_sum += $spendable_ios[$i]['amount'];
						}
						
						$to_address_key = $app->new_normal_address_key($account['currency_id'], $account);
						
						$to_address_ids = [$to_address_key['address_id']];

						$fee_int = (int)($account['account_transaction_fee']*pow(10, $blockchain->db_blockchain['decimal_places']));
						
						$amounts = [
							$io_input_sum - $fee_int
						];
						
						$error_message = null;
						$transaction_id = $blockchain->create_transaction('transaction', $amounts, false, $spend_io_ids, $to_address_ids, $fee_int, $error_message);
						
						echo "Join tx: ".json_encode($transaction_id).", message: ".json_encode($error_message)."\n";
						
						if ($transaction_id) {
							$transaction = $app->fetch_transaction_by_id($transaction_id);
							
							echo "TX hash: ".$transaction['tx_hash']."\n";
							
							$new_tx_count++;
							$join_txo_count += $quantity;

							$spendable_ios = $app->spendable_ios_in_gameless_account($account['account_id'], $account['join_txos_on_quantity']);

							if (count($spendable_ios) < $account['join_txos_on_quantity']) $keep_looping = false;
						}
						else $keep_looping = false;
						
						echo "\n";
					}
					else $keep_looping = false;
				}
				while ($keep_looping);
			}
			
			$app->set_site_constant("next_join_txos_time", $next_join_txos_time);
			
			echo "Join TXOs made ".$new_tx_count." transactions joining ".$join_txo_count." TXOs. Next run: ".date("Y-m-d H:i:s", $next_join_txos_time)."\n";
		}
		else echo "Process is already running.\n";
	}
	else echo "Process will run next at ".date("Y-m-d H:i:s", $next_join_txos_time)."\n";
}
else echo "Please run this script from the command line.\n";
