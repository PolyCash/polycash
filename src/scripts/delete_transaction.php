<?php
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id', 'tx_hash'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$blockchain = new Blockchain($app, $blockchain_id);
	$tx_hash = $_REQUEST['tx_hash'];
	$transaction = $blockchain->fetch_transaction_by_hash($tx_hash);
	
	if ($transaction) {
		$success = $blockchain->delete_transaction($transaction);
		
		if ($success) echo "The transaction has been deleted.\n";
		else echo "Failed to delete the transaction.\n";
	}
	else echo "No transaction found matching that tx_hash.\n";
}
else echo "You need admin privileges to run this script.\n";
?>