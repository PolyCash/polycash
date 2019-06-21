<?php
ini_set('memory_limit', '1024M');
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
	}
	else $blockchain_id = false;
	
	$block_id = false;
	if (!empty($_REQUEST['block_id'])) $block_id = (int)$_REQUEST['block_id'];
	
	$check_blockchains_q = "SELECT * FROM blockchains WHERE ";
	if ($blockchain_id) $check_blockchains_q .= "blockchain_id='".$blockchain_id."'";
	else $check_blockchains_q .= "online=1";
	$check_blockchains_q .= ";";
	$check_blockchains = $app->run_query($check_blockchains_q);
	
	while ($db_blockchain = $check_blockchains->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		$last_block_id = $blockchain->last_block_id();
		
		$check_blocks_q = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."'";
		if ($block_id) $check_blocks_q .= " AND block_id=".$block_id;
		else $check_blocks_q .= " AND ((num_ios_in IS NULL AND block_id>=".$db_blockchain['first_required_block'].") OR sum_coins_in<0 OR sum_coins_out<0 OR transactions_html IS NULL)";
		$check_blocks_q .= " AND block_id>= ".$blockchain->db_blockchain['first_required_block']." ORDER BY block_id ASC;";
		$check_blocks = $app->run_query($check_blocks_q);
		
		echo $db_blockchain['blockchain_name'].": checking ".$check_blocks->rowCount()." blocks<br/>\n";
		$app->flush_buffers();
		
		while ($check_block = $check_blocks->fetch()) {
			$num_trans = $blockchain->set_block_stats($check_block);
			
			if ($check_block['locally_saved'] == 1) $blockchain->render_transactions_in_block($check_block, false);
			
			if ($num_trans != $check_block['num_transactions']) {
				$message = "Error in block ".$check_block['block_id'].", (Should be ".$check_block['num_transactions']." but there are only ".$num_trans.")";
				echo "$message<br/>\n";
			}
			else echo $check_block['block_id']." ";
			
			$app->flush_buffers();
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>