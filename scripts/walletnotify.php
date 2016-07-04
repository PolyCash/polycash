<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
$start_time = microtime(true);

$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

$game = new Game($app, $app->get_site_constant('primary_game_id'));

echo $game->sync_coind($coin_rpc);

echo "walletnotify completed: ".(microtime(true)-$start_time)." sec\n";
?>
