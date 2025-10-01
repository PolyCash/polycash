<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','blockchain_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = true;

	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	
	$process_lock_name = "integrity_checks_".$blockchain->db_blockchain['blockchain_id'];
	
	$process_locked = $app->check_process_running($process_lock_name);

	$next_integrity_check_time = $app->get_site_constant("next_integrity_check_".$blockchain->db_blockchain['blockchain_id']);

	if ((empty($next_integrity_check_time) || time() >= $next_integrity_check_time) || !empty($_REQUEST['force'])) {
		if (!empty($_REQUEST['force']) || (!$process_locked && $app->lock_process($process_lock_name))) {
			if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
				$blockchain->load_coin_rpc();
				
				if ($blockchain->coin_rpc) {
					$blockchaininfo = $blockchain->coin_rpc->getblockchaininfo();
					if (isset($blockchaininfo['headers']) && $blockchain->last_complete_block() == $blockchaininfo['headers']) $fully_loaded = true;
					else $fully_loaded = false;
				}
				else $fully_loaded = false;
			}
			else $fully_loaded = true;
			
			if ($fully_loaded) {
				$fix_from_block = null;

				$false_spent_ios = $app->run_query("SELECT io.io_id, io.create_block_id FROM transaction_ios io WHERE io.blockchain_id=:blockchain_id AND io.spend_status='spent' AND io.create_block_id >= :first_required_block_id AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.transaction_id=io.spend_transaction_id);", [
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
					'first_required_block_id' => $blockchain->db_blockchain['first_required_block'],
				])->fetchAll(PDO::FETCH_ASSOC);

				if (count($false_spent_ios) > 0) {
					foreach ($false_spent_ios as $false_spent_io) {
						if ($fix_from_block === null || !empty($false_spent_io['create_block_id']) && $false_spent_io['create_block_id'] < $fix_from_block) $fix_from_block = (int) $false_spent_io['create_block_id'];
					}

					$message = $app->log_message("Integrity check found ".count($false_spent_ios)." txos incorrectly marked as spent from height ".$fix_from_block." on ".$blockchain->db_blockchain['blockchain_name']);
					if ($print_debug) $app->print_debug($message);
				}
				
				$tx_missing_position = $app->run_query("SELECT t.transaction_id, t.block_id FROM transactions t WHERE t.blockchain_id=:blockchain_id AND t.position_in_block IS NULL AND t.block_id IS NOT NULL AND t.block_id >= :first_required_block_id ORDER BY t.block_id ASC LIMIT 1;", [
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
					'first_required_block_id' => $blockchain->db_blockchain['first_required_block'],
				])->fetch(PDO::FETCH_ASSOC);
				
				if ($tx_missing_position) {
					$block_missing_position = $blockchain->fetch_block_by_id($tx_missing_position['block_id']);
					
					if ($block_missing_position && $block_missing_position['locally_saved']) {
						$message = $app->log_message("Integrity check found confirmed transactions missing position in block from height ".$tx_missing_position['block_id']." on ".$blockchain->db_blockchain['blockchain_name']);
						if ($print_debug) $app->print_debug($message);

						if ($fix_from_block === null) $fix_from_block = $tx_missing_position['block_id'];
						else $fix_from_block = min($fix_from_block, $tx_missing_position['block_id']);
					}
				}

				if ($fix_from_block !== null) {
					$message = $app->log_message("Integrity check initiating block deletion from height ".$fix_from_block." on ".$blockchain->db_blockchain['blockchain_name']);
					if ($print_debug) $app->print_debug($message);
					$blockchain->delete_blocks_from_height($fix_from_block, "integrity_checks");
				}

				$next_integrity_check_time = strtotime(date("Y-m-d H:00:01")." +6 hours");
				$app->set_site_constant("next_integrity_check_".$blockchain->db_blockchain['blockchain_id'], $next_integrity_check_time);

				echo "Next run: ".date("Y-m-d H:i:s", $next_integrity_check_time)."\n";
			}
			else echo "Postponing integrity check, blockchain is not fully loaded.\n";
		}
		else echo "Process is already running.\n";
	}
	else echo "Process will run next at ".date("Y-m-d H:i:s", $next_integrity_check_time)."\n";
}
else echo "Please run this script from the command line.\n";
