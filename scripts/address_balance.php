<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['address'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$db_address = $app->fetch_address($_REQUEST['address']);
	
	if ($db_address) {
		$blockchain = new Blockchain($app, $db_address['primary_blockchain_id']);
		$confirmed_bal = $blockchain->address_balance_at_block($db_address, $blockchain->last_block_id());
		$unconfirmed_bal = $blockchain->address_balance_at_block($db_address, false);
		
		echo "Confirmed balance: ".$app->format_bignum($confirmed_bal/pow(10,$blockchain->db_blockchain['decimal_places'])).", unconfirmed: ".$app->format_bignum($unconfirmed_bal/pow(10,$blockchain->db_blockchain['decimal_places']))."\n";
	}
	else echo "Could not find that address.\n";
}
else echo "You need admin privileges to run this script.\n";
?>