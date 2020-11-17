<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	
	if ($app->user_is_admin($thisuser) && $blockchain->db_blockchain['p2p_mode'] == "none") {
		$coinbase_quantity = (int) $_REQUEST['quantity'];
		$db_address = $blockchain->create_or_fetch_address($_REQUEST['to_address'], true, false, true, false, false);
		
		$coinbase_ios = $app->run_query("SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN address_keys ak ON io.address_id=ak.address_id WHERE t.blockchain_id=".$blockchain->db_blockchain['blockchain_id']." AND t.transaction_desc='coinbase' AND io.spend_status='unspent' AND ak.account_id IS NULL ORDER BY t.block_id ASC LIMIT ".$coinbase_quantity.";")->fetchAll();
		
		if (count($coinbase_ios) == $coinbase_quantity) {
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
				$app->output_message(1, '/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/transactions/'.$transaction['tx_hash']);
			}
			else $app->output_message(5, "Failed: ".$error_message);
		}
		else $app->output_message(4, "Couldn't find that many available coinbase outputs.");
	}
	else $app->output_message(3, "Permission denied.");
}
else $app->output_message(2, "Permission denied.");
