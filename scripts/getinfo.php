<?php
include("../includes/connect.php");
include("../includes/jsonRPCClient.php");

$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
echo "<pre>getinfo()\n";
print_r($empirecoin_rpc->getinfo());
echo "\n\ngetgenerate()\n";
print_r($empirecoin_rpc->getgenerate());
echo "</pre>";
?>