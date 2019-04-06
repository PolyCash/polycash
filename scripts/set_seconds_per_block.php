<?php
ini_set('memory_limit', '1024M');
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$q = "SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$db_blockchain = $r->fetch();
		
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain_id."' AND block_id>0 AND sec_since_prev_block IS NULL ORDER BY block_id ASC LIMIT 1;";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$first_unset_block = $r->fetch();
			
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain_id."' AND block_id>=".($first_unset_block['block_id']-1)." ORDER BY block_id ASC;";
			$r = $app->run_query($q);
			
			$last_block_time = false;
			
			echo "Setting times for ".$r->rowCount()." blocks.\n";
			
			while ($db_block = $r->fetch()) {
				if ($last_block_time !== false) {
					$sec_since_prev_block = $db_block['time_mined']-$last_block_time;
					
					$qq = "UPDATE blocks SET sec_since_prev_block='".$sec_since_prev_block."' WHERE internal_block_id='".$db_block['internal_block_id']."';";
					$rr = $app->run_query($qq);
					echo ". ";
				}
				$last_block_time = $db_block['time_mined'];
			}
			
			$q = "SELECT AVG(sec_since_prev_block) FROM `blocks` WHERE blockchain_id=".$db_blockchain['blockchain_id']." AND sec_since_prev_block > 1 AND sec_since_prev_block < ".($db_blockchain['seconds_per_block']*20).";";
			$r = $app->run_query($q);
			$avg = $r->fetch();
			
			$q = "UPDATE blockchains SET average_seconds_per_block=".$avg['AVG(sec_since_prev_block)']." WHERE blockchain_id='".$db_blockchain['blockchain_id']."';";
			$r = $app->run_query($q);
			
			echo "<br/>\nDone. (Average block time: ".$avg['AVG(sec_since_prev_block)']." sec)\n";
		}
		else echo "Please supply a valid blockchain ID.\n";
	}
	else echo "Failed to find a block to start on.\n";
}
else echo "Please supply the right key.\n";
?>