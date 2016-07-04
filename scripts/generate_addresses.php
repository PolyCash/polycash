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
	
	$option_str = $_REQUEST['option'];
	$option_id = intval($option_str);
	$option = false;
	
	if ($option_id > 0 && strval($option_id) === $option_str) {
		$q = "SELECT * FROM voting_options WHERE option_id='".$option_id."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) $option = mysql_fetch_array($r);
	}
	else if ($option_str != "") {
		$q = "SELECT * FROM voting_options WHERE name='".mysql_real_escape_string($option_str)."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) $option = mysql_fetch_array($r);
	}
	
	if ($option) {
		for ($i=0; $i<$quantity; $i++) {
			$new_addr_str = $empirecoin_rpc->getnewvotingaddress($option['name']);
			$new_addr_db = create_or_fetch_address($game, $new_addr_str, false, $empirecoin_rpc, true);
		}
	}
	else {
		for ($i=0; $i<$quantity; $i++) {
			$new_addr_str = $empirecoin_rpc->getnewaddress();
			$new_addr_db = create_or_fetch_address($game, $new_addr_str, false, $empirecoin_rpc, true);
		}
	}
}

$q = "SELECT * FROM game_voting_options WHERE game_id='".$game['game_id']."' ORDER BY nation_id ASC;";
$r = run_query($q);
while ($option = mysql_fetch_array($r)) {
	$qq = "SELECT COUNT(*) FROM addresses WHERE game_id='".$game['game_id']."' AND option_id='".$option['option_id']."' AND user_id IS NULL;";
	$rr = run_query($qq);
	$num_addr = mysql_fetch_row($rr);
	$num_addr = $num_addr[0];
	echo $num_addr." unallocated addresses for ".$option['name'].".<br/>\n";
}
?>