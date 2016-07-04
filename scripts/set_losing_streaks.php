<?php
include("../includes/connect.php");

$game_q = "SELECT * FROM games;";
$game_r = run_query($game_q);

while ($game = mysql_fetch_array($game_r)) {
	$last_block_id = last_block_id($game['game_id']);
	$current_round = block_to_round($game, $last_block_id+1);

	$q = "SELECT * FROM game_voting_options WHERE game_id='".$game['game_id']."' ORDER BY option_id ASC;";
	$r = run_query($q);

	while ($option = mysql_fetch_array($r)) {
		$qq = "SELECT * FROM cached_rounds WHERE game_id='".$game['game_id']."' AND winning_option_id='".$option['option_id']."' ORDER BY round_id DESC LIMIT 1;";
		$rr = run_query($qq);
		if (mysql_numrows($rr) > 0) {
			$last_won_round = mysql_fetch_array($rr);
			$losing_streak = ($current_round - 1 - $last_won_round['round_id']);
		}
		else $losing_streak = $current_round-1;
		
		$qq = "UPDATE game_voting_options SET losing_streak=".$losing_streak." WHERE game_id='".$game['game_id']."' AND voting_option_id='".$option['voting_option_id']."';";
		$rr = run_query($qq);
	}
}
echo "Great, losing streaks have been reset!";
?>