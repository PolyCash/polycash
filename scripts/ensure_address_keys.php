<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$blockchain_id = (int)$_REQUEST['blockchain_id'];
	
	$q = "SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount()  > 0) {
		$db_blockchain = $r->fetch();
		if ($db_blockchain['p2p_mode'] == "rpc") $save_method = "wallet.dat";
		else $save_method = "fake";
		
		$q = "SELECT * FROM addresses a WHERE a.is_mine=1";
		$q .= " AND a.primary_blockchain_id=".$db_blockchain['blockchain_id'];
		$q .= " AND NOT EXISTS (SELECT * FROM address_keys k WHERE k.address_id=a.address_id) LIMIT 1000;";
		$r = $app->run_query($q);
		
		echo "Adding keys for ".$r->rowCount()." addresses.<br/>\n";
		
		if ($r->rowCount() > 0) {
			$qq = "INSERT INTO address_keys (address_id, account_id, save_method, pub_key) VALUES ";
			while ($address = $r->fetch()) {
				$qq .= "(".$address['address_id'].", NULL, '".$save_method."', ".$app->quote_escape($address['address'])."), ";
			}
			$qq = substr($qq, 0, strlen($qq)-2).";";
			$rr = $app->run_query($qq);
			
			echo $qq."\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";