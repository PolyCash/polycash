<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['address'] = $cmd_vars['address'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM addresses WHERE address=".$app->quote_escape($_REQUEST['address']).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$db_address = $r->fetch();
		
		$blockchain = new Blockchain($app, $db_address['primary_blockchain_id']);
		$confirmed_bal = $blockchain->address_balance_at_block($db_address, $blockchain->last_block_id());
		$unconfirmed_bal = $blockchain->address_balance_at_block($db_address, false);
		
		echo "Confirmed balance: ".$app->format_bignum($confirmed_bal/pow(10,$blockchain->db_blockchain['decimal_places'])).", unconfirmed: ".$app->format_bignum($unconfirmed_bal/pow(10,$blockchain->db_blockchain['decimal_places']))."\n";
	}
	else echo "Could not find that address.\n";
}
?>