<?php
ini_set('memory_limit', '1024M');
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$blockchain = new Blockchain($app, $blockchain_id);
	
	$to_block_id = $blockchain->last_complete_block_id();
	$ref_time = microtime(true);

	$from_block = $app->run_query("SELECT MIN(block_id) AS block_id FROM blocks WHERE blockchain_id=:blockchain_id AND sec_since_prev_block IS NULL AND block_id > :first_required_block;", [
		'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		'first_required_block' => $blockchain->db_blockchain['first_required_block']
	])->fetch();

	$blocks_to_set = $to_block_id - $from_block['block_id'];

	$set_blocks = $app->run_query("SELECT time_mined, block_id, internal_block_id FROM blocks WHERE blockchain_id=:blockchain_id AND block_id>=:from_block AND block_id<=:to_block ORDER BY block_id ASC;", [
		'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		'from_block' => $from_block['block_id']-1,
		'to_block' => $to_block_id
	]);
	
	$prev_block = $set_blocks->fetch();
	
	$last_block_time = $prev_block['time_mined'];
	
	$block_i = 0;
	while ($db_block = $set_blocks->fetch()) {
		if ($last_block_time) {
			$sec_since_prev_block = $db_block['time_mined']-$last_block_time;
			
			$app->run_query("UPDATE blocks SET sec_since_prev_block=:sec_since_prev_block WHERE internal_block_id=:internal_block_id;", [
				'sec_since_prev_block' => $sec_since_prev_block,
				'internal_block_id' => $db_block['internal_block_id']
			]);
		}
		$last_block_time = $db_block['time_mined'];
		$block_i++;
	}
	
	$avg = $app->run_query("SELECT AVG(sec_since_prev_block) FROM `blocks` WHERE blockchain_id=:blockchain_id AND sec_since_prev_block < :max_seconds_per_block AND sec_since_prev_block>1 AND block_id>:block_id;", [
		'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		'max_seconds_per_block' => ($blockchain->seconds_per_block('target')*10),
		'block_id' => $blockchain->last_block_id()-100
	])->fetch();
	
	$app->run_query("UPDATE blockchains SET average_seconds_per_block=:average_seconds_per_block WHERE blockchain_id=:blockchain_id;", [
		'average_seconds_per_block' => $avg['AVG(sec_since_prev_block)'],
		'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
	]);
	$blockchain->db_blockchain['average_seconds_per_block'] = $avg['AVG(sec_since_prev_block)'];
	
	echo "Average block time: ".$blockchain->db_blockchain['average_seconds_per_block']." sec\n";
	echo "<br/>Completed in ".(microtime(true)-$ref_time)." sec\n";
}
else echo "Please supply the right key.\n";
?>