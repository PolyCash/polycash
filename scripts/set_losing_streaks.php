<?php
include("../includes/connect.php");

$last_block_id = last_block_id('beta');
$current_round = block_to_round($last_block_id+1);

$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
$r = run_query($q);

while ($nation = mysql_fetch_array($r)) {
	$qq = "SELECT * FROM cached_rounds WHERE winning_nation_id='".$nation['nation_id']."' ORDER BY round_id DESC LIMIT 1;";
	$rr = run_query($qq);
	if (mysql_numrows($rr) > 0) {
		$last_won_round = mysql_fetch_array($rr);
		$losing_streak = ($current_round - 1 - $last_won_round['round_id']);
	}
	else $losing_streak = $current_round-1;
	
	$qq = "UPDATE nations SET losing_streak=".$losing_streak." WHERE nation_id='".$nation['nation_id']."';";
	$rr = run_query($qq);
}

echo "Great, losing streaks have been reset!";
?>