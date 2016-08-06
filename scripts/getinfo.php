<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['bitcoin_rpc_user'].':'.$GLOBALS['bitcoin_rpc_password'].'@127.0.0.1:'.$GLOBALS['bitcoin_port'].'/');
echo "<pre>getinfo()\n";
print_r($coin_rpc);
print_r($coin_rpc->getinfo());
echo "</pre><br/>\n";

$real_game_r = $app->run_query("SELECT * FROM games WHERE game_type='real';");

while ($db_real_game = $real_game_r->fetch()) {
	echo $db_real_game['name'].":<br/>\n";
	try {
		$coin_rpc = new jsonRPCClient('http://'.$db_real_game['rpc_username'].':'.$db_real_game['rpc_password'].'@127.0.0.1:'.$db_real_game['rpc_port'].'/');
		
		echo "<pre>getinfo()\n";
		print_r($coin_rpc->getinfo());
		echo "</pre><br/>\n";
	}
	catch (Exception $e) {
		echo $e;
	}
}
?>
