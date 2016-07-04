<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == "2r987jifwow") {
	$num = rand(0, get_site_constant("minutes_per_block")-1);
	if ($_REQUEST['force_new_block'] == "1") $num = 0;
	
	if ($num == 0) {
		$q = "INSERT INTO blocks SET currency_mode='beta', time_created='".time()."';";
		$r = run_query($q);
		$last_block_id = mysql_insert_id();
		$mining_block_id = $last_block_id+1;
		
		$voting_round = block_to_round($mining_block_id);
		
		if ($voting_round%100 == 1) {
			$q = "UPDATE nations SET cached_force_multiplier=16, relevant_wins=1;";
			$r = run_query($q);
		}
		
		echo "Created block $last_block_id<br/>\n";
		
		if ($last_block_id%get_site_constant('round_length') == 0) {
			echo "<br/>Running payout on voting round #".($voting_round-1).", it's now round ".$voting_round."<br/>\n";
			$round_voting_stats = round_voting_stats($voting_round-1);
			
			$nation_votes = "";
			$nation_names = "";
			$vote_sum = 0;
			
			while ($nationsum = mysql_fetch_array($round_voting_stats)) {
				$vote_sum += $nationsum['voting_sum'];
				$nation_votes[$nationsum['nation_id']] = $nationsum['voting_sum'];
				$nation_names[$nationsum['nation_id']] = $nationsum['name'];
				$nation_scores[$nationsum['nation_id']] = $nationsum['voting_score'];
				echo $nationsum['name']." has ".($nationsum['voting_sum']/(pow(10, 8)))." EMP voted with a score of ".$nationsum['voting_score']." points.<br/>\n";
			}
			
			$maxVoteSum = floor($vote_sum/2);
			echo "Total votes: ".($vote_sum/(pow(10, 8)))." EMP<br/>\n";
			echo "Cutoff: ".($maxVoteSum/(pow(10, 8)))." EMP<br/>\n";
			
			$winning_nation = FALSE;
			$winning_votesum = 0;
			$winning_score = 0;
			for ($nation_id = 1; $nation_id <= 16; $nation_id++) {
				if ($nation_votes[$nation_id] > $maxVoteSum) {}
				else if ($nation_votes[$nation_id] > $winning_votesum) {
					$winning_nation = $nation_id;
					$winning_votesum = $nation_votes[$nation_id];
					$winning_score = $nation_scores[$nation_id];
				}
			}
			
			if ($winning_nation) {
				$q = "UPDATE nations SET relevant_wins=relevant_wins+1 WHERE nation_id='".$winning_nation."';";
				$r = run_query($q);
				
				$q = "UPDATE nations SET cached_force_multiplier=ROUND((16+".($voting_round%100 - 1).")/relevant_wins, 8);";
				$r = run_query($q);
				
				echo $nation_names[$winning_nation]." wins with ".($winning_votesum/(pow(10, 8)))." EMP voted and ".$winning_score." points.<br/>";
				
				$q = "SELECT * FROM webwallet_transactions t, users u WHERE t.user_id=u.user_id AND t.block_id >= ".((($voting_round-2)*get_site_constant('round_length'))+1)." AND t.block_id <= ".(($voting_round-1)*get_site_constant('round_length')-1)." AND t.amount > 0 AND t.nation_id=".$winning_nation.";";
				$r = run_query($q);
				
				while ($transaction = mysql_fetch_array($r)) {
					$payout_amount = floor(750*pow(10,8)*$transaction['amount']/$nation_votes[$winning_nation]);
					$qq = "INSERT INTO webwallet_transactions SET currency_mode='beta', vote_transaction_id='".$transaction['transaction_id']."', transaction_desc='votebase', amount=".$payout_amount.", user_id='".$transaction['user_id']."', block_id='".$last_block_id."', time_created='".time()."';";
					$rr = run_query($qq);
					echo "Pay ".$payout_amount/(pow(10,8))." EMP to ".$transaction['username']."<br/>\n";
				}
			}
			else echo "No winner<br/>";
			
			$q = "INSERT INTO cached_rounds SET round_id='".($voting_round-1)."', payout_block_id='".$last_block_id."'";
			if ($winning_nation) $q .= ", winning_nation_id='".$winning_nation."'";
			$q .= ", winning_vote_sum='".$winning_votesum."', winning_score='".$winning_score."'";
			$q .= ";";
			$r = run_query($q);
		}
	}
	else {
		$last_block_id = last_block_id('beta');
		$mining_block_id = $last_block_id+1;
		echo "No block (".$num.")<br/>";
	}
	
	// Apply user strategies
	$current_round_id = block_to_round($mining_block_id);
	$block_of_round = $mining_block_id%get_site_constant('round_length');
	
	if ($block_of_round != 0) {
		$q = "SELECT * FROM users WHERE (voting_strategy='by_rank' OR voting_strategy='by_nation' OR voting_strategy='api') AND vote_on_block_".$block_of_round."=1 ORDER BY RAND();";
		$r = run_query($q);
		
		echo "Applying user strategies for block #".$mining_block_id.", looping through ".mysql_numrows($r)." users.<br/>";
		
		while ($strategy_user = mysql_fetch_array($r)) {
			$user_coin_value = account_coin_value($strategy_user);
			$immature_balance = immature_balance($strategy_user);
			$mature_balance = $user_coin_value - $immature_balance;
			$free_balance = $mature_balance - $strategy_user['min_coins_available'];
			
			if ($user_coin_value > 0) {
				if ($strategy_user['voting_strategy'] == "api") {
					$api_result = file_get_contents("http://162.253.154.32/proxy908341/?url=".urlencode($strategy_user['api_url']));
					$api_obj = json_decode($api_result);
					
					if ($api_obj->recommendations && count($api_obj->recommendations) > 0 && in_array($api_obj->recommendation_unit, array('coin','percent'))) {
						$amount_error = false;
						$amount_sum = 0;
						$empire_id_error = false;
						
						echo "Hitting url: ".$strategy_user['api_url']."<br/>\n";
						
						for ($rec_id=0; $rec_id<count($api_obj->recommendations); $rec_id++) {
							if ($api_obj->recommendations[$rec_id]->recommended_amount && $api_obj->recommendations[$rec_id]->recommended_amount > 0 && intval($api_obj->recommendations[$rec_id]->recommended_amount) == $api_obj->recommendations[$rec_id]->recommended_amount) $amount_sum += $api_obj->recommendations[$rec_id]->recommended_amount;
							else $amount_error = true;
							
							if ($api_obj->recommendations[$rec_id]->empire_id >= 0 && $api_obj->recommendations[$rec_id]->empire_id < 16) {}
							else $empire_id_error = true;
						}
						
						if ($api_obj->recommendation_unit == "coin") {
							if ($amount_sum <= $mature_balance*pow(10,8)) {}
							else $amount_error = true;
						}
						else {
							if ($amount_sum <= 100) {}
							else $amount_error = true;
						}
						
						if ($amount_error) {
							echo "Error, an invalid amount was specified.";
						}
						else if ($empire_id_error) {
							echo "Error, one of the empire IDs was invalid.";
						}
						else {
							for ($rec_id=0; $rec_id<count($api_obj->recommendations); $rec_id++) {
								if ($api_obj->recommendation_unit == "coin") $vote_amount = $api_obj->recommendations[$rec_id]->recommended_amount;
								else $vote_amount = floor($mature_balance*pow(10,8)*$api_obj->recommendations[$rec_id]->recommended_amount/100);
								
								$vote_nation_id = $api_obj->recommendations[$rec_id]->empire_id + 1;
								echo "Vote ".$vote_amount." for ".$vote_nation_id."<br/>\n";
								
								$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', nation_id='".$vote_nation_id."', transaction_desc='transaction', amount=".$vote_amount.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
								$r = run_query($q);
								
								$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', transaction_desc='transaction', amount=".(-1)*$vote_amount.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
								$r = run_query($q);
							}
						}
					}
				}
				else {
					$pct_free = 100*$mature_balance/$user_coin_value;
					
					if ($pct_free >= $strategy_user['aggregate_threshold'] && $free_balance > 0) {
						$round_stats = round_voting_stats_all($current_round_id);
						$totalVoteSum = $round_stats[0];
						$ranked_stats = $round_stats[2];
						$nation_id_to_rank = $round_stats[3];
						
						$nation_pct_sum = 0;
						$skipped_pct_points = 0;
						$skipped_nations = "";
						$num_nations_skipped = 0;
						
						if ($strategy_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $strategy_user['by_rank_ranks']);
						
						for ($nation_id=1; $nation_id<=16; $nation_id++) {
							if ($strategy_user['voting_strategy'] == "by_nation") $nation_pct_sum += $strategy_user['nation_pct_'.$nation_id];
							
							$pct_of_votes = 100*$ranked_stats[$nation_id_to_rank[$nation_id]]['voting_sum']/$totalVoteSum;
							if ($pct_of_votes >= $strategy_user['min_votesum_pct'] && $pct_of_votes <= $strategy_user['max_votesum_pct']) {}
							else {
								$skipped_nations[$nation_id] = TRUE;
								if ($strategy_user['voting_strategy'] == "by_nation") $skipped_pct_points += $strategy_user['nation_pct_'.$nation_id];
								else if (in_array($nation_id_to_rank[$nation_id], $by_rank_ranks)) $num_nations_skipped++;
							}
						}
						
						if ($strategy_user['voting_strategy'] == "by_rank") {
							$divide_into = count($by_rank_ranks)-$num_nations_skipped;
							
							$coins_each = floor(pow(10,8)*$free_balance/$divide_into);
							
							echo "Dividing by rank among ".$divide_into." nations for ".$strategy_user['username']."<br/>";
							
							for ($rank=1; $rank<=16; $rank++) {
								if (in_array($rank, $by_rank_ranks) && !$skipped_nations[$ranked_stats[$rank-1]['nation_id']]) {
									echo "Vote ".round($coins_each/pow(10,8), 3)." EMP for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>";
									$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', nation_id='".$ranked_stats[$rank-1]['nation_id']."', transaction_desc='transaction', amount=".$coins_each.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
									$r = run_query($q);
									
									$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', transaction_desc='transaction', amount=".(-1)*$coins_each.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
									$r = run_query($q);
								}
							}
						}
						else { // by_nation
							echo "Dividing by nation for ".$strategy_user['username']." (".$free_balance." EMP)<br/>\n";
							
							$mult_factor = 1;
							if ($skipped_pct_points > 0) {
								$mult_factor = floor(pow(10,6)*$nation_pct_sum/($nation_pct_sum-$skipped_pct_points))/pow(10,6);
							}
							
							if ($nation_pct_sum == 100) {
								for ($nation_id=1; $nation_id<=16; $nation_id++) {
									if (!$skipped_nations[$nation_id] && $strategy_user['nation_pct_'.$nation_id] > 0) {
										$effective_frac = floor(pow(10,4)*$strategy_user['nation_pct_'.$nation_id]*$mult_factor)/pow(10,6);
										$coin_amount = floor($effective_frac*$free_balance*pow(10,8));
										
										echo "Vote ".$strategy_user['nation_pct_'.$nation_id]."% (".round($coins_amount/pow(10,8), 3)." EMP) for ".$ranked_stats[$nation_id_to_rank[$nation_id]]['name']."<br/>";
										
										$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', nation_id='".$nation_id."', transaction_desc='transaction', amount=".$coin_amount.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
										$r = run_query($q);
										
										$q = "INSERT INTO webwallet_transactions SET currency_mode='beta', transaction_desc='transaction', amount=".(-1)*$coin_amount.", user_id='".$strategy_user['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
										$r = run_query($q);
									}
								}
							}
						}
					}
				}
			}
		}
	}
}
else echo "Error: permission denied.";
?>