<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['coins'])) $_REQUEST['coins'] = $cmd_vars['coins'];
	if (!empty($cmd_vars['account_id'])) $_REQUEST['account_id'] = $cmd_vars['account_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$account_id = (int) $_REQUEST['account_id'];
	
	$q = "SELECT * FROM currency_accounts WHERE account_id=".$account_id.";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$account = $r->fetch();
		
		$currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$account['currency_id']."';")->fetch();
		
		$q = "SELECT * FROM blockchains WHERE blockchain_id='".$currency['blockchain_id']."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_blockchain = $r->fetch();
			
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
			
			$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' AND a.address_id='".$account['current_address_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$address_key = $r->fetch();
				$coins = ((int)$_REQUEST['coins'])*pow(10, $blockchain->db_blockchain['decimal_places']);
				$debug_text = "";
				
				$transaction_id = $blockchain->create_transaction("coinbase", array($coins), false, array(), array($address_key['address_id']), 0.001);
				
				$blockchain->new_block($debug_text);
				
				echo "tx id: ".$transaction_id.", ".$debug_text;
			}
			else echo "There's no valid address for this account.";
		}
		else echo "This currency does not have a valid associated blockchain.";
	}
	else echo "Invalid account ID.";
}
else echo "Please supply the correct key. Syntax is: create_coinbase_transaction.php?key=<key>&coins=<num_coins>&account_id=<account_id>";
?>