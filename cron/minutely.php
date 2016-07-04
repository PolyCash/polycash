<?php
$payments_pid = pcntl_fork();

if ($payments_pid == -1) {
	die('Failed to fork this process.');
}
else if ($payments_pid) {
	include("minutely_check_payments.php");
	die();
}
else {
	$addrminer_pid = pcntl_fork();
	
	if ($addrminer_pid == -1) {
		die("Failed to create a thread for mining addresses.");
	}
	else if ($addrminer_pid) {
		include("address_miner.php");
		die();
	}
	else {
		$fetchprice_pid = pcntl_fork();
		
		if ($fetchprice_pid == -1) {
			die('Failed to create a thread for updating currency prices.');
		}
		else if ($fetchprice_pid) {
			include("fetch_currency_prices.php");
			die();
		}
		else {
			include("minutely_main.php");
			die();
		}
	}
}
?>
