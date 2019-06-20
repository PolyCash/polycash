<?php
ini_set('memory_limit', '1024M');
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$db_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
	
	if ($db_blockchain) {
		$first_unset_block = $app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$blockchain_id."' AND block_id>0 AND sec_since_prev_block IS NULL ORDER BY block_id ASC LIMIT 1;")->fetch();
		
		if ($first_unset_block) {
			$set_blocks = $app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$blockchain_id."' AND block_id>=".($first_unset_block['block_id']-1)." ORDER BY block_id ASC;");
			
			$last_block_time = false;
			
			echo "Setting times for ".$set_blocks->rowCount()." blocks.\n";
			
			while ($db_block = $set_blocks->fetch()) {
				if ($last_block_time !== false) {
					$sec_since_prev_block = $db_block['time_mined']-$last_block_time;
					
					$app->run_query("UPDATE blocks SET sec_since_prev_block='".$sec_since_prev_block."' WHERE internal_block_id='".$db_block['internal_block_id']."';");
					
					echo ". ";
				}
				$last_block_time = $db_block['time_mined'];
			}
			
			$avg = $app->run_query("SELECT AVG(sec_since_prev_block) FROM `blocks` WHERE blockchain_id=".$db_blockchain['blockchain_id']." AND sec_since_prev_block > 1 AND sec_since_prev_block < ".($db_blockchain['seconds_per_block']*20).";")->fetch();
			
			$app->run_query("UPDATE blockchains SET average_seconds_per_block=".$avg['AVG(sec_since_prev_block)']." WHERE blockchain_id='".$db_blockchain['blockchain_id']."';");
			
			echo "<br/>\nDone. (Average block time: ".$avg['AVG(sec_since_prev_block)']." sec)\n";
		}
		else echo "Please supply a valid blockchain ID.\n";
	}
	else echo "Failed to find a block to start on.\n";
}
else echo "Please supply the right key.\n";
?>