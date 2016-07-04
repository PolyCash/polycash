<?php
set_time_limit(0);

include("../includes/connect.php");
include("../includes/jsonRPCClient.php");

$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
$r = run_query($q);
$game = mysql_fetch_array($r) or die("Failed to get the game.");

$quantity = intval($_REQUEST['quantity']);

if ($quantity > 0) {
	$empirecoin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
	for ($i=0; $i<$quantity; $i++) {
		$new_addr_str = $empirecoin_rpc->getnewaddress();
		$new_addr_db = create_or_fetch_address($game, $new_addr_str, false, $empirecoin_rpc, true);
	}
}

$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
$r = run_query($q);
while ($nation = mysql_fetch_array($r)) {
	$qq = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game['game_id']."' AND nation_id='".$nation['nation_id']."' AND user_id IS NULL;";
	$rr = run_query($qq);
	$num_addr = mysql_fetch_row($rr);
	$num_addr = $num_addr[0];
	echo "$qq<br/>\n".$num_addr." unallocated addresses for ".$nation['name'].".<br/>\n";
}
?>