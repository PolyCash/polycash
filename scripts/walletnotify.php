<?php
include("/var/www/html/includes/connect.php");

$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

$game = new Game($GLOBALS['app']->get_site_constant('primary_game_id'));

$game->walletnotify($coin_rpc, $argv[1]);

$unconfirmed_txs = $coin_rpc->getrawmempool();
echo "Looping through ".count($unconfirmed_txs)." unconfirmed transactions.<br/>\n";
for ($i=0; $i<count($unconfirmed_txs); $i++) {
	$game->walletnotify($coin_rpc, $unconfirmed_txs[$i]);
}

echo "Done!";
?>