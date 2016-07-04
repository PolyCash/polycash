<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
$start_time = microtime(true);

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	$game_id = $cmd_vars["game_id"];
}
else {
	$game_id = intval($_REQUEST['game_id']);
}

$game = new Game($app, $game_id);

$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');

echo $game->sync_coind($coin_rpc);

echo "walletnotify completed: ".(microtime(true)-$start_time)." sec\n";
?>
