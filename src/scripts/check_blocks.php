<?php
ini_set('memory_limit', '4096M');
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id','check_method','from_block','to_block','block_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (empty($_REQUEST['check_method'])) $check_method = "general";
	else $check_method = $_REQUEST['check_method'];
	
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
	}
	else $blockchain_id = false;
	
	$block_id = false;
	if (!empty($_REQUEST['block_id'])) $block_id = (int)$_REQUEST['block_id'];
	
	$check_blockchain_params = [];
	$check_blockchains_q = "SELECT * FROM blockchains WHERE ";
	if ($blockchain_id) {
		$check_blockchains_q .= "blockchain_id=:blockchain_id";
		$check_blockchain_params['blockchain_id'] = $blockchain_id;
	}
	else $check_blockchains_q .= "online=1";
	
	$check_blockchains = $app->run_query($check_blockchains_q, $check_blockchain_params);
	
	while ($db_blockchain = $check_blockchains->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		$last_block_id = $blockchain->last_block_id();
		
		if ($check_method == "general") {
			$check_blocks_params = [
				'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
				'first_required_block' => $db_blockchain['first_required_block']
			];
			$check_blocks_q = "SELECT internal_block_id, block_id, num_ios_in, num_ios_out, num_transactions, locally_saved FROM blocks WHERE blockchain_id=:blockchain_id";
			if ($block_id) {
				$check_blocks_q .= " AND block_id=:block_id";
				$check_blocks_params['block_id'] = $block_id;
			}
			else {
				$check_blocks_q .= " AND ((num_ios_in IS NULL AND block_id >= :first_required_block) OR sum_coins_in<0 OR sum_coins_out<0 OR transactions_html IS NULL)";
			}
			$check_blocks_q .= " AND block_id>= :first_required_block ORDER BY block_id ASC;";
			$check_blocks = $app->run_query($check_blocks_q, $check_blocks_params)->fetchAll();
			
			$app->print_debug($db_blockchain['blockchain_name'].": checking ".count($check_blocks)." blocks");
			
			foreach ($check_blocks as $check_block) {
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
		else if ($check_method == "tx_inputs") {
			$check_blocks_params = [
				'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
				'from_block' => $_REQUEST['from_block'],
				'to_block' => min($blockchain->db_blockchain['last_complete_block'], $_REQUEST['to_block'])
			];
			$check_blocks_q = "SELECT internal_block_id, block_id, num_ios_in, num_ios_out FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :from_block AND block_id <= :to_block ORDER BY block_id ASC;";
			
			$check_blocks = $app->run_query($check_blocks_q, $check_blocks_params)->fetchAll();
			
			$app->print_debug($blockchain->db_blockchain['blockchain_name'].": checking ".count($check_blocks)." blocks");
			
			echo "<pre>\n";
			foreach ($check_blocks as $check_block) {
				echo $check_block['block_id'].": ";
				
				$num_trans = $blockchain->set_block_stats($check_block);
				
				$num_ios_in = $app->run_query("SELECT COUNT(*) FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
					'block_id' => $check_block['block_id'],
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
				])->fetch(PDO::FETCH_NUM)[0];
				
				$num_ios_out = $app->run_query("SELECT COUNT(*) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
					'block_id' => $check_block['block_id'],
					'blockchain_id' => $blockchain->db_blockchain['blockchain_id']
				])->fetch(PDO::FETCH_NUM)[0];
				
				$this_block_error = false;
				if ($check_block['num_ios_in'] != $num_ios_in) {
					echo "\n".$num_ios_in." ios in but expected ".$check_block['num_ios_in'];
					$this_block_error = true;
				}
				if ($check_block['num_ios_out'] != $num_ios_out) {
					echo "\n".$num_ios_out." ios out but expected ".$check_block['num_ios_out'];
					$this_block_error = true;
				}
				
				list($any_error, $any_ios_in_error, $any_ios_out_error) = BlockchainVerifier::verifyBlock($app, $blockchain->db_blockchain['blockchain_id'], $check_block['block_id']);
				
				if ($any_ios_in_error) {
					$this_block_error = true;
					echo "\ninvalid ios in";
				}
				
				if ($any_ios_out_error) {
					$this_block_error = true;
					echo "\ninvalid ios out";
				}
				
				if (!$this_block_error) echo "ok";
				echo "\n";
				$app->flush_buffers();
			}
			echo "</pre>\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>