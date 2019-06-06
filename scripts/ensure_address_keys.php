<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$blockchain_id = (int)$_REQUEST['blockchain_id'];
	
	$db_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
	
	if ($db_blockchain) {
		if ($db_blockchain['p2p_mode'] == "rpc") $save_method = "wallet.dat";
		else $save_method = "fake";
		
		$need_key_addresses = $app->run_query("SELECT * FROM addresses a WHERE a.is_mine=1 AND a.primary_blockchain_id=".$db_blockchain['blockchain_id']." AND NOT EXISTS (SELECT * FROM address_keys k WHERE k.address_id=a.address_id) LIMIT 1000;");
		
		echo "Adding keys for ".$need_key_addresses->rowCount()." addresses.<br/>\n";
		
		if ($need_key_addresses->rowCount() > 0) {
			$new_key_q = "INSERT INTO address_keys (address_id, account_id, save_method, pub_key) VALUES ";
			while ($address = $need_key_addresses->fetch()) {
				$new_key_q .= "(".$address['address_id'].", NULL, '".$save_method."', ".$app->quote_escape($address['address'])."), ";
			}
			$new_key_q = substr($new_key_q, 0, strlen($new_key_q)-2).";";
			$app->run_query($new_key_q);
			
			echo $new_key_q."\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";