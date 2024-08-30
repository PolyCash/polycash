<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 1230;
$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug', 'blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;

	$blockchain = null;

	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	}

	if (empty($blockchain)) die("Please specify a valid blockchain_id.\n");
	if ($blockchain->db_blockchain['p2p_mode'] != "rpc") die("This process only runs for RPC blockchains.\n");

	$process_lock_name = "remove_unconfirmable_".$blockchain->db_blockchain['blockchain_id'];

	$process_locked = $app->check_process_running($process_lock_name);

	if (!$process_locked && $app->lock_process($process_lock_name)) {
		if ($print_debug) $app->print_debug("Removing unconfirmable transactions for ".$blockchain->db_blockchain['blockchain_name']);

		$loop_target_time = 600;
		do {
			$loop_start_time = microtime(true);

			$blockchain = new Blockchain($app, $blockchain->db_blockchain['blockchain_id']);
			$blockchain->load_coin_rpc();

			if ($blockchain->coin_rpc) {
				$unconfirmed_transactions = $blockchain->fetch_unconfirmed_transactions();

				if ($print_debug) $app->print_debug("Checking ".count($unconfirmed_transactions)." unconfirmed transactions.");

				foreach ($unconfirmed_transactions as $unconfirmed_transaction) {
					$rawtransaction = $blockchain->coin_rpc->getrawtransaction($unconfirmed_transaction['tx_hash']);

					if (is_string($rawtransaction)) {} // transaction exists
					else if (!empty($rawtransaction['code']) && $rawtransaction['code'] == -5) {
						$remove_successful = $blockchain->delete_transaction($unconfirmed_transaction);
						$message = $app->log_message("Removing unconfirmed transaction ".$unconfirmed_transaction['tx_hash']." from db that no longer exists in coin daemon (success: ".json_encode($remove_successful).")");
						if ($print_debug) $app->print_debug($message);
					}
					else if ($print_debug) $app->print_debug("Unexpected output encountered while checking if tx ".$unconfirmed_transaction['tx_hash']." exists in coin daemon: ".json_encode($rawtransaction));
				}
			}
			else if ($print_debug) $app->print_debug("Failed to initialize RPC client.");

			$loop_stop_time = microtime(true);
			$loop_time = $loop_stop_time-$loop_start_time;
			if ($loop_time < $loop_target_time) $sleep_usec = round(pow(10,6)*($loop_target_time - $loop_time));
			else $sleep_usec = 0;

			if ($print_debug) $app->print_debug("Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.");

			usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + $script_target_time);

		$runtime_sec = microtime(true)-$script_start_time;

		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "Remove unconfirmable transactions process is already running.\n";
}
else echo "Please run this script as administrator\n";
