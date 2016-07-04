<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$game = new Game($app, $game_id);
	
	if ($game) {
		$q = "DELETE FROM cached_rounds WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		$last_block_id = $game->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
		
		$start_round = $game->block_to_round($game->db_game['game_starting_block']);
		
		for ($round_id=$start_round; $round_id<=$current_round-1; $round_id++) {
			if ($game->db_game['game_type'] == "real") {
				$game->add_round_from_rpc($round_id);
			}
			else {
				/*$round_voting_stats = $game->round_voting_stats_all($round_id);
			
				$vote_sum = $round_voting_stats[0];
				$max_vote_sum = $round_voting_stats[1];
				$round_voting_stats = $round_voting_stats[2];
				$option_id2rank = $round_voting_stats[3];
			
				$winning_option = FALSE;
				$winning_votesum = 0;
				$winning_score = 0;
			
				for ($rank=0; $rank<$game['num_voting_options']; $rank++) {
					$option_id = $round_voting_stats[$rank]['option_id'];
					$option_rank2db_id[$rank] = $option_id;
					$option_scores = $game->option_score_in_round($option_id, $round_id);
				
					if ($option_scores['sum'] > $max_vote_sum) {}
					else if (!$winning_option && $option_scores['sum'] > 0) {
						$winning_option = $option_id;
						$winning_votesum = $option_scores['sum'];
						$winning_score = $option_scores['sum'];
					}
				}
			
				$q = "INSERT INTO cached_rounds SET game_id='".$game->db_game['game_id']."', round_id='".$round_id."', payout_block_id='".($round_id*$game->db_game['round_length'])."'";
				if ($winning_option) {
					$q .= ", winning_option_id='".$winning_option."'";
					$qq = "SELECT * FROM transactions WHERE transaction_desc='votebase' AND game_id='".$game->db_game['game_id']."' AND block_id = ".$round_id*$game->db_game['round_length'].";";
					$rr = $app->run_query($qq);
					if ($rr->rowCount() > 0) {
						$payout_transaction = $rr->fetch();
						$q .= ", payout_transaction_id='".$payout_transaction['transaction_id']."'";
					}
				}
				$q .= ", winning_score='".$winning_score."', score_sum='".$vote_sum."', time_created='".time()."'";
				for ($position=1; $position <= $game['num_voting_options']; $position++) {
					$q .= ", position_".$position."='".$option_rank2db_id[$position]."'";
				}
				$q .= ";";
				$r = $app->run_query($q);*/
			}
			echo "Added cached round #".$round_id." to ".$game->db_game['name']."<br/>\n";
		}
	}
	else echo "Error identifying the game.";
}
else echo "Incorrect key.";
?>
