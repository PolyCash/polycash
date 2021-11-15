<?php
class Event {
	public $db_event;
	public $game;
	public $avoid_bet_buffer_blocks = 1;
	
	public function __construct(&$game, $db_event, $event_id) {
		$this->game = $game;
		if ($db_event) {
			$event_id = $db_event['event_id'];
			$this->db_event = $db_event;
		}
		else $this->load_db_event($event_id);
	}
	
	public function load_db_event($event_id) {
		$this->db_event = $this->game->blockchain->app->run_query("SELECT *, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.event_id=:event_id;", ['event_id'=>$event_id])->fetch();
		if (!$this->db_event) {
			throw new Exception("Failed to load event #".$event_id);
		}
	}
	
	public function round_voting_stats() {
		$stats_params = [
			'event_id' => $this->db_event['event_id']
		];
		
		if ($this->game->db_game['order_options_by'] == "option_index") {
			$order_by = "op.event_option_index ASC";
		}
		else { // order_options_by = "bets"
			$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
			$order_by = "((op.votes+op.unconfirmed_votes)*:coins_per_vote)+(op.effective_destroy_score+op.unconfirmed_effective_destroy_score) DESC, op.option_id ASC";
			$stats_params['coins_per_vote'] = $coins_per_vote;
		}
		
		return $this->game->blockchain->app->run_query("SELECT * FROM options op LEFT JOIN images i ON op.image_id=i.image_id LEFT JOIN entities e ON op.entity_id=e.entity_id WHERE op.event_id=:event_id ORDER BY ".$order_by.";", $stats_params);
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
		return $this->game->blockchain->app->render_view('event', [
			'app' => $this->game->blockchain->app,
			'blockchain' => $this->game->blockchain,
			'game' => $this->game,
			'event' => $this,
			'user' => $user,
			'show_intro_text' => $show_intro_text,
			'clickable' => $clickable,
			'game_instance_id' => $game_instance_id,
			'game_event_index' => $game_event_index,
		]);
	}
	
	public function set_outcome_index($outcome_index) {
		$this->game->blockchain->app->run_query("UPDATE events SET outcome_index=:outcome_index WHERE event_id=:event_id;", [
			'outcome_index' => $outcome_index,
			'event_id' => $this->db_event['event_id']
		]);
		$this->db_event['outcome_index'] = $outcome_index;
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
		
		$all_bets = $this->game->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.event_id=:event_id AND gio.is_game_coinbase=0;", ['event_id'=>$this->db_event['event_id']])->fetchAll();
		$log_text .= "Refunding ".count($all_bets)." bets.<br/>\n";
		
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		
		foreach ($all_bets as $bet) {
			$bet_amount = floor($bet[$score_field]*$coins_per_vote) + $bet['destroy_amount'];
			
			$this->game->blockchain->app->run_query("UPDATE transaction_game_ios SET colored_amount=".AppSettings::sqlFloor($bet_amount."*contract_parts/".$bet['contract_parts'])." WHERE parent_io_id=:parent_io_id;", [
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
		
		$long_payout_frac = $contract_size > 0 ? $long_contract_price/$contract_size : 0;
		$long_payout_total = floor($total_reward*$long_payout_frac);
		$short_payout_total = $total_reward-$long_payout_total;
		
		$options_by_event = $this->game->blockchain->app->fetch_options_by_event($this->db_event['event_id']);
		
		while ($option = $options_by_event->fetch()) {
			if ($option['event_option_index'] == 0) $option_payout_total = $long_payout_total;
			else $option_payout_total = $short_payout_total;
			
			$option_effective_coins = $option['effective_destroy_score'] + $option['votes']*$coins_per_vote;
			
			if ($option_effective_coins > 0) {
				$bets_by_option = $this->game->blockchain->app->run_query("SELECT * FROM transaction_game_ios WHERE option_id=:option_id AND is_game_coinbase=0;", ['option_id'=>$option['option_id']]);
				
				while ($parent_io = $bets_by_option->fetch()) {
					$this_effective_coins = $parent_io['votes']*$coins_per_vote + $parent_io['effective_destroy_amount'];
					$this_payout_amount = floor($this->db_event['payout_rate']*$option_payout_total*$this_effective_coins/$option_effective_coins);
					$weighted_payout = $this_payout_amount/$parent_io['contract_parts'];
					
					$this->game->blockchain->app->run_query("UPDATE transaction_game_ios SET colored_amount=".AppSettings::sqlFloor($weighted_payout."*contract_parts")." WHERE parent_io_id=:parent_io_id AND resolved_before_spent=1;", [
						'parent_io_id' => $parent_io['game_io_id']
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
		$winning_bets = $this->game->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.option_id=:winning_option AND gio.is_game_coinbase=0;", [
			'winning_option' => $winning_option
		])->fetchAll();
		$log_text .= "Paying out ".count($winning_bets)." correct votes.<br/>\n";
		
		list($inflationary_reward, $destroy_reward, $total_reward) = $this->event_rewards();
		$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
		$winning_effective_coins = floor($winning_votes*$coins_per_vote) + $winning_effective_destroy_score;
		
		foreach ($winning_bets as $input) {
			$this_input_effective_coins = floor($input['votes']*$coins_per_vote) + $input['effective_destroy_amount'];
			$this_input_payout_amount = $winning_effective_coins>0 ? floor($total_reward*$this->db_event['payout_rate']*($this_input_effective_coins/$winning_effective_coins)) : 0;
			$weighted_payout = $input['contract_parts'] > 0 ? $this_input_payout_amount/$input['contract_parts'] : 0;
			
			$this->game->blockchain->app->run_query("UPDATE transaction_game_ios SET colored_amount=".AppSettings::sqlFloor($weighted_payout."*contract_parts")." WHERE parent_io_id=:game_io_id AND resolved_before_spent=1;", [
				'game_io_id' => $input['game_io_id']
			]);
		}
		
		return $log_text;
	}
	
	public function my_votes_table($round_id, &$user_game) {
		return $this->game->blockchain->app->render_view('my_votes_table', [
			'app' => $this->game->blockchain->app,
			'blockchain' => $this->game->blockchain,
			'game' => $this->game,
			'event' => $this,
			'round_id' => $round_id,
			'user_game' => $user_game,
		]);
	}
	
	public function set_event_completed() {
		$this->game->blockchain->app->run_query("UPDATE events SET completion_datetime=".AppSettings::sqlNow()." WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
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
		
		if ($this->game->db_game['inflation'] == "exponential") {
			$coins_per_vote = $this->game->blockchain->app->coins_per_vote($this->game->db_game);
			$inflationary_reward = $inflationary_score*$coins_per_vote;
		}
		else $inflationary_reward = 0;
		
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
			for ($rank=0; $rank<$this->db_event['num_options']; $rank++) {
				if ($rankings[$rank]['votes'] > $max_winning_votes) {}
				else if (!$winning_option_id) {
					$winning_option_id = $rankings[$rank]['option_id'];
					$winning_votes = $rankings[$rank]['votes'];
					$winning_effective_destroy_score = $rankings[$rank]['effective_destroy_score'];
					$rank = $this->db_event['num_options'];
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
				else throw new Exception("Failed to identify the winning option for event #".$this->db_event['event_index']);
			}
		}
		
		return [$winning_option_id, $winning_votes, $winning_effective_destroy_score];
	}
	
	public function pay_out_event() {
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
				$log_text .= "Binary payout response: ".$payout_response."<br/>\n";
			}
		}
		else {
			$payout_response = $this->new_linear_payout();
			$log_text .= "Linear payout response: ".$payout_response."<br/>\n";
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
		
		if ($this->db_event['option_block_rule'] == "basketball_game") {
			$rands_needed_per_option = 2;
			$chars_per_rand = 6;
			$rand_chars_per_option = $rands_needed_per_option*$chars_per_rand;
			$rand_chars_per_event = $rand_chars_per_option*$this->db_event['num_options'];
			$event_offset = $this->db_event['event_index'] - $round_first_event_index;
			
			$total_rand_chars_needed = $rand_chars_per_event*$events_in_round;
			$last_rand_hash = $random_data;
			
			while (strlen($random_data) < $total_rand_chars_needed) {
				$last_rand_hash = hash("sha256", $last_rand_hash);
				$random_data .= $last_rand_hash;
			}
			
			$event_blocks = $this->db_event['event_determined_to_block'] - $this->db_event['event_determined_from_block'] + 1;
			
			$rand_i = 0;
			$these_options = $this->game->blockchain->app->fetch_options_by_event($this->db_event['event_id']);
			
			while ($db_option = $these_options->fetch()) {
				$avg_points_per_block = round($db_option['target_score']/$event_blocks, 6);
				$max_points_per_block = $avg_points_per_block*2;
				
				$rand1_offset_start = $rand_chars_per_event*$event_offset + ($rand_i*$chars_per_rand);
				$rand1_chars = substr($random_data, $rand1_offset_start, $chars_per_rand);
				$rand1_num = hexdec($rand1_chars);
				$rand1_prob = ($rand1_num%pow(10, 5))/pow(10, 5);
				$rand_i++;
				
				$rand2_offset_start = $rand_chars_per_event*$event_offset + ($rand_i*$chars_per_rand);
				$rand2_chars = substr($random_data, $rand2_offset_start, $chars_per_rand);
				$rand2_num = hexdec($rand2_chars);
				$rand2_prob = ($rand2_num%pow(10, 5))/pow(10, 5);
				$rand_i++;
				
				$points_scored_float = round($max_points_per_block*$rand1_prob, 6);
				
				if ($points_scored_float == round($points_scored_float)) $points_scored_int = $points_scored_float;
				else {
					$points_scored_int = floor($points_scored_float);
					$points_scored_remainder = $points_scored_float - $points_scored_int;
					if ($points_scored_remainder >= $rand2_prob) $points_scored_int++;
				}
				
				$this->game->blockchain->app->run_insert_query("option_blocks", [
					'score' => $points_scored_int,
					'option_id' => $db_option['option_id'],
					'block_height' => $game_block['block_id']
				]);
				
				if ($points_scored_int > 0) {
					$this->game->blockchain->app->run_query("UPDATE options SET option_block_score=option_block_score+:score WHERE option_id=:option_id;", [
						'score' => $points_scored_int,
						'option_id' => $db_option['option_id']
					]);
				}
			}
		}
	}
	
	public function update_option_votes($last_block_id, $round_id) {
		// Initialize all option values to zero
		$this->game->blockchain->app->run_query("UPDATE options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, destroy_score=0, unconfirmed_destroy_score=0, votes=0, unconfirmed_votes=0, effective_destroy_score=0, unconfirmed_effective_destroy_score=0 WHERE event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
		
		// Set option confirmed values
		$inner_q = "SELECT option_id, SUM(colored_amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes, SUM(destroy_amount) sum_destroyed, SUM(effective_destroy_amount) sum_effective_destroyed FROM transaction_game_ios WHERE event_id=:event_id AND create_round_id IS NOT NULL AND colored_amount > 0 GROUP BY option_id";
		
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (".$inner_q.") i ON op.option_id=i.option_id SET op.coin_score=i.sum_amount, op.coin_block_score=i.sum_cbd, op.coin_round_score=i.sum_crd, op.votes=i.sum_votes, op.destroy_score=i.sum_destroyed, op.effective_destroy_score=i.sum_effective_destroyed WHERE op.event_id=:event_id;", ['event_id'=>$this->db_event['event_id']]);
		}
		else {
			$info_by_op = $this->game->blockchain->app->run_query($inner_q, ['event_id' => $this->db_event['event_id']])->fetchAll();
			
			if (count($info_by_op) > 0) {
				$option_ids = array_column($info_by_op, 0);
				$set_data['coin_score'] = array_column($info_by_op, 1);
				$set_data['coin_block_score'] = array_column($info_by_op, 2);
				$set_data['coin_round_score'] = array_column($info_by_op, 3);
				$set_data['votes'] = array_column($info_by_op, 4);
				$set_data['destroy_score'] = array_column($info_by_op, 5);
				$set_data['effective_destroy_score'] = array_column($info_by_op, 6);
				
				$this->game->blockchain->app->bulk_mapped_update_query("options", $set_data, ['option_id' => $option_ids]);
			}
		}
		
		// Only set unconfirmed option values when the event is in progress to exclude bets confirmed too late
		if ($last_block_id < $this->db_event['event_final_block']) {
			$effectiveness_factor = $this->block_id_to_effectiveness_factor($last_block_id+1);
			
			// 3 different queries to set option unconfirmed values depending on the event payout weight type
			if ($this->game->db_game['payout_weight'] == "coin") {
				$inner_q = "SELECT option_id, SUM(colored_amount) sum_amount, SUM(colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed FROM transaction_game_ios WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0 GROUP BY option_id";
				
				if (empty(AppSettings::getParam('sqlite_db'))) {
					$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (".$inner_q.") i ON op.option_id=i.option_id SET op.unconfirmed_coin_score=i.sum_amount, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed WHERE op.event_id=:event_id;", [
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					]);
				}
				else {
					$info_by_op = $this->game->blockchain->app->run_query($inner_q, [
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					])->fetchAll();
					
					if (count($info_by_op) > 0) {
						$option_ids = array_column($info_by_op, 0);
						$set_data['unconfirmed_coin_score'] = array_column($info_by_op, 1);
						$set_data['unconfirmed_votes'] = array_column($info_by_op, 2);
						$set_data['unconfirmed_destroy_score'] = array_column($info_by_op, 3);
						
						$this->game->blockchain->app->bulk_mapped_update_query("options", $set_data, ['option_id' => $option_ids]);
					}
				}
			}
			else if ($this->game->db_game['payout_weight'] == "coin_block") {
				$inner_q = "SELECT option_id, SUM(ref_coin_blocks+(:ref_block_id-ref_block_id)*colored_amount) sum_cbd, SUM(ref_coin_blocks+(:ref_block_id-ref_block_id)*colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*:effectiveness_factor unconfirmed_sum_destroyed FROM transaction_game_ios WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0 GROUP BY option_id";
				
				if (empty(AppSettings::getParam('sqlite_db'))) {
					$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (".$inner_q.") i ON op.option_id=i.option_id SET op.unconfirmed_coin_block_score=i.sum_cbd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id=:event_id;", [
						'ref_block_id' => ($last_block_id+1),
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					]);
				}
				else {
					$info_by_op = $this->game->blockchain->app->run_query($inner_q, [
						'ref_block_id' => ($last_block_id+1),
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					])->fetchAll();
					
					if (count($info_by_op) > 0) {
						$option_ids = array_column($info_by_op, 0);
						$set_data['unconfirmed_coin_block_score'] = array_column($info_by_op, 1);
						$set_data['unconfirmed_votes'] = array_column($info_by_op, 2);
						$set_data['unconfirmed_destroy_score'] = array_column($info_by_op, 3);
						$set_data['unconfirmed_effective_destroy_score'] = array_column($info_by_op, 4);
						
						$this->game->blockchain->app->bulk_mapped_update_query("options", $set_data, ['option_id' => $option_ids]);
					}
				}
			}
			else { // payout_weight = "coin_round"
				if (empty($round_id)) $round_id = $this->game->block_to_round($last_block_id+1);
				
				$inner_q = "SELECT option_id, SUM(ref_coin_rounds+(:round_id-ref_round_id)*colored_amount) sum_crd, SUM(ref_coin_rounds+(:round_id-ref_round_id)*colored_amount)*:effectiveness_factor sum_votes, SUM(destroy_amount) sum_destroyed, SUM(destroy_amount)*:effectiveness_factor unconfirmed_sum_destroyed FROM transaction_game_ios WHERE event_id=:event_id AND create_round_id IS NULL AND colored_amount > 0 GROUP BY option_id";
				
				if (empty(AppSettings::getParam('sqlite_db'))) {
					$this->game->blockchain->app->run_query("UPDATE options op INNER JOIN (".$inner_q.") i ON op.option_id=i.option_id SET op.unconfirmed_coin_round_score=i.sum_crd, op.unconfirmed_votes=i.sum_votes, op.unconfirmed_destroy_score=i.sum_destroyed, op.unconfirmed_effective_destroy_score=i.unconfirmed_sum_destroyed WHERE op.event_id=:event_id;", [
						'round_id' => $round_id,
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					]);
				}
				else {
					$info_by_op = $this->game->blockchain->app->run_query($inner_q, [
						'round_id' => $round_id,
						'effectiveness_factor' => $effectiveness_factor,
						'event_id' => $this->db_event['event_id']
					])->fetchAll();
					
					if (count($info_by_op) > 0) {
						$option_ids = array_column($info_by_op, 0);
						$set_data['unconfirmed_coin_round_score'] = array_column($info_by_op, 1);
						$set_data['unconfirmed_votes'] = array_column($info_by_op, 2);
						$set_data['unconfirmed_destroy_score'] = array_column($info_by_op, 3);
						$set_data['unconfirmed_effective_destroy_score'] = array_column($info_by_op, 4);
						
						$this->game->blockchain->app->bulk_mapped_update_query("options", $set_data, ['option_id' => $option_ids]);
					}
				}
			}
		}
		
		// Now set event values by summing up the option values
		$inner_q = "SELECT SUM(".$this->game->db_game['payout_weight']."_score) sum_score, SUM(destroy_score) destroy_score, SUM(votes) sum_votes, SUM(effective_destroy_score) effective_destroy_score, SUM(unconfirmed_".$this->game->db_game['payout_weight']."_score) sum_unconfirmed_score, SUM(unconfirmed_votes) sum_unconfirmed_votes, SUM(unconfirmed_destroy_score) sum_unconfirmed_destroy_score, SUM(unconfirmed_effective_destroy_score) sum_unconfirmed_effective_destroy_score FROM options WHERE event_id=:event_id";
		
		$update_fields = ['sum_score', 'destroy_score', 'sum_votes', 'effective_destroy_score', 'sum_unconfirmed_score', 'sum_unconfirmed_votes', 'sum_unconfirmed_destroy_score', 'sum_unconfirmed_effective_destroy_score'];
		
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$update_q = "UPDATE events e JOIN (".$inner_q.") op SET ";
			foreach ($update_fields as $update_field) {
				$update_q .= "e.".$update_field."=op.".$update_field.", ";
			}
			$update_q = substr($update_q, 0, -2)." WHERE e.event_id=:event_id;";
			
			$this->game->blockchain->app->run_query($update_q, [
				'event_id' => $this->db_event['event_id']
			]);
		}
		else {
			$event_info = $this->game->blockchain->app->run_query($inner_q, ['event_id' => $this->db_event['event_id']])->fetch();
			$update_q = "UPDATE events SET ";
			foreach ($update_fields as $update_field) {
				$update_q .= $update_field."='".$event_info[$update_field]."', ";
			}
			$update_q = substr($update_q, 0, -2)." WHERE event_id=:event_id;";
			
			$this->game->blockchain->app->run_query($update_q, [
				'event_id' => $this->db_event['event_id']
			]);
		}
	}
	
	public function fetch_option_blocks() {
		return $this->game->blockchain->app->run_query("SELECT * FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id=:event_id AND ob.score > 0 ORDER BY ob.option_block_id ASC;", ['event_id' => $this->db_event['event_id']])->fetchAll();
	}
	
	public function option_block_info() {
		$options_by_score = $this->game->blockchain->app->run_query("SELECT SUM(ob.score) AS total_score, o.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id WHERE o.event_id=:event_id GROUP BY o.option_id ORDER BY total_score DESC;", ['event_id' => $this->db_event['event_id']])->fetchAll();
		
		$is_tie = true;
		$last_total_score = null;
		
		foreach ($options_by_score as $option_info) {
			if ($last_total_score === null) $last_total_score = $option_info['total_score'];
			if ($option_info['total_score'] != $last_total_score) $is_tie = false;
		}
		
		$options_by_index = [];
		foreach ($options_by_score as $option_info) {
			$options_by_index[$option_info['event_option_index']] = $option_info;
		}
		asort($options_by_index);
		
		$score_disp = "";
		foreach ($options_by_index as $option) {
			$score_disp .= ((int)$option['option_block_score'])."-";
		}
		$score_disp = substr($score_disp, 0, strlen($score_disp)-1);
		
		$in_progress_summary = "";
		if ((string)$this->db_event['outcome_index'] === "") {
			if ($is_tie) $in_progress_summary = "Tied";
			else $in_progress_summary = $options_by_score[0]['name']." is winning";
		}
		
		return [$options_by_score, $options_by_index, $is_tie, $score_disp, $in_progress_summary];
	}
	
	public function set_target_scores($last_block_id) {
		$options = $this->game->blockchain->app->fetch_options_by_event($this->db_event['event_id'], true)->fetchAll();
		
		$info_arr = $this->game->blockchain->app->run_query("SELECT o.entity_id, COUNT(*), SUM(score) FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id WHERE ev.game_id=:game_id AND o.entity_id IN (".implode(",", array_column($options, 'entity_id')).") AND ev.event_determined_to_block<=:last_block_id AND ev.season_index=:season_index GROUP BY o.entity_id;", [
			'game_id' => $this->game->db_game['game_id'],
			'last_block_id' => $last_block_id,
			'season_index' => $this->db_event['season_index'],
		])->fetchAll();
		
		$past_avg_by_entity_id = [];
		
		foreach ($options as $option) {
			$past_avg_by_entity_id[$option['entity_id']] = $this->game->db_game['target_option_block_score'];
		}
		
		foreach ($info_arr as $info) {
			$avg_points_per_block = round($info['SUM(score)']/$info['COUNT(*)'], 8);
			$past_avg = round($avg_points_per_block*($this->db_event['event_determined_to_block']-$this->db_event['event_determined_from_block']+1), 8);
			$past_avg_by_entity_id[$info['entity_id']] = $past_avg;
		}
		
		$event_past_avg = round(array_sum($past_avg_by_entity_id)/count($options), 8);
		$event_score_boost = round(($this->game->db_game['target_option_block_score']-$event_past_avg)/2, 8);
		
		foreach ($options as $option) {
			$target_score = round($past_avg_by_entity_id[$option['entity_id']]+$event_score_boost, 4);
			
			$this->game->blockchain->app->run_query("UPDATE options SET target_score=:target_score WHERE option_id=:option_id;", [
				'target_score' => $target_score,
				'option_id' => $option['option_id'],
			]);
		}
	}
}
?>
