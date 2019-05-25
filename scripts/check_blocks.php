<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
	}
	else $blockchain_id = false;
	
	$block_id = false;
	if (!empty($_REQUEST['block_id'])) $block_id = (int)$_REQUEST['block_id'];
	
	$q = "SELECT * FROM blockchains WHERE ";
	if ($blockchain_id) $q .= "blockchain_id='".$blockchain_id."'";
	else $q .= "online=1";
	$q .= ";";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		$last_block_id = $blockchain->last_block_id();
		
		$qq = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."'";
		if ($block_id) $qq .= " AND block_id=".$block_id;
		else $qq .= " AND ((num_ios_in IS NULL AND block_id>=".$db_blockchain['first_required_block'].") OR sum_coins_in<0 OR sum_coins_out<0)";
		$qq .= " ORDER BY block_id ASC;";
		$rr = $app->run_query($qq);
		
		echo $db_blockchain['blockchain_name'].": checking ".$rr->rowCount()." blocks<br/>\n";
		$app->flush_buffers();
		
		while ($temp_block = $rr->fetch()) {
			$num_trans = $blockchain->set_block_stats($temp_block);
			
			if ($num_trans != $temp_block['num_transactions']) {
				$message = "Error in block ".$temp_block['block_id'].", (Should be ".$temp_block['num_transactions']." but there are only ".$num_trans.")";
				echo "$message<br/>\n";
			}
			else echo $temp_block['block_id']." ";
			
			$app->flush_buffers();
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>