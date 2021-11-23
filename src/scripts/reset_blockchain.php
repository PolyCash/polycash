<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['blockchain_id', 'block_id', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin() || $app->user_is_admin($thisuser)) {
	set_time_limit(0);
	$start_time = microtime(true);
	
	if (!AppSettings::runningFromCommandLine()) echo "<pre>";
	
	if (empty($_REQUEST['blockchain_id'])) die("Please specify a blockchain_id.\n");
	else $blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	
	$process_lock_name = "load_blocks_".$blockchain_id;
	
	$app->print_debug("Waiting for block loading script to finish");
	
	do {
		$app->print_debug(". ");
		$app->flush_buffers();
		sleep(1);
		$process_locked = $app->check_process_running($process_lock_name);
	}
	while ($process_locked);
	
	$app->print_debug("Now resetting ".$blockchain->db_blockchain['blockchain_name']);
	
	$app->set_site_constant($process_lock_name, getmypid());
	$blockchain->reset_blockchain(true);
	$app->set_site_constant($process_lock_name, 0);
	
	$app->print_debug("Completed in ".round(microtime(true)-$start_time, 6)." sec");
}
else {
	echo "You need admin privileges to run this script.\n";
}
?>
