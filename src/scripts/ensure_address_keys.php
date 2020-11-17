<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	
	if ($blockchain) {
		$currency_id = $blockchain->currency_id();
		
		$need_key_addresses = $app->run_query("SELECT * FROM addresses a WHERE a.is_mine=1 AND a.primary_blockchain_id=:blockchain_id AND NOT EXISTS (SELECT * FROM address_keys k WHERE k.address_id=a.address_id) LIMIT 1000;", ['blockchain_id' => $blockchain->db_blockchain['blockchain_id']])->fetchAll();
		
		echo "Adding keys for ".count($need_key_addresses)." addresses.<br/>\n";
		
		if (count($need_key_addresses) > 0) {
			$new_key_q = "INSERT INTO address_keys (primary_blockchain_id, address_id, currency_id, account_id, pub_key, option_index) VALUES ";
			foreach ($need_key_addresses as $address) {
				$new_key_q .= "(".$blockchain->db_blockchain['blockchain_id'].", ".$address['address_id'].", ".$currency_id.", NULL, ".$app->quote_escape($address['address']).", ".$address['option_index']."), ";
			}
			$new_key_q = substr($new_key_q, 0, strlen($new_key_q)-2).";";
			$app->run_query($new_key_q);
			
			echo $new_key_q."\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";