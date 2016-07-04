<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
$start_time = microtime(true);

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['walletnotify'])) $_REQUEST['walletnotify'] = $cmd_vars['walletnotify'];
	else if (!empty($cmd_vars[0])) $_REQUEST['walletnotify'] = $cmd_vars[0];
	$game_id = $cmd_vars["game_id"];
}
else {
	$game_id = intval($_REQUEST['game_id']);
}

$game_id = 2;
$game = new Game($app, $game_id);

$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');

$game->walletnotify($coin_rpc, $_REQUEST['walletnotify'], FALSE);

echo "walletnotify completed: ".(microtime(true)-$start_time)." sec\n";
?>
