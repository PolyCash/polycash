<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);
$ref_time = $script_start_time;

$allowed_params = ['print_debug','blockchain_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = true;

	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);

	$process_lock_name = "set_blockchain_block_stats_".$blockchain->db_blockchain['blockchain_id'];

	$process_locked = $app->check_process_running($process_lock_name);

	if (!empty($_REQUEST['force']) || (!$process_locked && $app->lock_process($process_lock_name))) {
		$keep_looping = true;
		do {
			$blocks = $app->run_query("SELECT block_id, internal_block_id FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=1 AND (sum_coins_out=0 OR sum_coins_out IS NULL) ORDER BY block_id DESC LIMIT 500;", [
				'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
			])->fetchAll(PDO::FETCH_ASSOC);

			if ($print_debug) $app->print_debug("Setting block stats for ".count($blocks)." blocks.");

			if (count($blocks) > 0) {
				foreach ($blocks as $block) {
					$num_transactions = $blockchain->set_block_stats($block);
				}

				if ($print_debug) {
					$app->print_debug("Completed to block #".$block['block_id']." in ".round(microtime(true)-$ref_time, 6)." sec.");
					$ref_time = microtime(true);
				}
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
	}
	else echo "Process is already running.\n";
}
else echo "Please run this script from the command line.\n";
