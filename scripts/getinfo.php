<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['bitcoin_rpc_user'].':'.$GLOBALS['bitcoin_rpc_password'].'@127.0.0.1:'.$GLOBALS['bitcoin_port'].'/');
	echo "<pre>getinfo()\n";
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
}
else echo "Please supply the correct key.";
?>
