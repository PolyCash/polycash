<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['game_id', 'block_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin() || $app->user_is_admin($thisuser)) {
	set_time_limit(0);
	
	if (empty($_REQUEST['blockchain_id'])) die("Please specify a blockchain_id.\n");
	else $blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	
	if (!empty($_REQUEST['block_id'])) $from_block_id = (int) $_REQUEST['block_id'];
	else $from_block_id = false;
	
	$process_lock_name = "load_blocks_".$blockchain_id;
	
	echo "Waiting for block loading script to finish";
	do {
		echo ". ";
		$app->flush_buffers();
		sleep(1);
		$process_locked = $app->check_process_running($process_lock_name);
	}
	while ($process_locked);
	echo "now inserting empty blocks<br/>\n";
	
	$app->set_site_constant($process_lock_name, getmypid());
	echo $blockchain->sync_initial($from_block_id);
	$app->set_site_constant($process_lock_name, 0);
	echo '<br/><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/blocks/">See Blocks</a>';
}
else {
	echo "You need admin privileges to run this script.\n";
}
?>
