<?php
class Event {
	public $db_event;
	public $app;
	public $game;
	
	public function __construct(&$game, $db_event, $event_id) {
		$this->game = $game;
		
		if ($db_event) {
			$this->db_event = $db_event;
		}
		else {
			$this->load_db_event($event_id);
		}
	}
	
	public function load_db_event($event_id) {
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.event_id='".$event_id."';";
		$r = $this->game->blockchain->app->run_query($q);
		$this->db_event = $r->fetch() or die("Error, could not load event #".$event_id);
	}
	
	public function round_voting_stats() {
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		$q = "SELECT * FROM options op LEFT JOIN images i ON op.image_id=i.image_id LEFT JOIN entities e ON op.entity_id=e.entity_id WHERE op.event_id='".$this->db_event['event_id']."' ORDER BY ((op.votes+op.unconfirmed_votes)*".$coins_per_vote.")+(op.effective_destroy_score+op.unconfirmed_effective_destroy_score) DESC, op.option_id ASC;";
		
		return $this->game->blockchain->app->run_query($q);
	}

	public function total_votes_in_round($include_unconfirmed) {
		$sum = 0;
		
		$base_q = "SELECT SUM(votes) FROM transaction_game_ios WHERE event_id='".$this->db_event['event_id']."' AND option_id > 0";
		$confirmed_q = $base_q." AND create_round_id IS NOT NULL;";
		$confirmed_r = $this->game->blockchain->app->run_query($confirmed_q);
		$confirmed_votes = $confirmed_r->fetch(PDO::FETCH_NUM);
		$confirmed_votes = $confirmed_votes[0];
		if ($confirmed_votes > 0) {} else $confirmed_votes = 0;
		
		$sum += $confirmed_votes;
		
		$returnvals['confirmed'] = $confirmed_votes;
		
		if ($include_unconfirmed) {
			$q = "SELECT SUM(unconfirmed_votes) FROM options WHERE event_id='".$this->db_event['event_id']."';";
			$r = $this->game->blockchain->app->run_query($q);
			$sums = $r->fetch(PDO::FETCH_NUM);
			
			$unconfirmed_votes = $sums[0];
			$sum += $unconfirmed_votes;
			$returnvals['unconfirmed'] = $unconfirmed_votes;
		}
		else $returnvals['unconfirmed'] = 0;
		
		$returnvals['sum'] = $sum;
		
		return $returnvals;
	}

	public function round_voting_stats_all() {
		$round_voting_stats = $this->round_voting_stats();
		$stats_all = false;
		$counter = 0;
		$option_id_csv = "";
		$option_id_to_rank = "";
		$confirmed_score = 0;
		$unconfirmed_score = 0;
		$destroy_score = 0;
		$unconfirmed_destroy_score = 0;
		$effective_destroy_score = 0;
		$unconfirmed_effective_destroy_score = 0;
		$score_identifier = $this->game->db_game['payout_weight'].'_score';
		
		while ($stat = $round_voting_stats->fetch()) {
			$stats_all[$counter] = $stat;
			$option_id_csv .= $stat['option_id'].",";
			$option_id_to_rank[$stat['option_id']] = $counter;
			$confirmed_score += $stat[$score_identifier];
			$unconfirmed_score += $stat['unconfirmed_'.$score_identifier];
			$destroy_score += $stat['destroy_score'];
			$unconfirmed_destroy_score += $stat['unconfirmed_destroy_score'];
			$effective_destroy_score += $stat['effective_destroy_score'];
			$unconfirmed_effective_destroy_score += $stat['unconfirmed_effective_destroy_score'];
			$counter++;
		}
		if ($option_id_csv != "") $option_id_csv = substr($option_id_csv, 0, strlen($option_id_csv)-1);
		
		$q = "SELECT * FROM options gvo LEFT JOIN images i ON gvo.image_id=i.image_id LEFT JOIN entities e ON gvo.entity_id=e.entity_id WHERE gvo.event_id='".$this->db_event['event_id']."'";
		if ($option_id_csv != "") $q .= " AND gvo.option_id NOT IN (".$option_id_csv.")";
		$q .= " ORDER BY gvo.option_id ASC;";
		$r = $this->game->blockchain->app->run_query($q);
		
		while ($stat = $r->fetch()) {
			$stat['votes'] = 0;
			$stat['unconfirmed_votes'] = 0;
			$stat['option_block_score'] = 0;
			$stat['destroy_score'] = 0;
			$stat['unconfirmed_destroy_score'] = 0;
			$stat['effective_destroy_score'] = 0;
			$stat['unconfirmed_effective_destroy_score'] = 0;
			
			$stats_all[$counter] = $stat;
			$option_id_to_rank[$stat['option_id']] = $counter;
			$counter++;
		}
		
		$current_round = $this->game->block_to_round($this->game->blockchain->last_block_id()+1);
		$include_unconfirmed = true;
		
		$sum_votes = $this->total_votes_in_round($include_unconfirmed);
		$output_arr[0] = $sum_votes['sum'];
		$output_arr[1] = floor($sum_votes['sum']*$this->db_event['max_voting_fraction']);
		$output_arr[2] = $stats_all;
		$output_arr[3] = $option_id_to_rank;
		$output_arr[4] = $sum_votes['confirmed'];
		$output_arr[5] = $sum_votes['unconfirmed'];
		$output_arr[6] = $confirmed_score;
		$output_arr[7] = $unconfirmed_score;
		$output_arr[8] = $destroy_score;
		$output_arr[9] = $unconfirmed_destroy_score;
		$output_arr[10] = $effective_destroy_score;
		$output_arr[11] = $unconfirmed_effective_destroy_score;
		
		return $output_arr;
	}
	
	public function current_round_table($user, $show_intro_text, $clickable, $game_instance_id, $game_event_index) {
		$score_field = $this->game->db_game['payout_weight']."_score";
		
		$max_block_id = min($this->db_event['event_final_block'], $this->game->blockchain->last_block_id());
		
		if ($this->game->db_game['inflation'] == "exponential") $votes_per_coin = $this->game->blockchain->app->votes_per_coin($this->game->db_game);
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		$display_mode = "slim";//$this->db_event['display_mode'];
		
		$html = '<div id="game'.$game_instance_id.'_event'.$game_event_index.'_round_table" class="round_table">';
		
		$option_max_width = $this->db_event['option_max_width'];
		if ($display_mode == "slim") $option_max_width = min(100, $option_max_width);
		
		$sq_px_per_pct_point = pow($option_max_width, 2)/100;
		$min_px_diam = 20;
		
		$round_stats_all = false;
		$winner = false;
		
		list($winning_option_id, $winning_votes, $winning_effective_destroy_score) = $this->determine_winning_option($round_stats_all);
		
		if ((string)$this->db_event['outcome_index'] !== "") {
			$expected_winner = $this->game->blockchain->app->run_query("SELECT * FROM options WHERE event_id='".$this->db_event['event_id']."' AND event_option_index='".$this->db_event['outcome_index']."';")->fetch();
		}
		else $expected_winner = false;
		
		$sum_votes = $round_stats_all[0];
		$max_sum_votes = $round_stats_all[1];
		$round_stats = $round_stats_all[2];
		$option_id_to_rank = $round_stats_all[3];
		$confirmed_sum_votes = $round_stats_all[4];
		$unconfirmed_sum_votes = $round_stats_all[5];
		$confirmed_score = $round_stats_all[6];
		$unconfirmed_score = $round_stats_all[7];
		$destroy_score = $round_stats_all[8];
		$unconfirmed_destroy_score = $round_stats_all[9];
		$effective_destroy_score = $round_stats_all[10];
		$unconfirmed_effective_destroy_score = $round_stats_all[11];
		
		$event_effective_coins = $sum_votes*$coins_per_vote + $effective_destroy_score + $unconfirmed_effective_destroy_score;
		
		$score_disp = "";
		if (!empty($this->db_event['option_block_rule'])) {
			$option_ids = array();
			$scores = array();
			
			if ($display_mode == "default") {
				$score_disp .= '<div class="event_score_box">';
				$score_disp .= "Current Scores:<br/>\n";
				for ($i=0; $i<count($round_stats); $i++) {
					$score_disp .= '<div class="row"><div class="col-sm-6 boldtext">'.$round_stats[$i]['entity_name'].'</div><div class="col-sm-6">'.$round_stats[$i]['option_block_score'].'</div></div>'."\n";
				}
				$score_disp .= "</div>\n";
			}
			else {
				$qq = "SELECT *, SUM(ob.score) AS option_block_score FROM options o LEFT JOIN option_blocks ob ON o.option_id=ob.option_id LEFT JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id='".$this->db_event['event_id']."' GROUP BY o.option_id ORDER BY o.option_index ASC;";
				$rr = $this->game->blockchain->app->run_query($qq);
				
				$score_disp = "";
				$first_option = false;
				$second_option = false;
				
				while ($option = $rr->fetch()) {
					$score_disp .= ((int)$option['option_block_score'])."-";
					if (empty($first_option)) $first_option = $option;
					else if (empty($second_option)) $second_option = $option;
				}
				$score_disp = substr($score_disp, 0, strlen($score_disp)-1);
				$score_disp .= " &nbsp; ";
				
				if ($first_option['option_block_score'] == $second_option['option_block_score']) $score_disp .= "Tied";
				else {
					if ($first_option['option_block_score'] > $second_option['option_block_score']) $score_disp .= $first_option['entity_name']." is winning";
					else $score_disp .= $second_option['entity_name']." is winning";
				}
			}
		}
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		
		if ($this->db_event['option_block_rule'] == "football_match") $html .= '<p><div class="event_timer_slim" id="game'.$game_instance_id.'_event'.$game_event_index.'_timer"></div>';
		else {
			$html .= '<p><div class="event_timer_slim">';
			
			if (!empty($this->db_event['event_final_time'])) {
				$sec_left = strtotime($this->db_event['event_final_time'])-time();
				$html .= date("Y-m-d G:i:s", strtotime($this->db_event['event_final_time']));
				$html .= "<br/>";
				if ($sec_left > 0) $html .= $this->game->blockchain->app->format_seconds($sec_left)." left";
				else $html .= '<font class="redtext">Expired '.$this->game->blockchain->app->format_seconds(-1*$sec_left).' ago</font>';
			}
			else {
				$blocks_left = $this->db_event['event_final_block'] - $max_block_id;
				if ($blocks_left > 0) {
					$sec_left = $this->game->blockchain->db_blockchain['seconds_per_block']*$blocks_left;
					$html .= $this->game->blockchain->app->format_bignum($blocks_left).' blocks left<br/>';
					$html .= $this->game->blockchain->app->format_seconds($sec_left);
				}
			}
			$html .= '</div></p>';
		}
		$html .= "<strong><a style=\"color: #000; text-decoration: underline;\" target=\"_blank\" href=\"/explorer/games/".$this->game->db_game['url_identifier']."/events/".$this->db_event['event_index']."\">".$this->db_event['event_name']."</a></strong> ";
		$html .= " &nbsp;&nbsp; ";
		$html .= $score_disp;
		$html .= "</p>\n";
		
		if ($expected_winner) {
			$html .= "<p class=\"greentext\">Winner: ".$expected_winner['name']."</p>\n";
		}
		
		if ($this->game->db_game['inflation'] == "exponential") {
			if ($votes_per_coin > 0) $confirmed_coins = $destroy_score + $confirmed_score/$votes_per_coin;
			else $confirmed_coins = $destroy_score;
			
			$unconfirmed_coins = $total_reward - $confirmed_coins;
			
			$html .= "<p>".$this->game->blockchain->app->format_bignum($confirmed_coins/pow(10,$this->game->db_game['decimal_places']))." ".$this->game->db_game['coin_name_plural']." in confirmed bets";
			if ($unconfirmed_coins > 0) $html .= ", ".$this->game->blockchain->app->format_bignum($unconfirmed_coins/pow(10,$this->game->db_game['decimal_places']))." unconfirmed";
			$html .= "</p>\n";
		}
		
		/* To-do
		if ($this->db_event['vote_effectiveness_function'] != "constant") {
			$q = "SELECT SUM(".$this->game->db_game['payout_weight']."_score), SUM(unconfirmed_".$this->game->db_game['payout_weight']."_score), SUM(votes), SUM(unconfirmed_votes) FROM options WHERE event_id='".$this->db_event['event_id']."';";
			$r = $this->game->blockchain->app->run_query($q);
			$score_votes = $r->fetch();
			$score = ($score_votes['SUM('.$this->game->db_game['payout_weight'].'_score)']+$score_votes['SUM(unconfirmed_'.$this->game->db_game['payout_weight'].'_score)']);
			$votes = $score_votes['SUM(votes)']+$score_votes['SUM(unconfirmed_votes)'];
			if ($score > 0) $average_effectiveness = $votes/$score;
			else $average_effectiveness = 1;
			
			$nextblock_effectiveness = $this->block_id_to_effectiveness_factor($max_block_id+1);
			$actual_votes_per_coin = $this->game->blockchain->app->votes_per_coin($this->game->db_game)*$average_effectiveness;
			
			if ($display_mode == "slim") {}
			else {
				if ($nextblock_effectiveness > 0) $html .= "Votes are ".round(100*$nextblock_effectiveness)."% effective right now. \n";
				$html .= '<div class="row"><div class="col-sm-6 boldtext">Average Effectiveness:</div><div class="col-sm-6">'.round(100*$average_effectiveness, 2)."%";
				if ($this->game->db_game['inflation'] == "exponential") {
					$html .= " (".$this->game->blockchain->app->format_bignum($actual_votes_per_coin)." votes per ".$this->game->db_game['coin_name'].")";
				}
				$html .= "</div></div>\n";
			}
		} */
		
		for ($i=0; $i<count($round_stats); $i++) {
			$option_votes = $round_stats[$i]['votes'] + $round_stats[$i]['unconfirmed_votes'];
			$option_effective_coins = $option_votes*$coins_per_vote + $round_stats[$i]['effective_destroy_score'] + $round_stats[$i]['unconfirmed_effective_destroy_score'];
			
			if ($this->db_event['event_winning_rule'] == "max_below_cap" && !$winning_option_id && $option_votes <= $max_sum_votes && $option_votes > 0) $winning_option_id = $round_stats[$i]['option_id'];
			
			if ($option_effective_coins > 0) {
				$pct_votes = 100*(floor(1000*$option_effective_coins/$event_effective_coins)/1000);
				$odds = $event_effective_coins/$option_effective_coins;
				$odds_disp = "x".round($odds, 2);
			}
			else {
				$pct_votes = 0;
				$odds_disp = "";
			}
			
			$sq_px = $pct_votes*$sq_px_per_pct_point;
			$box_diam = round(sqrt($sq_px));
			if ($box_diam < $min_px_diam) $box_diam = $min_px_diam;
			
			$holder_width = $box_diam;
			
			$show_boundbox = false;
			if (!empty($this->db_event['max_voting_fraction']) && $this->db_event['max_voting_fraction'] != 1 && ($i == 0 || $option_votes > $max_sum_votes)) {
				$show_boundbox = true;
				$boundbox_sq_px = $this->db_event['max_voting_fraction']*100*$sq_px_per_pct_point;
				$boundbox_diam = round(sqrt($boundbox_sq_px));
				if ($boundbox_diam > $holder_width) $holder_width = $boundbox_diam;
			}
			
			$html .= '
			<div class="vote_option_box_container">';
			
			if ($this->game->db_game['view_mode'] == "simple") {
				$onclick_html = 'if (!transaction_in_progress) {add_utxo_to_vote(utxo_spend_offset, true, false); games['.$game_instance_id.'].add_option_to_vote('.$game_event_index.', '.$round_stats[$i]['option_id'].', \''.$round_stats[$i]['name'].'\'); confirm_compose_vote(); setTimeout(\'games[0].show_next_event();\', 1200);}';
				$html .= '<img id="option'.$round_stats[$i]['option_id'].'_image" src="" style="cursor: pointer; max-width: 400px; max-height: 400px; border: 1px solid black; margin-bottom: 5px;" onclick="'.$onclick_html.'" />';
			}
			else $onclick_html = 'games['.$game_instance_id.'].events['.$game_event_index.'].option_selected('.$i.'); games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');';
			
			$html .= '
				<div class="vote_option_label';
				if ($this->db_event['event_winning_rule'] == "max_below_cap") {
					if ($option_votes > $max_sum_votes) $html .=  " redtext";
					else if ($winning_option_id == $round_stats[$i]['option_id']) $html .=  " greentext";
				}
				$html .= '"';
				if ($clickable) $html .= ' style="cursor: pointer;" onclick="'.$onclick_html.'"';
				$html .= '>'.$round_stats[$i]['name'];
				if (!empty($odds_disp)) $html .= ' &nbsp; '.$odds_disp;
				$html .= ' &nbsp; ('.$pct_votes.'%)';
				$html .= '
				</div>';
			if ($this->game->db_game['view_mode'] == "simple") {}
			else {
				$html .= '
				<div class="stage vote_option_box_holder" style="height: '.$holder_width.'px; width: '.$holder_width.'px;">';
				if ($show_boundbox) {
					$html .= '<div onclick="games['.$game_instance_id.'].events['.$game_event_index.'].option_selected('.$i.'); games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');" class="vote_option_boundbox" style="cursor: pointer; height: '.$boundbox_diam.'px; width: '.$boundbox_diam.'px;';
					if ($holder_width != $boundbox_diam) $html .= 'left: '.(($holder_width-$boundbox_diam)/2).'px; top: '.(($holder_width-$boundbox_diam)/2).'px;';
					$html .= '"></div>';
				}
				$html .= '
					<div class="ball vote_option_box" style="width: '.$box_diam.'px; height: '.$box_diam.'px;';
					if ($holder_width != $box_diam) $html .= 'left: '.(($holder_width-$box_diam)/2).'px; top: '.(($holder_width-$box_diam)/2).'px;';
					if ($round_stats[$i]['image_id'] > 0) $html .= 'background-image: url(\''.$this->game->blockchain->app->image_url($round_stats[$i]).'\');';
					if ($clickable) $html .= 'cursor: pointer;';
					if ($this->db_event['event_winning_rule'] == "max_below_cap" && $option_votes > $max_sum_votes) $html .= 'opacity: 0.5;';
					$html .= '" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_option_'.$i.'"';
					if ($clickable) $html .= ' onmouseover="games['.$game_instance_id.'].events['.$game_event_index.'].option_selected('.$i.');" onclick="games['.$game_instance_id.'].events['.$game_event_index.'].option_selected('.$i.'); games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');"';
					$html .= '>
						<input type="hidden" id="game'.$game_instance_id.'_event'.$game_event_index.'_option_id2rank_'.$round_stats[$i]['option_id'].'" value="'.$i.'" />
						<input type="hidden" id="game'.$game_instance_id.'_event'.$game_event_index.'_rank2option_id_'.$i.'" value="'.$round_stats[$i]['option_id'].'" />
					</div>
				</div>';
			}
			$html .= '
			</div>';
		}
		$html .= "</div>";
		
		return $html;
	}
	
	public function new_payout_transaction($winning_option, $winning_votes, $winning_effective_destroy_score) {
		$log_text = "";
		
		if ($this->game->db_game['payout_weight'] == "coin") $score_field = "colored_amount";
		else $score_field = $this->game->db_game['payout_weight']."s_destroyed";
		
		// Loop through the correctly voted UTXOs
		$q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.event_id='".$this->db_event['event_id']."' AND gio.option_id=".$winning_option.";";
		$r = $this->game->blockchain->app->run_query($q);
		$log_text .= "Paying out ".$r->rowCount()." correct votes.<br/>\n";
		$total_paid = 0;
		$out_index = 0;
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		$winning_effective_coins = floor($winning_votes*$coins_per_vote) + $winning_effective_destroy_score;
		
		while ($input = $r->fetch()) {
			$this_input_effective_coins = floor($input['votes']*$coins_per_vote) + $input['effective_destroy_amount'];
			$this_input_payout_amount = floor($total_reward*($this_input_effective_coins/$winning_effective_coins));
			
			$total_paid += $this_input_payout_amount;
			
			$payout_io_id = $this->game->trace_io_to_unspent_io_in_block($input, $this->db_event['event_payout_block']);
			
			$qq = "INSERT INTO transaction_game_ios SET io_id='".$payout_io_id."', original_io_id='".$input['io_id']."', is_coinbase=1, instantly_mature=0, game_id='".$this->game->db_game['game_id']."', event_id='".$this->db_event['event_id']."'";
			$qq .= ", colored_amount='".$this_input_payout_amount."', create_round_id='".$this->game->block_to_round($this->db_event['event_payout_block'])."';";
			$rr = $this->game->blockchain->app->run_query($qq);
			$output_id = $this->game->blockchain->app->last_insert_id();
			
			$this->game->blockchain->app->log_message($output_id." ".$qq);
			
			$qq = "UPDATE transaction_game_ios SET payout_game_io_id='".$output_id."' WHERE game_io_id='".$input['game_io_id']."';";
			$rr = $this->game->blockchain->app->run_query($qq);
			
			$payout_disp = $this_input_payout_amount/(pow(10,$this->game->db_game['decimal_places']));
			$log_text .= "Pay ".$payout_disp." ";
			if ($payout_disp == '1') $log_text .= $this->game->db_game['coin_name'];
			else $log_text .= $this->game->db_game['coin_name_plural'];
			$log_text .= " to ".$input['address']."<br/>\n";
			$out_index++;
		}
		
		return $log_text;
	}
	
	public function user_votes_in_event($user_id, $include_unconfirmed) {
		/*$q = "SELECT SUM(t_fees.fee_amount) FROM (SELECT t.fee_amount FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN options op ON gio.option_id=op.option_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE gio.game_id='".$this->game->db_game['game_id']."' AND gio.create_round_id = ".$round_id." AND io.user_id='".$user_id."' GROUP BY t.transaction_id) t_fees;";
		$r = $this->game->blockchain->app->run_query($q);
		$fee_amount = $r->fetch(PDO::FETCH_NUM);
		$fee_amount = $fee_amount[0];*/
		$fee_amount = 0;
		
		$q = "SELECT op.*, SUM(gio.colored_amount), SUM(gio.coin_blocks_destroyed), SUM(gio.coin_rounds_destroyed), SUM(gio.votes) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN options op ON gio.option_id=op.option_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE gio.event_id='".$this->db_event['event_id']."' AND io.user_id='".$user_id."' GROUP BY gio.option_id ORDER BY op.option_id ASC;";
		$r = $this->game->blockchain->app->run_query($q);
		$coins_voted = 0;
		$coin_blocks_voted = 0;
		$coin_rounds_voted = 0;
		$votes = 0;
		$my_votes = array();
		while ($votesum = $r->fetch()) {
			$my_votes[$votesum['option_id']]['coins'] = $votesum['SUM(gio.colored_amount)'];
			$my_votes[$votesum['option_id']]['coin_blocks'] = $votesum['SUM(gio.coin_blocks_destroyed)'];
			$my_votes[$votesum['option_id']]['coin_rounds'] = $votesum['SUM(gio.coin_rounds_destroyed)'];
			$my_votes[$votesum['option_id']]['votes'] = $votesum['SUM(gio.votes)'];
			$coins_voted += $votesum['SUM(gio.colored_amount)'];
			$coin_blocks_voted += $votesum['SUM(gio.coin_blocks_destroyed)'];
			$coin_rounds_voted += $votesum['SUM(gio.coin_rounds_destroyed)'];
			$votes += $votesum['SUM(gio.votes)'];
		}
		$returnvals[0] = $my_votes;
		$returnvals[1] = $coins_voted;
		$returnvals[2] = $coin_blocks_voted;
		$returnvals[3] = $coin_rounds_voted;
		$returnvals[4] = $votes;
		$returnvals['fee_amount'] = $fee_amount;
		return $returnvals;
	}

	public function my_votes_table($round_id, &$user_game) {
		$last_block_id = $this->game->blockchain->last_block_id();
		$html = "";
		$confirmed_html = "";
		$num_confirmed = 0;
		$unconfirmed_html = "";
		$num_unconfirmed = 0;
		
		if ($this->game->db_game['payout_weight'] == "coin") $score_field = "gio.colored_amount";
		else {
			if ($this->game->db_game['payout_weight'] == "coin_block") $score_field = "gio.coin_blocks_destroyed";
			else $score_field = "gio.coin_rounds_destroyed";
		}
		
		$q = "SELECT gio.*, op.option_id, op.name, t.transaction_id, t.tx_hash, t.fee_amount, io.spend_status FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN options op ON gio.option_id=op.option_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.event_id='".$this->db_event['event_id']."' AND gio.create_round_id IS NOT NULL AND k.account_id='".$user_game['account_id']."' ORDER BY t.transaction_id DESC;";
		$r = $this->game->blockchain->app->run_query($q);
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		while ($my_vote = $r->fetch()) {
			$color = "green";
			$num_votes = $my_vote[$this->game->db_game['payout_weight'].'s_destroyed'];
			$effective_votes = floor($num_votes*$my_vote['effectiveness_factor']);
			$net_effective_coins = $effective_votes*$coins_per_vote + $my_vote['destroy_amount']*$my_vote['effectiveness_factor'];
			$option_votes = $this->option_stats($my_vote['option_id']);
			$option_effective_coins = $option_votes['sum']*$coins_per_vote + $option_votes['effective_destroy_sum'];
			if ($option_effective_coins > 0) {
				$expected_payout = $total_reward*$net_effective_coins/$option_effective_coins;
			}
			else $expected_payout = 0;
			
			$confirmed_html .= '<div class="row">';
			$confirmed_html .= '<div class="col-sm-4 '.$color.'text">'.$my_vote['name'].'</div>';
			$confirmed_html .= '<div class="col-sm-3 '.$color.'text">';
			$confirmed_html .= '<a target="_blank" href="/explorer/games/'.$this->game->db_game['url_identifier'].'/transactions/'.$my_vote['tx_hash'].'">';
			
			if ($this->game->db_game['inflation'] == "exponential") {
				$coin_stake = $my_vote['destroy_amount'] + $num_votes*$coins_per_vote;
				
				if ($coin_stake > 0) $odds = round($expected_payout/$coin_stake, 2);
				else $odds = 0;
				
				$confirmed_html .= $this->game->blockchain->app->format_bignum($coin_stake/pow(10,$this->game->db_game['decimal_places']), 2)."&nbsp;(x".$odds.")";
			}
			else {
				$confirmed_html .=  $this->game->blockchain->app->format_bignum($num_votes/pow(10,$this->game->db_game['decimal_places']), 2).' votes';
			}
			$confirmed_html .= '</a></div>';
			
			$payout_disp = $this->game->blockchain->app->format_bignum($expected_payout/pow(10,$this->game->db_game['decimal_places']));
			$confirmed_html .= '<div class="col-sm-5 '.$color.'text">+'.$payout_disp.' ';
			if ($payout_disp == '1') $confirmed_html .= $this->game->db_game['coin_name'];
			else $confirmed_html .= $this->game->db_game['coin_name_plural'];
			$confirmed_html .= '</div>';
			
			$confirmed_html .= "</div>\n";
			
			$num_confirmed++;
		}
		
		$q = "SELECT gio.*, gvo.option_id, gvo.name, t.transaction_id, t.tx_hash, t.fee_amount, t.amount AS transaction_amount FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN options gvo ON gio.option_id=gvo.option_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.event_id='".$this->db_event['event_id']."' AND io.create_block_id IS NULL AND t.block_id IS NULL AND k.account_id='".$user_game['account_id']."' ORDER BY gio.colored_amount DESC;";
		$r = $this->game->blockchain->app->run_query($q);
		
		$current_effectiveness_factor = $this->block_id_to_effectiveness_factor($last_block_id+1);
		
		while ($my_vote = $r->fetch()) {
			$color = "yellow";
			
			if ($this->game->db_game['payout_weight'] == "coin_block") {
				$num_votes = $my_vote['ref_coin_blocks'] + ((1+$last_block_id)-$my_vote['ref_block_id'])*$my_vote['colored_amount'];
			}
			else if ($this->game->db_game['payout_weight'] == "coin_round") {
				$num_votes = $my_vote['ref_coin_rounds'] + ($this->game->block_to_round(1+$last_block_id)-$my_vote['ref_round_id'])*$my_vote['colored_amount'];
			}
			else {
				$num_votes = $my_vote['colored_amount'];
			}
			
			$effective_votes = floor($num_votes*$current_effectiveness_factor);
			$net_effective_coins = $effective_votes*$coins_per_vote + $my_vote['destroy_amount']*$my_vote['effectiveness_factor'];
			$option_votes = $this->option_stats($my_vote['option_id']);
			$option_effective_coins = $option_votes['sum']*$coins_per_vote + $option_votes['effective_destroy_sum'];
			$expected_payout = $total_reward*$net_effective_coins/$option_effective_coins;
			
			$unconfirmed_html .= '<div class="row">';
			$unconfirmed_html .= '<div class="col-sm-4 '.$color.'text">'.$my_vote['name'].'</div>';
			$unconfirmed_html .= '<div class="col-sm-3 '.$color.'text">';
			$unconfirmed_html .= '<a target="_blank" href="/explorer/games/'.$this->game->db_game['url_identifier'].'/transactions/'.$my_vote['tx_hash'].'">';
			
			if ($this->game->db_game['inflation'] == "exponential") {
				$coin_stake = $my_vote['destroy_amount'] + $num_votes*$coins_per_vote;
				$odds = round($expected_payout/$coin_stake, 2);
				$unconfirmed_html .= $this->game->blockchain->app->format_bignum($coin_stake/pow(10,$this->game->db_game['decimal_places']), 2)." (x".$odds.")";
			}
			else {
				$unconfirmed_html .=  $this->game->blockchain->app->format_bignum($effective_votes/pow(10,$this->game->db_game['decimal_places']), 2).' votes';
			}
			$unconfirmed_html .= '</a></div>';
			
			$payout_disp = $this->game->blockchain->app->format_bignum($expected_payout/pow(10,$this->game->db_game['decimal_places']));
			$unconfirmed_html .= '<div class="col-sm-5 '.$color.'text">+'.$payout_disp.' ';
			if ($payout_disp == '1') $unconfirmed_html .= $this->game->db_game['coin_name'];
			else $unconfirmed_html .= $this->game->db_game['coin_name_plural'];
			$unconfirmed_html .= '</div>';
			
			$unconfirmed_html .= "</div>\n";
			
			$num_unconfirmed++;
		}
		
		if ($num_unconfirmed + $num_confirmed > 0) {
			$html .= '
			<div class="my_votes_table">
				<div class="row my_votes_header">
					<div class="col-sm-4">'.ucwords($this->db_event['option_name']).'</div>
					<div class="col-sm-3">Stake</div>
					<div class="col-sm-5">Payout</div>
				</div>
				'.$unconfirmed_html.$confirmed_html.'
			</div>';
		}
		
		return $html;
	}
	
	public function initialize_vote_option_details($option_id2rank, $sum_votes, $user_id, $game_instance_id, $game_event_index) {
		$html = "";
		$option_q = "SELECT * FROM options WHERE event_id='".$this->db_event['event_id']."' ORDER BY event_option_index ASC;";
		$option_r = $this->game->blockchain->app->run_query($option_q);
		
		$last_block_id = $this->game->last_block_id();
		$current_round = $this->game->block_to_round($last_block_id+1);
		
		while ($option = $option_r->fetch()) {
			$rank = $option_id2rank[$option['option_id']]+1;
			$confirmed_votes = $option['votes'];
			$unconfirmed_votes = $option['unconfirmed_votes'];
			
			$html .= '
			<div style="display: none;" class="modal fade" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_confirm_'.$option['option_id'].'">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-body">
							<h2>Vote for '.$option['name'].'</h2>
							<div id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_option_details_'.$option['option_id'].'">
								'.$this->game->blockchain->app->vote_option_details($option, $rank, $confirmed_votes, $unconfirmed_votes, $sum_votes).'
							</div>
							<div id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_details_'.$option['option_id'].'"></div>
							<div class="redtext" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_error_'.$option['option_id'].'"></div>
						</div>
						<div class="modal-footer">
							<button class="btn btn-primary" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_confirm_btn_'.$option['option_id'].'" onclick="games['.$game_instance_id.'].add_option_to_vote('.$game_event_index.', '.$option['option_id'].', \''.$option['name'].'\');">Add '.$option['name'].' to my vote</button>
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>';
		}
		return $html;
	}
	
	public function set_event_completed() {
		$q = "UPDATE events SET completion_datetime=NOW() WHERE event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
	}
	
	public function ensure_options() {
		/*$qq = "SELECT * FROM option_group_memberships mem JOIN entities ent ON mem.entity_id=ent.entity_id WHERE mem.option_group_id='".$this->db_event['option_group_id']."' AND NOT EXISTS (SELECT * FROM options op WHERE op.event_id='".$this->db_event['event_id']."' AND op.entity_id=mem.entity_id);";
		$rr = $this->game->blockchain->app->run_query($qq);
		while ($required_membership = $rr->fetch()) {
			$qqq = "INSERT INTO options SET event_id='".$this->db_event['event_id']."', entity_id='".$required_membership['entity_id']."', membership_id='".$required_membership['membership_id']."'";
			if ($required_membership['default_image_id'] > 0) $qqq .= ", image_id='".$required_membership['default_image_id']."'";
			$qqq .= ", name='".$required_membership['name']."', voting_character='".$required_membership['voting_character']."';";
			$rrr = $this->game->blockchain->app->run_query($qqq);
		}*/
	}
	
	public function delete_options() {
		$qq = "DELETE FROM options WHERE event_id='".$this->db_event['event_id']."';";
		$rr = $this->game->blockchain->app->run_query($qq);
	}
	
	public function round_index_to_effectiveness_factor($round_index) {
		if ($this->db_event['vote_effectiveness_function'] == "linear_decrease") {
			$slope = -1*$this->db_event['effectiveness_param1'];
			$frac_complete = floor(pow(10,8)*$round_index/$this->game->db_game['round_length'])/pow(10,8);
			$effectiveness = floor(pow(10,8)*$frac_complete*$slope)/pow(10,8) + 1;
			return $effectiveness;
		}
		else return 1;
	}
	
	public function block_id_to_effectiveness_factor($block_id) {
		return $this->round_index_to_effectiveness_factor($this->game->block_id_to_round_index($block_id));
	}
	
	public function option_stats($option_id) {
		$q = "SELECT coin_score, unconfirmed_coin_score, coin_block_score, unconfirmed_coin_block_score, coin_round_score, unconfirmed_coin_round_score, votes, unconfirmed_votes, destroy_score, unconfirmed_destroy_score, effective_destroy_score, unconfirmed_effective_destroy_score FROM options WHERE option_id='".$option_id."';";
		$r = $this->game->blockchain->app->run_query($q);
		$result = $r->fetch();
		
		$confirmed_votes = $result['votes'];
		$unconfirmed_votes = $result['unconfirmed_votes'];
		$confirmed_score = $result[$this->game->db_game['payout_weight'].'_score'];
		$unconfirmed_score = $result['unconfirmed_'.$this->game->db_game['payout_weight'].'_score'];
		$confirmed_effective_destroy = $result["effective_destroy_score"];
		$unconfirmed_effective_destroy = $result['unconfirmed_effective_destroy_score'];
		$confirmed_destroy = $result['destroy_score'];
		$unconfirmed_destroy = $result['unconfirmed_destroy_score'];
		
		if (!$confirmed_votes) $confirmed_votes = 0;
		if (!$unconfirmed_votes) $unconfirmed_votes = 0;
		
		return array(
			'confirmed'=>$confirmed_votes, 
			'unconfirmed'=>$unconfirmed_votes,
			'sum'=>$confirmed_votes+$unconfirmed_votes,
			'confirmed_score'=>$confirmed_score,
			'unconfirmed_score'=>$unconfirmed_score,
			'score_sum'=>$confirmed_score+$unconfirmed_score,
			'effective_destroy'=>$confirmed_effective_destroy,
			'unconfirmed_effective_destroy'=>$unconfirmed_effective_destroy,
			'effective_destroy_sum'=>$confirmed_effective_destroy+$unconfirmed_effective_destroy,
			'destroy'=>$confirmed_destroy,
			'unconfirmed_destroy'=>$unconfirmed_destroy,
			'destroy_sum'=>$confirmed_destroy+$unconfirmed_destroy
		);
	}
	
	public function event_rewards() {
		list($inflationary_score, $destroy_reward) = $this->event_total_scores();
		
		if ($this->game->db_game['inflation'] == "linear") $inflationary_reward = $this->game->db_game['pos_reward'];
		else {
			$votes_per_coin = $this->game->blockchain->app->votes_per_coin($this->game->db_game);
			
			if ($votes_per_coin == 0) $inflationary_reward = 0;
			else $inflationary_reward = $inflationary_score/$votes_per_coin;
		}
		$total_reward = $destroy_reward + $inflationary_reward;
		
		return array($inflationary_reward, $destroy_reward, $total_reward);
	}
	
	public function event_total_scores() {
		$q = "SELECT SUM(".$this->game->db_game['payout_weight']."_score), SUM(unconfirmed_".$this->game->db_game['payout_weight']."_score), SUM(destroy_score), SUM(unconfirmed_destroy_score) FROM options WHERE event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
		$r = $r->fetch();
		
		$score = $r['SUM('.$this->game->db_game['payout_weight'].'_score)']+$r['SUM(unconfirmed_'.$this->game->db_game['payout_weight'].'_score)'];
		$destroy_score = $r['SUM(destroy_score)']+$r['SUM(unconfirmed_destroy_score)'];
		
		return array($score, $destroy_score);
	}
	
	public function determine_winning_option(&$round_voting_stats_all) {
		$round_voting_stats_all = $this->round_voting_stats_all();
		
		$max_winning_votes = $round_voting_stats_all[1];
		$rankings = $round_voting_stats_all[2];
		$option_id_to_rank = $round_voting_stats_all[3];
		
		$winning_option_id = FALSE;
		$winning_votes = 0;
		$winning_effective_destroy_score = 0;
		
		if ($this->db_event['event_winning_rule'] == "max_below_cap") {
			for ($rank=0; $rank<$this->db_event['num_voting_options']; $rank++) {
				if ($rankings[$rank]['votes'] > $max_winning_votes) {}
				else if (!$winning_option_id) {
					$winning_option_id = $rankings[$rank]['option_id'];
					$winning_votes = $rankings[$rank]['votes'];
					$winning_effective_destroy_score = $rankings[$rank]['effective_destroy_score'];
					$rank = $this->db_event['num_voting_options'];
				}
			}
		}
		else if ($this->db_event['event_winning_rule'] == "game_definition") {
			if ((string) $this->db_event['outcome_index'] !== "") {
				$db_winning_option_q = "SELECT * FROM options WHERE event_id='".$this->db_event['event_id']."' AND event_option_index='".$this->db_event['outcome_index']."';";
				$db_winning_option_r = $this->game->blockchain->app->run_query($db_winning_option_q);
				
				if ($db_winning_option_r->rowCount() > 0) {
					$db_winning_option = $db_winning_option_r->fetch();
					$winning_option_id = $db_winning_option['option_id'];
					$rank_index = $option_id_to_rank[$winning_option_id];
					$winning_votes = $rankings[$rank_index]['votes'];
					$winning_effective_destroy_score = $rankings[$rank_index]['effective_destroy_score'];
				}
			}
		}
		
		return array($winning_option_id, $winning_votes, $winning_effective_destroy_score);
	}
	
	public function set_outcome_from_db($add_payout_transaction) {
		$this->update_option_votes($this->db_event['event_final_block'], false);
		
		$round_voting_stats_all = false;
		list($winning_option, $winning_votes, $winning_effective_destroy_score) = $this->determine_winning_option($round_voting_stats_all);
		
		$sum_votes = $round_voting_stats_all[0];
		$max_sum_votes = $round_voting_stats_all[1];
		$option_id2rank = $round_voting_stats_all[3];
		$round_voting_stats = $round_voting_stats_all[2];
		$event_destroy_score = $round_voting_stats_all[8];
		$event_effective_destroy_score = $round_voting_stats_all[10];
		
		$log_text = "Event ".$this->db_event['event_index']." (".$this->db_event['event_name']."), total votes: ".($sum_votes/(pow(10, 8)))."<br/>\n";
		
		$payout_transaction_id = false;
		
		if ($winning_option !== false) {
			$log_text .= $round_voting_stats[$option_id2rank[$winning_option]]['name']." wins with ".($winning_votes/(pow(10, 8)))." votes.<br/>";
		}
		else $log_text .= "No winner<br/>";
		
		$this->game->blockchain->app->dbh->beginTransaction();
		
		$this->load_db_event($this->db_event['event_id']);
		
		list($inflationary_score, $destroy_reward) = $this->event_total_scores();
		
		$q = "UPDATE events SET sum_score='".$inflationary_score."'";
		if ($winning_option) $q .= ", winning_option_id='".$winning_option."'";
		$q .= ", sum_votes='".$sum_votes."', winning_votes='".$winning_votes."', winning_effective_destroy_score='".$winning_effective_destroy_score."', destroy_score='".$event_destroy_score."', effective_destroy_score='".$event_effective_destroy_score."' WHERE event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
		
		if ($winning_option !== false && $add_payout_transaction) {
			$payout_response = $this->new_payout_transaction($winning_option, $winning_votes, $winning_effective_destroy_score);
			
			$log_text .= "Payout response: ".$payout_response;
			$log_text .= "<br/>\n";
		}
		
		$this->set_event_completed();
		
		$this->game->blockchain->app->dbh->commit();
		return $log_text;
	}
	
	public function process_option_blocks(&$game_block, $events_in_round, $round_first_event_index) {
		$q = "DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id WHERE o.event_id='".$this->db_event['event_id']."' AND ob.block_height='".$game_block['block_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
		
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->game->db_game['blockchain_id']."' AND block_id='".$game_block['block_id']."';";
		$block = $this->game->blockchain->app->run_query($q)->fetch();
		$random_data = hash("sha256", $block['block_hash']);
		
		if ($this->db_event['option_block_rule'] == "football_match") {
			$rand_chars_per_option = 2;
			$rand_chars_per_event = $rand_chars_per_option*2;
			$event_offset = $this->db_event['event_index'] - $round_first_event_index;
			
			$total_rand_chars_needed = $rand_chars_per_event*$events_in_round;
			$last_rand_hash = $random_data;
			
			while (strlen($random_data) < $total_rand_chars_needed) {
				$last_rand_hash = hash("sha256", $last_rand_hash);
				$random_data .= $last_rand_hash;
			}
			
			$event_blocks = $this->db_event['event_final_block'] - $this->db_event['event_starting_block'] + 1;
			$team_avg_goals_per_game = 1.35;
			
			$rand_i = 0;
			$q = "SELECT * FROM options WHERE event_id='".$this->db_event['event_id']."' ORDER BY option_index ASC;";
			$r = $this->game->blockchain->app->run_query($q);
			
			while ($db_option = $r->fetch()) {
				$score_prob = min(1, $team_avg_goals_per_game/$event_blocks);
				$rand_offset_start = $rand_chars_per_event*$event_offset + ($rand_i*$rand_chars_per_option);
				$rand_chars = substr($random_data, $rand_offset_start, $rand_chars_per_option);
				$rand_prob = hexdec($rand_chars)/pow(2, 4*strlen($rand_chars));
				
				if ($rand_prob <= $score_prob) $score = 1;
				else $score = 0;
				
				$qq = "INSERT INTO option_blocks SET rand_chars='".$rand_chars."', rand_prob=".$rand_prob.", score=".$score.", option_id='".$db_option['option_id']."', block_height='".$game_block['block_id']."';";
				$rr = $this->game->blockchain->app->run_query($qq);
				
				if ($score > 0) {
					$qq = "UPDATE options SET option_block_score=option_block_score+".$score." WHERE option_id='".$db_option['option_id']."';";
					$rr = $this->game->blockchain->app->run_query($qq);
				}
				
				$rand_i++;
			}
		}
	}
	
	public function update_option_votes($last_block_id, $round_id) {
		$effectiveness_factor = $this->block_id_to_effectiveness_factor($last_block_id+1);
		
		$q = "UPDATE options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, destroy_score=0, unconfirmed_destroy_score=0, votes=0, unconfirmed_votes=0, effective_destroy_score=0, unconfirmed_effective_destroy_score=0 WHERE event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
		
		$q = "UPDATE options op INNER JOIN (
			SELECT option_id, SUM(colored_amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes, SUM(destroy_amount) sum_destroyed, SUM(effective_destroy_amount) sum_effective_destroyed FROM transaction_game_ios 
			WHERE event_id='".$this->db_event['event_id']."' AND create_round_id IS NOT NULL AND colored_amount > 0
			GROUP BY option_id
		) i ON op.option_id=i.option_id SET op.coin_score=i.sum_amount, op.coin_block_score=i.sum_cbd, op.coin_round_score=i.sum_crd, op.votes=i.sum_votes, op.destroy_score=i.sum_destroyed, op.effective_destroy_score=i.sum_effective_destroyed WHERE op.event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
		
		if ($this->game->db_game['payout_weight'] == "coin") {
			$q = "UPDATE options op INNER JOIN (
				SELECT option_id, SUM(colored_amount) sum_amount, SUM(colored_amount)*".$effectiveness_factor." sum_votes, SUM(destroy_amount) sum_destroyed FROM transaction_game_ios 
				WHERE event_id='".$this->db_event['event_id']."' AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_score=i.sum_amount, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed WHERE op.event_id='".$this->db_event['event_id']."';";
			$r = $this->game->blockchain->app->run_query($q);
		}
		else if ($this->game->db_game['payout_weight'] == "coin_block") {
			$q = "UPDATE options op INNER JOIN (
				SELECT option_id, SUM(ref_coin_blocks+(".($last_block_id+1)."-ref_block_id)*colored_amount) sum_cbd, SUM(ref_coin_blocks+(".($last_block_id+1)."-ref_block_id)*colored_amount)*".$effectiveness_factor." sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*".$effectiveness_factor." unconfirmed_sum_destroyed FROM transaction_game_ios 
				WHERE event_id='".$this->db_event['event_id']."' AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_block_score=i.sum_cbd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id='".$this->db_event['event_id']."';";
			$r = $this->game->blockchain->app->run_query($q);
		}
		else {
			if (empty($round_id)) $round_id = $this->game->block_to_round($last_block_id+1);
			$q = "UPDATE options op INNER JOIN (
				SELECT option_id, SUM(ref_coin_rounds+(".$round_id."-ref_round_id)*colored_amount) sum_crd, SUM(ref_coin_rounds+(".$round_id."-ref_round_id)*colored_amount)*".$effectiveness_factor." sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*".$effectiveness_factor." unconfirmed_sum_destroyed FROM transaction_game_ios
				WHERE event_id='".$this->db_event['event_id']."' AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_round_score=i.sum_crd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id='".$this->db_event['event_id']."';";
			$r = $this->game->blockchain->app->run_query($q);
		}
		
		$q = "UPDATE events e JOIN (
				SELECT SUM(".$this->game->db_game['payout_weight']."_score) sum_score, SUM(destroy_score) destroy_score, SUM(votes) sum_votes, SUM(effective_destroy_score) effective_destroy_score FROM options WHERE event_id='".$this->db_event['event_id']."'
			) op SET e.sum_score=op.sum_score, e.destroy_score=op.destroy_score, e.sum_votes=op.sum_votes, e.effective_destroy_score=op.effective_destroy_score WHERE e.event_id='".$this->db_event['event_id']."';";
		$r = $this->game->blockchain->app->run_query($q);
	}
}
?>
