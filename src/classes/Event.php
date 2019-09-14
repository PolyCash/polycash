<?php
class Event {
	public $db_event;
	public $game;
	
	public function __construct(&$game, $db_event, $event_id) {
		$this->game = $game;
		if ($db_event) {
			$event_id = $db_event['event_id'];
			$this->db_event = $db_event;
		}
		else $this->load_db_event($event_id);
	}
	
	public function load_db_event($event_id) {
		$this->db_event = $this->game->blockchain->app->run_query("SELECT *, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.event_id=:event_id;", ['event_id'=>$event_id])->fetch();
		if (!$this->db_event) die("Error, could not load event #".$event_id);
	}
	
	public function round_voting_stats() {
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		return $this->game->blockchain->app->run_query("SELECT * FROM options op LEFT JOIN images i ON op.image_id=i.image_id LEFT JOIN entities e ON op.entity_id=e.entity_id WHERE op.event_id=:event_id ORDER BY ((op.votes+op.unconfirmed_votes)*:coins_per_vote)+(op.effective_destroy_score+op.unconfirmed_effective_destroy_score) DESC, op.option_id ASC;", [
			'event_id' => $this->db_event['event_id'],
			'coins_per_vote' => $coins_per_vote
		]);
	}

	public function total_votes_in_round($include_unconfirmed) {
		$confirmed_votes = (int)($this->game->blockchain->app->run_query("SELECT SUM(votes) FROM transaction_game_ios WHERE event_id=:event_id AND create_round_id IS NOT NULL;", ['event_id'=>$this->db_event['event_id']])->fetch(PDO::FETCH_NUM)[0]);
		
		if ($include_unconfirmed) {
			$unconfirmed_votes = (int)($this->game->blockchain->app->run_query("SELECT SUM(unconfirmed_votes) FROM options WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']])->fetch(PDO::FETCH_NUM)[0]);
		}
		else $unconfirmed_votes = 0;
		
		return [
			'confirmed' => $confirmed_votes,
			'unconfirmed' => $unconfirmed_votes,
			'sum' => $confirmed_votes+$unconfirmed_votes
		];
	}

	public function round_voting_stats_all() {
		$round_voting_stats = $this->round_voting_stats();
		$stats_all = false;
		$counter = 0;
		$option_id_csv = "";
		$option_id_to_rank = [];
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
	
	public function event_html($user, $show_intro_text, $clickable, $game_instance_id, $game_event_index) {
		$score_field = $this->game->db_game['payout_weight']."_score";
		
		$last_block_id = $this->game->blockchain->last_block_id();
		$max_block_id = min($this->db_event['event_final_block'], $last_block_id);
		
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		$display_mode = "slim";
		
		$html = '';
		
		$option_max_width = $this->db_event['option_max_width'];
		if ($display_mode == "slim") $option_max_width = min(100, $option_max_width);
		
		$sq_px_per_pct_point = pow($option_max_width, 2)/100;
		$min_px_diam = 20;
		
		$round_stats_all = false;
		$winner = false;
		
		list($winning_option_id, $winning_votes, $winning_effective_destroy_score) = $this->determine_winning_option($round_stats_all);
		
		if ((string)$this->db_event['outcome_index'] !== "") {
			$expected_winner = $this->game->blockchain->app->fetch_option_by_event_option_index($this->db_event['event_id'], $this->db_event['outcome_index']);
		}
		else $expected_winner = false;
		
		$game_defined_winner = false;
		$gde = $this->game->blockchain->app->fetch_game_defined_event_by_index($this->game->db_game['game_id'], $this->db_event['event_index']);
		
		if ($gde) {
			if ((string)$gde['outcome_index'] !== "") {
				$game_defined_winner = $this->game->blockchain->app->fetch_option_by_event_option_index($this->db_event['event_id'], $gde['outcome_index']);
			}
		}
		
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
			$option_ids = [];
			$scores = [];
			
			if ($display_mode == "default") {
				$score_disp .= '<div class="event_score_box">';
				$score_disp .= "Current Scores:<br/>\n";
				for ($i=0; $i<count($round_stats); $i++) {
					$score_disp .= '<div class="row"><div class="col-sm-6 boldtext">'.$round_stats[$i]['entity_name'].'</div><div class="col-sm-6">'.$round_stats[$i]['option_block_score'].'</div></div>'."\n";
				}
				$score_disp .= "</div>\n";
			}
			else {
				$option_block_info = $this->game->blockchain->app->run_query("SELECT *, SUM(ob.score) AS option_block_score FROM options o LEFT JOIN option_blocks ob ON o.option_id=ob.option_id LEFT JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id=:event_id GROUP BY o.option_id ORDER BY o.option_index ASC;", [
					'event_id' => $this->db_event['event_id']
				]);
				
				$score_disp = "";
				$first_option = false;
				$second_option = false;
				
				while ($option = $option_block_info->fetch()) {
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
			
			$blocks_left = $this->db_event['event_final_block'] - $max_block_id;
			
			$html .= '<font style="font-size: 88%">';
			
			if (!empty($this->db_event['event_final_time']) && $blocks_left > 0) {
				$sec_left = strtotime($this->db_event['event_final_time'])-time();
				if ($sec_left <= 0) {
					$html .= '<font class="redtext">Expired '.$this->game->blockchain->app->format_seconds(-1*$sec_left).' ago</font><br/>';
				}
			}
			
			if ($this->db_event['event_starting_block'] > $last_block_id) {
				$blocks_to_start = $this->db_event['event_starting_block'] - $last_block_id;
				$sec_to_start = $this->game->blockchain->seconds_per_block('average')*$blocks_to_start;
				$html .= "Betting starts in ".number_format($blocks_to_start)." blocks (".$this->game->blockchain->app->format_seconds($sec_to_start).")<br/>\n";
			}
			else if ($blocks_left > 0) {
				$sec_left = $this->game->blockchain->seconds_per_block('average')*$blocks_left;
				$html .= $this->game->blockchain->app->format_bignum($blocks_left)." betting blocks left";
				$html .= " (".$this->game->blockchain->app->format_seconds($sec_left).")<br/>\n";
			}
			
			if ($last_block_id < $this->db_event['event_payout_block']) {
				$payout_blocks_left = $this->db_event['event_payout_block'] - $last_block_id;
				
				if (!empty($this->db_event['event_payout_time'])) {
					$html .= "Pays out at ".$this->db_event['event_payout_time']." UTC (";
					$html .= $this->game->blockchain->app->format_seconds(strtotime($this->db_event['event_payout_time'])-time());
					$html .= ")";
				}
				else {
					$html .= "Pays out in ";
					$html .= $this->game->blockchain->app->format_seconds($this->game->blockchain->seconds_per_block('average')*$payout_blocks_left);
				}
			}
			else {
				$payout_block = $this->game->blockchain->fetch_block_by_id($this->db_event['event_payout_block']);
				$html .= "Paid ".$this->game->blockchain->app->format_seconds(time()-$payout_block['time_mined'])." ago<br/>".date("Y-m-d H:m:s", $payout_block['time_mined'])." UTC";
			}
			
			$html .= "<br/>".$this->game->blockchain->app->format_percentage((1-$this->db_event['payout_rate'])*100)."% fee\n";
			
			$html .= "</font>\n";
			
			$html .= '</div></p>';
		}
		$html .= "<strong><a style=\"color: #000; text-decoration: underline; display: inline-block;\" target=\"_blank\" href=\"/explorer/games/".$this->game->db_game['url_identifier']."/events/".$this->db_event['event_index']."\">".$this->db_event['event_name']."</a></strong> ";
		$html .= " &nbsp;&nbsp; ";
		$html .= $score_disp;
		$html .= "</p>\n";
		
		if (!empty($this->db_event['sport_name']) || !empty($this->db_event['league_name'])) {
			$html .= "<p>".$this->db_event['sport_name']." &nbsp;&nbsp; ".$this->db_event['league_name']."</p>\n";
		}
		
		if ($this->db_event['outcome_index'] == "-1") {
			$html .= "<p class=\"redtext\">This event has been canceled</p>\n";
		}
		else if ($expected_winner || $game_defined_winner) {
			$html .= "<p class=\"greentext\">";
			$html .= "Winner: ";
			if ($expected_winner) $html .= $expected_winner['name'];
			if ($expected_winner && $game_defined_winner && $expected_winner['option_id'] != $game_defined_winner['option_id'] || ($expected_winner && !$game_defined_winner)) $html .=" &rarr; ";
			if ($game_defined_winner && (!$expected_winner || ($expected_winner && $expected_winner['option_id'] != $game_defined_winner['option_id']))) $html .= $game_defined_winner['name'];
			if ($expected_winner && !$game_defined_winner) $html .= "Unset";
			$html .= "</p>\n";
		}
		
		if ($this->game->db_game['inflation'] == "exponential") {
			$confirmed_coins = $destroy_score + $confirmed_score*$coins_per_vote;
			$unconfirmed_coins = $total_reward - $confirmed_coins;
			
			if ($this->db_event['payout_rule'] == "binary") {
				$html .= "<p>".$this->game->blockchain->app->format_bignum($confirmed_coins/pow(10,$this->game->db_game['decimal_places']))." ".$this->game->db_game['coin_name_plural']." in confirmed bets";
				if ($unconfirmed_coins > 0) $html .= ", ".$this->game->blockchain->app->format_bignum($unconfirmed_coins/pow(10,$this->game->db_game['decimal_places']))." unconfirmed";
				$html .= "</p>\n";
			}
			else {
				$two_sided_contract_price = $this->db_event['track_max_price']-$this->db_event['track_min_price'];
				$confirmed_equivalent_contracts = $confirmed_coins/$two_sided_contract_price/pow(10,$this->game->db_game['decimal_places']);
				$unconfirmed_equivalent_contracts = $unconfirmed_coins/$two_sided_contract_price/pow(10,$this->game->db_game['decimal_places']);
				
				$html .= "<p>".$this->game->blockchain->app->format_bignum($confirmed_equivalent_contracts)." ".$this->db_event['track_name_short']." issued at $".$this->game->blockchain->app->format_bignum($two_sided_contract_price)." per contract";
				if ($unconfirmed_coins > 0) $html .= " +&nbsp;".$this->game->blockchain->app->format_bignum($unconfirmed_equivalent_contracts)."&nbsp;unconfirmed&nbsp;".$this->db_event['track_name_short']."<br/>\n";
				$html .= " (".$this->game->blockchain->app->format_bignum(($confirmed_coins+$unconfirmed_coins)/pow(10,$this->game->db_game['decimal_places']))."&nbsp;".$this->game->db_game['coin_name_plural'].")";
				$html .= "</p>\n";
			}
		}
		
		if ($this->game->db_game['module'] == "CryptoDuels") {
			$btc_currency = $this->game->blockchain->app->get_currency_by_abbreviation("BTC");
			$event_starting_block = $this->game->blockchain->fetch_block_by_id($this->db_event['event_starting_block']);
			$event_final_block = $this->game->blockchain->fetch_block_by_id($this->db_event['event_final_block']);
			if ($event_final_block && !empty($event_final_block['time_mined'])) $event_to_time = $event_final_block['time_mined'];
			else $event_to_time = time();
		}
		
		if ($this->db_event['payout_rule'] == "linear") {
			$track_entity = $this->game->blockchain->app->fetch_entity_by_id($round_stats[0]['entity_id']);
			
			$track_price_info = $this->game->blockchain->app->exchange_rate_between_currencies(1, $track_entity['currency_id'], time(), 6);
			$track_price_usd = max($this->db_event['track_min_price'], min($this->db_event['track_max_price'], $track_price_info['exchange_rate']));
			
			// For tracked asset events, the buy position is always the first option (min option ID)
			$min_option_id = min(array_keys($option_id_to_rank));
			$min_option_index = $option_id_to_rank[$min_option_id];
			
			$buy_pos_votes = $round_stats[$min_option_index]['votes'] + $round_stats[$min_option_index]['unconfirmed_votes'];
			$buy_pos_effective_coins = $buy_pos_votes*$coins_per_vote + $round_stats[$min_option_index]['effective_destroy_score'] + $round_stats[$min_option_index]['unconfirmed_effective_destroy_score'];
			
			if ($last_block_id < $this->db_event['event_payout_block']) {
				$html .= "Market price: &nbsp; $".$this->game->blockchain->app->round_to($track_price_usd, 2, 4, true);
				if (time()-$track_price_info['time'] >= 60*30) $html .= ' &nbsp; <font class="redtext">'.$this->game->blockchain->app->format_seconds(time()-$track_price_info['time'])." ago</font>";
				$html .= "<br/>\n";
			}
			
			$buy_pos_payout_frac = false;
			$our_buy_price = false;
			
			if ($event_effective_coins > 0) {
				$buy_pos_payout_frac = $buy_pos_effective_coins/$event_effective_coins;
				$our_buy_price = $this->db_event['track_min_price'] + $buy_pos_payout_frac*($this->db_event['track_max_price']-$this->db_event['track_min_price']);
				
				if ($last_block_id < $this->db_event['event_final_block']) {
					$html .= "Buy here for: &nbsp; $".$this->game->blockchain->app->round_to($our_buy_price, 2, 4, true)."<br/>\n";
				}
				else {
					$html .= "Bought at: &nbsp; $".$this->game->blockchain->app->round_to($our_buy_price, 2, 4, true)."<br/>\n";
				}
			}
			
			if ((string)$this->db_event['track_payout_price'] != "") {
				if ($our_buy_price) $pct_gain = 100*($this->db_event['track_payout_price']/$our_buy_price-1);
				else $pct_gain = 0;
				$html .= "Paid out at: &nbsp; $".$this->game->blockchain->app->format_bignum($this->db_event['track_payout_price'])."<br/>\n";
			}
			else if ($our_buy_price > 0) {
				$pct_gain = 100*($track_price_usd/$our_buy_price-1);
			}
			else $pct_gain = 0;
			
			$pct_gain = round($pct_gain, 2);
			
			$html .= $this-> db_event['track_name_short'];
			
			if ($pct_gain >= 0) {
				$html .= ' up <font class="greentext">'.$pct_gain."%</font>\n";
			}
			else {
				$html .= ' down <font class="redtext">'.abs($pct_gain)."%</font>\n";
			}
			$html .= "<br/>\n";
		}
		
		for ($i=0; $i<count($round_stats); $i++) {
			$option_votes = $round_stats[$i]['votes'] + $round_stats[$i]['unconfirmed_votes'];
			$option_effective_coins = $option_votes*$coins_per_vote + $round_stats[$i]['effective_destroy_score'] + $round_stats[$i]['unconfirmed_effective_destroy_score'];
			
			if ($this->db_event['event_winning_rule'] == "max_below_cap" && !$winning_option_id && $option_votes <= $max_sum_votes && $option_votes > 0) $winning_option_id = $round_stats[$i]['option_id'];
			
			if ($option_effective_coins > 0) {
				$pct_votes = 100*(floor(1000*$option_effective_coins/$event_effective_coins)/1000);
				$odds = $this->db_event['payout_rate']*$event_effective_coins/$option_effective_coins;
				$odds_disp = "x".$this->game->blockchain->app->round_to($odds, 2, 4, true);
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
				$onclick_html = 'if (!thisPageManager.transaction_in_progress) {thisPageManager.add_utxo_to_vote(utxo_spend_offset); games['.$game_instance_id.'].add_option_to_vote('.$game_event_index.', '.$round_stats[$i]['option_id'].'); thisPageManager.confirm_compose_bets(); setTimeout(function() {games[0].show_next_event()}, 1200);}';
				$html .= '<img id="option'.$round_stats[$i]['option_id'].'_image" src="" style="cursor: pointer; max-width: 400px; max-height: 400px; border: 1px solid black; margin-bottom: 5px;" onclick="'.$onclick_html.'" />';
			}
			else $onclick_html = 'games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');';
			
			if ($this->game->db_game['module'] == "CryptoDuels") {
				$db_currency = $this->game->blockchain->app->fetch_currency_by_name($round_stats[$i]['name']);
				$initial_price = $this->game->blockchain->app->currency_price_after_time($db_currency['currency_id'], $btc_currency['currency_id'], $event_starting_block['time_mined'], $event_to_time);
				
				if ($round_stats[$i]['name'] == "Bitcoin") {
					$final_price = 0;
					$final_performance = 1;
				}
				else {
					$final_price = $this->game->blockchain->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $event_to_time);
					if (empty($initial_price['price'])) $final_performance = 1;
					else $final_performance = $final_price['price']/$initial_price['price'];
				}
			}
			
			$html .= '
				<div class="vote_option_label';
				if ($this->db_event['event_winning_rule'] == "max_below_cap") {
					if ($option_votes > $max_sum_votes) $html .=  " redtext";
					else if ($winning_option_id == $round_stats[$i]['option_id']) $html .=  " greentext";
				}
				$html .= '"';
				if ($clickable) $html .= ' style="cursor: pointer;" onclick="'.$onclick_html.'"';
				$html .= '>'.$round_stats[$i]['name'];
				if ($this->db_event['payout_rule'] == "binary") {
					if (!empty($odds_disp)) $html .= ' &nbsp; '.$odds_disp;
				}
				else {
					if ($our_buy_price) {
						if ($round_stats[$i]['event_option_index'] == 0) $position_price = $our_buy_price-$this->db_event['track_min_price'];
						else $position_price = $this->db_event['track_max_price']-$our_buy_price;
						$html .= " &nbsp; <font class=\"greentext\">$".number_format($position_price, 2)."</font>\n";
					}
				}
				$html .= ' &nbsp; ('.$pct_votes.'%)';
				
				if ($this->game->db_game['module'] == "CryptoDuels") {
					$html .= '<br/>';
					if ($final_performance >= 1) $html .= '<font class="greentext">Up '.round(($final_performance-1)*100, 3).'%</font>';
					else $html .= '<font class="redtext">Down '.round((1-$final_performance)*100, 3).'%</font>';
				}
				$html .= '
				</div>';
			if ($this->game->db_game['view_mode'] == "simple") {}
			else {
				$html .= '
				<div class="stage vote_option_box_holder" style="height: '.$holder_width.'px; width: '.$holder_width.'px;">';
				if ($show_boundbox) {
					$html .= '<div onclick="games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');" class="vote_option_boundbox" style="cursor: pointer; height: '.$boundbox_diam.'px; width: '.$boundbox_diam.'px;';
					if ($holder_width != $boundbox_diam) $html .= 'left: '.(($holder_width-$boundbox_diam)/2).'px; top: '.(($holder_width-$boundbox_diam)/2).'px;';
					$html .= '"></div>';
				}
				$html .= '
					<div class="ball vote_option_box" style="width: '.$box_diam.'px; height: '.$box_diam.'px;';
					if ($holder_width != $box_diam) $html .= 'left: '.(($holder_width-$box_diam)/2).'px; top: '.(($holder_width-$box_diam)/2).'px;';
					
					if ($round_stats[$i]['image_id'] > 0) $bg_im_url = $this->game->blockchain->app->image_url($round_stats[$i]);
					else if (!empty($round_stats[$i]['content_url'])) $bg_im_url = $round_stats[$i]['content_url'];
					else $bg_im_url = "";
					if ($bg_im_url != "") $html .= 'background-image: url('.$this->game->blockchain->app->quote_escape($bg_im_url).');';
					
					if ($clickable) $html .= 'cursor: pointer;';
					if ($this->db_event['event_winning_rule'] == "max_below_cap" && $option_votes > $max_sum_votes) $html .= 'opacity: 0.5;';
					$html .= '" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_option_'.$i.'"';
					if ($clickable) $html .= ' onclick="games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');"';
					$html .= '>
						<input type="hidden" id="game'.$game_instance_id.'_event'.$game_event_index.'_option_id2rank_'.$round_stats[$i]['option_id'].'" value="'.$i.'" />
						<input type="hidden" id="game'.$game_instance_id.'_event'.$game_event_index.'_rank2option_id_'.$i.'" value="'.$round_stats[$i]['option_id'].'" />
					</div>
				</div>';
			}
			$html .= '
			</div>';
		}
		
		return $html;
	}
	
	public function set_track_payout_price() {
		$db_block = $this->game->blockchain->fetch_block_by_id($this->db_event['event_payout_block']);
		if ($db_block) $ref_time = $db_block['time_mined'];
		else $ref_time = time();
		
		$track_entity = $this->game->blockchain->app->fetch_entity_by_id($this->db_event['track_entity_id']);
		$track_price_info = $this->game->blockchain->app->exchange_rate_between_currencies(1, $track_entity['currency_id'], $ref_time, 6);
		
		$track_price_usd = $this->game->blockchain->app->to_significant_digits($track_price_info['exchange_rate'], 8);
		
		$this->game->blockchain->app->run_query("UPDATE events SET track_payout_price=:track_payout_price WHERE event_id=:event_id;", [
			'track_payout_price' => $track_price_usd,
			'event_id' => $this->db_event['event_id']
		]);
		$this->game->blockchain->app->run_query("UPDATE game_defined_events SET track_payout_price=:track_payout_price WHERE game_id=:game_id AND event_index=:event_index;", [
			'track_payout_price' => $track_price_usd,
			'game_id' => $this->game->db_game['game_id'],
			'event_index' => $this->db_event['event_index']
		]);
		
		$this->db_event['track_payout_price'] = $track_price_usd;
	}
	
	public function new_refund_payout() {
		$log_text = "";
		
		if ($this->game->db_game['payout_weight'] == "coin") $score_field = "colored_amount";
		else $score_field = $this->game->db_game['payout_weight']."s_destroyed";
		
		$all_bets = $this->game->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.event_id=:event_id AND gio.is_coinbase=0;", ['event_id'=>$this->db_event['event_id']]);
		$log_text .= "Refunding ".$all_bets->rowCount()." bets.<br/>\n";
		
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		while ($bet = $all_bets->fetch()) {
			$bet_amount = floor($bet[$score_field]*$coins_per_vote) + $bet['destroy_amount'];
			
			$this->game->blockchain->app->run_query("UPDATE transaction_game_ios SET colored_amount=FLOOR(".$bet_amount."*contract_parts/".$bet['contract_parts'].") WHERE parent_io_id=:parent_io_id;", [
				'parent_io_id' => $bet['game_io_id']
			]);
		}
		
		return $log_text;
	}
	
	public function new_linear_payout() {
		$log_text = "";
		
		if (empty($this->db_event['track_payout_price'])) $this->set_track_payout_price();
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		$contract_size = $this->db_event['track_max_price']-$this->db_event['track_min_price'];
		if ($this->db_event['track_payout_price'] > $this->db_event['track_max_price']) $long_contract_price = $contract_size;
		else if ($this->db_event['track_payout_price'] < $this->db_event['track_min_price']) $long_contract_price = 0;
		else $long_contract_price = $this->db_event['track_payout_price']-$this->db_event['track_min_price'];
		
		$long_payout_frac = $long_contract_price/$contract_size;
		$long_payout_total = floor($total_reward*$long_payout_frac);
		$short_payout_total = $total_reward-$long_payout_total;
		
		$options_by_event = $this->game->blockchain->app->fetch_options_by_event($this->db_event['event_id']);
		
		while ($option = $options_by_event->fetch()) {
			if ($option['event_option_index'] == 0) $option_payout_total = $long_payout_total;
			else $option_payout_total = $short_payout_total;
			
			$option_effective_coins = $option['effective_destroy_score'] + $option['votes']*$coins_per_vote;
			
			if ($option_effective_coins > 0) {
				$bets_by_option = $this->game->blockchain->app->run_query("SELECT p.*, gio.game_io_id AS game_io_id FROM transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id WHERE gio.option_id=:option_id AND gio.is_coinbase=1;", ['option_id'=>$option['option_id']]);
				
				while ($payout_io = $bets_by_option->fetch()) {
					$this_effective_coins = $payout_io['votes']*$coins_per_vote + $payout_io['effective_destroy_amount'];
					$this_payout_amount = floor($this->db_event['payout_rate']*$option_payout_total*$this_effective_coins/$option_effective_coins);
					$weighted_payout = $this_payout_amount*$payout_io['contract_parts'];
					
					$this->game->blockchain->app->run_query("UPDATE transaction_game_ios SET colored_amount=FLOOR(".$weighted_payout."*contract_parts) WHERE parent_io_id=:parent_io_id AND resolved_before_spent=1;", [
						'parent_io_id' => $payout_io['parent_io_id']
					]);
				}
			}
		}
		
		return $log_text;
	}
	
	public function new_binary_payout($winning_option, $winning_votes, $winning_effective_destroy_score) {
		$log_text = "";
		
		if ($this->game->db_game['payout_weight'] == "coin") $score_field = "colored_amount";
		else $score_field = $this->game->db_game['payout_weight']."s_destroyed";
		
		// Loop through the correctly voted UTXOs
		$winning_bets = $this->game->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.option_id=:winning_option AND gio.is_coinbase=0;", [
			'winning_option' => $winning_option
		]);
		$log_text .= "Paying out ".$winning_bets->rowCount()." correct votes.<br/>\n";
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		$winning_effective_coins = floor($winning_votes*$coins_per_vote) + $winning_effective_destroy_score;
		
		while ($input = $winning_bets->fetch()) {
			$this_input_effective_coins = floor($input['votes']*$coins_per_vote) + $input['effective_destroy_amount'];
			$this_input_payout_amount = floor($total_reward*$this->db_event['payout_rate']*($this_input_effective_coins/$winning_effective_coins));
			$weighted_payout = $this_input_payout_amount/$input['contract_parts'];
			
			$this->game->blockchain->app->run_query("UPDATE transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id SET gio.colored_amount=FLOOR(".$weighted_payout."*gio.contract_parts) WHERE gio.parent_io_id=:game_io_id AND gio.resolved_before_spent=1;", [
				'game_io_id' => $input['game_io_id']
			]);
		}
		
		return $log_text;
	}
	
	public function my_votes_table($round_id, &$user_game) {
		$last_block_id = $this->game->blockchain->last_block_id();
		$html = "";
		$confirmed_html = "";
		$unconfirmed_html = "";
		
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		$unconfirmed_html = $this->my_votes_html("yellow", $coins_per_vote, $user_game, $last_block_id);
		$confirmed_html = $this->my_votes_html("green", $coins_per_vote, $user_game, $last_block_id);
		
		if (strlen($unconfirmed_html.$confirmed_html) > 0) {
			$html .= '
			<div class="my_votes_table">
				<div class="row my_votes_header">
					<div class="col-sm-6">'.ucwords($this->db_event['option_name']).'</div>
					<div class="col-sm-6">Payout</div>
				</div>
				'.$unconfirmed_html.$confirmed_html.'
			</div>';
		}
		
		return $html;
	}
	
	public function my_votes_html($color, &$coins_per_vote, &$user_game, &$last_block_id) {
		$html = "";
		
		$my_votes_q = "SELECT p.*, p.contract_parts AS total_contract_parts, gio.contract_parts, gio.is_coinbase AS is_coinbase, gio.game_out_index AS game_out_index, op.*, ev.*, p.votes, op.votes AS option_votes, op.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, t.transaction_id, t.tx_hash, t.fee_amount, io.spend_status FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN options op ON gio.option_id=op.option_id JOIN events ev ON op.event_id=ev.event_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.event_id=:event_id AND k.account_id=:account_id AND gio.resolved_before_spent=1";
		if ($color == "green") $my_votes_q .= " AND io.create_block_id IS NOT NULL";
		else $my_votes_q .= " AND io.create_block_id IS NULL";
		$my_votes_q .= " ORDER BY gio.game_io_id ASC;";
		$my_votes = $this->game->blockchain->app->run_query($my_votes_q, [
			'event_id' => $this->db_event['event_id'],
			'account_id' => $user_game['account_id']
		]);
		
		while ($my_vote = $my_votes->fetch()) {
			$unconfirmed_votes = 0;
			$temp_html = "";
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees) = $this->game->get_payout_info($my_vote, $coins_per_vote, $last_block_id, $temp_html);
			
			$html .= '<div class="row" style="padding: 5px;">'.$temp_html;
			
			$coin_stake = (($my_vote['contract_parts']/$my_vote['total_contract_parts'])*$my_vote['destroy_amount']) + $inflation_stake;
			
			if ($this->db_event['payout_rule'] == "binary") {
				$html .= '<div class="col-sm-6">';
				$payout_disp = $this->game->blockchain->app->format_bignum($max_payout/pow(10,$this->game->db_game['decimal_places']));
				$html .= '<font class="'.$color.'text">';
				$html .= '<a target="_blank" href="/explorer/games/'.$this->game->db_game['url_identifier'].'/utxo/'.$my_vote['tx_hash'].'/'.$my_vote['game_out_index'].'">';
				$html .= "Staked ".$this->game->blockchain->app->format_bignum($coin_stake/pow(10,$this->game->db_game['decimal_places']))."</a> ";
				$html .= " on ".$my_vote['name'];
				$html .= "</font> ";
				$html .= '</div>';
				
				$html .= '<div class="col-sm-6">';
				$html .= '<font class="'.$color.'text">';
				$html .= '+'.$payout_disp.' '.$this->game->db_game['coin_name_plural']." &nbsp; (x".$this->game->blockchain->app->format_bignum($odds).")</font>\n";
				$html .= '</div>';
			}
			else {
				$html .= '<div class="col-sm-6">';
				$html .= '<font class="'.$color.'text">'.$my_vote['name']."</font><br/>\n";
				$html .= '<a target="_blank" href="/explorer/games/'.$this->game->db_game['url_identifier'].'/utxo/'.$my_vote['tx_hash'].'/'.$my_vote['game_out_index'].'">';
				$html .= "Paid ".$this->game->blockchain->app->format_bignum($coin_stake/pow(10,$this->game->db_game['decimal_places']));
				$html .= ' '.$this->game->db_game['coin_name_plural']."</a>";
				$html .= ' @ $'.$this->game->blockchain->app->format_bignum($asset_price_usd);
				$html .= '<br/>'.$this->game->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->game->db_game['decimal_places'])).' '.$this->db_event['track_name_short'].' @ $'.$this->game->blockchain->app->format_bignum($bought_price_usd);
				if ($bought_leverage != 1) $html .= ' &nbsp; ('.$this->game->blockchain->app->format_bignum($bought_leverage).'X)';
				$html .= '</div>';
				
				$html .= '<div class="col-sm-6">';
				$html .= '<font class="'.$color.'text">'.$this->game->blockchain->app->format_bignum(($fair_io_value-$payout_fees)/pow(10,$this->game->db_game['decimal_places']))." ".$this->game->db_game['coin_name_plural']."</font>\n";
				$html .= "@ ";
				$html .= "$".$this->game->blockchain->app->format_bignum($track_pay_price);
				if ($track_pay_price != $track_price_usd) $html .= " ($".$this->game->blockchain->app->format_bignum($track_price_usd).")";
				$html .= "<br/>\n";
				if ($my_vote['event_option_index'] != 0) $html .= '-';
				$html .= $this->game->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->game->db_game['decimal_places'])).' '.$this->db_event['track_name_short'].' ';
				
				if ($borrow_delta != 0) {
					if ($borrow_delta > 0) $html .= '<font class="greentext">+ ';
					else $html .= '<font class="redtext">- ';
					$html .= $this->game->blockchain->app->format_bignum(abs($borrow_delta/pow(10, $this->game->db_game['decimal_places'])));
					$html .= "</font>\n";
				}
				if ($current_leverage && $current_leverage != 1) $html .= " &nbsp; (".$this->game->blockchain->app->format_bignum($current_leverage)."X)\n";
				$html .= "<br/>\n";
				
				if ($net_delta < 0) $html .= '<font class="redtext">Net loss of ';
				else $html .= '<font class="greentext">Net gain of ';
				$html .= $this->game->blockchain->app->format_bignum(abs($net_delta)/pow(10, $this->game->db_game['decimal_places'])).' '.$this->game->db_game['coin_name_plural'];
				$html .= '</font>';
				
				$html .= '</div>';
			}
			
			$html .= "</div>\n";
		}
		
		return $html;
	}
	
	public function set_event_completed() {
		$this->game->blockchain->app->run_query("UPDATE events SET completion_datetime=NOW() WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
	}
	
	public function delete_options() {
		$this->game->blockchain->app->run_query("DELETE FROM options WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
	}
	
	public function block_id_to_effectiveness_factor($block_id) {
		if ($this->db_event['vote_effectiveness_function'] == "linear_decrease") {
			$slope = -1*$this->db_event['effectiveness_param1'];
			$event_length_blocks = $this->db_event['event_final_block']-$this->db_event['event_starting_block']+1;
			$blocks_in = $block_id-$this->db_event['event_starting_block'];
			$frac_complete = $blocks_in/$event_length_blocks;
			$effectiveness = floor(pow(10,8)*$frac_complete*$slope)/pow(10,8) + 1;
			return $effectiveness;
		}
		else return 1;
	}
	
	public function option_stats($option_id) {
		$info = $this->game->blockchain->app->run_query("SELECT coin_score, unconfirmed_coin_score, coin_block_score, unconfirmed_coin_block_score, coin_round_score, unconfirmed_coin_round_score, votes, unconfirmed_votes, destroy_score, unconfirmed_destroy_score, effective_destroy_score, unconfirmed_effective_destroy_score FROM options WHERE option_id=:option_id;", ['option_id'=>$option_id])->fetch();
		
		$confirmed_votes = $info['votes'];
		$unconfirmed_votes = $info['unconfirmed_votes'];
		$confirmed_score = $info[$this->game->db_game['payout_weight'].'_score'];
		$unconfirmed_score = $info['unconfirmed_'.$this->game->db_game['payout_weight'].'_score'];
		$confirmed_effective_destroy = $info["effective_destroy_score"];
		$unconfirmed_effective_destroy = $info['unconfirmed_effective_destroy_score'];
		$confirmed_destroy = $info['destroy_score'];
		$unconfirmed_destroy = $info['unconfirmed_destroy_score'];
		
		if (!$confirmed_votes) $confirmed_votes = 0;
		if (!$unconfirmed_votes) $unconfirmed_votes = 0;
		
		return [
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
		];
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
		
		return [$inflationary_reward, $destroy_reward, $total_reward];
	}
	
	public function event_total_scores() {
		$info = $this->game->blockchain->app->run_query("SELECT SUM(".$this->game->db_game['payout_weight']."_score), SUM(unconfirmed_".$this->game->db_game['payout_weight']."_score), SUM(destroy_score), SUM(unconfirmed_destroy_score) FROM options WHERE event_id=:event_id;", [
			'event_id' => $this->db_event['event_id']
		])->fetch();
		
		$score = $info['SUM('.$this->game->db_game['payout_weight'].'_score)']+$info['SUM(unconfirmed_'.$this->game->db_game['payout_weight'].'_score)'];
		$destroy_score = $info['SUM(destroy_score)']+$info['SUM(unconfirmed_destroy_score)'];
		
		return [$score, $destroy_score];
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
			if (!in_array((string)$this->db_event['outcome_index'], ["", "-1"])) {
				$db_winning_option = $this->game->blockchain->app->fetch_option_by_event_option_index($this->db_event['event_id'], $this->db_event['outcome_index']);
				
				if ($db_winning_option) {
					$winning_option_id = $db_winning_option['option_id'];
					$rank_index = $option_id_to_rank[$winning_option_id];
					$winning_votes = $rankings[$rank_index]['votes'];
					$winning_effective_destroy_score = $rankings[$rank_index]['effective_destroy_score'];
				}
				else throw new Exception("Failed to identify the winning option for event #".$this->db_event['event_id']);
			}
		}
		
		return [$winning_option_id, $winning_votes, $winning_effective_destroy_score];
	}
	
	public function set_outcome_from_db() {
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
		
		$this->load_db_event($this->db_event['event_id']);
		
		list($inflationary_score, $destroy_reward) = $this->event_total_scores();
		
		$update_event_params = [
			'sum_score' => $inflationary_score,
			'sum_votes' => $sum_votes,
			'winning_votes' => $winning_votes,
			'winning_effective_destroy_score' => $winning_effective_destroy_score,
			'destroy_score' => $event_destroy_score,
			'effective_destroy_score' => $event_effective_destroy_score,
			'event_id' => $this->db_event['event_id']
		];
		$update_event_q = "UPDATE events SET sum_score=:sum_score";
		if ($winning_option) {
			$update_event_q .= ", winning_option_id=:winning_option_id";
			$update_event_params['winning_option_id'] = $winning_option;
		}
		$update_event_q .= ", sum_votes=:sum_votes, winning_votes=:winning_votes, winning_effective_destroy_score=:winning_effective_destroy_score, destroy_score=:destroy_score, effective_destroy_score=:effective_destroy_score WHERE event_id=:event_id;";
		$this->game->blockchain->app->run_query($update_event_q, $update_event_params);
		
		if ($this->db_event['outcome_index'] == -1) {
			$refund_response = $this->new_refund_payout();
			$log_text .= "Refund: ".$refund_response."<br/>\n";
		}
		else if ($this->db_event['payout_rule'] == "binary") {
			if ($winning_option !== false) {
				$payout_response = $this->new_binary_payout($winning_option, $winning_votes, $winning_effective_destroy_score);
				$log_text .= "Payout response: ".$payout_response."<br/>\n";
			}
		}
		else {
			$payout_response = $this->new_linear_payout();
			$log_text .= "Payout response: ".$payout_response."<br/>\n";
		}
		
		$this->set_event_completed();
		
		return $log_text;
	}
	
	public function process_option_blocks(&$game_block, $events_in_round, $round_first_event_index) {
		$this->game->blockchain->app->run_query("DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id WHERE o.event_id=:event_id AND ob.block_height=:block_height;", [
			'event_id' => $this->db_event['event_id'],
			'block_height' => $game_block['block_id']
		]);
		
		$block = $this->game->blockchain->fetch_block_by_id($game_block['block_id']);
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
			$these_options = $this->game->blockchain->app->fetch_options_by_event($this->db_event['event_id']);
			
			while ($db_option = $these_options->fetch()) {
				$score_prob = min(1, $team_avg_goals_per_game/$event_blocks);
				$rand_offset_start = $rand_chars_per_event*$event_offset + ($rand_i*$rand_chars_per_option);
				$rand_chars = substr($random_data, $rand_offset_start, $rand_chars_per_option);
				$rand_prob = hexdec($rand_chars)/pow(2, 4*strlen($rand_chars));
				
				if ($rand_prob <= $score_prob) $score = 1;
				else $score = 0;
				
				$this->game->blockchain->app->run_query("INSERT INTO option_blocks SET rand_chars=:rand_chars, rand_prob=:rand_prob, score=:score, option_id=:option_id, block_height=:block_height;", [
					'rand_chars' => $rand_chars,
					'rand_prob' => $rand_prob,
					'score' => $score,
					'option_id' => $db_option['option_id'],
					'block_height' => $game_block['block_id']
				]);
				
				if ($score > 0) {
					$this->game->blockchain->app->run_query("UPDATE options SET option_block_score=option_block_score+:score WHERE option_id=:option_id;", [
						'score' => $score,
						'option_id' => $db_option['option_id']
					]);
				}
				
				$rand_i++;
			}
		}
	}
	
	public function update_option_votes($last_block_id, $round_id) {
		$effectiveness_factor = $this->block_id_to_effectiveness_factor($last_block_id+1);
		
		$this->game->blockchain->app->run_query("UPDATE options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, destroy_score=0, unconfirmed_destroy_score=0, votes=0, unconfirmed_votes=0, effective_destroy_score=0, unconfirmed_effective_destroy_score=0 WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
		
		$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (
			SELECT option_id, SUM(colored_amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes, SUM(destroy_amount) sum_destroyed, SUM(effective_destroy_amount) sum_effective_destroyed FROM transaction_game_ios 
			WHERE event_id=:event_id AND create_round_id IS NOT NULL AND colored_amount > 0
			GROUP BY option_id
		) i ON op.option_id=i.option_id SET op.coin_score=i.sum_amount, op.coin_block_score=i.sum_cbd, op.coin_round_score=i.sum_crd, op.votes=i.sum_votes, op.destroy_score=i.sum_destroyed, op.effective_destroy_score=i.sum_effective_destroyed WHERE op.event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
		
		if ($this->game->db_game['payout_weight'] == "coin") {
			$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (
				SELECT option_id, SUM(colored_amount) sum_amount, SUM(colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed FROM transaction_game_ios 
				WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_score=i.sum_amount, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed WHERE op.event_id=:event_id;", [
				'effectiveness_factor' => $effectiveness_factor,
				'event_id' => $this->db_event['event_id']
			]);
		}
		else if ($this->game->db_game['payout_weight'] == "coin_block") {
			$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (
				SELECT option_id, SUM(ref_coin_blocks+(:ref_block_id-ref_block_id)*colored_amount) sum_cbd, SUM(ref_coin_blocks+(:ref_block_id-ref_block_id)*colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*:effectiveness_factor unconfirmed_sum_destroyed FROM transaction_game_ios 
				WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_block_score=i.sum_cbd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id=:event_id;", [
				'ref_block_id' => ($last_block_id+1),
				'effectiveness_factor' => $effectiveness_factor,
				'event_id' => $this->db_event['event_id']
			]);
		}
		else { // payout_weight = "coin_round"
			if (empty($round_id)) $round_id = $this->game->block_to_round($last_block_id+1);
			$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (
				SELECT option_id, SUM(ref_coin_rounds+(:round_id-ref_round_id)*colored_amount) sum_crd, SUM(ref_coin_rounds+(:round_id-ref_round_id)*colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*:effectiveness_factor unconfirmed_sum_destroyed FROM transaction_game_ios
				WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.unconfirmed_coin_round_score=i.sum_crd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id=:event_id;", [
				'round_id' => $round_id,
				'effectiveness_factor' => $effectiveness_factor,
				'event_id' => $this->db_event['event_id']
			]);
		}
		
		$this->game->blockchain->app->run_query("UPDATE events e JOIN (
			SELECT SUM(:payout_weight_score) sum_score, SUM(destroy_score) destroy_score, SUM(votes) sum_votes, SUM(effective_destroy_score) effective_destroy_score, SUM(:unconfirmed_payout_weight_score) sum_unconfirmed_score, SUM(unconfirmed_votes) sum_unconfirmed_votes, SUM(unconfirmed_destroy_score) sum_unconfirmed_destroy_score, SUM(unconfirmed_effective_destroy_score) sum_unconfirmed_effective_destroy_score FROM options WHERE event_id=:event_id
		) op SET e.sum_score=op.sum_score, e.destroy_score=op.destroy_score, e.sum_votes=op.sum_votes, e.effective_destroy_score=op.effective_destroy_score, e.sum_unconfirmed_score=op.sum_unconfirmed_score, e.sum_unconfirmed_votes=op.sum_unconfirmed_votes, e.sum_unconfirmed_destroy_score=op.sum_unconfirmed_destroy_score, e.sum_unconfirmed_effective_destroy_score=op.sum_unconfirmed_effective_destroy_score WHERE e.event_id=:event_id;", [
			'payout_weight_score' => $this->game->db_game['payout_weight']."_score",
			'unconfirmed_payout_weight_score' => "unconfirmed_".$this->game->db_game['payout_weight']."_score",
			'event_id' => $this->db_event['event_id']
		]);
	}
}
?>
