<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 1230;
$script_start_time = microtime(true);

$allowed_params = ['print_debug', 'blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;

	$blockchain = null;

	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	}

	if (empty($blockchain)) die("Please specify a valid blockchain_id.\n");

	$process_lock_name = "blockchain_checks_".$blockchain->db_blockchain['blockchain_id'];

	$process_locked = $app->check_process_running($process_lock_name);

	if (!$process_locked && $app->lock_process($process_lock_name)) {
		do {
			$inProgressChecks = BlockchainVerifier::fetchInProgressChecksForBlockchain($app, $blockchain);

			if ($print_debug) $app->print_debug("Processing ".count($inProgressChecks)." blockchain checks for ".$blockchain->db_blockchain['blockchain_name']);

			$loop_start_time = microtime(true);

			$blockchain = new Blockchain($app, $blockchain->db_blockchain['blockchain_id']);

			foreach ($inProgressChecks as $inProgressCheck) {
				$ref_time = microtime(true);
				$from_block = (string) $inProgressCheck['from_block'] === "" ? $inProgressCheck['from_block'] : $inProgressCheck['processed_to_block']+1;
				$to_block = min($from_block+5000-1, $blockchain->db_blockchain['last_complete_block']);
				$any_error = false;
				$error_message = null;

				$check_blocks_params = [
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
					'from_block' => $from_block,
					'to_block' => $to_block,
				];
				$check_blocks_q = "SELECT internal_block_id, block_id, num_ios_in, num_ios_out FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :from_block AND block_id <= :to_block ORDER BY block_id ASC;";

				$check_blocks = $app->run_query($check_blocks_q, $check_blocks_params)->fetchAll();

				if (count($check_blocks) == 0) {
					BlockchainVerifier::setCheckComplete($app, $inProgressCheck, null, null);
					break;
				}
				else {
					if($print_debug) $app->print_debug($blockchain->db_blockchain['blockchain_name']." check #".$inProgressCheck['blockchain_check_id'].": checking ".count($check_blocks)." blocks from ".$from_block." to ".$to_block);

					foreach ($check_blocks as $check_block) {
						$num_trans = $blockchain->set_block_stats($check_block);

						$num_ios_in = $app->run_query("SELECT COUNT(*) FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
							'block_id' => $check_block['block_id'],
							'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
						])->fetch(PDO::FETCH_NUM)[0];

						$num_ios_out = $app->run_query("SELECT COUNT(*) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
							'block_id' => $check_block['block_id'],
							'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
						])->fetch(PDO::FETCH_NUM)[0];

						if ($check_block['num_ios_in'] != $num_ios_in) {
							$any_error = true;
							$error_message = "Block #".$check_block['block_id']." had ".$num_ios_in." TXOs in but expected ".$check_block['num_ios_in'];
						}
						if ($check_block['num_ios_out'] != $num_ios_out) {
							$any_error = true;
							$error_message = "Block #".$check_block['block_id']." had ".$num_ios_out." TXOs out but expected ".$check_block['num_ios_out'];
						}
						
						if (!$any_error) {
							list($any_verify_block_error, $any_ios_in_error, $any_ios_out_error) = BlockchainVerifier::verifyBlock($app, $blockchain->db_blockchain['blockchain_id'], $check_block['block_id']);
							
							if ($any_ios_in_error) {
								$any_error = true;
								$error_message = "Block #".$check_block['block_id']." had invalid TXOs in";
							}
							
							if ($any_ios_out_error) {
								$any_error = true;
								$error_message = "Block #".$check_block['block_id']." had invalid TXOs out";
							}
						}
						
						if ($any_error) {
							BlockchainVerifier::setCheckComplete($app, $inProgressCheck, $check_block['block_id'], $error_message);
							if ($print_debug) $app->print_debug("Check #".$inProgressCheck['blockchain_check_id']." encountered first error on block #".$check_block['block_id'].": ".$error_message);
							break;
						}
						else if ($check_block['block_id'] % 10 == 0) BlockchainVerifier::setCheckProcessedToBlock($app, $inProgressCheck, $check_block['block_id']);
					}

					BlockchainVerifier::setCheckProcessedToBlock($app, $inProgressCheck, $check_block['block_id']);
					
					if ($print_debug) $app->print_debug("Check #".$inProgressCheck['blockchain_check_id']." completed to block #".$check_block['block_id']." in ".round(microtime(true)-$ref_time, 6)." sec.");
				}
			}

			$inProgressChecks = BlockchainVerifier::fetchInProgressChecksForBlockchain($app, $blockchain);
		}
		while (count($inProgressChecks) > 0);

		$runtime_sec = microtime(true)-$script_start_time;

		if ($print_debug) $app->print_debug("Script ran for ".round($runtime_sec, 2)." seconds.");
	}
	else echo "Blockchain checks process is already running.\n";
}
else echo "Please run this script as administrator\n";
