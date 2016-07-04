<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$game = mysql_fetch_array($r);
		$q = "DELETE FROM cached_rounds WHERE game_id='".$game['game_id']."';";
		$r = run_query($q);

		$last_block_id = last_block_id($game['game_id']);
		$current_round = block_to_round($game, $last_block_id+1);

		for ($round_id=1; $round_id<=$current_round-1; $round_id++) {
			$round_voting_stats = round_voting_stats_all($game, $round_id);
			
			$vote_sum = $round_voting_stats[0];
			$max_vote_sum = $round_voting_stats[1];
			$round_voting_stats = $round_voting_stats[2];
			$nation_id2rank = $round_voting_stats[3];
			
			$winning_nation = FALSE;
			$winning_votesum = 0;
			$winning_score = 0;
			$rank = 1;
			for ($rank=1; $rank<=$game['num_voting_options']; $rank++) {
				$nation_id = $round_voting_stats[$rank-1]['nation_id'];
				$nation_rank2db_id[$rank] = $nation_id;
				$nation_scores = nation_score_in_round($game, $nation_id, $round_id);
				
				if ($nation_scores['sum'] > $max_vote_sum) {}
				else if (!$winning_nation && $nation_scores['sum'] > 0) {
					$winning_nation = $nation_id;
					$winning_votesum = $nation_scores['sum'];
					$winning_score = $nation_scores['sum'];
				}
			}
			
			$q = "INSERT INTO cached_rounds SET game_id='".$game['game_id']."', round_id='".$round_id."', payout_block_id='".($round_id*$game['round_length'])."'";
			if ($winning_nation) {
				$q .= ", winning_nation_id='".$winning_nation."'";
				$qq = "SELECT * FROM transactions WHERE transaction_desc='votebase' AND game_id='".$game['game_id']."' AND block_id = ".$round_id*$game['round_length'].";";
				$rr = run_query($qq);
				if (mysql_numrows($rr) > 0) {
					$payout_transaction = mysql_fetch_array($rr);
					$q .= ", payout_transaction_id='".$payout_transaction['transaction_id']."'";
				}
			}
			$q .= ", winning_score='".$winning_score."', score_sum='".$vote_sum."', time_created='".time()."'";
			for ($position=1; $position <= $game['num_voting_options']; $position++) {
				$q .= ", position_".$position."='".$nation_rank2db_id[$position]."'";
			}
			$q .= ";";
			$r = run_query($q);
			echo "Added cached round #".$round_id." to ".$game['name']."<br/>\n";
		}
	}
	else echo "Error identifying the game.";
}
else echo "Incorrect key.";
?>