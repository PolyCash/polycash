<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id', 'tx_hash'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);

	$transaction = $blockchain->fetch_transaction_by_hash($_REQUEST['tx_hash']);

	$inputs = $app->run_query("SELECT io_id FROM transaction_ios WHERE spend_transaction_id=:spend_transaction_id;", [
		'spend_transaction_id' => $transaction['transaction_id'],
	])->fetchAll(PDO::FETCH_ASSOC);

	$input_ids = [];

	foreach ($inputs as $input) {
		$input_ids[] = $input['io_id'];
	}

	$outputs = $app->run_query("SELECT io_id FROM transaction_ios WHERE create_transaction_id=:create_transaction_id;", [
		'create_transaction_id' => $transaction['transaction_id'],
	])->fetchAll(PDO::FETCH_ASSOC);

	$output_ids = [];

	foreach ($outputs as $output) {
		$output_ids[] = $output['io_id'];
	}

	$app->cancel_transaction($transaction, $input_ids, $output_ids);

	echo "Done\n";
}
else echo "You need admin privileges to run this script.\n";
?>