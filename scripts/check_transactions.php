<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$q = "SELECT * FROM games";
	if ($game_id > 0) $q .= " WHERE game_id='".$game_id."'";
	$q .= ";";
	$r = $GLOBALS['app']->run_query($q);
	
	while ($db_game = mysql_fetch_array($r)) {
		$game = new Game($db_game['game_id']);
		
		$qq = "SELECT * FROM transactions WHERE game_id='".$game->db_game['game_id']."' AND transaction_desc='transaction';";
		$rr = $GLOBALS['app']->run_query($qq);
		echo "Checking ".mysql_numrows($rr)." transactions for ".$game->db_game['name']."<br/>\n";
		
		while ($transaction = mysql_fetch_array($rr)) {
			$coins_in = $GLOBALS['app']->transaction_coins_in($transaction['transaction_id']);
			$coins_out = $GLOBALS['app']->transaction_coins_out($transaction['transaction_id']);
			if (($coins_in == 0 || $coins_out == 0) || $coins_in < $coins_out || $coins_out-$coins_in > 0.5) echo '<a href="/explorer/'.$game->db_game['url_identifier'].'/transactions/'.$transaction['transaction_id'].'">TX '.$transaction['transaction_id'].'</a> has '.$GLOBALS['app']->format_bignum($coins_in/pow(10,8)).' coins in and '.$GLOBALS['app']->format_bignum($coins_out/pow(10,8)).' coins out.<br/>';
		}
	}
	echo "Done!";
}
else echo "Incorrect key.";
?>