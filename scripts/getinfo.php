<?php
include("../includes/connect.php");
include("../includes/jsonRPCClient.php");

try {
	$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
	//var_dump($coin_rpc);

	echo "<pre>getinfo()\n";
	print_r($coin_rpc->getinfo());
	echo "\n\ngetgenerate()\n";
	print_r($coin_rpc->getgenerate());
	echo "</pre>";
}
catch (Exception $e) {
	echo $e;
	die();
}
?>