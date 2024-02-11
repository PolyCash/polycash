<?php
require(dirname(__DIR__)."/includes/connect.php");

if ($app->running_as_admin()) {
	$address_id_columns = [
		[
			'currency_accounts',
			'current_address_id',
		],
		[
			'address_keys',
			'address_id',
		],
		[
			'currency_invoices',
			'address_id',
		],
		[
			'currency_invoices',
			'receive_address_id',
		],
		[
			'transaction_ios',
			'address_id',
		],
		[
			'transaction_game_ios',
			'address_id',
		],
		[
			'card_printrequests',
			'address_id',
		],
		[
			'games',
			'invoice_address_id',
		],
	];

	$duplicate_addresses = $app->run_query("SELECT address, COUNT(*) FROM addresses GROUP BY address HAVING COUNT(*) > 1 ORDER BY COUNT(*) DESC;")->fetchAll();
	
	echo "Processing ".count($duplicate_addresses)." addresses.\n";
	
	foreach ($duplicate_addresses as $duplicate_address) {
		echo $duplicate_address['COUNT(*)']." ".$duplicate_address['address']."\n";
		
		$these_addresses = $app->run_query("SELECT * FROM addresses WHERE address=:address ORDER BY address_id ASC;", ['address' => $duplicate_address['address']])->fetchAll();
		
		$keep_address = $these_addresses[0];
		
		foreach ($these_addresses as $pos => $address) {
			if ($pos > 0) {
				echo $pos.": ".$address['address_id']." -> ".$keep_address['address_id']." (".$address['address'].")\n";
				
				foreach ($address_id_columns as $info) {
					$app->run_query("UPDATE ".$info[0]." SET ".$info[1]."=:keep_address_id WHERE ".$info[1]."=:address_id;", [
						'keep_address_id' => $keep_address['address_id'],
						'address_id' => $address['address_id'],
					]);
				}
				
				$app->run_query("DELETE FROM addresses WHERE address_id=:address_id;", [
					'address_id' => $address['address_id'],
				]);
			}
		}
		echo "\n";
	}
}
else echo "Please run this script as admin.\n";
