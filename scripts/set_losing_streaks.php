<?php
include("../includes/connect.php");

$game_q = "SELECT * FROM games;";
$game_r = $GLOBALS['app']->run_query($game_q);

while ($db_game = mysql_fetch_array($game_r)) {
	$game = new Game($db_game['game_id']);
	$last_block_id = $game->last_block_id();
	$current_round = $game->block_to_round($last_block_id+1);

	$q = "SELECT * FROM game_voting_options WHERE game_id='".$game->db_game['game_id']."' ORDER BY option_id ASC;";
	$r = $GLOBALS['app']->run_query($q);

	while ($option = mysql_fetch_array($r)) {
		$qq = "SELECT * FROM cached_rounds WHERE game_id='".$game->db_game['game_id']."' AND winning_option_id='".$option['option_id']."' ORDER BY round_id DESC LIMIT 1;";
		$rr = $GLOBALS['app']->run_query($qq);
		if (mysql_numrows($rr) > 0) {
			$last_won_round = mysql_fetch_array($rr);
			$losing_streak = ($current_round - 1 - $last_won_round['round_id']);
		}
		else $losing_streak = $current_round-1;
		
		$qq = "UPDATE game_voting_options SET losing_streak=".$losing_streak." WHERE option_id='".$option['option_id']."';";
		$rr = $GLOBALS['app']->run_query($qq);
	}
}
echo "Great, losing streaks have been reset!";
?>