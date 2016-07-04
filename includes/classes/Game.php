<?php
class Game {
	public $db_game;
	public $app;
	
	public function __construct(&$app, $game_id) {
		$this->app = $app;
		
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = $this->app->run_query($q);
		$this->db_game = $r->fetch() or die("Error, could not load game #".$game_id);
	}
	
	public function current_block() {
		$q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) return $r->fetch();
		else return false;
	}

	public function last_block_id() {
		$block = $this->current_block();
		if ($block) return $block['block_id'];
		else return 0;
	}

	public function block_to_round($mining_block_id) {
		return ceil($mining_block_id/$this->db_game['round_length']);
	}
	
	public function round_voting_stats($round_id) {
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		
		if ($round_id == $current_round) {
			$q = "SELECT * FROM game_voting_options gvo LEFT JOIN images i ON gvo.image_id=i.image_id WHERE gvo.game_id='".$this->db_game['game_id']."' ORDER BY (gvo.votes+gvo.unconfirmed_votes) DESC, gvo.option_id ASC;";
			return $this->app->run_query($q);
		}
		else {
			$q = "SELECT gvo.*, i.*, SUM(i.votes) AS votes FROM transaction_ios i JOIN game_voting_options gvo ON i.option_id=gvo.option_id LEFT JOIN images im ON gvo.image_id=im.image_id WHERE i.game_id='".$this->db_game['game_id']."' AND i.create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND i.create_block_id <= ".($round_id*$this->db_game['round_length']-1)." GROUP BY i.option_id ORDER BY SUM(i.votes) DESC, i.option_id ASC;";
			return $this->app->run_query($q);
		}
	}

	public function total_score_in_round($round_id, $include_unconfirmed) {
		$sum = 0;
		
		$base_q = "SELECT SUM(votes) FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."' AND option_id > 0 AND amount > 0";
		$confirmed_q = $base_q." AND (create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND create_block_id <= ".($round_id*$this->db_game['round_length']-1).");";
		$confirmed_r = $this->app->run_query($confirmed_q);
		$confirmed_score = $confirmed_r->fetch(PDO::FETCH_NUM);
		$confirmed_score = $confirmed_score[0];
		if ($confirmed_score > 0) {} else $confirmed_score = 0;
		
		$sum += $confirmed_score;
		$returnvals['confirmed'] = $confirmed_score;
		
		if ($include_unconfirmed) {
			$q = "SELECT SUM(unconfirmed_votes) FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			$sums = $r->fetch(PDO::FETCH_NUM);
			
			$unconfirmed_score = $sums[0];
			$sum += $unconfirmed_score;
			$returnvals['unconfirmed'] = $unconfirmed_score;
		}
		else $returnvals['unconfirmed'] = 0;
		
		$returnvals['sum'] = $sum;
		
		return $returnvals;
	}

	public function round_voting_stats_all($voting_round) {
		$round_voting_stats = $this->round_voting_stats($voting_round);
		$stats_all = false;
		$counter = 0;
		$option_id_csv = "";
		$option_id_to_rank = "";
		
		while ($stat = $round_voting_stats->fetch()) {
			$stats_all[$counter] = $stat;
			$option_id_csv .= $stat['option_id'].",";
			$option_id_to_rank[$stat['option_id']] = $counter;
			$counter++;
		}
		if ($option_id_csv != "") $option_id_csv = substr($option_id_csv, 0, strlen($option_id_csv)-1);
		
		$q = "SELECT * FROM game_voting_options gvo LEFT JOIN images i ON gvo.image_id=i.image_id WHERE gvo.game_id='".$this->db_game['game_id']."'";
		if ($option_id_csv != "") $q .= " AND gvo.option_id NOT IN (".$option_id_csv.")";
		$q .= " ORDER BY gvo.option_id ASC;";
		$r = $this->app->run_query($q);
		
		while ($stat = $r->fetch()) {
			$stat['votes'] = 0;
			$stat['unconfirmed_votes'] = 0;
			
			$stats_all[$counter] = $stat;
			$option_id_to_rank[$stat['option_id']] = $counter;
			$counter++;
		}
		
		$current_round = $this->block_to_round($this->last_block_id()+1);
		if ($voting_round == $current_round) $include_unconfirmed = true;
		else $include_unconfirmed = false;
		
		$score_sums = $this->total_score_in_round($voting_round, $include_unconfirmed);
		$output_arr[0] = $score_sums['sum'];
		$output_arr[1] = floor($score_sums['sum']*$this->db_game['max_voting_fraction']);
		$output_arr[2] = $stats_all;
		$output_arr[3] = $option_id_to_rank;
		$output_arr[4] = $score_sums['confirmed'];
		$output_arr[5] = $score_sums['unconfirmed'];
		
		return $output_arr;
	}

	public function get_round_winner($round_stats_all) {
		$winner_option_id = false;
		$winner_index = false;
		$max_score_sum = $round_stats_all[1];
		$round_stats = $round_stats_all[2];
		
		for ($i=0; $i<count($round_stats); $i++) {
			if (!$winner_option_id && $round_stats[$i]['votes'] <= $max_score_sum && $round_stats[$i]['votes'] > 0) {
				$winner_option_id = $round_stats[$i]['option_id'];
				$winner_index = $i;
			}
		}
		if ($winner_option_id) {
			$q = "SELECT * FROM game_voting_options WHERE option_id='".$winner_option_id."';";
			$r = $this->app->run_query($q);
			$option = $r->fetch();
			
			$option['winning_score'] = $round_stats[$winner_index]['votes'];
			
			return $option;
		}
		else return false;
	}

	public function current_round_table($current_round, $user, $show_intro_text, $clickable) {
		$score_field = $this->db_game['payout_weight']."_score";
		
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		$block_within_round = $this->block_id_to_round_index($last_block_id+1);
		
		$round_stats_all = $this->round_voting_stats_all($current_round);
		$score_sum = $round_stats_all[0];
		$max_score_sum = $round_stats_all[1];
		$round_stats = $round_stats_all[2];
		$confirmed_score_sum = $round_stats_all[4];
		$unconfirmed_score_sum = $round_stats_all[5];
		
		$winner_option_id = FALSE;
		
		$html = '<div id="round_table">';
		
		$max_circle_diam = 200;
		$sq_px_per_pct_point = pow($max_circle_diam, 2)/100;
		$min_px_diam = 30;
		
		if ($show_intro_text) {
			if ($block_within_round != $this->db_game['round_length']) $html .= "<h2>".ucwords($this->db_game['option_name'])." Rankings - Round #".$current_round."</h2>\n";
			else {
				$winner = $this->get_round_winner($round_stats_all);
				if ($winner) $html .= "<h1>".$winner['name']." won round #".$current_round."</h1>";
				else $html .= "<h1>No winner in round #".$current_round."</h1>";
			}
			if ($last_block_id == 0) $html .= 'Currently mining the first block.<br/>';
			else $html .= 'Last block completed was #'.$last_block_id.', currently mining #'.($last_block_id+1).'<br/>';
			
			if ($block_within_round == $this->db_game['round_length']) {
				$html .= $this->app->format_bignum($score_sum/pow(10,8)).' votes were cast in this round.<br/>';
				$my_votes = $this->my_votes_in_round($current_round, $user->db_user['user_id']);
				$fees_paid = $my_votes['fee_amount'];

				if (empty($my_votes[0])) {
					$my_winning_votes = 0;
					$html .= "You didn't cast any votes for ".$winner['name'].".<br/>\n";
				}
				else {
					$my_winning_votes = $my_votes[0][$winner['option_id']]["votes"];
					$win_amount = floor(pos_reward_in_round($this->db_game, $current_round)*$my_winning_votes/$winner['winning_score'] - $fees_paid)/pow(10,8);
					$html .= "You correctly cast ".$this->app->format_bignum($my_winning_votes/pow(10,8))." votes";
					$html .= ' and won <font class="greentext">+'.$this->app->format_bignum($win_amount)."</font> coins.<br/>\n";
				}
			}
			else {
				$html .= $this->app->format_bignum($confirmed_score_sum/pow(10,8)).' confirmed and '.$this->app->format_bignum($unconfirmed_score_sum/pow(10,8)).' unconfirmed votes have been cast so far. Current votes count towards block '.$block_within_round.'/'.$this->db_game['round_length'].' in round #'.$current_round.'<br/>';
				$seconds_left = round(($this->db_game['round_length'] - $last_block_id%$this->db_game['round_length'] - 1)*$this->db_game['seconds_per_block']);
				$minutes_left = round($seconds_left/60);
				$payout_disp = $this->app->format_bignum(pos_reward_in_round($this->db_game, $current_round)/pow(10,8));
				$html .= $payout_disp.' ';
				if ($payout_disp == '1') $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
				$html .= ' will be given to the winners in approximately ';
				if ($minutes_left > 1) $html .= $minutes_left." minutes";
				else $html .= $seconds_left." seconds";
				$html .= '. Max voting percentage is '.($this->db_game['max_voting_fraction']*100).'%.<br/>';
			}
		}
		
		for ($i=0; $i<count($round_stats); $i++) {
			$option_score = $round_stats[$i]['votes'] + $round_stats[$i]['unconfirmed_votes'];
			
			if (!$winner_option_id && $option_score <= $max_score_sum && $option_score > 0) $winner_option_id = $round_stats[$i]['option_id'];
			
			if ($score_sum > 0) {
				$pct_votes = 100*(floor(1000*$option_score/$score_sum)/1000);
			}
			else $pct_votes = 0;
			
			$sq_px = $pct_votes*$sq_px_per_pct_point;
			$box_diam = round(sqrt($sq_px));
			if ($box_diam < $min_px_diam) $box_diam = $min_px_diam;
			
			$html .= '
			<div class="vote_option_box_container">
				<div class="vote_option_label';
				if ($option_score > $max_score_sum) $html .=  " redtext";
				else if ($winner_option_id == $round_stats[$i]['option_id']) $html .=  " greentext";
				$html .= '"';
				if ($clickable) $html .= ' style="cursor: pointer;" onclick="option_selected('.$i.'); start_vote('.$round_stats[$i]['option_id'].');"';
				$html .= '>'.$round_stats[$i]['name'].' ('.$pct_votes.'%)</div>
				<div class="stage vote_option_box_holder" style="height: '.$box_diam.'px; width: '.$box_diam.'px;">
					<div class="ball vote_option_box" style="';
					if ($round_stats[$i]['image_id'] > 0) $html .= 'background-image: url(\'/img/custom/'.$round_stats[$i]['image_id'].'_'.$round_stats[$i]['access_key'].'.'.$round_stats[$i]['extension'].'\');';
					if ($clickable) $html .= 'cursor: pointer;';
					$html .= '" id="vote_option_'.$i.'"';
					if ($clickable) $html .= ' onmouseover="option_selected('.$i.');" onclick="option_selected('.$i.'); start_vote('.$round_stats[$i]['option_id'].');"';
					$html .= '>
						<input type="hidden" id="option_id2rank_'.$round_stats[$i]['option_id'].'" value="'.$i.'" />
						<input type="hidden" id="rank2option_id_'.$i.'" value="'.$round_stats[$i]['option_id'].'" />
					</div>
				</div>
			</div>';
		}
		$html .= "</div>";
		
		return $html;
	}
	
	public function last_voting_transaction_id() {
		$q = "SELECT transaction_id FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND option_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		if ($r[0] > 0) {} else $r[0] = 0;
		return $r[0];
	}
	
	public function last_transaction_id() {
		$q = "SELECT transaction_id FROM transactions WHERE game_id='".$this->db_game['game_id']."' ORDER BY transaction_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		if ($r[0] > 0) {} else $r[0] = 0;
		return $r[0];
	}
	
	public function new_nonuser_address() {
		$new_address = "E";
		$rand1 = rand(0, 1);
		if ($rand1 == 0) $new_address .= "e";
		else $new_address .= "E";
		$new_address .= "x".$this->app->random_string(31);
		
		$qq = "INSERT INTO addresses SET game_id='".$this->db_game['game_id']."', option_id=NULL, user_id=NULL, address='".$new_address."', time_created='".time()."';";
		$rr = $this->app->run_query($qq);
		return $this->app->last_insert_id();
	}
	
	public function new_payout_transaction($round_id, $block_id, $winning_option, $winning_score) {
		$log_text = "";
		
		if ($this->db_game['payout_weight'] == "coin") $score_field = "amount";
		else $score_field = $this->db_game['payout_weight']."s_destroyed";
		
		$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', tx_hash='".$this->app->random_string(64)."', transaction_desc='votebase', amount=0, block_id='".$block_id."', time_created='".time()."';";
		$r = $this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		// Loop through the correctly voted UTXOs
		$q = "SELECT * FROM transaction_ios i JOIN users u ON i.user_id=u.user_id WHERE i.game_id='".$this->db_game['game_id']."' AND i.create_block_id > ".(($round_id-1)*$this->db_game['round_length'])." AND i.create_block_id < ".($round_id*$this->db_game['round_length'])." AND i.option_id=".$winning_option.";";
		$r = $this->app->run_query($q);
		
		$total_paid = 0;
		$out_index = 0;
		
		while ($input = $r->fetch()) {
			$payout_amount = floor(pos_reward_in_round($this->db_game, $round_id)*$input[$score_field]/$winning_score);
			
			$total_paid += $payout_amount;
			
			$qq = "INSERT INTO transaction_ios SET spend_status='unspent', out_index='".$out_index."', instantly_mature=0, game_id='".$this->db_game['game_id']."', user_id='".$input['user_id']."', address_id='".$input['address_id']."'";
			if ($winning_option > 0) $qq .= ", option_id='".$winning_option."'";
			$qq .= ", create_transaction_id='".$transaction_id."', amount='".$payout_amount."', create_block_id='".$block_id."', create_round_id='".$round_id."';";
			$rr = $this->app->run_query($qq);
			$output_id = $this->app->last_insert_id();
			
			$qq = "UPDATE transaction_ios SET payout_io_id='".$output_id."' WHERE io_id='".$input['io_id']."';";
			$rr = $this->app->run_query($qq);
			
			$payout_disp = $payout_amount/(pow(10,8));
			$log_text .= "Pay ".$payout_disp." ";
			if ($payout_disp == '1') $log_text .= $this->db_game['coin_name'];
			else $log_text .= $this->db_game['coin_name_plural'];
			$log_text .= " to ".$input['username']."<br/>\n";
			$out_index++;
		}
		
		$q = "UPDATE transactions SET amount='".$total_paid."' WHERE transaction_id='".$transaction_id."';";
		$r = $this->app->run_query($q);
		
		$returnvals[0] = $transaction_id;
		$returnvals[1] = $log_text;
		
		return $returnvals;
	}
	
	public function new_betbase_transaction($round_id, $mining_block_id, $winning_option) {
		$log_text = "";
		
		$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."'";
		if ($this->db_game['game_type'] == "simulation") $q .= ", tx_hash='".$this->app->random_string(64)."'";
		$q .= ", transaction_desc='betbase', block_id='".($mining_block_id-1)."', time_created='".time()."';";
		$r = $this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		$bet_mid_q = "transaction_ios i, addresses a WHERE i.game_id='".$this->db_game['game_id']."' AND i.address_id=a.address_id AND a.bet_round_id = ".$round_id." AND i.create_block_id <= ".$this->round_to_last_betting_block($round_id);
		
		$total_burned_q = "SELECT SUM(i.amount) FROM ".$bet_mid_q.";";
		$total_burned_r = $this->app->run_query($total_burned_q);
		$total_burned = $total_burned_r->fetch(PDO::FETCH_NUM);
		$total_burned = $total_burned[0];
		
		if ($total_burned > 0) {
			$winners_burned_q = "SELECT SUM(i.amount) FROM ".$bet_mid_q;
			if ($winning_option) $winners_burned_q .= " AND bet_option_id=".$winning_option.";";
			else $winners_burned_q .= " AND bet_option_id IS NULL;";
			$winners_burned_r = $this->app->run_query($winners_burned_q);
			$winners_burned = $winners_burned_r->fetch(PDO::FETCH_NUM);
			$winners_burned = $winners_burned[0];
			
			$win_multiplier = 0;
			if ($winners_burned > 0) $win_multiplier = floor(pow(10,8)*$total_burned/$winners_burned)/pow(10,8);
			
			$log_text .= $total_burned/pow(10,8)." coins should be paid to the winning bettors (x".$win_multiplier.").<br/>\n";
			
			if ($winners_burned > 0) {
				$bet_winners_q = "SELECT * FROM ".$bet_mid_q." AND bet_option_id=".$winning_option.";";
				$bet_winners_r = $this->app->run_query($bet_winners_q);
				
				$betbase_sum = 0;
				
				while ($bet_winner = $bet_winners_r->fetch()) {
					$win_amount = floor($bet_winner['amount']*$win_multiplier);
					$payback_address = bet_transaction_payback_address($bet_winner['create_transaction_id']);
					
					if ($payback_address) {
						$qq = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', user_id='".$payback_address['user_id']."', address_id='".$payback_address['address_id']."'";
						if ($payback_address['option_id'] > 0) $qq .= ", option_id=".$payback_address['option_id'];
						$qq .= ", create_transaction_id='".$transaction_id."', amount='".$win_amount."', create_block_id='".($mining_block_id-1)."', create_round_id='".$this->block_to_round($mining_block_id-1)."';";
						$rr = $this->app->run_query($qq);
						$output_id = $this->app->last_insert_id();
						
						$qq = "UPDATE transaction_ios SET payout_io_id='".$output_id."' WHERE io_id='".$bet_winner['io_id']."';";
						$rr = $this->app->run_query($qq);
						
						$log_text .= "Pay ".$win_amount/(pow(10,8))." coins to ".$payback_address['address']." for winning the bet.<br/>\n";
						
						$betbase_sum += $win_amount;
					}
					else $log_text .= "No payback address was found for transaction #".$bet_winner['create_transaction_id']."<br/>\n";
				}
				
				$q = "UPDATE transactions SET amount='".$betbase_sum."' WHERE transaction_id='".$transaction_id."';";
				$r = $this->app->run_query($q);
			}
			else $log_text .= "None of the bettors predicted this outcome!<br/>\n";
		}
		else {
			$log_text .= "No one placed losable bets on this round.<br/>\n";
			$q = "DELETE FROM transactions WHERE transaction_id='".$transaction_id."';";
			$r = $this->app->run_query($q);
			$transaction_id = false;
		}
		
		$returnvals[0] = $transaction_id;
		$returnvals[1] = $log_text;
		
		return $returnvals;
	}

	public function new_transaction($option_ids, $amounts, $from_user_id, $to_user_id, $block_id, $type, $io_ids, $address_ids, $remainder_address_id, $transaction_fee) {
		if (!$type || $type == "") $type = "transaction";
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			$amount += $amounts[$i];
		}
		
		if ($type == "giveaway") $instantly_mature = 1;
		else $instantly_mature = 0;
		
		$from_user = new User($this->app, $from_user_id);
		$to_user = new User($this->app, $to_user_id);
		
		$account_value = $from_user->account_coin_value($this);
		$immature_balance = $from_user->immature_balance($this);
		$mature_balance = $from_user->mature_balance($this);
		$utxo_balance = false;
		if ($io_ids) {
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", $io_ids).");";
			$r = $this->app->run_query($q);
			$utxo_balance = $r->fetch(PDO::FETCH_NUM);
			$utxo_balance = $utxo_balance[0];
		}
		
		$raw_txin = array();
		$raw_txout = array();
		$affected_input_ids = array();
		$created_input_ids = array();
		
		if ($type == "giveaway" || $type == "votebase" || $type == "coinbase") $amount_ok = true;
		else if ($utxo_balance == $amount || (!$io_ids && $amount <= $mature_balance)) $amount_ok = true;
		else $amount_ok = false;
		
		if ($amount_ok && (count($option_ids) == count($amounts) || ($type == "bet" && count($amounts) == count($address_ids)))) {
			// For real games, don't insert a tx record, it will come in via walletnotify
			if ($this->db_game['game_type'] != "real") {
				$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', fee_amount='".$transaction_fee."'";
				if ($this->db_game['game_type'] == "simulation") $q .= ", tx_hash='".$this->app->random_string(64)."'";
				if ($option_id) $q .= ", option_id=NULL";
				$q .= ", transaction_desc='".$type."', amount=".$amount.", ";
				if ($from_user_id) $q .= "from_user_id='".$from_user_id."', ";
				if ($to_user_id) $q .= "to_user_id='".$to_user_id."', ";
				if ($type == "bet") {
					$qq = "SELECT bet_round_id FROM addresses WHERE address_id='".$address_ids[0]."';";
					$rr = $this->app->run_query($qq);
					$bet_round_id = $rr->fetch(PDO::FETCH_NUM);
					$bet_round_id = $bet_round_id[0];
					$q .= "bet_round_id='".$bet_round_id."', ";
				}
				$q .= "address_id=NULL";
				if ($block_id !== false) $q .= ", block_id='".$block_id."', round_id='".$this->block_to_round($block_id)."', taper_factor='".$this->block_id_to_taper_factor($block_id)."'";
				$q .= ", time_created='".time()."';";
				$r = $this->app->run_query($q);
				$transaction_id = $this->app->last_insert_id();
			}
			
			$overshoot_amount = 0;
			$overshoot_return_addr_id = $remainder_address_id;
			
			if ($type == "giveaway" || $type == "votebase" || $type == "coinbase") {}
			else {
				$q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status='unspent' AND io.user_id='".$from_user_id."' AND io.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($this->last_block_id()-$this->db_game['maturity'])." OR io.instantly_mature=1)";
				if ($io_ids) $q .= " AND io.io_id IN (".implode(",", $io_ids).")";
				$q .= " ORDER BY io.amount ASC;";
				$r = $this->app->run_query($q);
				
				$input_sum = 0;
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				$ref_block_id = $this->last_block_id()+1;
				$ref_round_id = $this->block_to_round($ref_block_id);
				$ref_cbd = 0;
				$ref_crd = 0;
				
				while ($transaction_input = $r->fetch()) {
					if ($input_sum < $amount) {
						if ($this->db_game['game_type'] != "real") {
							$qq = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_transaction_id='".$transaction_id."'";
							if ($block_id !== false) $qq .= ", spend_status='spent', spend_block_id='".$block_id."', spend_round_id='".$this->block_to_round($block_id)."'";
							$qq .= " WHERE io_id='".$transaction_input['io_id']."';";
							$rr = $this->app->run_query($qq);
						}
						
						if (!$overshoot_return_addr_id) $overshoot_return_addr_id = intval($transaction_input['address_id']);
						
						$input_sum += $transaction_input['amount'];
						$ref_cbd += ($ref_block_id-$transaction_input['create_block_id'])*$transaction_input['amount'];
						$ref_crd += ($ref_round_id-$transaction_input['create_round_id'])*$transaction_input['amount'];
						
						if ($block_id !== false) {
							$coin_blocks_destroyed += ($block_id - $transaction_input['create_block_id'])*$transaction_input['amount'];
							$coin_rounds_destroyed += ($this->block_to_round($block_id) - $transaction_input['create_round_id'])*$transaction_input['amount'];
						}
						
						$affected_input_ids[count($affected_input_ids)] = $transaction_input['io_id'];
						
						$raw_txin[count($raw_txin)] = array(
							"txid"=>$transaction_input['tx_hash'],
							"vout"=>intval($transaction_input['out_index'])
						);
					}
				}
				
				$overshoot_amount = $input_sum - $amount;
				
				if ($this->db_game['game_type'] != "real") {
					$qq = "UPDATE transactions SET ref_block_id='".$ref_block_id."', ref_coin_blocks_destroyed='".$ref_cbd."', ref_round_id='".$ref_round_id."', ref_coin_rounds_destroyed='".$ref_crd."' WHERE transaction_id='".$transaction_id."';";
					$rr = $this->app->run_query($qq);
				}
			}
			
			$output_error = false;
			$out_index = 0;
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					if ($address_ids) {
						if (count($address_ids) == count($amounts)) $address_id = $address_ids[$out_index];
						else $address_id = $address_ids[0];
					}
					else $address_id = $to_user->user_address_id($this->db_game['game_id'], $option_ids[$out_index]);
					
					if ($address_id) {
						$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
						$r = $this->app->run_query($q);
						$address = $r->fetch();
						
						if ($this->db_game['game_type'] != "real") {
							$q = "INSERT INTO transaction_ios SET spend_status='";
							if ($instantly_mature == 1) $q .= "unspent";
							else $q .= "unconfirmed";
							$q .= "', out_index='".$out_index."', ";
							if ($to_user_id) $q .= "user_id='".$to_user_id."', ";
							if ($block_id !== false) {
								$output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								$output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								$q .= "coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', ";
								
								if ($this->db_game['payout_weight'] == "coin") $votes = floor($amounts[$out_index]/$input_sum);
								else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
								else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
								else $votes = 0;
								
								$votes = floor($votes*$this->block_id_to_taper_factor($block_id));
								
								$q .= "votes='".$votes."', ";
							}
							$q .= "instantly_mature='".$instantly_mature."', game_id='".$this->db_game['game_id']."', ";
							if ($block_id !== false) {
								$q .= "create_block_id='".$block_id."', create_round_id='".$this->block_to_round($block_id)."', ";
							}
							$q .= "address_id='".$address_id."', ";
							if ($address['option_id'] > 0) $q .= "option_id='".$address['option_id']."', ";
							$q .= "create_transaction_id='".$transaction_id."', amount='".$amounts[$out_index]."';";
							
							$r = $this->app->run_query($q);
							$created_input_ids[count($created_input_ids)] = $this->app->last_insert_id();
						}
						
						$raw_txout[$address['address']] = $amounts[$out_index]/pow(10,8);
					}
					else $output_error = true;
				}
			}
			
			if ($output_error) {
				$this->app->cancel_transaction($transaction_id, $affected_input_ids, false);
				return false;
			}
			else {
				if ($overshoot_amount > 0) {
					$out_index++;
					
					$q = "SELECT * FROM addresses WHERE address_id='".$overshoot_return_addr_id."';";
					$r = $this->app->run_query($q);
					$overshoot_address = $r->fetch();
					
					if ($this->db_game['game_type'] != "real") {
						$q = "INSERT INTO transaction_ios SET out_index='".$out_index."', spend_status='unconfirmed', game_id='".$this->db_game['game_id']."', ";
						if ($block_id !== false) {
							$overshoot_cbd = floor($coin_blocks_destroyed*($overshoot_amount/$input_sum));
							$overshoot_crd = floor($coin_rounds_destroyed*($overshoot_amount/$input_sum));
							$q .= "coin_blocks_destroyed='".$overshoot_cbd."', coin_rounds_destroyed='".$overshoot_crd."', ";
						}
						$q .= "user_id='".$from_user_id."', address_id='".$overshoot_return_addr_id."', ";
						if ($overshoot_address['option_id'] > 0) $q .= "option_id='".$overshoot_address['option_id']."', ";
						$q .= "create_transaction_id='".$transaction_id."', ";
						if ($block_id !== false) {
							$q .= "create_block_id='".$block_id."', create_round_id='".$this->block_to_round($block_id)."', ";
						}
						$q .= "amount='".$overshoot_amount."';";
						$r = $this->app->run_query($q);
						$created_input_ids[count($created_input_ids)] = $this->app->last_insert_id();
					}
					
					$raw_txout[$overshoot_address['address']] = $overshoot_amount/pow(10,8);
				}
				
				$rpc_error = false;
				
				if ($this->db_game['game_type'] == "real") {
					$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');
					try {
						$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
						$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction);
						$decoded_transaction = $coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
						$tx_hash = $decoded_transaction['txid'];
						$verified_tx_hash = $coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
						
						$this->walletnotify($coin_rpc, $tx_hash);
						$this->walletnotify($coin_rpc, $verified_tx_hash);
						$this->update_option_scores();
						
						return true;
					}
					catch (Exception $e) {
						var_dump($raw_txin);
						echo "<br/><br/>\n\n";
						var_dump($raw_txout);
						echo "<br/><br/>\n\n";
						var_dump($decoded_transaction);
						echo "<br/><br/>\n\n";
						var_dump($e);
						return false;
					}
				}
				else return $transaction_id;
			}
		}
		else return false;
	}

	public function update_option_scores() {
		$last_block_id = $this->last_block_id();
		$round_id = $this->block_to_round($last_block_id+1);
		$taper_factor = $this->block_id_to_taper_factor($last_block_id+1);
		
		$q = "UPDATE game_voting_options gvo INNER JOIN (
			SELECT option_id, SUM(amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes FROM transaction_ios 
			WHERE game_id='".$this->db_game['game_id']."' AND create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND amount > 0
			GROUP BY option_id
		) i ON gvo.option_id=i.option_id SET gvo.coin_score=i.sum_amount, gvo.coin_block_score=i.sum_cbd, gvo.coin_round_score=i.sum_crd, gvo.votes=i.sum_votes WHERE gvo.game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "UPDATE game_voting_options SET unconfirmed_coin_score=0, unconfirmed_coin_block_score=0, unconfirmed_coin_round_score=0, unconfirmed_votes=0 WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		if ($this->db_game['payout_weight'] == "coin") {
			$q = "UPDATE game_voting_options gvo INNER JOIN (
				SELECT option_id, SUM(amount) sum_amount, SUM(amount)*".$taper_factor." sum_votes FROM transaction_ios 
				WHERE game_id='".$this->db_game['game_id']."' AND create_block_id IS NULL AND amount > 0
				GROUP BY option_id
			) i ON gvo.option_id=i.option_id SET gvo.unconfirmed_coin_score=i.sum_amount, gvo.unconfirmed_votes=i.sum_votes WHERE gvo.game_id='".$this->db_game['game_id']."';";	
			$r = $this->app->run_query($q);
		}
		else if ($this->db_game['payout_weight'] == "coin_block") {
			$q = "UPDATE game_voting_options gvo INNER JOIN (
				SELECT io.option_id, SUM((t.ref_coin_blocks_destroyed+(".($last_block_id+1)."-t.ref_block_id)*t.amount)*io.amount/t.amount) sum_cbd, SUM((t.ref_coin_blocks_destroyed+(".($last_block_id+1)."-t.ref_block_id)*t.amount)*io.amount/t.amount)*".$taper_factor." sum_votes FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id
				WHERE t.game_id='".$this->db_game['game_id']."' AND io.create_block_id IS NULL AND io.amount > 0 AND t.block_id IS NULL
				GROUP BY io.option_id
			) i ON gvo.option_id=i.option_id SET gvo.unconfirmed_coin_block_score=i.sum_cbd, gvo.unconfirmed_votes=i.sum_votes WHERE gvo.game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
		else {
			$q = "UPDATE game_voting_options gvo INNER JOIN (
				SELECT io.option_id, SUM((t.ref_coin_rounds_destroyed+(".$round_id."-t.ref_round_id)*t.amount)*io.amount/t.amount) sum_crd, SUM((t.ref_coin_rounds_destroyed+(".$round_id."-t.ref_round_id)*t.amount)*io.amount/t.amount)*".$taper_factor." sum_votes FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id
				WHERE t.game_id='".$this->db_game['game_id']."' AND io.create_block_id IS NULL AND io.amount > 0 AND t.block_id IS NULL
				GROUP BY io.option_id
			) i ON gvo.option_id=i.option_id SET gvo.unconfirmed_coin_round_score=i.sum_crd, gvo.unconfirmed_votes=i.sum_votes WHERE gvo.game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
	}
	
	public function option_score_in_round($option_id, $round_id) {
		if ($this->db_game['payout_weight'] == "coin") $score_field = "amount";
		else $score_field = $this->db_game['payout_weight']."s_destroyed";
		
		$mining_block_id = $this->last_block_id()+1;
		$current_round_id = $this->block_to_round($mining_block_id);
		
		if ($current_round_id == $round_id) {
			$q = "SELECT coin_score, unconfirmed_coin_score, coin_block_score, unconfirmed_coin_block_score, coin_round_score, unconfirmed_coin_round_score, votes, unconfirmed_votes FROM game_voting_options WHERE option_id='".$option_id."' AND game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			$sums = $r->fetch();
			$confirmed_score = $sums['votes'];
			$unconfirmed_score = $sums['unconfirmed_votes'];
		}
		else {
			$q = "SELECT SUM(".$score_field."), SUM(votes) FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."' AND ";
			$q .= "(create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND create_block_id <= ".($round_id*$this->db_game['round_length']-1).") AND option_id='".$option_id."';";
			$r = $this->app->run_query($q);
			$confirmed_score = $r->fetch(PDO::FETCH_NUM);
			$confirmed_score = $confirmed_score[1];
			$unconfirmed_score = 0;
		}
		if (!$confirmed_score) $confirmed_score = 0;
		if (!$unconfirmed_score) $unconfirmed_score = 0;
		
		return array('confirmed'=>$confirmed_score, 'unconfirmed'=>$unconfirmed_score, 'sum'=>$confirmed_score+$unconfirmed_score);
	}

	public function my_votes_in_round($round_id, $user_id, $include_unconfirmed) {
		$q = "SELECT SUM(t_fees.fee_amount) FROM (SELECT t.fee_amount FROM transaction_ios io JOIN game_voting_options gvo ON io.option_id=gvo.option_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE t.game_id='".$this->db_game['game_id']."' AND io.create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND io.create_block_id <= ".($round_id*$this->db_game['round_length']-1)." AND io.user_id='".$user_id."' GROUP BY t.transaction_id) t_fees;";
		$r = $this->app->run_query($q);
		$fee_amount = $r->fetch(PDO::FETCH_NUM);
		$fee_amount = $fee_amount[0];
		
		$q = "SELECT gvo.*, SUM(io.amount), SUM(io.coin_blocks_destroyed), SUM(io.coin_rounds_destroyed), SUM(io.votes) FROM transaction_ios io JOIN game_voting_options gvo ON io.option_id=gvo.option_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.game_id='".$this->db_game['game_id']."' AND io.create_block_id >= ".((($round_id-1)*$this->db_game['round_length'])+1)." AND io.create_block_id <= ".($round_id*$this->db_game['round_length']-1)." AND io.user_id='".$user_id."' GROUP BY io.option_id ORDER BY gvo.option_id ASC;";
		$r = $this->app->run_query($q);
		$coins_voted = 0;
		$coin_blocks_voted = 0;
		$coin_rounds_voted = 0;
		$votes = 0;
		$my_votes = array();
		while ($votesum = $r->fetch()) {
			$my_votes[$votesum['option_id']]['coins'] = $votesum['SUM(io.amount)'];
			$my_votes[$votesum['option_id']]['coin_blocks'] = $votesum['SUM(io.coin_blocks_destroyed)'];
			$my_votes[$votesum['option_id']]['coin_rounds'] = $votesum['SUM(io.coin_rounds_destroyed)'];
			$my_votes[$votesum['option_id']]['votes'] = $votesum['SUM(io.votes)'];
			$coins_voted += $votesum['SUM(io.amount)'];
			$coin_blocks_voted += $votesum['SUM(io.coin_blocks_destroyed)'];
			$coin_rounds_voted += $votesum['SUM(io.coin_rounds_destroyed)'];
			$votes += $votesum['SUM(io.votes)'];
		}
		$returnvals[0] = $my_votes;
		$returnvals[1] = $coins_voted;
		$returnvals[2] = $coin_blocks_voted;
		$returnvals[3] = $coin_rounds_voted;
		$returnvals[4] = $votes;
		$returnvals['fee_amount'] = $fee_amount;
		return $returnvals;
	}

	public function my_votes_table($round_id, $user) {
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		
		$html = "";
		
		$confirmed_html = "";
		$num_confirmed = 0;
		
		$unconfirmed_html = "";
		$num_unconfirmed = 0;
		
		if ($this->db_game['payout_weight'] == "coin") $score_field = "amount";
		else $score_field = $this->db_game['payout_weight']."s_destroyed";
		
		$q = "SELECT gvo.*, t.transaction_id, t.fee_amount, io.spend_status, SUM(io.amount*t.taper_factor), SUM(io.coin_blocks_destroyed*t.taper_factor), SUM(io.coin_rounds_destroyed*t.taper_factor) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN game_voting_options gvo ON io.option_id=gvo.option_id WHERE io.game_id='".$this->db_game['game_id']."' AND (io.create_block_id > ".(($round_id-1)*$this->db_game['round_length'])." AND io.create_block_id < ".($round_id*$this->db_game['round_length']).") AND io.user_id='".$user->db_user['user_id']."' AND t.block_id=io.create_block_id GROUP BY io.option_id ORDER BY SUM(io.amount) DESC;";
		$r = $this->app->run_query($q);
		
		while ($my_vote = $r->fetch()) {
			$color = "green";
			$num_votes = $my_vote['SUM(io.'.$score_field.'*t.taper_factor)'];
			$option_scores = $this->option_score_in_round($my_vote['option_id'], $round_id);
			$expected_payout = floor(pos_reward_in_round($this->db_game, $round_id)*($num_votes/$option_scores['sum'])-$my_vote['fee_amount'])/pow(10,8);
			if ($expected_payout < 0) $expected_payout = 0;
			
			$confirmed_html .= '<div class="row">';
			$confirmed_html .= '<div class="col-sm-4 '.$color.'text">'.$my_vote['name'].'</div>';
			$confirmed_html .= '<div class="col-sm-3 '.$color.'text"><a target="_blank" href="/explorer/'.$this->db_game['url_identifier'].'/transactions/'.$my_vote['transaction_id'].'">'.$this->app->format_bignum($num_votes/pow(10,8), 2).' votes</a></div>';
			
			$payout_disp = $this->app->format_bignum($expected_payout);
			$confirmed_html .= '<div class="col-sm-5 '.$color.'text">+'.$payout_disp.' ';
			if ($payout_disp == '1') $confirmed_html .= $this->db_game['coin_name'];
			else $confirmed_html .= $this->db_game['coin_name_plural'];
			$confirmed_html .= '</div>';
			
			$confirmed_html .= "</div>\n";
			
			$num_confirmed++;
		}
		
		$q = "SELECT gvo.*, io.amount, t.transaction_id, t.fee_amount, t.amount AS transaction_amount, t.ref_block_id, t.ref_coin_blocks_destroyed, t.ref_round_id, t.ref_coin_rounds_destroyed FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN game_voting_options gvo ON io.option_id=gvo.option_id WHERE io.game_id='".$this->db_game['game_id']."' AND io.create_block_id IS NULL AND t.block_id IS NULL AND io.user_id='".$user->db_user['user_id']."' ORDER BY io.amount DESC;";
		$r = $this->app->run_query($q);
		
		while ($my_vote = $r->fetch()) {
			$color = "yellow";
			$option_scores = $this->option_score_in_round($my_vote['option_id'], $round_id);
			
			if ($this->db_game['payout_weight'] == "coin_block") {
				$transaction_cbd = $my_vote['ref_coin_blocks_destroyed'] + ((1+$last_block_id)-$my_vote['ref_block_id'])*$my_vote['transaction_amount'];
				$num_votes = floor($transaction_cbd*($my_vote['amount']/$my_vote['transaction_amount']));
			}
			else if ($this->db_game['payout_weight'] == "coin_round") {
				$transaction_crd = $my_vote['ref_coin_rounds_destroyed'] + ($current_round-$my_vote['ref_round_id'])*$my_vote['transaction_amount'];
				$num_votes = floor($transaction_crd*($my_vote['amount']/$my_vote['transaction_amount']));
			}
			else {
				$num_votes = $my_vote[$score_field];
			}
			
			$num_votes = floor($num_votes*$this->block_id_to_taper_factor($last_block_id+1));
			$expected_payout = floor(pos_reward_in_round($this->db_game, $round_id)*($num_votes/$option_scores['sum'])-$my_vote['fee_amount'])/pow(10,8);
			if ($expected_payout < 0) $expected_payout = 0;
			
			$unconfirmed_html .= '<div class="row">';
			$unconfirmed_html .= '<div class="col-sm-4 '.$color.'text">'.$my_vote['name'].'</div>';
			$unconfirmed_html .= '<div class="col-sm-3 '.$color.'text"><a target="_blank" href="/explorer/'.$this->db_game['url_identifier'].'/transactions/'.$my_vote['transaction_id'].'">'.$this->app->format_bignum($num_votes/pow(10,8), 2).' votes</a></div>';
			
			$payout_disp = $this->app->format_bignum($expected_payout);
			$unconfirmed_html .= '<div class="col-sm-5 '.$color.'text">+'.$payout_disp.' ';
			if ($payout_disp == '1') $unconfirmed_html .= $this->db_game['coin_name'];
			else $unconfirmed_html .= $this->db_game['coin_name_plural'];
			$unconfirmed_html .= '</div>';
			
			$unconfirmed_html .= "</div>\n";
			
			$num_unconfirmed++;
		}
		
		if ($num_unconfirmed + $num_confirmed > 0) {
			$html .= '
			<div class="my_votes_table">
				<div class="row my_votes_header">
					<div class="col-sm-4">'.ucwords($this->db_game['option_name']).'</div>
					<div class="col-sm-3">Amount</div>
					<div class="col-sm-5">Payout</div>
				</div>
				'.$unconfirmed_html.$confirmed_html.'
			</div>';
		}
		else $html .= "You haven't voted yet in this round.";
		
		return $html;
	}
	
	public function initialize_vote_option_details($option_id2rank, $score_sum, $user_id) {
		$html = "";
		$option_q = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."' ORDER BY option_id ASC;";
		$option_r = $this->app->run_query($option_q);
		
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		
		while ($option = $option_r->fetch()) {
			if (!$option['last_win_round']) $losing_streak = false;
			else $losing_streak = $current_round - $option['last_win_round'] - 1;
			
			$rank = $option_id2rank[$option['option_id']]+1;
			$confirmed_votes = $option[$this->db_game['payout_weight'].'_score'];
			$unconfirmed_votes = $option['unconfirmed_'.$this->db_game['payout_weight'].'_score'];
			$html .= '
			<div style="display: none;" class="modal fade" id="vote_confirm_'.$option['option_id'].'">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-body">
							<h2>Vote for '.$option['name'].'</h2>
							<div id="vote_option_details_'.$option['option_id'].'">
								'.$this->app->vote_option_details($option, $rank, $confirmed_votes, $unconfirmed_votes, $score_sum, $losing_streak).'
							</div>
							<div id="vote_details_'.$option['option_id'].'"></div>
							<div class="redtext" id="vote_error_'.$option['option_id'].'"></div>
						</div>
						<div class="modal-footer">
							<button class="btn btn-primary" id="vote_confirm_btn_'.$option['option_id'].'" onclick="add_option_to_vote('.$option['option_id'].', \''.$option['name'].'\');">Add '.$option['name'].' to my vote</button>
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>';
		}
		return $html;
	}

	public function new_block() {
		// This public function only runs for games with game_type='simulation'
		$log_text = "";
		$last_block_id = $this->last_block_id();
		
		$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_id='".($last_block_id+1)."', block_hash='".$this->app->random_string(64)."', time_created='".time()."', taper_factor='".$this->block_id_to_taper_factor($last_block_id+1)."';";
		$r = $this->app->run_query($q);
		$last_block_id = $this->app->last_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
		$r = $this->app->run_query($q);
		$block = $r->fetch();
		$last_block_id = $block['block_id'];
		$mining_block_id = $last_block_id+1;
		
		$justmined_round = $this->block_to_round($last_block_id);
		
		$log_text .= "Created block $last_block_id<br/>\n";
		
		$this->delete_unconfirmable_transactions();
		
		// Include all unconfirmed TXs in the just-mined block
		$q = "SELECT * FROM transactions WHERE transaction_desc='transaction' AND game_id='".$this->db_game['game_id']."' AND block_id IS NULL;";
		$r = $this->app->run_query($q);
		$fee_sum = 0;
		
		while ($unconfirmed_tx = $r->fetch()) {
			$coins_in = $this->app->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->app->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if ($coins_in > 0 && $coins_in >= $coins_out) {
				$fee_amount = $coins_in - $coins_out;
				
				$qq = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->app->run_query($qq);
				
				$total_coin_blocks_created = 0;
				$total_coin_rounds_created = 0;
				
				while ($input_utxo = $rr->fetch()) {
					$coin_blocks_created = ($last_block_id - $input_utxo['create_block_id'])*$input_utxo['amount'];
					$coin_rounds_created = ($justmined_round - $input_utxo['create_round_id'])*$input_utxo['amount'];
					$qqq = "UPDATE transaction_ios SET coin_blocks_created='".$coin_blocks_created."', coin_rounds_created='".$coin_rounds_created."' WHERE io_id='".$input_utxo['io_id']."';";
					$rrr = $this->app->run_query($qqq);
					$total_coin_blocks_created += $coin_blocks_created;
					$total_coin_rounds_created += $coin_rounds_created;
				}
				
				$voted_coins_out = $this->app->transaction_voted_coins_out($unconfirmed_tx['transaction_id']);
				
				$cbd_per_coin_out = floor(pow(10,8)*$total_coin_blocks_created/$voted_coins_out)/pow(10,8);
				$crd_per_coin_out = floor(pow(10,8)*$total_coin_rounds_created/$voted_coins_out)/pow(10,8);
				
				$qq = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$unconfirmed_tx['transaction_id']."' AND a.option_id > 0;";
				$rr = $this->app->run_query($qq);
				
				while ($output_utxo = $rr->fetch()) {
					$coin_blocks_destroyed = floor($cbd_per_coin_out*$output_utxo['amount']);
					$coin_rounds_destroyed = floor($crd_per_coin_out*$output_utxo['amount']);
					
					if ($this->db_game['payout_weight'] == "coin") $votes = $output_utxo['amount'];
					else if ($this->db_game['payout_weight'] == "coin_block") $votes = $coin_blocks_destroyed;
					else if ($this->db_game['payout_weight'] == "coin_round") $votes = $coin_rounds_destroyed;
					else $votes = 0;
					
					$votes = floor($votes*$this->block_id_to_taper_factor($last_block_id));
					
					$qqq = "UPDATE transaction_ios SET coin_blocks_destroyed='".$coin_blocks_destroyed."', coin_rounds_destroyed='".$coin_rounds_destroyed."', votes='".$votes."' WHERE io_id='".$output_utxo['io_id']."';";
					$rrr = $this->app->run_query($qqq);;
				}
				
				$qq = "UPDATE transactions t JOIN transaction_ios o ON t.transaction_id=o.create_transaction_id JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id SET t.block_id='".$last_block_id."', t.round_id='".$justmined_round."', t.taper_factor='".$this->block_id_to_taper_factor($last_block_id)."', o.spend_status='unspent', o.create_block_id='".$last_block_id."', o.create_round_id='".$justmined_round."', i.spend_status='spent', i.spend_block_id='".$last_block_id."', i.spend_round_id='".$justmined_round."' WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->app->run_query($qq);
				
				$fee_sum += $fee_amount;
			}
		}
		
		$mined_address = $this->create_or_fetch_address("Ex".$this->app->random_string(32), true, false, false, true);
		$mined_transaction_id = $this->new_transaction(array(false), array(pow_reward_in_round($this->db_game, $justmined_round)+$fee_sum), false, false, $last_block_id, "coinbase", false, array($mined_address['address_id']), false, 0);
		
		if ($GLOBALS['outbound_email_enabled'] && $this->db_game['game_type'] == "real") {
			// Send notifications for coins that just became available
			$q = "SELECT u.* FROM users u, transaction_ios i WHERE i.game_id='".$this->db_game['game_id']."' AND i.user_id=u.user_id AND u.notification_preference='email' AND u.notification_email != '' AND i.create_block_id='".($last_block_id - $this->db_game['maturity'])."' AND i.amount > 0 GROUP BY u.user_id;";
			$r = $this->app->run_query($q);
			while ($notify_user = $r->fetch()) {
				$account_value = $this->account_coin_value($notify_user);
				$immature_balance = $this->immature_balance($notify_user);
				$mature_balance = $this->mature_balance($notify_user);
				
				if ($mature_balance >= $account_value*$notify_user['aggregate_threshold']/100) {
					$subject = $this->app->format_bignum($mature_balance/pow(10,8))." ".$this->db_game['coin_name_plural']." are now available to vote.";
					$message = "<p>Some of your coins just became available.</p>";
					$message .= "<p>You currently have ".$this->app->format_bignum($mature_balance/pow(10,8))." coins available to vote. To cast a vote, please log in:</p>";
					$message .= '<p><a href="'.$GLOBALS['base_url'].'/wallet/">'.$GLOBALS['base_url'].'/wallet/</a></p>';
					$message .= '<p>This message was sent by '.$GLOBALS['site_domain'].'<br/>To disable these notifications, please log in and then click "Settings"';
					
					$delivery_id = mail_async($notify_user['notification_email'], $GLOBALS['site_name'], "noreply@".$GLOBALS['site_domain'], $subject, $message, "", "");
					
					$log_text .= "A notification of new coins available has been sent to ".$notify_user['notification_email'].".<br/>\n";
				}
			}
		}
		
		// Run payouts
		if ($last_block_id%$this->db_game['round_length'] == 0) {
			$log_text .= "<br/>Running payout on voting round #".$justmined_round.", it's now round ".($justmined_round+1)."<br/>\n";
			$round_voting_stats = $this->round_voting_stats_all($justmined_round);
			
			$score_sum = $round_voting_stats[0];
			$max_score_sum = $round_voting_stats[1];
			$option_id2rank = $round_voting_stats[3];
			$round_voting_stats = $round_voting_stats[2];
			
			$winning_option = FALSE;
			$winning_votesum = 0;
			$winning_score = 0;
			$rank = 1;
			for ($rank=1; $rank<=$this->db_game['num_voting_options']; $rank++) {
				$option_id = $round_voting_stats[$rank-1]['option_id'];
				$option_rank2db_id[$rank] = $option_id;
				$option_scores = $this->option_score_in_round($option_id, $justmined_round);
				
				if ($option_scores['sum'] > $max_score_sum) {}
				else if (!$winning_option && $option_scores['sum'] > 0) {
					$winning_option = $option_id;
					$winning_votesum = $option_scores['sum'];
					$winning_score = $option_scores['sum'];
				}
			}
			
			$log_text .= "Total votes: ".($score_sum/(pow(10, 8)))."<br/>\n";
			$log_text .= "Cutoff: ".($max_score_sum/(pow(10, 8)))."<br/>\n";
			
			$q = "UPDATE game_voting_options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, votes=0, unconfirmed_votes=0 WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			
			$payout_transaction_id = false;
			
			if ($winning_option) {
				$q = "UPDATE game_voting_options SET last_win_round=".$justmined_round." WHERE game_id='".$this->db_game['game_id']."' AND option_id='".$winning_option."';";
				$r = $this->app->run_query($q);
				
				$log_text .= $round_voting_stats[$option_id2rank[$winning_option]]['name']." wins with ".($winning_votesum/(pow(10, 8)))." coins voted.<br/>";
				$payout_response = $this->new_payout_transaction($justmined_round, $last_block_id, $winning_option, $winning_votesum);
				$payout_transaction_id = $payout_response[0];
				$log_text .= "Payout response: ".$payout_response[1];
				$log_text .= "<br/>\n";
			}
			else $log_text .= "No winner<br/>";
			
			if ($this->db_game['losable_bets_enabled'] == 1) {
				$betbase_response = $this->new_betbase_transaction($justmined_round, $last_block_id+1, $winning_option);
				$log_text .= $betbase_response[1];
			}
			
			$q = "INSERT INTO cached_rounds SET game_id='".$this->db_game['game_id']."', round_id='".$justmined_round."', payout_block_id='".$last_block_id."'";
			if ($payout_transaction_id) $q .= ", payout_transaction_id='".$payout_transaction_id."'";
			if ($winning_option) $q .= ", winning_option_id='".$winning_option."'";
			$q .= ", winning_score='".$winning_score."', score_sum='".$score_sum."', time_created='".time()."'";
			for ($position=1; $position<=$this->db_game['num_voting_options']; $position++) {
				$q .= ", position_".$position."='".$option_rank2db_id[$position]."'";
			}
			$q .= ";";
			$r = $this->app->run_query($q);

			if ($justmined_round >= $this->db_game['final_round']) {
				$this->set_game_completed();
			}
		}
		
		$this->update_option_scores();
		
		return $log_text;
	}

	public function set_game_completed() {
		$q = "UPDATE games SET game_status='completed', completion_datetime=NOW() WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
	}

	public function apply_user_strategies() {
		$log_text = "";
		$last_block_id = $this->last_block_id();
		$mining_block_id = $last_block_id+1;
		
		$current_round_id = $this->block_to_round($mining_block_id);
		$block_of_round = $this->block_id_to_round_index($mining_block_id);
		
		echo 'applying user strategies, block of round = '.$block_of_round.', round length: '.$this->db_game['round_length']."<br/>\n";
		if ($block_of_round != $this->db_game['round_length']) {
			$q = "SELECT * FROM users u JOIN user_games g ON u.user_id=g.user_id JOIN user_strategies s ON g.strategy_id=s.strategy_id";
			$q .= " JOIN user_strategy_blocks usb ON s.strategy_id=usb.strategy_id";
			$q .= " WHERE g.game_id='".$this->db_game['game_id']."' AND usb.block_within_round='".$block_of_round."'";
			$q .= " AND (s.voting_strategy='by_rank' OR s.voting_strategy='by_option' OR s.voting_strategy='api' OR s.voting_strategy='by_plan')";
			$q .= " ORDER BY RAND();";
			$r = $this->app->run_query($q);
			
			$log_text .= "Applying user strategies for block #".$mining_block_id." of ".$this->db_game['name']." looping through ".$r->rowCount()." users.<br/>\n";
			while ($db_user = $r->fetch()) {
				$strategy_user = new User($this->app, $db_user['user_id']);
				$user_coin_value = $strategy_user->account_coin_value($this);
				$immature_balance = $strategy_user->immature_balance($this);
				$mature_balance = $strategy_user->mature_balance($this);
				$free_balance = $mature_balance;
				$available_votes = $strategy_user->user_current_votes($this, $last_block_id, $current_round_id);
				
				$log_text .= $strategy_user->db_user['username'].": ".$this->app->format_bignum($free_balance/pow(10,8))." coins (".$mature_balance.") ".$db_user['voting_strategy']."<br/>\n";
				
				if ($free_balance > 0 && $available_votes > 0) {
					if ($db_user['voting_strategy'] == "api") {
						if ($GLOBALS['api_proxy_url']) $api_client_url = $GLOBALS['api_proxy_url'].urlencode($strategy_user->db_user['api_url']);
						else $api_client_url = $strategy_user->db_user['api_url'];
						
						$api_result = file_get_contents($api_client_url);
						$api_obj = json_decode($api_result);
						
						if ($api_obj->recommendations && count($api_obj->recommendations) > 0 && in_array($api_obj->recommendation_unit, array('coin','percent'))) {
							$input_error = false;
							$input_io_ids = array();
							
							if ($api_obj->input_utxo_ids) {
								if (count($api_obj->input_utxo_ids) > 0) {
									for ($i=0; $i<count($api_obj->input_utxo_ids); $i++) {
										if (!$input_error) {
											$utxo_id = intval($api_obj->input_utxo_ids[$i]);
											if (strval($utxo_id) === strval($api_obj->input_utxo_ids[$i])) {
												$utxo_q = "SELECT *, io.user_id AS io_user_id, a.user_id AS address_user_id FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.io_id='".$utxo_id."' AND io.game_id='".$this->db_game['game_id']."';";
												$utxo_r = $this->app->run_query($utxo_q);
												if ($utxo_r->rowCount() == 1) {
													$utxo = $utxo_r->fetch();
													if ($utxo['io_user_id'] == $strategy_user->db_user['user_id'] && $utxo['address_user_id'] == $strategy_user->db_user['user_id']) {
														if (!$utxo['spend_transaction_id'] && $utxo['spend_status'] == "unspent" && $utxo['create_block_id'] !== "") {
															$input_io_ids[count($input_io_ids)] = $utxo['io_id'];
														}
														else {
															$input_error = true;
															$log_text .= "Error, you specified an input which has already been spent.";
														}
													}
													else {
														$input_error = true;
														$log_text .= "Error, you specified an input which is not associated with your user account.";
													}
												}
												else {
													$input_error = true;
													$log_text .= "Error, an invalid transaction input was specified.";
												}
											}
											else {
												$input_error = true;
												$log_text .= "Error, an invalid transaction input was specified.";
											}
										}
									}
								}
								else {
									$input_error = true;
									$log_text .= "Error, invalid format for transaction inputs.";
								}
							}
							if (count($input_io_ids) > 0 && $input_error == false) {}
							else $input_io_ids = false;
							
							$amount_error = false;
							$amount_sum = 0;
							$option_id_error = false;
							
							$log_text .= $strategy_user->db_user['username']." has ".$mature_balance/pow(10,8)." coins available, hitting url: ".$strategy_user->db_user['api_url']."<br/>\n";
							
							foreach ($api_obj->recommendations as $recommendation) {
								if ($recommendation->recommended_amount && $recommendation->recommended_amount > 0 && friendly_intval($recommendation->recommended_amount) == $recommendation->recommended_amount) $amount_sum += $recommendation->recommended_amount;
								else $amount_error = true;
								
								$qq = "SELECT * FROM game_voting_options WHERE option_id='".$recommendation->option_id."' AND game_id='".$this->db_game['game_id']."';";
								$rr = $this->app->run_query($qq);
								if ($rr->rowCount() == 1) {}
								else $option_id_error = true;
							}
							
							if ($api_obj->recommendation_unit == "coin") {
								if ($amount_sum <= $mature_balance) {}
								else $amount_error = true;
							}
							else {
								if ($amount_sum <= 100) {}
								else $amount_error = true;
							}
							
							if ($amount_error) {
								$log_text .= "Error, an invalid amount was specified.";
							}
							else if ($option_id_error) {
								$log_text .= "Error, one of the option IDs was invalid.";
							}
							else {
								$vote_option_ids = array();
								$vote_amounts = array();
								
								foreach ($api_obj->recommendations as $recommendation) {
									if ($api_obj->recommendation_unit == "coin") $vote_amount = $recommendation->recommended_amount;
									else $vote_amount = floor($mature_balance*$recommendation->recommended_amount/100);
									
									$vote_option_id = $recommendation->option_id;
									
									$vote_option_ids[count($vote_option_ids)] = $vote_option_id;
									$vote_amounts[count($vote_amounts)] = $vote_amount;
									
									$log_text .= "Vote ".$vote_amount." for ".$vote_option_id."<br/>\n";
								}
								
								$transaction_id = $this->new_transaction($vote_option_ids, $vote_amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', $input_io_ids, false, false, false);
								
								if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
								else $log_text .= "Failed to add transaction.<br/>\n";
							}
						}
					}
					else {
						$pct_free = 100*$mature_balance/$user_coin_value;
						
						if ($pct_free >= $db_user['aggregate_threshold']) {
							$round_stats = $this->round_voting_stats_all($current_round_id);
							$score_sum = $round_stats[0];
							$ranked_stats = $round_stats[2];
							$option_id2rank = $round_stats[3];
							
							$option_pct_sum = 0;
							$skipped_pct_points = 0;
							$skipped_options = "";
							$num_options_skipped = 0;
							
							if ($db_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $db_user['by_rank_ranks']);
							
							$strategy_option_points = false;
							$qq = "SELECT * FROM user_strategy_options WHERE strategy_id='".$db_user['strategy_id']."';";
							$rr = $this->app->run_query($qq);
							while ($strategy_option = $rr->fetch()) {
								$strategy_option_points[$strategy_option['option_id']] = intval($strategy_option['pct_points']);
							}
							
							$qq = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
							$rr = $this->app->run_query($qq);
							while ($voting_option = $rr->fetch()) {
								if ($db_user['voting_strategy'] == "by_option") {
									$by_option_pct_points = 0;
									if (empty($strategy_option_points[$voting_option['option_id']])) $by_option_pct_points = 0;
									else $by_option_pct_points = $strategy_option_points[$voting_option['option_id']];
									$option_pct_sum += $by_option_pct_points;
								}
								
								if ($score_sum > 0) {
									$pct_of_votes = 100*$ranked_stats[$option_id2rank[$voting_option['option_id']]]['votes']/$score_sum;
									if ($pct_of_votes >= $db_user['min_votesum_pct'] && $pct_of_votes <= $db_user['max_votesum_pct']) {}
									else {
										$skipped_options[$voting_option['option_id']] = TRUE;
										if ($db_user == "by_option") $skipped_pct_points += $by_option_pct_points;
										else if (in_array($option_id2rank[$voting_option['option_id']], $by_rank_ranks)) $num_options_skipped++;
									}
								}
							}
							
							if ($db_user['voting_strategy'] == "by_rank") {
								$divide_into = count($by_rank_ranks)-$num_options_skipped;
								
								$coins_each = floor(($free_balance-$db_user['transaction_fee'])/$divide_into);
								$remainder_coins = ($free_balance-$db_user['transaction_fee']) - count($by_rank_ranks)*$coins_each;
								
								$log_text .= "Dividing by rank among ".$divide_into." options for ".$strategy_user->db_user['username']."<br/>\n";
								
								$option_ids = array();
								$amounts = array();
								
								$qq = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
								$rr = $this->app->run_query($qq);
								
								while ($voting_option = $rr->fetch()) {
									$rank = $option_id2rank[$voting_option['option_id']]+1;
									if (in_array($rank, $by_rank_ranks) && empty($skipped_options[$ranked_stats[$rank-1]['option_id']])) {
										$log_text .= "Vote ".round($coins_each/pow(10,8), 3)." coins for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>\n";
										
										$option_ids[count($option_ids)] = $ranked_stats[$rank-1]['option_id'];
										$amounts[count($amounts)] = $coins_each;
									}
								}
								if ($remainder_coins > 0) $amounts[count($amounts)-1] += $remainder_coins;
								
								$transaction_id = $this->new_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
								
								if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
								else $log_text .= "Failed to add transaction.<br/>\n";
							}
							else if ($db_user['voting_strategy'] == "by_option") {
								$log_text .= "Dividing by option for ".$strategy_user->db_user['username']." (".(($free_balance-$db_user['transaction_fee'])/pow(10,8))." coins)<br/>\n";
								
								$mult_factor = 1;
								if ($skipped_pct_points > 0) {
									$mult_factor = floor(pow(10,6)*$option_pct_sum/($option_pct_sum-$skipped_pct_points))/pow(10,6);
								}
								
								if ($option_pct_sum == 100) {
									$option_ids = array();
									$amounts = array();
									$amount_sum = 0;
									
									$qq = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
									$rr = $this->app->run_query($qq);
									while ($voting_option = $rr->fetch()) {
										$by_option_pct_points = 0;
										if (!empty($strategy_option_points[$voting_option['option_id']])) $by_option_pct_points = $strategy_option_points[$voting_option['option_id']];
										if (empty($skipped_options[$voting_option['option_id']]) && $by_option_pct_points > 0) {
											$effective_frac = floor(pow(10,4)*$by_option_pct_points*$mult_factor)/pow(10,6);
											$coin_amount = floor($effective_frac*($free_balance-$db_user['transaction_fee']));
											
											$log_text .= "Vote ".$by_option_pct_points."% (".round($coin_amount/pow(10,8), 3)." coins) for ".$ranked_stats[$option_id2rank[$voting_option['option_id']]]['name']."<br/>\n";
											
											$option_ids[count($option_ids)] = $voting_option['option_id'];
											$amounts[count($amounts)] = $coin_amount;
											$amount_sum += $coin_amount;
										}
									}
									if ($amount_sum < ($free_balance-$db_user['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$db_user['transaction_fee']) - $amount_sum;
									
									$transaction_id = $this->new_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
									
									if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
									else $log_text .= "Failed to add transaction.<br/>\n";
								}
							}
							else { // by_plan
								$log_text .= "Dividing by plan for ".$strategy_user->db_user['username']."<br/>\n";
								
								$qq = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$db_user['strategy_id']."' AND round_id='".$current_round_id."' AND applied=0;";
								$rr = $this->app->run_query($qq);
								
								if ($rr->rowCount() > 0) {
									$allocations = array();
									$point_sum = 0;
									
									while ($allocation = $rr->fetch()) {
										$allocations[count($allocations)] = $allocation;
										$point_sum += intval($allocation['points']);
									}
									
									$option_ids = array();
									$amounts = array();
									$amount_sum = 0;
									
									for ($i=0; $i<count($allocations); $i++) {
										$option_ids[$i] = $allocations[$i]['option_id'];
										$amount = intval(floor(($free_balance-$db_user['transaction_fee'])*$allocations[$i]['points']/$point_sum));
										$amounts[$i] = $amount;
										$amount_sum += $amount;
									}
									if ($amount_sum < ($free_balance-$db_user['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$db_user['transaction_fee']) - $amount_sum;
									
									$transaction_id = $this->new_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
									
									if ($transaction_id) {
										$log_text .= "Added transaction $transaction_id<br/>\n";
										
										for ($i=0; $i<count($allocations); $i++) {
											$qq = "UPDATE strategy_round_allocations SET applied=1 WHERE allocation_id='".$allocations[$i]['allocation_id']."';";
											$rr = $this->app->run_query($qq);
										}
									}
									else $log_text .= "Failed to add transaction.<br/>\n";
								}
							}
						}
					}
				}
			}
			$this->update_option_scores();
		}
		return $log_text;
	}

	public function ensure_game_options() {
		$qq = "SELECT * FROM voting_options vo WHERE vo.option_group_id='".$this->db_game['option_group_id']."' AND NOT EXISTS (SELECT * FROM game_voting_options gvo WHERE gvo.game_id='".$this->db_game['game_id']."' AND gvo.voting_option_id=vo.voting_option_id);";
		$rr = $this->app->run_query($qq);
		while ($option = $rr->fetch()) {
			$qqq = "INSERT INTO game_voting_options SET game_id='".$this->db_game['game_id']."', voting_option_id='".$option['voting_option_id']."'";
			if ($option['default_image_id'] > 0) $qqq .= ", image_id='".$option['default_image_id']."'";
			$qqq .= ", name='".$option['name']."', voting_character='".$option['voting_character']."';";
			$rrr = $this->app->run_query($qqq);
		}
	}
	
	public function delete_game_options() {
		$qq = "DELETE FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->app->run_query($qq);
	}

	public function delete_reset_game($delete_or_reset) {
		$q = "DELETE FROM transactions WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM blocks WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM cached_rounds WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM cached_round_options WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);

		$q = "DELETE FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$invite_user_ids = array();
		if ($delete_or_reset == "reset") {
			$q = "SELECT * FROM invitations WHERE game_id='".$this->db_game['game_id']."' AND used_user_id > 0;";
			$r = $this->app->run_query($q);
			while ($invitation = $r->fetch()) {
				$invite_user_ids[count($invite_user_ids)] = $invitation['used_user_id'];
			}
		}

		$q = "DELETE FROM invitations WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		if ($this->db_game['game_type'] == "simulation") {
			$q = "DELETE FROM addresses WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
		
		if ($delete_or_reset == "reset") {
			$this->ensure_game_options();
			if ($this->db_game['game_type'] == "simulation") {
				$q = "UPDATE games SET game_status='published' WHERE game_id='".$this->db_game['game_id']."';";
				$r = $this->app->run_query($q);
			}
			
			$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			
			$giveaway_block_id = $this->last_block_id();
			if (!$giveaway_block_id) $giveaway_block_id = 0;
			
			while ($user_game = $r->fetch()) {
				$temp_user = new User($this->app, $user_game['user_id']);
				$temp_user->generate_user_addresses($this);
			}
			
			for ($i=0; $i<count($invite_user_ids); $i++) {
				$invitation = false;
				$this->generate_invitation($this->db_game['creator_id'], $invitation, $invite_user_ids[$i]);
				$invite_game = false;
				$this->app->try_apply_invite_key($invite_user_ids[$i], $invitation['invitation_key'], $invite_game);
			}
		}
		else {
			$q = "DELETE g.*, ug.* FROM games g, user_games ug WHERE g.game_id=".$this->db_game['game_id']." AND ug.game_id=g.game_id;";
			$r = $this->app->run_query($q);
			
			$q = "DELETE s.*, sra.* FROM user_strategies s LEFT JOIN strategy_round_allocations sra ON s.strategy_id=sra.strategy_id WHERE s.game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
		return true;
	}

	public function block_id_to_round_index($block_id) {
		return (($block_id-1)%$this->db_game['round_length'])+1;
	}

	public function render_transaction($transaction, $selected_address_id, $firstcell_text) {
		$html = "";
		$html .= '<div class="row bordered_row"><div class="col-md-6">';
		$html .= '<a href="/explorer/'.$this->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="display: inline-block; max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a><br/>';
		if ($firstcell_text != "") $html .= $firstcell_text."<br/>\n";
		
		if ($transaction['transaction_desc'] == "giveaway") {
			$q = "SELECT * FROM game_giveaways WHERE transaction_id='".$transaction['transaction_id']."';";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$giveaway = $r->fetch();
				$html .= $this->app->format_bignum($giveaway['amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." were given to a player for joining.";
			}
		}
		else if ($transaction['transaction_desc'] == "votebase") {
			$payout_disp = round($transaction['amount']/pow(10,8), 2);
			$html .= "Voting Payout&nbsp;&nbsp;".$payout_disp." ";
			if ($payout_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
		}
		else if ($transaction['transaction_desc'] == "coinbase") {
			$html .= "Miner found a block.";
		}
		else {
			$qq = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id LEFT JOIN game_voting_options gvo ON a.option_id=gvo.option_id WHERE i.spend_transaction_id='".$transaction['transaction_id']."' ORDER BY i.amount DESC;";
			$rr = $this->app->run_query($qq);
			$input_sum = 0;
			while ($input = $rr->fetch()) {
				$amount_disp = $this->app->format_bignum($input['amount']/pow(10,8));
				$html .= $amount_disp."&nbsp;";
				if ($amount_disp == '1') $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
				$html .= "&nbsp; ";
				$html .= '<a class="display_address" style="';
				if ($input['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
				$html .= '" href="/explorer/'.$this->db_game['url_identifier'].'/addresses/'.$input['address'].'">'.$input['address'].'</a>';
				if ($input['name'] != "") $html .= "&nbsp;&nbsp;(".$input['name'].")";
				$html .= "<br/>\n";
				$input_sum += $input['amount'];
			}
		}
		$html .= '</div><div class="col-md-6">';
		$qq = "SELECT i.*, gvo.*, a.*, p.amount AS payout_amount FROM transaction_ios i LEFT JOIN transaction_ios p ON i.payout_io_id=p.io_id, addresses a LEFT JOIN game_voting_options gvo ON a.option_id=gvo.option_id WHERE i.create_transaction_id='".$transaction['transaction_id']."' AND i.address_id=a.address_id ORDER BY i.out_index ASC;";
		$rr = $this->app->run_query($qq);
		$output_sum = 0;
		while ($output = $rr->fetch()) {
			$html .= '<a class="display_address" style="';
			if ($output['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= '" href="/explorer/'.$this->db_game['url_identifier'].'/addresses/'.$output['address'].'">'.$output['address'].'</a>&nbsp; ';
			
			$amount_disp = $this->app->format_bignum($output['amount']/pow(10,8));
			$html .= $amount_disp."&nbsp;";
			if ($amount_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			$html .= '&nbsp; ';
			
			if ($output['name'] != "") $html .= "&nbsp;&nbsp;".$output['name'];
			if ($output['payout_amount'] > 0) $html .= '&nbsp;&nbsp;<font class="greentext">+'.$this->app->format_bignum($output['payout_amount']/pow(10,8)).'</font>';
			$html .= "<br/>\n";
			$output_sum += $output['amount'];
		}
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase" && $transaction['transaction_desc'] != "votebase") {
			$fee_disp = $this->app->format_bignum($transaction_fee/pow(10,8));
			$html .= "Transaction fee: ".$fee_disp." ";
			if ($fee_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
		}
		$html .= '</div></div>'."\n";
		
		return $html;
	}
	
	public function select_input_buttons($user_id) {
		$js = "mature_ios.length = 0;\n";
		$html = "";
		$input_buttons_html = "";
		
		$last_block_id = $this->last_block_id();
		
		$output_q = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_status='unspent' AND i.spend_transaction_id IS NULL AND a.user_id='".$user_id."' AND i.game_id='".$this->db_game['game_id']."' AND (i.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR i.instantly_mature=1)";
		if ($this->db_game['payout_weight'] == "coin_round") $output_q .= " AND i.create_round_id < ".$this->block_to_round($last_block_id+1);
		$output_q .= " ORDER BY i.io_id ASC;";
		$output_r = $this->app->run_query($output_q);
		
		$utxos = array();
		
		while ($utxo = $output_r->fetch()) {
			if (intval($utxo['create_block_id']) > 0) {} else $utxo['create_block_id'] = 0;
			
			$utxos[count($utxos)] = $utxo;
			$input_buttons_html .= '<div ';
			
			$input_buttons_html .= 'id="select_utxo_'.$utxo['io_id'].'" class="btn btn-default select_utxo" onclick="add_utxo_to_vote(\''.$utxo['io_id'].'\', '.$utxo['amount'].', '.$utxo['create_block_id'].');">';
			$input_buttons_html .= '</div>'."\n";
			
			$js .= "mature_ios.push(new mature_io(mature_ios.length, ".$utxo['io_id'].", ".$utxo['amount'].", ".$utxo['create_block_id']."));\n";
		}
		$js .= "refresh_mature_io_btns();\n";
		
		$html .= '<div id="select_input_buttons_msg"></div>'."\n";
		
		$html .= $input_buttons_html;

		$html .= '<script type="text/javascript">'.$js."</script>\n";
		
		return $html;
	}
	
	public function mature_io_ids_csv($user_id) {
		if ($user_id > 0) {
			$ids_csv = "";
			$last_block_id = $this->last_block_id();
			$io_q = "SELECT i.io_id FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_status='unspent' AND i.spend_transaction_id IS NULL AND a.user_id='".$user_id."' AND i.game_id='".$this->db_game['game_id']."' AND (i.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR i.instantly_mature = 1)";
			if ($this->db_game['payout_weight'] == "coin_round") {
				$io_q .= " AND i.create_round_id < ".$this->block_to_round($last_block_id+1);
			}
			$io_q .= " ORDER BY i.io_id ASC;";
			$io_r = $this->app->run_query($io_q);
			while ($io = $io_r->fetch(PDO::FETCH_NUM)) {
				$ids_csv .= $io[0].",";
			}
			if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, strlen($ids_csv)-1);
			return $ids_csv;
		}
		else return "";
	}
	
	public function bet_round_range() {
		$last_block_id = $this->last_block_id();
		$mining_block_within_round = $this->block_id_to_round_index($last_block_id+1);
		$current_round = $this->block_to_round($last_block_id+1);
		
		if ($mining_block_within_round <= 5) $start_round_id = $current_round;
		else $start_round_id = $current_round+1;
		$stop_round_id = $start_round_id+99;
		
		return array($start_round_id, $stop_round_id);
	}
	
	public function round_to_last_betting_block($round_id) {
		return ($round_id-1)*$this->db_game['round_length']+5;
	}
	
	public function select_bet_round($current_round) {
		$html = '<select id="bet_round" class="form-control" required="required" onchange="bet_round_changed();">';
		$html .= '<option value="">-- Please Select --</option>'."\n";
		$bet_round_range = $this->bet_round_range();
		for ($round_id=$bet_round_range[0]; $round_id<=$bet_round_range[1]; $round_id++) {
			$html .= '<option value="'.$round_id.'">Round #'.$round_id;
			if ($round_id == $current_round) $html .= " (Current round)";
			else {
				$seconds_until = floor(($round_id-$current_round)*$this->db_game['round_length']*$this->db_game['seconds_per_block']);
				$minutes_until = floor($seconds_until/60);
				$hours_until = floor($seconds_until/3600);
				$html .= " (";
				if ($hours_until > 1) $html .= "+".$hours_until." hours";
				else if ($minutes_until > 1) $html .= "+".$minutes_until." minutes";
				else $html .= "+".$seconds_until." seconds";
				$html .= ")";
			}
			$html .= "</option>\n";
		}
		$html .= "</select>\n";
		return $html;
	}

	public function burn_address_text($round_id, $winner) {
		$addr_text = "";
		if ($winner) {
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."' AND option_id='".$winner."';";
			$r = $this->app->run_query($q);
			if ($r->rowCount() == 1) {
				$option = $r->fetch();
				$addr_text .= strtolower($option['name'])."_wins";
			}
			else return false;
		}
		else {
			$addr_text .= "no_winner";
		}
		$addr_text .= "_round_".$round_id;
		
		return $addr_text;
	}

	public function get_bet_burn_address($round_id, $option_id) {
		if ($this->db_game['losable_bets_enabled'] == 1) {
			$burn_address_text = $this->burn_address_text($round_id, $option_id);
			
			$q = "SELECT * FROM addresses WHERE game_id='".$this->db_game['game_id']."' AND address='".$burn_address_text."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$burn_address = $r->fetch();
			}
			else {
				$q = "INSERT INTO addresses SET game_id='".$this->db_game['game_id']."', address='".$burn_address_text."', bet_round_id='".$round_id."'";
				if ($option_id > 0) $q .= ", bet_option_id='".$option_id."'";
				$q .= ";";
				$r = $this->app->run_query($q);
				$burn_address_id = $this->app->last_insert_id();
				
				$q = "SELECT * FROM addresses WHERE address_id='".$burn_address_id."';";
				$r = $this->app->run_query($q);
				$burn_address = $r->fetch();
			}
			return $burn_address;
		}
		else return false;
	}

	public function rounds_complete_html($max_round_id, $limit) {
		$html = "";
		
		$show_initial = false;
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		if ($max_round_id == $current_round) {
			$current_score_q = "SELECT SUM(unconfirmed_coin_block_score+coin_block_score) coin_block_score, SUM(unconfirmed_coin_score+coin_score) coin_score FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
			$current_score_r = $this->app->run_query($current_score_q);
			$current_score = $current_score_r->fetch(PDO::FETCH_NUM);
			$current_score = $current_score[0];
			if ($current_score > 0) {} else $current_score = 0;
			
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-2"><a href="/explorer/'.$this->db_game['url_identifier'].'/rounds/'.$max_round_id.'">Round #'.$max_round_id.'</a></div>';
			$html .= '<div class="col-sm-7">Not yet decided';
			$html .= '</div>';
			$html .= '<div class="col-sm-3">'.$this->app->format_bignum($current_score/pow(10,8)).' votes cast</div>';
			$html .= '</div>'."\n";
			
			if ($current_round == 1) $show_initial = true;
		}
		
		$q = "SELECT * FROM cached_rounds r LEFT JOIN game_voting_options gvo ON r.winning_option_id=gvo.option_id WHERE r.game_id='".$this->db_game['game_id']."' AND r.round_id <= ".$max_round_id." ORDER BY r.round_id DESC LIMIT ".$limit.";";
		$r = $this->app->run_query($q);
		
		$last_round_shown = 0;
		while ($cached_round = $r->fetch()) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-2"><a href="/explorer/'.$this->db_game['url_identifier'].'/rounds/'.$cached_round['round_id'].'">Round #'.$cached_round['round_id'].'</a></div>';
			$html .= '<div class="col-sm-7">';
			if ($cached_round['winning_option_id'] > 0) {
				$html .= $cached_round['name']." wins with ".$this->app->format_bignum($cached_round['winning_score']/pow(10,8))." votes (".round(100*$cached_round['winning_score']/$cached_round['score_sum'], 2)."%)";
			}
			else $html .= "No winner";
			$html .= "</div>";
			$html .= '<div class="col-sm-3">'.$this->app->format_bignum($cached_round['score_sum']/pow(10,8)).' votes cast</div>';
			$html .= "</div>\n";
			$last_round_shown = $cached_round['round_id'];
			if ($cached_round['round_id'] == 1) $show_initial = true;
		}
		
		if ($show_initial) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-2"><a href="/explorer/'.$this->db_game['url_identifier'].'/rounds/0">Round #0</a></div>';
			$html .= '<div class="col-sm-10">Initial Distribution</div>';
			$html .= '</div>';
		}
		
		$returnvals[0] = $last_round_shown;
		$returnvals[1] = $html;
		
		return $returnvals;
	}

	public function addr_text_to_option_id($addr_text) {
		$option_id = false;
		
		if (strtolower($addr_text[0].$addr_text[1]) == "ee") {
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."' AND voting_character='".strtolower($addr_text[2])."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$option = $r->fetch();
				$option_id = $option['option_id'];
			}
			else return false;
		}
		return $option_id;
	}

	public function my_bets($user) {
		$html = "";
		$q = "SELECT * FROM transactions WHERE transaction_desc='bet' AND game_id='".$this->db_game['game_id']."' AND from_user_id='".$user->db_user['user_id']."' GROUP BY bet_round_id ORDER BY bet_round_id ASC;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$last_block_id = $this->last_block_id();
			$current_round = $this->block_to_round($last_block_id+1);
			
			$html .= "<h2>You've placed bets on ".$r->rowCount()." round";
			if ($r->rowCount() != 1) $html .= "s";
			$html .= ".</h2>\n";
			$html .= '<div class="bets_table">';
			while ($bet_round = $r->fetch()) {
				$html .= '<div class="row bordered_row bet_row">';
				$disp_html = "";
				$qq = "SELECT a.*, n.*, SUM(i.amount) FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id JOIN addresses a ON i.address_id=a.address_id LEFT JOIN game_voting_options gvo ON a.bet_option_id=gvo.option_id WHERE t.game_id='".$this->db_game['game_id']."' AND t.from_user_id='".$user['user_id']."' AND t.bet_round_id='".$bet_round['bet_round_id']."' AND a.bet_round_id > 0 GROUP BY a.address_id ORDER BY SUM(i.amount) DESC;";
				$rr = $this->app->run_query($qq);
				$coins_bet_for_round = 0;
				while ($option_bet = $rr->fetch()) {
					if ($option_bet['name'] == "") $option_bet['name'] = "No Winner";
					$coins_bet_for_round += $option_bet['SUM(i.amount)'];
					$disp_html .= '<div class="">';
					$disp_html .= '<div class="col-md-5">'.$this->app->format_bignum($option_bet['SUM(i.amount)']/pow(10,8))." coins towards ".$option_bet['name'].'</div>';
					$disp_html .= '<div class="col-md-5"><a href="/explorer/'.$this->db_game['url_identifier'].'/addresses/'.$option_bet['address'].'">'.$option_bet['address'].'</a></div>';
					$disp_html .= "</div>\n";
				}
				if ($bet_round['bet_round_id'] >= $current_round) {
					$html .= "You made bets totalling ".$this->app->format_bignum($coins_bet_for_round/pow(10,8))." coins on round ".$bet_round['bet_round_id'].".";
				}
				else {
					$qq = "SELECT SUM(i.amount) FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id JOIN addresses a ON i.address_id=a.address_id WHERE t.block_id='".($bet_round['bet_round_id']*$this->db_game['round_length'])."' AND t.transaction_desc='betbase' AND a.user_id='".$user['user_id']."';";
					$rr = $this->app->run_query($qq);
					$amount_won = $rr->fetch(PDO::FETCH_NUM);
					$amount_won = $amount_won[0];
					if ($amount_won > 0) {
						$html .= "You bet ".$this->app->format_bignum($coins_bet_for_round/pow(10,8))." coins and won ".$this->app->format_bignum($amount_won/pow(10,8))." back for a ";
						if ($amount_won-$coins_bet_for_round >= 0) $html .= 'profit of <font class="greentext">+'.$this->app->format_bignum(($amount_won-$coins_bet_for_round)/pow(10,8)).'</font> coins.';
						else $html .= 'loss of <font class="redtext">'.$this->app->format_bignum(($coins_bet_for_round-$amount_won)/pow(10,8))."</font> coins.";
					}
				}
				$html .= '&nbsp;&nbsp; <a href="" onclick="$(\'#my_bets_details_'.$bet_round['bet_round_id'].'\').toggle(\'fast\'); return false;">Details</a><br/>'."\n";
				$html .= '<div id="my_bets_details_'.$bet_round['bet_round_id'].'" style="display: none;">'.$disp_html."</div>\n";
				$html .= "</div>\n";
			}
			$html .= "</div>\n";
		}
		return $html;
	}

	public function add_round_from_rpc($round_id) {
		$q = "UPDATE game_voting_options SET coin_score=0, coin_block_score=0, votes=0 WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$winning_option_id = false;
		$q = "SELECT * FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id JOIN addresses a ON a.address_id=i.address_id WHERE t.game_id='".$this->db_game['game_id']."' AND t.block_id='".$round_id*$this->db_game['round_length']."' AND t.transaction_desc='votebase' AND i.out_index=1;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) {
			$votebase_transaction = $r->fetch();
			$winning_option_id = $votebase_transaction['option_id'];
		}
		
		$q = "SELECT * FROM cached_rounds WHERE game_id='".$this->db_game['game_id']."' AND round_id='".$round_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$existing_round = $r->fetch();
			$update_insert = "update";
		}
		else $update_insert = "insert";
		
		if ($update_insert == "update") $q = "UPDATE cached_rounds SET ";
		else $q = "INSERT INTO cached_rounds SET game_id='".$this->db_game['game_id']."', round_id='".$round_id."', ";
		$q .= "payout_block_id='".($round_id*$this->db_game['round_length'])."'";
		if ($winning_option_id) $q .= ", winning_option_id='".$winning_option_id."'";
		
		$rankings = $this->round_voting_stats_all($round_id);
		
		$score_sum = $rankings[0];
		$option_id_to_rank = $rankings[3];
		$rankings = $rankings[2];
		
		$option_scores = $this->option_score_in_round($winning_option_id, $round_id);
		$q .= ", winning_score='".$option_scores['sum']."', score_sum='".$score_sum."', time_created='".time()."'";
		if ($update_insert == "update") $q .= " WHERE internal_round_id='".$existing_round['internal_round_id']."'";
		$q .= ";";
		$r = $this->app->run_query($q);
		$internal_round_id = $this->app->last_insert_id();
		
		$this->app->run_query("DELETE FROM cached_round_options WHERE round_id='".$round_id."' AND game_id='".$this->db_game['game_id']."';");
		
		for ($i=0; $i<count($rankings); $i++) {
			$qq = "INSERT INTO cached_round_options SET internal_round_id='".$internal_round_id."', round_id='".$round_id."', game_id='".$this->db_game['game_id']."', option_id='".$rankings[$i]['option_id']."', rank='".($i+1)."', score='".$rankings[$i]['votes']."';";
			$rr = $this->app->run_query($qq);
		}
	}

	public function create_or_fetch_address($address, $check_existing, $rpc, $delete_optionless, $claimable) {
		if ($check_existing) {
			$q = "SELECT * FROM addresses WHERE game_id='".$this->db_game['game_id']."' AND address='".$address."';";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				return $r->fetch();
			}
		}
		$address_option_id = $this->addr_text_to_option_id($address);
		
		if ($address_option_id > 0 || !$delete_optionless) {
			$q = "INSERT INTO addresses SET game_id='".$this->db_game['game_id']."', address='".$address."'";
			if ($address_option_id > 0) $q .= ", option_id='".$address_option_id."'";
			$q .= ", time_created='".time()."';";
			$r = $this->app->run_query($q);
			$output_address_id = $this->app->last_insert_id();
			
			if ($rpc) {
				$validate_address = $rpc->validateaddress($address);
				
				if ($validate_address['ismine']) $is_mine = 1;
				else $is_mine = 0;
				
				$q = "UPDATE addresses SET is_mine=".$is_mine;
				if ($is_mine == 1 && $GLOBALS['default_coin_winner'] && $claimable) {
					$qq = "SELECT * FROM users WHERE username=".$this->app->quote_escape($GLOBALS['default_coin_winner']).";";
					$rr = $this->app->run_query($qq);
					if ($rr->rowCount() > 0) {
						$coin_winner = $rr->fetch();
						$q .= ", user_id='".$coin_winner['user_id']."'";
					}
				}
				$q .= " WHERE address_id='".$output_address_id."';";
				$r = $this->app->run_query($q);
			}
			
			$q = "SELECT * FROM addresses WHERE address_id='".$output_address_id."';";
			$r = $this->app->run_query($q);
			
			return $r->fetch();
		}
		else return false;
	}

	public function walletnotify($coin_rpc, $tx_hash) {
		$start_time = microtime(true);
		$this->app->set_site_constant('walletnotify', $tx_hash);
		
		$html = "";
		
		if ($tx_hash != "") {
			$q = "SELECT * FROM transactions WHERE tx_hash='".$tx_hash."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() == 0) {
				$lastblock_id = $this->last_block_id();
			
				try {
					try {
						$raw_transaction = $coin_rpc->getrawtransaction($tx_hash);
						$transaction_obj = $coin_rpc->decoderawtransaction($raw_transaction);
					}
					catch (Exception $e) {
						echo "Failed to get/decode the transaction";
						die();
					}
					
					$outputs = $transaction_obj["vout"];
					$inputs = $transaction_obj["vin"];
					
					if (count($inputs) == 1 && $inputs[0]['coinbase']) {
						$transaction_type = "coinbase";
						if (count($outputs) > 1) $transaction_type = "votebase";
					}
					else $transaction_type = "transaction";
					
					$output_sum = 0;
					for ($j=0; $j<count($outputs); $j++) {
						$output_sum += pow(10,8)*$outputs[$j]["value"];
					}
					
					$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', amount='".$output_sum."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', address_id=NULL, block_id=NULL, time_created='".time()."';";
					$r = $this->app->run_query($q);
					$db_transaction_id = $this->app->last_insert_id();
					
					$q = "SELECT * FROM transactions WHERE transaction_id='".$db_transaction_id."';";
					$r = $this->app->run_query($q);
					$transaction = $r->fetch();
					
					$input_sum = 0;
					$ref_block_id = $this->last_block_id()+1;
					$ref_round_id = $this->block_to_round($ref_block_id);
					$ref_cbd = 0;
					$ref_crd = 0;
					
					for ($j=0; $j<count($inputs); $j++) {
						$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.tx_hash='".$inputs[$j]['txid']."' AND i.out_index='".$inputs[$j]['vout']."';";
						$r = $this->app->run_query($q);
						
						if ($r->rowCount() == 1) {
							$db_input = $r->fetch();
							$input_sum += $db_input['amount'];
							
							$ref_cbd += ($ref_block_id-$db_input['create_block_id'])*$db_input['amount'];
							$ref_crd += ($ref_round_id-$db_input['create_round_id'])*$db_input['amount'];
							
							$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_transaction_id='".$db_transaction_id."' WHERE io_id='".$db_input['io_id']."';";
							$r = $this->app->run_query($q);
						}
					}
					
					$q = "UPDATE transactions SET ref_block_id='".$ref_block_id."', ref_coin_blocks_destroyed='".$ref_cbd."', ref_round_id='".$ref_round_id."', ref_coin_rounds_destroyed='".$ref_crd."', fee_amount='".($input_sum-$output_sum)."' WHERE transaction_id='".$db_transaction_id."';";
					$r = $this->app->run_query($q);
					
					for ($j=0; $j<count($outputs); $j++) {
						$address = $outputs[$j]["scriptPubKey"]["addresses"][0];
						
						$claimable = false;
						if ($transaction_type == "coinbase") $claimable = true;
						
						$output_address = $this->create_or_fetch_address($address, true, $coin_rpc, false, $claimable);
						
						$q = "INSERT INTO transaction_ios SET spend_status='unconfirmed', instantly_mature=0, game_id='".$this->db_game['game_id']."'";
						$q .= ", out_index='".$j."'";
						if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
						$q .= ", address_id='".$output_address['address_id']."'";
						if ($output_address['option_id'] > 0) $q .= ", option_id=".$output_address['option_id'];
						$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."';";
						$r = $this->app->run_query($q);
					}
				}
				catch (Exception $e) {
					$html .= "Please make sure that txindex=1 is included in your EmpireCoin.conf<br/>\n";
					$html .= "Exception Error:<br/>\n";
					$html .= json_encode($e);
					die($html);
				}
			}
		}
		return $html;
	}

	public function new_game_giveaway($user_id, $type, $amount) {
		if ($type != "buyin") {
			$type = "initial_purchase";
			$amount = $this->db_game['giveaway_amount'];
		}
		
		$transaction_id = false;
		if ($amount > 0) {
			$addr_id = $this->new_nonuser_address();
			
			$addr_ids = array();
			$amounts = array();
			$option_ids = array();
			
			for ($i=0; $i<5; $i++) {
				$amounts[$i] = floor($amount/5);
				$addr_ids[$i] = $addr_id;
				$option_ids[$i] = false;
			}
			$transaction_id = $this->new_transaction($option_ids, $amounts, false, false, 0, 'giveaway', false, $addr_ids, false, 0);
		}
		
		$q = "INSERT INTO game_giveaways SET type='".$type."', game_id='".$this->db_game['game_id']."'";
		if ($transaction_id > 0) $q .= ", transaction_id='".$transaction_id."'";
		if ($user_id) $q .= ", user_id='".$user_id."', status='claimed'";
		$q .= ";";
		$r = $this->app->run_query($q);
		$giveaway_id = $this->app->last_insert_id();

		$q = "SELECT * FROM game_giveaways WHERE giveaway_id='".$giveaway_id."';";
		$r = $this->app->run_query($q);
		
		return $r->fetch();
	}

	public function generate_invitation($inviter_id, &$invitation, $user_id) {
		$q = "INSERT INTO invitations SET game_id='".$this->db_game['game_id']."', inviter_id=".$inviter_id.", invitation_key='".strtolower($this->app->random_string(32))."', time_created='".time()."'";
		if ($user_id) $q .= ", used_user_id='".$user_id."'";
		$q .= ";";
		$r = $this->app->run_query($q);
		$invitation_id = $this->app->last_insert_id();
		
		if (in_array($this->db_game['giveaway_status'], array("invite_free", "public_free"))) {
			$giveaway = $this->new_game_giveaway($user_id, 'initial_purchase', false);
			$q = "UPDATE invitations SET giveaway_id='".$giveaway['giveaway_id']."' WHERE invitation_id='".$invitation_id."';";
			$r = $this->app->run_query($q);
		}
		
		$q = "SELECT * FROM invitations WHERE invitation_id='".$invitation_id."';";
		$r = $this->app->run_query($q);
		$invitation = $r->fetch();
	}

	public function check_giveaway_available($user, &$giveaway) {
		if ($this->db_game['game_type'] == "simulation") {
			$q = "SELECT * FROM game_giveaways g JOIN transactions t ON g.transaction_id=t.transaction_id WHERE g.status='claimed' AND g.game_id='".$this->db_game['game_id']."' AND g.user_id='".$user->db_user['user_id']."';";
			$r = $this->app->run_query($q);

			if ($r->rowCount() > 0) {
				$giveaway = $r->fetch();
				return true;
			}
			else return false;
		}
		else return false;
	}

	public function try_capture_giveaway($user, &$giveaway) {
		$giveaway_available = $this->check_giveaway_available($user, $giveaway);

		if ($giveaway_available) {
			$q = "UPDATE addresses a JOIN transaction_ios io ON a.address_id=io.address_id SET a.user_id='".$user->db_user['user_id']."', io.user_id='".$user->db_user['user_id']."' WHERE io.create_transaction_id='".$giveaway['transaction_id']."';";
			$r = $this->app->run_query($q);
			
			$q = "UPDATE game_giveaways SET status='redeemed' WHERE giveaway_id='".$giveaway['giveaway_id']."';";
			$r = $this->app->run_query($q);

			return true;
		}
		else return false;
	}

	public function get_user_strategy($user_id, &$user_strategy) {
		$q = "SELECT * FROM user_strategies s JOIN user_games g ON s.strategy_id=g.strategy_id WHERE s.user_id='".$user_id."' AND g.game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) {
			$user_strategy = $r->fetch();
			return true;
		}
		else {
			$user_strategy = false;
			return false;
		}
	}
	
	public function plan_options_html($from_round, $to_round) {
		$html = "";
		for ($round=$from_round; $round<=$to_round; $round++) {
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."' ORDER BY option_id ASC;";
			$r = $this->app->run_query($q);
			$html .= '<div class="plan_row">#'.$round.": ";
			$option_index = 0;
			while ($game_option = $r->fetch()) {
				$html .= '<div class="plan_option" id="plan_option_'.$round.'_'.$option_index.'" onclick="plan_option_clicked('.$round.', '.$option_index.');">';
				$html .= '<div class="plan_option_label" id="plan_option_label_'.$round.'_'.$option_index.'">'.$game_option['name']."</div>";
				$html .= '<div class="plan_option_amount" id="plan_option_amount_'.$round.'_'.$option_index.'"></div>';
				$html .= '<input type="hidden" id="plan_option_input_'.$round.'_'.$option_index.'" name="poi_'.$round.'_'.$game_option['option_id'].'" value="" />';
				$html .= '</div>';
				$option_index++;
			}
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function paid_players_in_game() {
		$q = "SELECT COUNT(*) FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND ug.payment_required=0;";
		$r = $this->app->run_query($q);
		$num_players = $r->fetch(PDO::FETCH_NUM);
		return intval($num_players[0]);
	}
	
	public function start_game() {
		$qq = "UPDATE games SET initial_coins='".coins_in_existence($this->app, $this->db_game, false)."', game_status='running', start_time='".time()."', start_datetime=NOW() WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->app->run_query($qq);

		$qq = "SELECT * FROM user_games ug JOIN users u ON ug.game_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND u.notification_email LIKE '%@%';";
		$rr = $this->app->run_query($qq);
		while ($player = $rr->fetch()) {
			$subject = $GLOBALS['coin_brand_name']." game \"".$this->db_game['name']."\" has started.";
			$message = $this->db_game['name']." has started. If haven't already entered your votes, please log in now and start playing.<br/>\n";
			$message .= game_info_table($this->app, $this->db_game);
			$email_id = $this->app->mail_async($player['notification_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
		}
		
		if ($this->db_game['variation_id'] > 0) {
			$q = "SELECT * FROM game_types gt JOIN game_type_variations tv ON gt.game_type_id=tv.game_type_id JOIN voting_option_groups vog ON gt.option_group_id=vog.option_group_id WHERE tv.variation_id='".$this->db_game['variation_id']."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$game_variation = $r->fetch();
				$this->app->generate_open_games_by_variation($game_variation);
			}
		}
	}
	
	public function pot_value() {
		$value = $this->paid_players_in_game()*$this->db_game['invite_cost'];
		$qq = "SELECT SUM(settle_amount) FROM game_buyins WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->app->run_query($qq);
		$amt = $rr->fetch(PDO::FETCH_NUM);
		$value += $amt[0];
		return $value;
	}
	
	public function account_value_html($account_value) {
		$html = '<font class="greentext">'.$this->app->format_bignum($account_value/pow(10,8), 2).'</font> '.$this->db_game['coin_name_plural'];
		$html .= ' <font style="font-size: 12px;">(';
		$coins_in_existence = coins_in_existence($this->app, $this->db_game, false);
		if ($coins_in_existence > 0) $html .= $this->app->format_bignum(100*$account_value/$coins_in_existence)."%";
		else $html .= "0%";

		$q = "SELECT * FROM currencies WHERE currency_id='".$this->db_game['invite_currency']."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$payout_currency = $r->fetch();
			$coins_in_existence = coins_in_existence($this->app, $this->db_game, false);
			if ($coins_in_existence > 0) $payout_currency_value = $this->pot_value()*$account_value/$coins_in_existence;
			else $payout_currency_value = 0;
			
			$html .= "&nbsp;=&nbsp;<a href=\"/".$this->db_game['url_identifier']."/?action=show_escrow\">".$payout_currency['symbol'].$this->app->format_bignum($payout_currency_value)."</a>";
		}
		$html .= ")</font>";
		return $html;
	}
	
	public function send_invitation_email($to_email, &$invitation) {
		$blocks_per_hour = 3600/$this->db_game['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,8);
		$rounds_per_hour = 3600/($this->db_game['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->db_game['seconds_per_block']*$this->db_game['round_length'];
		
		if ($this->db_game['inflation'] == "linear") $miner_pct = 100*($this->db_game['pow_reward']*$this->db_game['round_length'])/($round_reward*pow(10,8));
		else $miner_pct = 100*$this->db_game['exponential_inflation_minershare'];

		$invite_currency = false;
		if ($this->db_game['invite_currency'] > 0) {
			$q = "SELECT * FROM currencies WHERE currency_id='".$this->db_game['invite_currency']."';";
			$r = $this->app->run_query($q);
			$invite_currency = $r->fetch();
		}

		$subject = "You've been invited to join ".$this->db_game['name'];
		if ($this->db_game['giveaway_status'] == "invite_pay" || $this->db_game['giveaway_status'] == "public_pay") {
			$subject .= ". Join by paying ".$this->app->format_bignum($this->db_game['invite_cost'])." ".$invite_currency['short_name']."s for ".$this->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural'].".";
		}
		else {
			$subject .= ". Get ".$this->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." for free by accepting this invitation.";
		}
		
		$message .= "<p>";
		if ($this->db_game['inflation'] == "linear") $message .= $this->db_game['name']." is a cryptocurrency which generates ".$coins_per_hour." ".$this->db_game['coin_name_plural']." per hour. ";
		else $message .= $this->db_game['name']." is a cryptocurrency with ".($this->db_game['exponential_inflation_rate']*100)."% inflation every ".$this->app->format_seconds($seconds_per_round).". ";
		$message .= $miner_pct."% is given to miners for securing the network and the remaining ".(100-$miner_pct)."% is given to players for casting winning votes. ";
		if ($this->db_game['final_round'] > 0) {
			$game_total_seconds = $seconds_per_round*$this->db_game['final_round'];
			$message .= "Once this game starts, it will last for ".$this->app->format_seconds($game_total_seconds)." (".$this->db_game['final_round']." rounds). ";
			$message .= "At the end, all ".$invite_currency['short_name']."s that have been paid in will be divided up and given out to all players in proportion to players' final balances.";
		}
		$message .= "</p>";
		
		$message .= "<p>In this game, you can vote for one of ".$this->db_game['num_voting_options']." ".$this->db_game['option_name_plural']." every ".$this->app->format_seconds($seconds_per_round).".  Team up with other players and cast your votes strategically to win coins and destroy your competitors.</p>";
		$table = str_replace('<div class="row"><div class="col-sm-5">', '<tr><td>', game_info_table($this->app, $this->db_game));
		$table = str_replace('</div><div class="col-sm-7">', '</td><td>', $table);
		$table = str_replace('</div></div>', '</td></tr>', $table);
		$message .= '<table>'.$table.'</table>';
		$message .= "<p>To start playing, accept your invitation by following <a href=\"".$GLOBALS['base_url']."/wallet/".$this->db_game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
		$message .= "<p>This message was sent to you by ".$GLOBALS['site_name']."</p>";

		$email_id = $this->app->mail_async($to_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
		
		$q = "UPDATE invitations SET sent_email_id='".$email_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
		$r = $this->app->run_query($q);
		
		return $email_id;
	}
	
	public function game_status_explanation() {
		$html = "";
		if ($this->db_game['game_status'] == "editable") $html .= "The game creator hasn't yet published this game; it's parameters can still be changed.";
		else if ($this->db_game['game_status'] == "published") {
			if ($this->db_game['start_condition'] == "players_joined") {
				$num_players = $this->paid_players_in_game();
				$players_needed = ($this->db_game['start_condition_players']-$num_players);
				if ($players_needed > 0) {
					$html .= $num_players."/".$this->db_game['start_condition_players']." players have already joined, waiting for ".$players_needed." more players.";
				}
			}
			else $html .= "This game starts at ".$this->db_game['start_datetime'];
		}
		else if ($this->db_game['game_status'] == "completed") $html .= "This game is over.";

		return $html;
	}
	
	public function game_description() {
		$html = "";
		$blocks_per_hour = 3600/$this->db_game['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,8);
		$rounds_per_hour = 3600/($this->db_game['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->db_game['seconds_per_block']*$this->db_game['round_length'];
		$coins_per_block = $this->app->format_bignum($this->db_game['pow_reward']/pow(10,8));
		
		$post_buyin_supply = $this->db_game['giveaway_amount']+coins_in_existence($this->app, $this->db_game, false);
		if ($post_buyin_supply > 0) $receive_pct = (100*$this->db_game['giveaway_amount']/$post_buyin_supply);
		else $receive_pct = 100;
		
		if ($this->db_game['giveaway_status'] == "invite_pay" || $this->db_game['giveaway_status'] == "public_pay") {
			$invite_disp = $this->app->format_bignum($this->db_game['invite_cost']);
			$html .= "To join this game, buy ".$this->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." (".round($receive_pct, 2)."% of the coins) for ".$invite_disp." ".$this->db_game['currency_short_name'];
			if ($invite_disp != '1') $html .= "s";
			$html .= ". ";
		}
		else {
			if ($this->db_game['giveaway_amount'] > 0) {
				$coin_disp = $this->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8));
				$html .= "Join this game and get ".$coin_disp." ";
				if ($coin_disp == "1") $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
				$html .= " (".round($receive_pct, 2)."% of the coins) for free. ";
			}
		}

		if ($this->db_game['game_status'] == "running") {
			$html .= "This game started ".$this->app->format_seconds(time()-$this->db_game['start_time'])." ago; ".$this->app->format_bignum(coins_in_existence($this->app, $this->db_game, false)/pow(10,8))." ".$this->db_game['coin_name_plural']."  are already in circulation. ";
		}
		else {
			if ($this->db_game['start_condition'] == "fixed_time") {
				$unix_starttime = strtotime($this->db_game['start_datetime']);
				
				$html .= "This game starts in ".$this->app->format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
			}
			else {
				$current_players = $this->paid_players_in_game();
				$html .= "This game will start when ".$this->db_game['start_condition_players']." player";
				if ($this->db_game['start_condition_players'] == 1) $html .= " joins";
				else $html .= "s have joined";
				$html .= ". ".($this->db_game['start_condition_players']-$current_players)." player";
				if ($this->db_game['start_condition_players']-$current_players == 1) $html .= " is";
				else $html .= "s are";
				$html .= " needed, ".$current_players;
				if ($current_players == 1) $html .= " has";
				else $html .= " have";
				$html .= " already joined. ";
			}
		}

		if ($this->db_game['final_round'] > 0) {
			$game_total_seconds = $seconds_per_round*$this->db_game['final_round'];
			$html .= "This game will last ".$this->db_game['final_round']." rounds (".$this->app->format_seconds($game_total_seconds)."). ";
		}
		else $html .= "This game doesn't end, but you can sell out at any time. ";

		$html .= '';
		if ($this->db_game['inflation'] == "linear") {
			$html .= "This coin has linear inflation: ".$this->app->format_bignum($round_reward)." ".$this->db_game['coin_name_plural']." are minted approximately every ".$this->app->format_seconds($seconds_per_round);
			$html .= " (".$this->app->format_bignum($coins_per_hour)." coins per hour)";
			$html .= ". In each round, ".$this->app->format_bignum($this->db_game['pos_reward']/pow(10,8))." ".$this->db_game['coin_name_plural']." are given to voters and ".$this->app->format_bignum($this->db_game['pow_reward']*$this->db_game['round_length']/pow(10,8))." ".$this->db_game['coin_name_plural']." are given to miners";
			$html .= " (".$coins_per_block." coin";
			if ($coins_per_block != 1) $html .= "s";
			$html .= " per block). ";
		}
		else $html .= "This currency grows by ".(100*$this->db_game['exponential_inflation_rate'])."% per round. ".(100 - 100*$this->db_game['exponential_inflation_minershare'])."% is given to voters and ".(100*$this->db_game['exponential_inflation_minershare'])."% is given to miners every ".$this->app->format_seconds($seconds_per_round).". ";

		$html .= "Each round consists of ".$this->db_game['round_length'].", ".str_replace(" ", "-", rtrim($this->app->format_seconds($this->db_game['seconds_per_block']), 's'))." blocks. ";
		if ($this->db_game['maturity'] > 0) {
			$html .= ucwords($this->db_game['coin_name_plural'])." are locked for ";
			$html .= $this->db_game['maturity']." block";
			if ($this->db_game['maturity'] != 1) $html .= "s";
			$html .= " when spent. ";
		}
		
		return $html;
	}
	
	public function render_game_players() {
		$html = "";
		
		$q = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND ug.payment_required=0;";
		$r = $this->app->run_query($q);
		$html .= "<h3>".$r->rowCount()." players</h3>\n";
		
		while ($temp_user_game = $r->fetch()) {
			$temp_user = new User($this->app, $temp_user_game['user_id']);
			$networth_disp = $this->app->format_bignum($temp_user->account_coin_value($this)/pow(10,8));
			
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-4"><a href="" onclick="openChatWindow('.$temp_user_game['user_id'].'); return false;">'.$temp_user_game['username'].'</a></div>';
			
			$html .= '<div class="col-sm-4">'.$networth_disp.' ';
			if ($networth_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			$html .= '</div>';
			
			$html .= '</div>';
		}
		
		return $html;
	}
	
	public function scramble_plan_allocations($strategy, $weight_map, $from_round, $to_round) {
		if (!$weight_map) $weight_map[0] = 1;
		
		$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
		$r = $this->app->run_query($q);
		
		$db_voting_options = array();
		$q = "SELECT * FROM game_voting_options WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		$num_voting_options = $r->rowCount();
		while ($db_voting_options[count($db_voting_options)] = $r->fetch()) {}
		
		for ($round_id=$from_round; $round_id<=$to_round; $round_id++) {
			$used_option_ids = false;
			for ($i=0; $i<count($weight_map); $i++) {
				$option_index = rand(0,$num_voting_options-1);
				if (empty($used_option_ids[$option_index])) {
					$points = round($weight_map[$i]*rand(1, 5));
				
					$qq = "INSERT INTO strategy_round_allocations SET strategy_id='".$strategy['strategy_id']."', round_id='".$round_id."', option_id='".$db_voting_options[$option_index]['option_id']."', points='".$points."';";
					$rr = $this->app->run_query($qq);
					$used_option_ids[$option_index] = true;
				}
			}
		}
	}

	public function coind_add_block(&$coin_rpc, $block_hash, $block_height) {
		$html = "";
		
		try {
			$lastblock_rpc = $coin_rpc->getblock($block_hash);
		}
		catch (Exception $e) {
			$this->app->set_site_constant("last_sync_start_time", "0");
			var_dump($e);
			die("RPC failed to get block $block_hash");
		}
		
		$q = "SELECT * FROM blocks WHERE block_hash='".$block_hash."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$this->app->set_site_constant("last_sync_start_time", time());
			
			$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_hash='".$block_hash."', block_id='".$block_height."', time_created='".time()."', taper_factor='".$this->block_id_to_taper_factor($block_height)."';";
			$r = $this->app->run_query($q);
			$block_within_round = $this->block_id_to_round_index($block_height);
			
			$html .= $block_height." ";

			$transaction_rpcs = false;
			
			// Transactions are sequentially looped through twice
			// This is the first loop, it creates all transactions. The 2nd verifies & deletes invalid TXs
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				
				$q = "SELECT * FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND tx_hash='".$tx_hash."';";
				$r = $this->app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$unconfirmed_tx = $r->fetch();
					
					$q = "DELETE t.*, io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
					$r = $this->app->run_query($q);
				}

				$transaction_rpc = false;
				try {
					$raw_transaction = $coin_rpc->getrawtransaction($tx_hash);
					$transaction_rpc = $coin_rpc->decoderawtransaction($raw_transaction);
					$transaction_rpcs[$i] = $transaction_rpc;
				}
				catch (Exception $e) {
					$this->app->set_site_constant("last_sync_start_time", "0");
					die("Error, transaction ".$tx_hash." was not found in block ".$block_height.".");
				}
				
				$outputs = $transaction_rpc["vout"];
				$inputs = $transaction_rpc["vin"];
				
				if (count($inputs) == 1 && $inputs[0]['coinbase']) {
					$transaction_type = "coinbase";
					if (count($outputs) > 1) $transaction_type = "votebase";
				}
				else $transaction_type = "transaction";
				
				$output_sum = 0;
				for ($j=0; $j<count($outputs); $j++) {
					$output_sum += pow(10,8)*$outputs[$j]["value"];
				}
				
				$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', amount='".$output_sum."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', address_id=NULL, block_id='".$block_height."', round_id='".$this->block_to_round($block_height)."', taper_factor='".$this->block_id_to_taper_factor($block_height)."', time_created='".time()."';";
				$r = $this->app->run_query($q);
				$db_transaction_id = $this->app->last_insert_id();
				$html .= ". ";
				
				for ($j=0; $j<count($outputs); $j++) {
					$address = $outputs[$j]["scriptPubKey"]["addresses"][0];
					
					$output_address = $this->create_or_fetch_address($address, true, $coin_rpc, false, true);
					
					$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', out_index='".$j."'";
					// Coinbases don't get updated in 2nd loop, so we need to set votes here.
					// For coin_block and coin_round payout weights, coinbase txns always have 0 votes
					if ($this->db_game['payout_weight'] == "coin" && $transaction_type == "coinbase" && $j==0 && !empty($output_address['option_id'])) {
						$q .= ", votes='".$outputs[$j]["value"]*pow(10,8)."'";
					}
					if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
					$q .= ", address_id='".$output_address['address_id']."'";
					if ($output_address['option_id'] > 0) $q .= ", option_id=".$output_address['option_id'];
					$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."', create_block_id='".$block_height."', create_round_id='".$this->block_to_round($block_height)."';";
					$r = $this->app->run_query($q);
				}
			}
			
			// Loop through and verify TXs; delete invalid ones
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				$q = "SELECT * FROM transactions WHERE tx_hash='".$tx_hash."';";
				$r = $this->app->run_query($q);
				$transaction = $r->fetch();
				
				try {
					$transaction_rpc = $transaction_rpcs[$i];
				}
				catch (Exception $e) {
					$this->app->set_site_constant("last_sync_start_time", "0");
					var_dump($e);
					die("Failed to get transaction ".$tx_hash);
				}
				
				$outputs = $transaction_rpc["vout"];
				$inputs = $transaction_rpc["vin"];
				
				$transaction_error = false;
				
				$output_sum = 0;
				for ($j=0; $j<count($outputs); $j++) {
					$output_sum += pow(10,8)*$outputs[$j]["value"];
				}
				
				$spend_io_ids = array();
				$input_sum = 0;
				
				if ($transaction['transaction_desc'] == "transaction") {
					$coin_blocks_destroyed = 0;
					$coin_rounds_destroyed = 0;
					
					for ($j=0; $j<count($inputs); $j++) {
						$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.game_id='".$this->db_game['game_id']."' AND i.spend_status='unspent' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
						$r = $this->app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$spend_io = $r->fetch();
							$spend_io_ids[$j] = $spend_io['io_id'];
							$input_sum += $spend_io['amount'];
							
							$coin_blocks_destroyed += ($block_height - $spend_io['block_id'])*$spend_io['amount'];
							$coin_rounds_destroyed += ($this->block_to_round($block_height) - $spend_io['create_round_id'])*$spend_io['amount'];
						}
						else {
							$transaction_error = true;
							$html .= "Error in block ".$block_height.", Nothing found for: ".$q."<br/>\n";
						}
					}
					
					if (!$transaction_error && $input_sum >= $output_sum) {
						if (count($spend_io_ids) > 0) {
							$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_status='spent', spend_transaction_id='".$transaction['transaction_id']."', spend_block_id='".$block_height."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
							$r = $this->app->run_query($q);
							
							$q = "UPDATE transactions SET fee_amount='".($input_sum-$output_sum)."' WHERE transaction_id='".$transaction['transaction_id']."';";
							$r = $this->app->run_query($q);
							
							for ($j=0; $j<count($outputs); $j++) {
								$q = "SELECT * FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND out_index='".$j."';";
								$r = $this->app->run_query($q);

								if ($r->rowCount() == 1) {
									$db_output = $r->fetch();
									
									$output_cbd = floor($coin_blocks_destroyed*($db_output['amount']/$input_sum));
									$output_crd = floor($coin_rounds_destroyed*($db_output['amount']*pow(10,8)/$input_sum));

									if ($this->db_game['payout_weight'] == "coin") $votes = $db_output['amount'];
									else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
									else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
									else $votes = 0;

									$votes = floor($votes*$this->block_id_to_taper_factor($block_height));
									$q = "UPDATE transaction_ios SET votes='".$votes."' WHERE io_id='".$db_output['io_id']."';";
									$r = $this->app->run_query($q);
								}
							}

							$html .= ", ";
						}
					}
					else {
						$html .= "Error in transaction #".$transaction['transaction_id']." (".$input_sum." vs ".$output_sum.")<br/>\n";
					}
				}
			}
			
			if ($block_height%$this->db_game['round_length'] == 0) $this->add_round_from_rpc($block_height/$this->db_game['round_length']);
		}
		
		return $html;
	}
	
	public function sync_coind(&$coin_rpc) {
		$html = "";
		$last_sync_start_time = (int) $this->app->get_site_constant("last_sync_start_time");
		
		if ($last_sync_start_time > time()-30) {
			$html = "Synchronization is already running, skipping...\n";
		}
		else {
$this->app->set_site_constant("last_sync_start_time", time());
			$last_block_id = $this->last_block_id();

			$startblock_q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id='".$last_block_id."';";
			$startblock_r = $this->app->run_query($startblock_q);
		
			if ($startblock_r->rowCount() == 0) {
				if ($last_block_id == 0) {
					$this->add_genesis_block($coin_rpc);
					$startblock_r = $this->app->run_query($startblock_q);
				}
				else {
					$this->app->set_site_constant("last_sync_start_time", "0");
					die("sync_coind failed, block $last_block_id is missing.\n");
				}
			}
		
			if ($startblock_r->rowCount() == 1) {
				$last_block = $startblock_r->fetch();
			
				$current_block = $coin_rpc->getblock($last_block['block_hash']);
			
				if ($current_block['confirmations'] < 0) {
					$this->app->log("Detected a chain fork at block #".$last_block['block_id']);
					
					$delete_block_height = $last_block_id;
					$delete_block = $current_block;
					$keep_looping = true;
					do {
						$prev_block = $coin_rpc->getblock($delete_block['previousblockhash']);
						if ($prev_block['confirmations'] < 0) {
							$delete_block = $prev_block;
							$delete_block_height--;
						}
						else $keep_looping = false;
					}
					while ($keep_looping);
					
					$this->app->log("Deleting blocks #".$delete_block_height." and above.");
					
					$this->delete_blocks_from_height($delete_block_height);

					$last_block_id = $this->last_block_id();

					$q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id='".$last_block_id."';";
					$r = $this->app->run_query($q);
					$last_block = $r->fetch();
	
					$current_block = $coin_rpc->getblock($last_block['block_hash']);
				}
		
				$block_height = $last_block['block_id'];
				$keep_looping = true;

				do {
					$block_height++;
			
					if (empty($current_block['nextblockhash'])) {
						$keep_looping = false;
					}
					else {
						$nextblockhash = $current_block['nextblockhash'];
						$current_block = $coin_rpc->getblock($nextblockhash);
				
						echo $this->coind_add_block($coin_rpc, $nextblockhash, $block_height);
					}
				}
				while ($keep_looping);

				$unconfirmed_txs = $coin_rpc->getrawmempool();
				$html .= "Looping through ".count($unconfirmed_txs)." unconfirmed transactions.<br/>\n";
				for ($i=0; $i<count($unconfirmed_txs); $i++) {
					$this->walletnotify($coin_rpc, $unconfirmed_txs[$i]);
				}

				$this->update_option_scores();
			}
		}
		
		return $html;
	}
	
	function round_index_to_taper_factor($round_index) {
		if ($this->db_game['payout_taper_function'] == "linear_decrease") {
			return floor(pow(10,8)*($this->db_game['round_length']-$round_index)/($this->db_game['round_length']-1))/pow(10,8);
		}
		else return 1;
	}
	
	function block_id_to_taper_factor($block_id) {
		return $this->round_index_to_taper_factor($this->block_id_to_round_index($block_id));
	}
	
	function delete_blocks_from_height($block_height) {
		echo "deleting from block #".$block_height." and up.<br/>\n";
		$this->app->run_query("DELETE FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND (block_id >= ".$block_height." OR block_id IS NULL);");
		$this->app->run_query("DELETE FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."' AND (create_block_id >= ".$block_height." OR create_block_id IS NULL);");
		$this->app->run_query("UPDATE transaction_ios SET spend_round_id=NULL, coin_blocks_created=0, coin_rounds_created=0, votes=0, spend_transaction_id=NULL, spend_count=NULL, spend_status='unspent', payout_io_id=NULL WHERE game_id='".$this->db_game['game_id']."' AND spend_block_id >= ".$block_height.";");
		
		$this->app->run_query("DELETE FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id >= ".$block_height.";");

		$round_id = $this->block_to_round($block_height);
		$this->app->run_query("DELETE FROM cached_rounds WHERE game_id='".$this->db_game['game_id']."' AND round_id >= ".$round_id.";");
		$this->app->run_query("DELETE FROM cached_round_options WHERE game_id='".$this->db_game['game_id']."' AND round_id >= ".$round_id.";");

		$this->app->run_query("UPDATE strategy_round_allocations sra JOIN user_strategies us ON us.strategy_id=sra.strategy_id SET sra.applied=0 WHERE us.game_id='".$this->db_game['game_id']."' AND sra.round_id >= ".$round_id.";");
		
		$this->update_option_scores();
	}
	
	public function add_genesis_block(&$coin_rpc) {
		$html = "";
		$genesis_hash = $coin_rpc->getblockhash(0);
		$html .= "genesis hash: ".$genesis_hash."<br/>\n";

		$rpc_block = new block($coin_rpc->getblock($genesis_hash), 0, $genesis_hash);
		$tx_hash = $rpc_block->json_obj['tx'][0];
		$genesis_transactions = new transaction($tx_hash, "", false, 0);
		
		$output_address = $this->create_or_fetch_address("genesis_address", true, false, false, false);
		
		$this->app->run_query("DELETE t.*, io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.tx_hash='".$tx_hash."' AND t.game_id='".$this->db_game['game_id']."';");
		
		$this->app->run_query("INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', amount='".$this->db_game['pow_reward']."', transaction_desc='coinbase', tx_hash='".$tx_hash."', address_id=".$output_address['address_id'].", block_id='0', time_created='".time()."';");
		$transaction_id = $this->app->last_insert_id();
		
		$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', user_id=NULL, address_id='".$output_address['address_id']."'";
		$q .= ", create_transaction_id='".$transaction_id."', amount='".$this->db_game['pow_reward']."', create_block_id='0';";
		$r = $this->app->run_query($q);
		
		$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_hash='".$genesis_hash."', block_id='0', time_created='".time()."';";
		$r = $this->app->run_query($q);
		
		$html .= "Added the genesis transaction!<br/>\n";
		
		$returnvals['log_text'] = $html;
		$returnvals['genesis_hash'] = $genesis_hash;
		$returnvals['nextblockhash'] = $rpc_block->json_obj['nextblockhash'];
		return $returnvals;
	}
}
?>
