<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
$start_time = microtime(true);

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['walletnotify'])) $_REQUEST['walletnotify'] = $cmd_vars['walletnotify'];
	else if (!empty($cmd_vars[0])) $_REQUEST['walletnotify'] = $cmd_vars[0];
	$blockchain_id = (int) $cmd_vars["blockchain_id"];
}
else {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
}

$blockchain = new Blockchain($app, $blockchain_id);
$coin_rpc = new jsonRPCClient('http://'.$blockchain->db_blockchain['rpc_username'].':'.$blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchain->db_blockchain['rpc_port'].'/');
$blockchain->walletnotify($coin_rpc, $_REQUEST['walletnotify'], FALSE);

echo "walletnotify completed: ".(microtime(true)-$start_time)." sec\n";
?>
