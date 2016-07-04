<?php
class User {
	public $db_user;
	public $app;
	
	public function __construct(&$app, $user_id) {
		$this->app = $app;
		
		$q = "SELECT * FROM users WHERE user_id='".$user_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) $this->db_user = $r->fetch();
	}
	
	public function account_coin_value($game) {
		$q = "SELECT SUM(amount) FROM transaction_ios WHERE spend_status='unspent' AND game_id='".$game->db_game['game_id']."' AND user_id='".$this->db_user['user_id']."' AND create_block_id IS NOT NULL;";
		$r = $this->app->run_query($q);
		$coins = $r->fetch(PDO::FETCH_NUM);
		$coins = $coins[0];
		if ($coins > 0) return $coins;
		else return 0;
	}

	public function immature_balance($game) {
		$q = "SELECT SUM(amount) FROM transaction_ios WHERE game_id='".$game->db_game['game_id']."' AND user_id='".$this->db_user['user_id']."' AND (create_block_id > ".($game->last_block_id() - $game->db_game['maturity'])." OR create_block_id IS NULL) AND instantly_mature = 0;";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function mature_balance($game) {
		$q = "SELECT SUM(amount) FROM transaction_ios WHERE spend_status='unspent' AND spend_transaction_id IS NULL AND game_id='".$game->db_game['game_id']."' AND user_id='".$this->db_user['user_id']."' AND (create_block_id <= ".($game->last_block_id() - $game->db_game['maturity'])." OR instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function user_current_votes($game, $last_block_id, $current_round) {
		$q = "SELECT ROUND(SUM(amount)) coins, ROUND(SUM(amount*(".($last_block_id+1)."-create_block_id))) coin_blocks, ROUND(SUM(amount*(".$current_round."-create_round_id))) coin_rounds FROM transaction_ios WHERE spend_status='unspent' AND spend_transaction_id IS NULL AND game_id='".$game->db_game['game_id']."' AND user_id='".$this->db_user['user_id']."' AND (create_block_id <= ".($game->last_block_id() - $game->db_game['maturity'])." OR instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch();
		$votes = $sum[$game->db_game['payout_weight']."s"];
		$votes = floor($game->block_id_to_taper_factor($last_block_id+1)*$votes);
		if ($votes > 0) return $votes;
		else return 0;
	}
	
	public function performance_history($game, $from_round_id, $to_round_id) {
		$html = "";
		
		$q = "SELECT * FROM cached_rounds r LEFT JOIN game_voting_options gvo ON r.winning_option_id=gvo.option_id WHERE r.game_id='".$game->db_game['game_id']."' AND r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
		$r = $this->app->run_query($q);
		
		while ($round = $r->fetch()) {
			$first_voting_block_id = ($round['round_id']-1)*$game->db_game['round_length']+1;
			$last_voting_block_id = $first_voting_block_id + $game->db_game['round_length']-1;
			$score_sum = 0;
			$details_html = "";
			
			$option_scores = $game->option_score_in_round($round['winning_option_id'], $round['round_id']);
			
			$html .= '<div class="row" style="font-size: 13px;">';
			$html .= '<div class="col-sm-1">Round&nbsp;#'.$round['round_id'].'</div>';
			$html .= '<div class="col-sm-4">';
			if ($round['name'] != "") $html .= $round['name']." won with ".$this->app->format_bignum($round['winning_score']/pow(10,8))." votes";
			else $html .= "No winner";
			$html .= '</div>';
			
			$my_votes_in_round = $game->my_votes_in_round($round['round_id'], $this->db_user['user_id']);
			$my_votes = $my_votes_in_round[0];
			$coins_voted = $my_votes_in_round[1];
			
			if ($my_votes[$round['winning_option_id']] > 0) {
				if ($game->db_game['payout_weight'] == "coin") $win_text = "You correctly voted ".$this->app->format_bignum($my_votes[$round['winning_option_id']]['coins']/pow(10,8))." coins.";
				else $win_text = "You correctly cast ".$this->app->format_bignum($my_votes[$round['winning_option_id']][$game->db_game['payout_weight'].'s']/pow(10,8))." votes.";
			}
			else if ($coins_voted > 0) $win_text = "You didn't vote for the winning ".$game->db_game['option_name'].".";
			else $win_text = "You didn't cast any votes.";
			
			$html .= '<div class="col-sm-5">';
			$html .= $win_text;
			$html .= ' <a href="/explorer/'.$game->db_game['url_identifier'].'/rounds/'.$round['round_id'].'" target="_blank">Details</a>';
			$html .= '</div>';
			
			$win_amt = pos_reward_in_round($game->db_game, $round['round_id'])*$my_votes[$round['winning_option_id']][$game->db_game['payout_weight'].'s']/$option_scores['sum'];
			$payout_amt = ($win_amt - $my_votes_in_round['fee_amount'])/pow(10,8);
			
			$html .= '<div class="col-sm-2">';
			$html .= '<font title="'.$this->app->format_bignum($win_amt/pow(10,8)).' coins won, '.$this->app->format_bignum($my_votes_in_round['fee_amount']/pow(10,8)).' paid in fees" class="';
			if ($payout_amt >= 0) $html .= 'greentext';
			else $html .= 'redtext';
			
			$html .= '">';
			if ($payout_amt >= 0) $html .= '+';
			$payout_disp = $this->app->format_bignum($payout_amt);
			$html .= $payout_disp.' ';
			if ($payout_disp == '1') $html .= $game->db_game['coin_name'];
			else $html .= $game->db_game['coin_name_plural'];
			$html .= '</font>';
			$html .= '</div>';
			
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function my_last_transaction_id($game_id) {
		if ($game_id > 0) {
			$start_q = "SELECT t.transaction_id FROM transactions t, addresses a, transaction_ios i WHERE a.address_id=i.address_id AND ";
			$end_q = " AND a.user_id='".$this->db_user['user_id']."' AND i.game_id='".$game_id."' ORDER BY t.transaction_id DESC LIMIT 1;";
			
			$create_r = $this->app->run_query($start_q."i.create_transaction_id=t.transaction_id".$end_q);
			$create_trans_id = $create_r->fetch(PDO::FETCH_NUM);
			$create_trans_id = $create_trans_id[0];
			
			$spend_r = $this->app->run_query($start_q."i.spend_transaction_id=t.transaction_id".$end_q);
			$spend_trans_id = $spend_r->fetch(PDO::FETCH_NUM);
			$spend_trans_id = $spend_trans_id[0];
			
			if ($create_trans_id > $spend_trans_id) return intval($create_trans_id);
			else return intval($spend_trans_id);
		}
		else return 0;
	}
	
	public function wallet_text_stats($game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance) {
		$html = '<div class="row"><div class="col-sm-2">Available&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="greentext">';
		$html .= $this->app->format_bignum($mature_balance/pow(10,8));
		$html .= "</font> ".$game->db_game['coin_name_plural']."</div></div>\n";
		if ($game->db_game['payout_weight'] != "coin") {
			$html .= '<div class="row"><div class="col-sm-2">Votes:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($this->user_current_votes($game, $last_block_id, $current_round)/pow(10,8)).'</font> votes available</div></div>'."\n";
		}
		$html .= '<div class="row"><div class="col-sm-2">Locked&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="redtext">'.$this->app->format_bignum($immature_balance/pow(10,8)).'</font> '.$game->db_game['coin_name_plural'].'</div>';
		if ($immature_balance > 0) $html .= '<div class="col-sm-1"><a href="" onclick="$(\'#lockedfunds_details\').toggle(\'fast\'); return false;">Details</a></div>';
		$html .= "</div>\n";
		$html .= "Last block completed: #".$last_block_id.", currently mining #".($last_block_id+1)."<br/>\n";
		$html .= "Current votes count towards block ".$block_within_round."/".$game->db_game['round_length']." in round #".$current_round.".<br/>\n";
		if ($game->db_game['payout_taper_function'] != "constant") $html .= "Votes are ".round(100*$game->round_index_to_taper_factor($block_within_round),1)."% effective right now.<br/>\n";
		
		if ($immature_balance > 0) {
			$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id LEFT JOIN game_voting_options gvo ON i.option_id=gvo.option_id WHERE i.game_id='".$game->db_game['game_id']."' AND i.user_id='".$this->db_user['user_id']."' AND (i.create_block_id > ".($game->last_block_id() - $game->db_game['maturity'])." OR i.create_block_id IS NULL) ORDER BY i.io_id ASC;";
			$r = $this->app->run_query($q);
			
			$html .= '<div class="lockedfunds_details" id="lockedfunds_details">';
			while ($next_transaction = $r->fetch()) {
				$avail_block = $game->db_game['maturity'] + $next_transaction['create_block_id'] + 1;
				$seconds_to_avail = round(($avail_block - $last_block_id - 1)*$game->db_game['seconds_per_block']);
				$minutes_to_avail = round($seconds_to_avail/60);
				
				if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
				$html .= '<font class="greentext">'.$this->app->format_bignum($next_transaction['amount']/(pow(10, 8)))."</font> ";
				
				if ($next_transaction['create_block_id'] == "") {
					$html .= "coins were just ";
					if ($next_transaction['option_id'] > 0) {
						$html .= "voted for ".$next_transaction['name'];
					}
					else $html .= "spent";
					$html .= ". This transaction is not yet confirmed.";
				}
				else {
					if ($next_transaction['transaction_desc'] == "votebase") $html .= "coins in block ".$next_transaction['create_block_id'].". Coins";
					else $html .= "coins received in block #".$next_transaction['create_block_id'];
					
					$html .= " can be spent in block #".$avail_block.". (Approximately ";
					if ($minutes_to_avail > 1) $html .= $minutes_to_avail." minutes";
					else $html .= $seconds_to_avail." seconds";
					$html .= "). ";
					if ($next_transaction['option_id'] > 0) {
						$html .= "You voted for ".$next_transaction['name']." in round #".$game->block_to_round($next_transaction['create_block_id']).". ";
					}
				}
				$html .= '(tx: <a target="_blank" href="/explorer/'.$game->db_game['url_identifier'].'/transactions/'.$next_transaction['tx_hash'].'">'.$next_transaction['transaction_id']."</a>)<br/>\n";
			}
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function user_address_id($game_id, $option_id) {
		$q = "SELECT * FROM addresses WHERE game_id='".$game_id."' AND user_id='".$this->db_user['user_id']."'";
		if ($option_id) $q .= " AND option_id='".$option_id."'";
		else $q .= " AND option_id IS NULL";
		$q .= ";";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$address = $r->fetch();
			return $address['address_id'];
		}
		else return false;
	}

	public function ensure_user_in_game($game_id) {
		$game = new Game($this->app, $game_id);

		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$this->db_user['user_id']."' AND ug.game_id='".$game_id."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "INSERT INTO user_games SET user_id='".$this->db_user['user_id']."', game_id='".$game_id."'";
			if ($this->db_user['bitcoin_address_id'] > 0) $q .= ", bitcoin_address_id='".$this->db_user['bitcoin_address_id']."'";
			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") $q .= ", payment_required=1";
			$q .= ";";
			$r = $this->app->run_query($q);
			$user_game_id = $this->app->last_insert_id();
			
			$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_game_id='".$user_game_id."';";
			$r = $this->app->run_query($q);
			$user_game = $r->fetch();
		}
		else {
			$user_game = $r->fetch();
		}
		
		if ($user_game['strategy_id'] > 0) {}
		else {
			$q = "INSERT INTO user_strategies SET voting_strategy='by_plan', game_id='".$game_id."', user_id='".$user_game['user_id']."';";
			$r = $this->app->run_query($q);
			$strategy_id = $this->app->last_insert_id();
			
			$q = "SELECT * FROM user_strategies WHERE strategy_id='".$strategy_id."';";
			$r = $this->app->run_query($q);
			$strategy = $r->fetch();
			
			$q = "INSERT INTO user_strategy_blocks SET strategy_id='".$strategy_id."', block_within_round='1';";
			$r = $this->app->run_query($q);
			
			$scramble_from_round = 1;
			$scramble_to_round = $scramble_from_round+19;
			if ($game->db_game['final_round'] > 0) {
				$scramble_to_round = $game->db_game['final_round'];
				if ($scramble_to_round > 100) $scramble_to_round = 100;
			}
			
			$game->scramble_plan_allocations($strategy, array(0=>1, 1=>0.5), $scramble_from_round, $scramble_to_round);
			
			$q = "UPDATE user_games SET strategy_id='".$strategy_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
			$r = $this->app->run_query($q);
		}
		
		if ($game->db_game['game_status'] == "published" && $game->db_game['start_condition'] == "num_players") {
			$num_players = $game->paid_players_in_game();
			if ($num_players >= $game->db_game['start_condition_players']) {
				$game->start_game();
			}
		}

		$this->generate_user_addresses($game);
	}

	public function log_user_in(&$redirect_url, $viewer_id) {
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$this->db_user['user_id']."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() == 0) {
				$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$this->db_user['user_id']."';";
				$r = $this->app->run_query($q);
			}
		}
		
		$session_key = session_id();
		$expire_time = time()+3600*24;
		
		$q = "INSERT INTO user_sessions SET user_id='".$this->db_user['user_id']."', session_key='".$session_key."', login_time='".time()."', expire_time='".$expire_time."'";
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q .= ", ip_address='".$_SERVER['REMOTE_ADDR']."'";
		}
		$q .= ";";
		$r = $this->app->run_query($q);
		
		$q = "UPDATE users SET logged_in=1";
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q .= ", ip_address='".$_SERVER['REMOTE_ADDR']."'";
		}
		$q .= " WHERE user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
		
		if (!empty($_REQUEST['invite_key'])) {
			$this->app->try_apply_invite_key($this->db_user['user_id'], $_REQUEST['invite_key']);
		}
		
		if (!empty($_REQUEST['redirect_id'])) {
			$redirect_url_id = intval($_REQUEST['redirect_id']);
			$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
			$r = $this->app->run_query($q);
		
			if ($r->rowCount() == 1) {
				$redirect_url = $r->fetch();
			}
		}
	}
	public function user_in_game($game_id) {
		$q = "SELECT * FROM user_games WHERE user_id='".$this->db_user['user_id']."' AND game_id='".$game_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) return true;
		else return false;
	}
	public function user_can_invite_game($db_game) {
		if ($db_game['giveaway_status'] == "invite_free" || $db_game['giveaway_status'] == "invite_pay") {
			if ($this->db_user['user_id'] == $db_game['creator_id']) return true;
			else return false;
		}
		else if ($db_game['giveaway_status'] == "public_pay" || $db_game['giveaway_status'] == "public_free") return true;
		else return false;
	}
	public function count_user_games_created() {
		$q = "SELECT * FROM games WHERE creator_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
		$num_games = $r->rowCount();
		return $num_games;
	}
	
	public function new_game_permission() {
		$games_created_by_user = $this->count_user_games_created();
		if ((string)$GLOBALS['new_games_per_user'] == "unlimited") return true;
		else if ($games_created_by_user < $this->db_user['authorized_games']) return true;
		else return false;
	}

	public function user_buyin_limit($game) {
		$q = "SELECT COUNT(*), SUM(pay_amount), SUM(settle_amount) FROM game_buyins WHERE user_id='".$this->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."' AND status IN ('confirmed','settled');";
		$r = $this->app->run_query($q);
		$buyin_stats = $r->fetch();
		$user_buyin_total = $buyin_stats['SUM(settle_amount)'];
		
		$q = "SELECT COUNT(*), SUM(pay_amount), SUM(settle_amount) FROM game_buyins WHERE game_id='".$game->db_game['game_id']."' AND status IN ('confirmed','settled');";
		$r = $this->app->run_query($q);
		$buyin_stats = $r->fetch();
		$game_buyin_total = $buyin_stats['SUM(settle_amount)'];
		
		$returnvals['user_buyin_total'] = $user_buyin_total;
		$returnvals['game_buyin_total'] = $game_buyin_total;
		
		if ($game->db_game['buyin_policy'] == "unlimited") {
			$user_buyin_limit = false;
		}
		else if ($game->db_game['buyin_policy'] == "per_user_cap") {
			$user_buyin_limit = max(0, $game->db_game['per_user_buyin_cap']-$user_buyin_total);
		}
		else if ($game->db_game['buyin_policy'] == "game_cap") {
			$user_buyin_limit = max(0, $game->db_game['game_buyin_cap']-$game_buyin_total);
		}
		else if ($game->db_game['buyin_policy'] == "game_and_user_cap") {
			$user_buyin_limit = max(0, $game->db_game['game_buyin_cap']-$game_buyin_total);
			$user_buyin_limit = min($user_buyin_limit, $game->db_game['per_user_buyin_cap']-$user_buyin_total);
		}
		else die("Invalid buy-in policy.");
		
		$returnvals['user_buyin_limit'] = $user_buyin_limit;
		
		return $returnvals;
	}

	public function generate_user_addresses($game) {
		$q = "SELECT * FROM game_voting_options gvo WHERE game_id='".$game->db_game['game_id']."' AND NOT EXISTS(SELECT * FROM addresses a WHERE a.user_id='".$this->db_user['user_id']."' AND a.game_id='".$game->db_game['game_id']."' AND a.option_id=gvo.option_id) ORDER BY gvo.option_id ASC;";
		$r = $this->app->run_query($q);
		
		// Try to give the user voting addresses for all options in this game
		while ($option = $r->fetch()) {
			if ($game->db_game['game_type'] == "real") {
				$qq = "SELECT * FROM addresses WHERE option_id='".$option['option_id']."' AND game_id='".$game->db_game['game_id']."' AND is_mine=1 AND user_id IS NULL;";
				$rr = $this->app->run_query($qq);
				
				if ($rr->rowCount() > 0) {
					$address = $rr->fetch();
					
					$qq = "UPDATE addresses SET user_id='".$this->db_user['user_id']."' WHERE address_id='".$address['address_id']."';";
					$rr = $this->app->run_query($qq);
				}
			}
			else {
				$new_address = "E";
				$rand1 = rand(0, 1);
				$rand2 = rand(0, 1);
				if ($rand1 == 0) $new_address .= "e";
				else $new_address .= "E";
				if ($rand2 == 0) $new_address .= strtoupper($option['voting_character']);
				else $new_address .= $option['voting_character'];
				$new_address .= $this->app->random_string(31);
				
				$qq = "INSERT INTO addresses SET game_id='".$game->db_game['game_id']."', option_id='".$option['option_id']."', user_id='".$this->db_user['user_id']."', address='".$new_address."', time_created='".time()."';";
				$rr = $this->app->run_query($qq);
			}
		}
		
		// Make sure the user has a non-voting address
		$q = "SELECT * FROM addresses WHERE option_id IS NULL AND game_id='".$game->db_game['game_id']."' AND user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			if ($game->db_game['game_type'] == "real") {
				$q = "SELECT * FROM addresses WHERE option_id IS NULL AND game_id='".$game->db_game['game_id']."' AND is_mine=1 AND user_id IS NULL;";
				$r = $this->app->run_query($q);
				if ($r->rowCount() > 0) {
					$address = $r->fetch();
					
					$q = "UPDATE addresses SET user_id='".$game->db_game['game_id']."' WHERE address_id='".$address['address_id']."';";
					$r = $this->app->run_query($q);
				}
			}
			else {
				$new_address = "Ex";
				$new_address .= $this->app->random_string(32);
				
				$qq = "INSERT INTO addresses SET game_id='".$game->db_game['game_id']."', user_id='".$this->db_user['user_id']."', address='".$new_address."', time_created='".time()."';";
				$rr = $this->app->run_query($qq);
			}
		}
	}

	public function set_user_active() {
		$q = "UPDATE users SET logged_in=1, last_active='".time()."' WHERE user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
	}
	
	public function save_plan_allocations($user_strategy, $from_round, $to_round) {
		if ($from_round > 0 && $to_round > 0 && $to_round >= $from_round) {
			$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
			$r = $this->app->run_query($q);
			
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$user_strategy['game_id']."';";
			$r = $this->app->run_query($q);
			while ($gvo = $r->fetch()) {
				for ($round_id=$from_round; $round_id<=$to_round; $round_id++) {
					$points = intval($_REQUEST['poi_'.$round_id.'_'.$gvo['option_id']]);
					if ($points > 0) {
						$qq = "INSERT INTO strategy_round_allocations SET strategy_id='".$user_strategy['strategy_id']."', round_id='".$round_id."', option_id='".$gvo['option_id']."', points='".$points."';";
						$rr = $this->app->run_query($qq);
					}
				}
			}
		}
	}
}
?>
