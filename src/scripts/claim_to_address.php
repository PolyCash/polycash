<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id','genesis_blocks','address'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	
	if (in_array($blockchain->db_blockchain['p2p_mode'], ['none','web_api'])) {
		$claim_genesis_blocks = (int) $_REQUEST['genesis_blocks'];
		$db_address = $blockchain->create_or_fetch_address($_REQUEST['address'], true, false, true, false, false);
		
		$coinbase_ios = $app->run_query("SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN address_keys ak ON io.address_id=ak.address_id WHERE t.blockchain_id=".$blockchain->db_blockchain['blockchain_id']." AND t.transaction_desc='coinbase' AND io.spend_status='unspent' AND ak.account_id IS NULL ORDER BY t.block_id ASC LIMIT ".$claim_genesis_blocks.";");
		
		$in_sum = 0;
		$io_ids = [];
		
		foreach ($coinbase_ios as $coinbase_io) {
			array_push($io_ids, $coinbase_io['io_id']);
			$in_sum += $coinbase_io['amount'];
		}
		
		$error_message = "";
		$transaction_id = $blockchain->create_transaction("transaction", [$in_sum], false, $io_ids, [$db_address['address_id']], 0, $error_message);
		
		if ($transaction_id) {
			$transaction = $app->fetch_transaction_by_id($transaction_id);
			echo "successful: ".$transaction['tx_hash']."\n";
		}
		else echo "failed\n";
	}
	else echo "This action cannot be performed for this blockchain.\n";
}
else echo "Please run this script as admin.\n";
