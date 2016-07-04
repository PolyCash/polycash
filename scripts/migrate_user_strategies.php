<?php
include("../includes/connect.php");

$q = "SELECT * FROM users u INNER JOIN user_games g ON u.user_id=g.user_id WHERE (g.strategy_id IS NULL OR g.strategy_id=0);";
$r = run_query($q);

echo "q (".mysql_numrows($r)."): $q<br/>\n";

while ($user_game = mysql_fetch_array($r)) {
	$strategy_vars = explode(",", "voting_strategy,aggregate_threshold,by_rank_ranks,api_url,min_votesum_pct,max_votesum_pct,min_coins_available");
	$qq = "INSERT INTO user_strategies SET ";
	for ($i=0; $i<count($strategy_vars); $i++) {
		$qq .= $strategy_vars[$i]."='".mysql_real_escape_string($user_game[$strategy_vars[$i]])."', ";
	}
	for ($i=1; $i<=9; $i++) {
		$qq .= "vote_on_block_".$i."=".$user_game['vote_on_block_'.$i].", ";
	}
	for ($i=1; $i<=16; $i++) {
		$qq .= "nation_pct_".$i."=".$user_game['nation_pct_'.$i].", ";
	}
	$qq = substr($qq, 0, strlen($qq)-2).";";
	$rr = run_query($qq);
	$strategy_id = mysql_insert_id();
	
	$qq = "UPDATE user_games SET strategy_id='".$strategy_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
	$rr = run_query($qq);
}
?>