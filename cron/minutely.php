<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == "2r987jifwow") {
	$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
	$r = run_query($q);
	
	$last_block_id = last_block_id(get_site_constant('primary_game_id'));
	
	$num = rand(0, get_site_constant("minutes_per_block")-1);
	if ($_REQUEST['force_new_block'] == "1") $num = 0;
	
	if ($num == 0) {
		$q = "INSERT INTO blocks SET game_id='".get_site_constant('primary_game_id')."', block_id='".($last_block_id+1)."', time_created='".time()."';";
		$r = run_query($q);
		$last_block_id = mysql_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
		$r = run_query($q);
		$block = mysql_fetch_array($r);
		$last_block_id = $block['block_id'];
		
		$mining_block_id = $last_block_id+1;
		
		$voting_round = block_to_round($mining_block_id);
		
		if (get_site_constant('blocks_in_era') == 0 || $voting_round%get_site_constant('blocks_in_era') == 1) {
			$q = "UPDATE nations SET cached_force_multiplier=".get_site_constant('num_voting_options').", relevant_wins=1;";
			$r = run_query($q);
		}
		
		echo "Created block $last_block_id<br/>\n";
		
		// Send notifications for coins that just became available
		$q = "SELECT u.* FROM users u, webwallet_transactions t WHERE t.game_id='".get_site_constant('primary_game_id')."' AND u.game_id=t.game_id AND t.user_id=u.user_id AND u.notification_preference='email' AND u.notification_email != '' AND t.block_id='".($last_block_id - get_site_constant('maturity'))."' AND t.amount > 0 GROUP BY u.user_id;";
		$r = run_query($q);
		while ($notify_user = mysql_fetch_array($r)) {
			$account_value = account_coin_value($notify_user);
			$immature_balance = immature_balance($notify_user);
			$mature_balance = $account_value - $immature_balance;
			
			if ($mature_balance >= $account_value*$notify_user['aggregate_threshold']/100) {
				$subject = number_format($mature_balance, 5)." EmpireCoins are now available to vote.";
				$message = "<p>Some of your EmpireCoins just became available.</p>";
				$message .= "<p>You currently have ".number_format($mature_balance, 5)." coins available to vote. To cast a vote, please log in:</p>";
				$message .= "<p><a href=\"http://empireco.in/wallet/\">http://empireco.in/wallet/</a></p>";
				$message .= "<p>This message was sent by EmpireCo.in<br/>To disable these notifications, please log in and then click \"Voting Strategy\"";
				
				$delivery_id = mail_async($notify_user['notification_email'], "EmpireCo.in", "noreply@empireco.in", $subject, $message, "", "");
				
				echo "A notification of new coins available has been sent to ".$notify_user['notification_email'].".<br/>\n";
			}
		}
		
		// Run payouts
		if ($last_block_id%get_site_constant('round_length') == 0) {
			echo "<br/>Running payout on voting round #".($voting_round-1).", it's now round ".$voting_round."<br/>\n";
			$round_voting_stats = round_voting_stats_all(get_site_constant('primary_game_id'), $voting_round-1);
			
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
				$nation_score = nation_score_in_round(get_site_constant('primary_game_id'), $nation_id, $voting_round-1);
				
				if ($nation_score > $max_vote_sum) {}
				else if (!$winning_nation && $nation_score > 0) {
					$winning_nation = $nation_id;
					$winning_votesum = $nation_score;
					$winning_score = $nation_score;
				}
			}
			
			echo "Total votes: ".($vote_sum/(pow(10, 8)))." EMP<br/>\n";
			echo "Cutoff: ".($max_vote_sum/(pow(10, 8)))." EMP<br/>\n";
			
			if ($winning_nation) {
				if (get_site_constant('blocks_in_era') > 0) {
					$q = "UPDATE nations SET relevant_wins=relevant_wins+1 WHERE nation_id='".$winning_nation."';";
					$r = run_query($q);
					
					$q = "UPDATE nations SET cached_force_multiplier=ROUND((16+".($voting_round%100 - 1).")/relevant_wins, 8);";
					$r = run_query($q);
				}
				
				$q = "UPDATE game_nations SET losing_streak=losing_streak+1 WHERE game_id='".get_site_constant('primary_game_id')."';";
				$r = run_query($q);
				
				$q = "UPDATE game_nations SET losing_streak=0 WHERE game_id='".get_site_constant('primary_game_id')."' AND nation_id='".$winning_nation."';";
				$r = run_query($q);
				
				echo $round_voting_stats[$nation_id2rank[$winning_nation]]['name']." wins with ".($winning_votesum/(pow(10, 8)))." EMP voted.<br/>";
				
				$q = "SELECT * FROM webwallet_transactions t, users u WHERE t.game_id='".get_site_constant('primary_game_id')."' AND t.user_id=u.user_id AND t.block_id >= ".((($voting_round-2)*get_site_constant('round_length'))+1)." AND t.block_id <= ".(($voting_round-1)*get_site_constant('round_length')-1)." AND t.amount > 0 AND t.nation_id=".$winning_nation.";";
				$r = run_query($q);
				
				while ($transaction = mysql_fetch_array($r)) {
					$payout_amount = floor(750*pow(10,8)*$transaction['amount']/$winning_votesum);
					$qq = "INSERT INTO webwallet_transactions SET game_id='".get_site_constant('primary_game_id')."', vote_transaction_id='".$transaction['transaction_id']."', transaction_desc='votebase', amount=".$payout_amount.", user_id='".$transaction['user_id']."', address_id='".user_address_id(get_site_constant('primary_game_id'), $transaction['game_id'], $transaction['user_id'], false)."', block_id='".$last_block_id."', time_created='".time()."';";
					$rr = run_query($qq);
					echo "Pay ".$payout_amount/(pow(10,8))." EMP to ".$transaction['username']."<br/>\n";
				}
			}
			else echo "No winner<br/>";
			
			echo "<br/>\n";
			
			$q = "INSERT INTO cached_rounds SET game_id='".get_site_constant('primary_game_id')."', round_id='".($voting_round-1)."', payout_block_id='".$last_block_id."'";
			if ($winning_nation) $q .= ", winning_nation_id='".$winning_nation."'";
			$q .= ", winning_vote_sum='".$winning_votesum."', winning_score='".$winning_score."', total_vote_sum='".$vote_sum."', time_created='".time()."'";
			for ($position=1; $position<=16; $position++) {
				$q .= ", position_".$position."='".$nation_rank2db_id[$position]."'";
			}
			$q .= ";";
			$r = run_query($q);
		}
	}
	else {
		$last_block_id = last_block_id(get_site_constant('primary_game_id'));
		$mining_block_id = $last_block_id+1;
		echo "No block (".$num.")<br/>";
	}
	
	// Apply user strategies
	$current_round_id = block_to_round($mining_block_id);
	$block_of_round = $mining_block_id%get_site_constant('round_length');
	
	if ($block_of_round != 0) {
		$q = "SELECT * FROM users WHERE game_id='".get_site_constant('primary_game_id')."' AND logged_in=0 AND (voting_strategy='by_rank' OR voting_strategy='by_nation' OR voting_strategy='api') AND vote_on_block_".$block_of_round."=1 ORDER BY RAND();";
		$r = run_query($q);
		
		echo "Applying user strategies for block #".$mining_block_id.", looping through ".mysql_numrows($r)." users.<br/>";
		
		while ($strategy_user = mysql_fetch_array($r)) {
			$user_coin_value = account_coin_value(get_site_constant('primary_game_id'), $strategy_user);
			$immature_balance = immature_balance(get_site_constant('primary_game_id'), $strategy_user);
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
								
								$transaction_id = new_webwallet_transaction(get_site_constant('primary_game_id'), $vote_nation_id, $vote_amount, $strategy_user['user_id'], $mining_block_id);
							}
						}
					}
				}
				else {
					$pct_free = 100*$mature_balance/$user_coin_value;
					
					if ($pct_free >= $strategy_user['aggregate_threshold'] && $free_balance > 0) {
						$round_stats = round_voting_stats_all(get_site_constant('primary_game_id'), $current_round_id);
						$totalVoteSum = $round_stats[0];
						$ranked_stats = $round_stats[2];
						$nation_id2rank = $round_stats[3];
						
						$nation_pct_sum = 0;
						$skipped_pct_points = 0;
						$skipped_nations = "";
						$num_nations_skipped = 0;
						
						if ($strategy_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $strategy_user['by_rank_ranks']);
						
						for ($nation_id=1; $nation_id<=16; $nation_id++) {
							if ($strategy_user['voting_strategy'] == "by_nation") $nation_pct_sum += $strategy_user['nation_pct_'.$nation_id];
							
							$pct_of_votes = 100*$ranked_stats[$nation_id2rank[$nation_id]]['voting_sum']/$totalVoteSum;
							if ($pct_of_votes >= $strategy_user['min_votesum_pct'] && $pct_of_votes <= $strategy_user['max_votesum_pct']) {}
							else {
								$skipped_nations[$nation_id] = TRUE;
								if ($strategy_user['voting_strategy'] == "by_nation") $skipped_pct_points += $strategy_user['nation_pct_'.$nation_id];
								else if (in_array($nation_id2rank[$nation_id], $by_rank_ranks)) $num_nations_skipped++;
							}
						}
						
						if ($strategy_user['voting_strategy'] == "by_rank") {
							$divide_into = count($by_rank_ranks)-$num_nations_skipped;
							
							$coins_each = floor(pow(10,8)*$free_balance/$divide_into);
							
							echo "Dividing by rank among ".$divide_into." nations for ".$strategy_user['username']."<br/>";
							
							for ($rank=1; $rank<=16; $rank++) {
								if (in_array($rank, $by_rank_ranks) && !$skipped_nations[$ranked_stats[$rank-1]['nation_id']]) {
									echo "Vote ".round($coins_each/pow(10,8), 3)." EMP for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>";
									
									$transaction_id = new_webwallet_transaction(get_site_constant('primary_game_id'), $ranked_stats[$rank-1]['nation_id'], $coins_each, $strategy_user['user_id'], $mining_block_id);
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
										
										echo "Vote ".$strategy_user['nation_pct_'.$nation_id]."% (".round($coin_amount/pow(10,8), 3)." EMP) for ".$ranked_stats[$nation_id2rank[$nation_id]]['name']."<br/>";
										
										$transaction_id = new_webwallet_transaction(get_site_constant('primary_game_id'), $nation_id, $coin_amount, $strategy_user['user_id'], $mining_block_id);
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