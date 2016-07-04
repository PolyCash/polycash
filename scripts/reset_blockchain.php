<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == "oiwreu2490f98") {
	$q = "DELETE FROM webwallet_transactions WHERE transaction_desc != 'giveaway';";
	$r = run_query($q);
	
	$q = "UPDATE webwallet_transactions SET block_id=2;";
	$r = run_query($q);
	
	$q = "DELETE FROM blocks;";
	$r = run_query($q);
	
	$q = "DELETE FROM cached_rounds;";
	$r = run_query($q);
	
	$q = "ALTER TABLE blocks AUTO_INCREMENT=2;";
	$r = run_query($q);
	
	$q = "UPDATE nations SET cached_force_multiplier=16, relevant_wins=1;";
	$r = run_query($q);
	
	$q = "DELETE FROM game_nations;";
	$r = run_query($q);
	
	$q = "SELECT * FROM games;";
	$r = run_query($q);
	while ($game = mysql_fetch_array($r)) {
		$qq = "INSERT INTO blocks SET game_id='".$game['game_id']."', block_id='1', currency_mode='beta', time_created='".time()."';";
		$rr = run_query($qq);
		
		$qq = "SELECT * FROM nations;";
		$rr = run_query($qq);
		while ($nation = mysql_fetch_array($rr)) {
			$qqq = "INSERT INTO game_nations SET game_id='".$game['game_id']."', nation_id='".$nation['nation_id']."';";
			$rrr = run_query($qqq);
		}
	}
	
	echo "Great, the game has been reset!";
}
?>