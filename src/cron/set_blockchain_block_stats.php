<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug','blockchain_id', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = true;

	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);

	$process_lock_name = "set_blockchain_block_stats_".$blockchain->db_blockchain['blockchain_id'];

	$process_locked = $app->check_process_running($process_lock_name);

	if (!empty($_REQUEST['force']) || (!$process_locked && $app->lock_process($process_lock_name))) {
		$blocks = $app->run_query("SELECT block_id, internal_block_id FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=1 AND (num_transactions=0 OR num_transactions IS NULL) ORDER BY block_id ASC LIMIT 500;", [
			'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		])->fetchAll(PDO::FETCH_ASSOC);

		if ($print_debug) $app->print_debug("Setting block stats for ".count($blocks)." blocks.");

		foreach ($blocks as $block) {
			$num_transactions = $blockchain->set_block_stats($block);
		}

		if ($print_debug) $app->print_debug("Completed in ".round(microtime(true)-$script_start_time, 6)." sec.");
	}
	else echo "Process is already running.\n";
}
else echo "Please run this script from the command line.\n";
