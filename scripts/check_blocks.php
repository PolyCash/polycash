<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
	}
	else $blockchain_id = false;
	
	$q = "SELECT * FROM blockchains WHERE ";
	if ($blockchain_id) $q .= "blockchain_id='".$blockchain_id."'";
	else $q .= "online=1";
	$q .= ";";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		$last_block_id = $blockchain->last_block_id();
		
		$qq = "SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND num_ios_in IS NULL AND block_id>=".$db_blockchain['first_required_block']." ORDER BY block_id ASC LIMIT 1;";
		$rr = $app->run_query($qq);
		
		if ($rr->rowCount() > 0) {
			$start_block = $rr->fetch();
			$start_block_id = $start_block['block_id'];
			
			echo $db_blockchain['blockchain_name'].": checking blocks ".$start_block_id." to ".$last_block_id." (".number_format($last_block_id-$start_block_id+1)." blocks)<br/>\n";
			
			$app->flush_buffers();
			
			for ($block_id=$start_block_id; $block_id<=$last_block_id; $block_id++) {
				$temp_block = $app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block_id."';")->fetch();
				
				if ($temp_block) {
					$num_trans = $blockchain->set_block_stats($temp_block);
					
					if ($num_trans != $temp_block['num_transactions']) {
						$message = "Error in block ".$temp_block['block_id'].", (Should be ".$temp_block['num_transactions']." but there are only ".$num_trans.")";
						echo "$message<br/>\n";
						$app->log_message($message);
						
						//$qq = "UPDATE blocks SET locally_saved=0 WHERE internal_block_id='".$temp_block['internal_block_id']."';";
						//$rr = $app->run_query($qq);
					}
					else echo $temp_block['block_id']." ";
				}
				else $block_id = $last_block_id();
			}
		}
		else echo $db_blockchain['blockchain_name']." has no blocks which require updating.\n";
	}
}
else echo "Incorrect key.\n";
?>