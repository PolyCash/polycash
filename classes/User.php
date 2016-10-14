<?php
class User {
	public $db_user;
	public $app;
	
	public function __construct(&$app, $user_id) {
		$this->app = $app;
		
		$q = "SELECT * FROM users WHERE user_id='".$user_id."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 1) $this->db_user = $r->fetch();
		else throw new Exception("Failed to load user #".$user_id);
	}
	
	public function account_coin_value($game) {
		$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.spend_status='unspent' AND io.user_id='".$this->db_user['user_id']."' AND io.create_block_id IS NOT NULL;";
		$r = $this->app->run_query($q);
		$coins = $r->fetch(PDO::FETCH_NUM);
		$coins = $coins[0];
		if ($coins > 0) return $coins;
		else return 0;
	}

	public function immature_balance($game) {
		$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.user_id='".$this->db_user['user_id']."' AND (io.create_block_id > ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR io.create_block_id IS NULL) AND gio.instantly_mature = 0;";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function mature_balance($game) {
		$q = "SELECT SUM(colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND gio.game_id='".$game->db_game['game_id']."' AND io.user_id='".$this->db_user['user_id']."' AND (io.create_block_id <= ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR gio.instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function user_current_votes($game, $last_block_id, $current_round) {
		$q = "SELECT ROUND(SUM(gio.colored_amount)) coins, ROUND(SUM(gio.colored_amount*(".($last_block_id+1)."-io.create_block_id))) coin_blocks, ROUND(SUM(gio.colored_amount*(".$current_round."-gio.create_round_id))) coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND gio.game_id='".$game->db_game['game_id']."' AND io.user_id='".$this->db_user['user_id']."' AND (io.create_block_id <= ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR gio.instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch();
		$votes = $sum[$game->db_game['payout_weight']."s"];
		if ($votes > 0) return $votes;
		else return 0;
	}
	
	public function performance_history($game, $from_round_id, $to_round_id) {
		$html = "";
		
		$q = "SELECT e.event_index, r.*, real_winner.name AS real_winner_name, derived_winner.name AS derived_winner_name FROM event_outcomes r JOIN events e ON r.event_id=e.event_id LEFT JOIN options real_winner ON r.winning_option_id=real_winner.option_id LEFT JOIN options derived_winner ON r.derived_winning_option_id=derived_winner.option_id WHERE e.game_id='".$game->db_game['game_id']."' AND r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
		$r = $this->app->run_query($q);
		
		while ($event_outcome = $r->fetch()) {
			$event = new Event($game, false, $event_outcome['event_id']);
			$first_voting_block_id = ($event_outcome['round_id']-1)*$game->db_game['round_length']+1;
			$last_voting_block_id = $first_voting_block_id + $game->db_game['round_length']-1;
			$sum_votes = 0;
			$details_html = "";
			
			$option_votes = $event->option_votes_in_round($event_outcome['winning_option_id'], $event_outcome['round_id']);
			
			$html .= '<div class="row" style="font-size: 13px;">';
			$html .= '<div class="col-sm-3">'.$event->db_event['event_name'].'</div>';
			$html .= '<div class="col-sm-4">';
			if ($event_outcome['real_winner_name'] != "") {
				$html .= $event_outcome['real_winner_name']." with ".$this->app->format_bignum($event_outcome['winning_votes']/pow(10,8))." votes";
				if ($event_outcome['derived_winner_name'] != "" && $event_outcome['derived_winner_name'] != $event_outcome['real_winner_name']) $html .= " (Should have been ".$event_outcome['derived_winner_name']." with ".$this->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes)";
			}
			else {
				if ($event_outcome['derived_winner_name'] != "") $html .= $event_outcome['derived_winner_name']." won with ".$this->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes";
				else $html .= "No winner";
			}
			$html .= '</div>';
			
			$my_votes_in_round = $event->my_votes_in_round($event_outcome['round_id'], $this->db_user['user_id'], false);
			$my_votes = $my_votes_in_round[0];
			$coins_voted = $my_votes_in_round[1];
			
			if (!empty($my_votes[$event_outcome['winning_option_id']])) {
				if ($game->db_game['payout_weight'] == "coin") $win_text = "You correctly voted ".$this->app->format_bignum($my_votes[$event_outcome['winning_option_id']]['coins']/pow(10,8))." coins.";
				else $win_text = "You correctly cast ".$this->app->format_bignum($my_votes[$event_outcome['winning_option_id']][$game->db_game['payout_weight'].'s']/pow(10,8))." votes.";
			}
			else if ($coins_voted > 0) $win_text = "You didn't vote for the winning ".$game->db_game['option_name'].".";
			else $win_text = "You didn't cast any votes.";
			
			$html .= '<div class="col-sm-3">';
			$html .= $win_text;
			$html .= ' <a href="/explorer/'.$game->db_game['url_identifier'].'/events/'.($event_outcome['event_index']+1).'" target="_blank">Details</a>';
			$html .= '</div>';
			
			if (empty($event_outcome['winning_option_id'])) {
				$win_amt = 0;
				$payout_amt = 0;
			}
			else {
				if (empty($my_votes[$event_outcome['winning_option_id']])) $win_amt_temp = 0;
				else $win_amt_temp = $event->event_pos_reward_in_round($event_outcome['round_id'])*$my_votes[$event_outcome['winning_option_id']]['votes'];
				if ($option_votes['sum'] > 0) $win_amt = $win_amt_temp/$option_votes['sum'];
				else $win_amt = 0;
				$payout_amt = ($win_amt - $my_votes_in_round['fee_amount'])/pow(10,8);
			}
			
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
			$spend_q = "SELECT io.spend_transaction_id FROM addresses a JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE";
			$spend_q .= " a.user_id='".$this->db_user['user_id']."' AND gio.game_id='".$game_id."' ORDER BY io.spend_transaction_id DESC LIMIT 1;";
			$spend_r = $this->app->run_query($spend_q);
			$spend_trans_id = $spend_r->fetch(PDO::FETCH_NUM);
			$spend_trans_id = $spend_trans_id[0];
			
			return (int) $spend_trans_id;
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
		$html .= "Last block completed: <a href=\"/explorer/".$game->db_game['url_identifier']."/blocks/".$last_block_id."\">#".$last_block_id."</a>, currently mining #".($last_block_id+1)."<br/>\n";
		$html .= "Current votes count towards block ".$block_within_round."/".$game->db_game['round_length']." in round #".$current_round.".<br/>\n";
		//if ($game->db_game['vote_effectiveness_function'] != "constant") $html .= "Votes are ".round(100*$game->round_index_to_effectiveness_factor($block_within_round),1)."% effective right now.<br/>\n";
		
		if ($immature_balance > 0) {
			$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id LEFT JOIN options gvo ON i.option_id=gvo.option_id WHERE i.game_id='".$game->db_game['game_id']."' AND i.user_id='".$this->db_user['user_id']."' AND (i.create_block_id > ".($game->last_block_id() - $game->db_game['maturity'])." OR i.create_block_id IS NULL) ORDER BY i.io_id ASC;";
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
	
	public function user_address_id($game_id, $option_index, $option_id) {
		$q = "SELECT * FROM addresses WHERE user_id='".$this->db_user['user_id']."'";
		if ($option_index !== false) $q .= " AND option_index='".$option_index."'";
		else if ($option_id) {
			$db_option = $this->app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';")->fetch();
			$q .= " AND option_index='".$db_option['option_index']."'";
		}
		else $q .= " AND option_index IS NULL";
		$q .= ";";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$address = $r->fetch();
			return $address['address_id'];
		}
		else return false;
	}

	public function ensure_user_in_game($game_id) {
		$db_game = $this->app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($this->app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);

		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$this->db_user['user_id']."' AND ug.game_id='".$game_id."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "INSERT INTO user_games SET user_id='".$this->db_user['user_id']."', game_id='".$game_id."'";
			if ($this->db_user['bitcoin_address_id'] > 0) $q .= ", bitcoin_address_id='".$this->db_user['bitcoin_address_id']."'";
			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") $q .= ", payment_required=1";
			if (strpos($this->db_user['notification_email'], '@')) $q .= ", notification_preference='email'";
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
			$q = "INSERT INTO user_strategies SET voting_strategy='manual', game_id='".$game_id."', user_id='".$user_game['user_id']."';";
			$r = $this->app->run_query($q);
			$strategy_id = $this->app->last_insert_id();
			
			$q = "SELECT * FROM user_strategies WHERE strategy_id='".$strategy_id."';";
			$r = $this->app->run_query($q);
			$strategy = $r->fetch();
			
			for ($block=1; $block<$game->db_game['round_length']; $block++) {
				$q = "INSERT INTO user_strategy_blocks SET strategy_id='".$strategy_id."', block_within_round='".$block."';";
				$r = $this->app->run_query($q);
			}
			
			/*$scramble_from_round = 1;
			$scramble_to_round = $scramble_from_round+19;
			if ($game->db_game['final_round'] > 0) {
				$scramble_to_round = $game->db_game['final_round'];
				if ($scramble_to_round > 100) $scramble_to_round = 100;
			}
			
			$game->scramble_plan_allocations($strategy, array(0=>1, 1=>0.5), $scramble_from_round, $scramble_to_round);*/
			
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
		
		$this->ensure_currency_accounts();
		
		if (!empty($_REQUEST['redirect_id'])) {
			$redirect_url_id = intval($_REQUEST['redirect_id']);
			$q = "SELECT * FROM redirect_urls WHERE redirect_url_id=".$this->app->quote_escape($redirect_url_id).";";
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

	public function user_buyin_limit(&$game) {
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
	
	public function generate_user_addresses(&$game) {
		$option_index_range = $game->option_index_range();
		
		// Try to give the user voting addresses for all options in this game
		for ($option_index=$option_index_range[0]; $option_index<=$option_index_range[1]; $option_index++) {
			// Check if user already has a voting address for this option_index
			$qq = "SELECT * FROM addresses WHERE option_index='".$option_index."' AND user_id='".$this->db_user['user_id']."';";
			$rr = $this->app->run_query($qq);
			
			if ($rr->rowCount() == 0) {
				// If not, check if there is an unallocated address available to give to the user
				$qq = "SELECT * FROM addresses WHERE option_index='".$option_index."' AND is_mine=1 AND user_id IS NULL;";
				$rr = $this->app->run_query($qq);
				
				if ($rr->rowCount() > 0) {
					$address = $rr->fetch();
					
					$qq = "UPDATE addresses SET user_id='".$this->db_user['user_id']."' WHERE address_id='".$address['address_id']."';";
					$rr = $this->app->run_query($qq);
				}
			}
		}
		
		// Make sure the user has a non-voting address
		$q = "SELECT * FROM addresses WHERE option_index IS NULL AND user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "SELECT * FROM addresses WHERE option_index IS NULL AND is_mine=1 AND user_id IS NULL;";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$address = $r->fetch();
				
				$q = "UPDATE addresses SET user_id='".$game->db_game['game_id']."' WHERE address_id='".$address['address_id']."';";
				$r = $this->app->run_query($q);
			}
		}
	}

	public function set_user_active() {
		$q = "UPDATE users SET logged_in=1, last_active='".time()."' WHERE user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
	}
	
	public function save_plan_allocations($user_strategy, $from_round, $to_round) {
		if ($from_round > 0 && $to_round > 0 && $to_round >= $from_round) {
			$game = new Game($this->app, $user_strategy['game_id']);
			$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
			$r = $this->app->run_query($q);
			
			$q = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id='".$user_strategy['game_id']."';";
			$r = $this->app->run_query($q);
			while ($op = $r->fetch()) {
				$round_id = $game->block_to_round($op['event_starting_block']);
				$points = intval($_REQUEST['poi_'.$op['option_id']]);
				if ($points > 0) {
					$qq = "INSERT INTO strategy_round_allocations SET strategy_id='".$user_strategy['strategy_id']."', round_id='".$round_id."', option_id='".$op['option_id']."', points='".$points."';";
					$rr = $this->app->run_query($qq);
				}
			}
		}
	}
	
	public function ensure_currency_accounts() {
		$q = "SELECT * FROM currencies WHERE blockchain_id IS NOT NULL;";
		$r = $this->app->run_query($q);
		
		while ($currency = $r->fetch()) {
			$qq = "SELECT * FROM currency_accounts WHERE user_id='".$this->db_user['user_id']."' AND currency_id='".$currency['currency_id']."';";
			$rr = $this->app->run_query($qq);
			
			if ($rr->rowCount() == 0) {
				$qq = "INSERT INTO currency_accounts SET user_id='".$this->db_user['user_id']."', currency_id='".$currency['currency_id']."', account_name='Primary ".$currency['name']." Account', time_created='".time()."';";
				$rr = $this->app->run_query($qq);
				$account_id = $this->app->last_insert_id();
				
				$account = $this->app->fetch_account_by_id($account_id);
				
				$address_key = $this->app->new_address_key($currency['currency_id'], $account);
				
				$qq = "UPDATE currency_accounts SET current_address_id='".$address_key['address_id']."' WHERE account_id='".$account_id."';";
				$rr = $this->app->run_query($qq);
			}
		}
	}
	
	public function fetch_currency_account($currency_id) {
		$q = "SELECT * FROM currency_accounts WHERE currency_id='".$currency_id."' AND user_id='".$this->db_user['user_id']."' ORDER BY account_id DESC;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function create_or_fetch_game_currency_account(&$game) {
		$q = "SELECT * FROM currency_accounts WHERE user_id='".$this->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."' ORDER BY account_id ASC;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else {
			$currency_id = $game->blockchain->currency_id();
			
			$qq = "INSERT INTO currency_accounts SET user_id='".$this->db_user['user_id']."', game_id='".$game->db_game['game_id']."', currency_id='".$currency_id."', account_name=".$this->app->quote_escape(ucwords($game->blockchain->db_blockchain['coin_name_plural'])." for ".$game->db_game['name']).", time_created='".time()."';";
			$rr = $this->app->run_query($qq);
			$account_id = $this->app->last_insert_id();
			$account = $this->app->fetch_account_by_id($account_id);
			
			$address_key = $this->app->new_address_key($currency_id, $account);
			
			$qq = "UPDATE currency_accounts SET current_address_id='".$address_key['address_id']."' WHERE account_id='".$account_id."';";
			echo "qq: $qq<br/>\n";
			$rr = $this->app->run_query($qq);
			
			$qq = "SELECT * FROM currency_accounts WHERE account_id='".$account_id."';";
			$rr = $this->app->run_query($qq);
			return $rr->fetch();
		}
	}
}
?>
