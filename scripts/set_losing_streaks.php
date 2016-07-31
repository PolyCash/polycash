<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$game_q = "SELECT * FROM games;";
$game_r = $app->run_query($game_q);

while ($db_game = $game_r->fetch()) {
	$game = new Game($app, $db_game['game_id']);
	$last_block_id = $game->last_block_id();
	$current_round = $game->block_to_round($last_block_id+1);

	$q = "SELECT * FROM options WHERE game_id='".$game->db_game['game_id']."' ORDER BY option_id ASC;";
	$r = $app->run_query($q);

	while ($option = $r->fetch()) {
		$qq = "SELECT * FROM game_outcomes WHERE game_id='".$game->db_game['game_id']."' AND winning_option_id='".$option['option_id']."' ORDER BY round_id DESC LIMIT 1;";
		$rr = $app->run_query($qq);
		if ($rr->rowCount() > 0) {
			$last_won_round = $rr->fetch();
			$losing_streak = ($current_round - 1 - $last_won_round['round_id']);
		}
		else $losing_streak = $current_round-1;
		
		$qq = "UPDATE options SET losing_streak=".$losing_streak." WHERE option_id='".$option['option_id']."';";
		$rr = $app->run_query($qq);
	}
}
echo "Great, losing streaks have been reset!";
?>
