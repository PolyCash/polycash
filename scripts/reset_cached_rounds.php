<?php
include("../includes/connect.php");

/*
$q = "DELETE FROM cached_rounds;";
$r = run_query($q);

$last_block_id = last_block_id('beta');
$current_round = block_to_round($last_block_id+1);

for ($round_id=1; $round_id<=$current_round-1; $round_id++) {
	$round_voting_stats = round_voting_stats_all($round_id);
	
	$vote_sum = $round_voting_stats[0];
	$max_vote_sum = $round_voting_stats[1];
	$nation_id2rank = $round_voting_stats[3];
	$round_voting_stats = $round_voting_stats[2];
	
	$winning_nation = FALSE;
	$winning_votesum = 0;
	$winning_score = 0;
	$rank = 1;
	for ($rank=1; $rank<=get_site_constant('num_voting_options'); $rank++) {
		$nation_id = $round_voting_stats[$rank-1]['nation_id'];
		$nation_rank2db_id[$rank] = $nation_id;
		$nation_score = nation_score_in_round($nation_id, $round_id);
		
		if ($nation_score > $max_vote_sum) {}
		else if (!$winning_nation && $nation_score > 0) {
			$winning_nation = $nation_id;
			$winning_votesum = $nation_score;
			$winning_score = $nation_score;
		}
	}
	
	$q = "INSERT INTO cached_rounds SET game_id='".get_site_constant('game_id')."', round_id='".$round_id."', payout_block_id='".($round_id*10)."'";
	if ($winning_nation) $q .= ", winning_nation_id='".$winning_nation."'";
	$q .= ", winning_score='".$winning_score."', score_sum='".$vote_sum."', time_created='".time()."'";
	for ($position=1; $position<=16; $position++) {
		$q .= ", position_".$position."='".$nation_rank2db_id[$position]."'";
	}
	$q .= ";";
	$r = run_query($q);
	echo "q: $q<br/>\n";
}
*/
?>