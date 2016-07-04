<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$q = "SELECT * FROM games";
	if ($game_id > 0) $q .= " WHERE game_id='".$game_id."'";
	$q .= ";";
	$r = run_query($q);
	while ($game = mysql_fetch_array($r)) {
		$qq = "SELECT * FROM transactions WHERE game_id='".$game['game_id']."' AND transaction_desc='transaction';";
		$rr = run_query($qq);
		echo "Checking ".mysql_numrows($rr)." transactions for ".$game['name']."<br/>\n";
		while ($transaction = mysql_fetch_array($rr)) {
			$coins_in = transaction_coins_in($transaction['transaction_id']);
			$coins_out = transaction_coins_out($transaction['transaction_id']);
			if (($coins_in == 0 || $coins_out == 0) || $coins_in < $coins_out || $coins_out-$coins_in > 0.5) echo '<a href="/explorer/'.$game['url_identifier'].'/transactions/'.$transaction['transaction_id'].'">TX '.$transaction['transaction_id'].'</a> has '.format_bignum($coins_in/pow(10,8)).' coins in and '.format_bignum($coins_out/pow(10,8)).' coins out.<br/>';
		}
	}
	echo "Done!";
}
else echo "Incorrect key.";
?>