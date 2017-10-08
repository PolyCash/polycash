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
	
	public function account_coin_value(&$game, &$user_game) {
		$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND (io.spend_status='unspent' || io.spend_status='unconfirmed') AND k.account_id='".$user_game['account_id']."' GROUP BY io.io_id;";
		$r = $this->app->run_query($q);
		$sum = 0;
		while ($coins = $r->fetch(PDO::FETCH_NUM)) {
			$sum += $coins[0];
		}
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function immature_balance(&$game, &$user_game) {
		$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND k.account_id='".$user_game['account_id']."' AND (io.create_block_id > ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR io.create_block_id IS NULL) AND gio.instantly_mature = 0;";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function mature_balance(&$game, &$user_game) {
		$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND gio.game_id='".$game->db_game['game_id']."' AND k.account_id='".$user_game['account_id']."' AND (io.create_block_id <= ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR gio.instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function user_current_votes(&$game, $last_block_id, $current_round, &$user_game) {
		$q = "SELECT ROUND(SUM(gio.colored_amount)) coins, ROUND(SUM(gio.colored_amount*(".($last_block_id+1)."-io.create_block_id))) coin_blocks, ROUND(SUM(gio.colored_amount*(".$current_round."-gio.create_round_id))) coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND gio.game_id='".$game->db_game['game_id']."' AND k.account_id='".$user_game['account_id']."' AND (io.create_block_id <= ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR gio.instantly_mature = 1);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch();
		$votes = $sum[$game->db_game['payout_weight']."s"];
		if ($votes > 0) return $votes;
		else return 0;
	}
	
	public function performance_history(&$game, $from_round_id, $to_round_id) {
		$html = "";
		$to_block_id = $to_round_id*$game->db_game['round_length'];
		$from_block_id = $from_round_id*$game->db_game['round_length'];
		
		$last_block_id = $game->blockchain->last_block_id();
		
		$q = "SELECT e.*, r.*, e.event_id AS event_id, winner.name AS winner_name FROM events e LEFT JOIN event_outcomes r ON r.event_id=e.event_id LEFT JOIN options winner ON r.winning_option_id=winner.option_id WHERE e.game_id='".$game->db_game['game_id']."' AND e.event_final_block >= ".$from_block_id." AND e.event_final_block <= ".$to_block_id." ORDER BY e.event_index DESC;";
		$r = $this->app->run_query($q);
		
		while ($event_outcome = $r->fetch()) {
			$event = new Event($game, false, $event_outcome['event_id']);
			$event_round = $game->block_to_round($event_outcome['event_final_block']);
			$sum_votes = 0;
			$details_html = "";
			
			if (!empty($event_outcome['winning_option_id'])) {
				$option_votes = $event->option_votes_in_round($event_outcome['winning_option_id'], $event_round);
			}
			else $option_votes = 0;
			
			$html .= '<div class="row" style="font-size: 13px;">';
			$html .= '<div class="col-sm-3">'.$event->db_event['event_name'].'</div>';
			$html .= '<div class="col-sm-3">';
			if ($event->db_event['event_payout_block'] > $last_block_id) {
				if (!empty($event_outcome['winning_option_id'])) {
					$html .= $event_outcome['winner_name'].", Pending. ";
				}
				else {
					$html .= "Winner not yet determined. ";
				}
			}
			else {
				if ($event_outcome['winner_name'] != "") {
					if (!empty($event->db_event['option_block_rule'])) {
						$qq = "SELECT * FROM event_outcome_options WHERE outcome_id='".$event_outcome['outcome_id']."' ORDER BY option_id ASC;";
						$rr = $this->app->run_query($qq);
						$score_label = "";
						
						while ($outcome_option = $rr->fetch()) {
							if (empty($score_label)) $score_label = $outcome_option['option_block_score']."-";
							else $score_label .= $outcome_option['option_block_score'];
						}
						$html .= $score_label;
						$html .= " &nbsp;&nbsp; ".$event_outcome['winner_name'];
					}
					else {
						$html .= $event_outcome['winner_name'];
						$html .= " with ".$this->app->format_bignum($event_outcome['winning_votes']/pow(10,$game->db_game['decimal_places']))." votes. ";
					}
				}
				else {
					$html .= "No winner. ";
				}
			}
			
			if (empty($GLOBALS['prevent_changes_to_history'])) $html .= "<br/><a href=\"\" onclick=\"set_event_outcome(".$game->db_game['game_id'].", ".$event->db_event['event_id']."); return false;\">Set outcome</a>";
			
			$html .= '</div>';
			
			$my_votes_in_round = $event->my_votes_in_round($event_round, $this->db_user['user_id'], false);
			$my_votes = $my_votes_in_round[0];
			$coins_voted = $my_votes_in_round[1];
			
			if (!empty($my_votes[$event_outcome['winning_option_id']])) {
				if ($game->db_game['payout_weight'] == "coin") $win_text = "You correctly voted ".$this->app->format_bignum($my_votes[$event_outcome['winning_option_id']]['coins']/pow(10,$game->db_game['decimal_places']))." coins.";
				else $win_text = "You correctly cast ".$this->app->format_bignum($my_votes[$event_outcome['winning_option_id']][$game->db_game['payout_weight'].'s']/pow(10,$game->db_game['decimal_places']))." votes.";
			}
			else if ($coins_voted > 0) $win_text = "You didn't vote for the winning ".$event->db_event['option_name'].".";
			else $win_text = "You didn't cast any votes.";
			
			$html .= '<div class="col-sm-3">';
			$html .= $win_text;
			$html .= ' <a href="/explorer/games/'.$game->db_game['url_identifier'].'/events/'.($event_outcome['event_index']+1).'" target="_blank">Details</a>';
			$html .= '</div>';
			
			if ((string) $event_outcome['winning_option_id'] === "") {
				$win_amt = 0;
				$payout_amt = 0;
			}
			else {
				if (empty($my_votes[$event_outcome['winning_option_id']])) $win_amt_temp = 0;
				else $win_amt_temp = $event->event_pos_reward_in_round($event_outcome['round_id'])*$my_votes[$event_outcome['winning_option_id']]['votes'];
				if ($option_votes['sum'] > 0) $win_amt = $win_amt_temp/$option_votes['sum'];
				else $win_amt = 0;
				$payout_amt = $win_amt/pow(10,$game->db_game['decimal_places']);
			}
			
			$fee_disp = $this->app->format_bignum($my_votes_in_round['fee_amount']/pow(10,$game->blockchain->db_blockchain['decimal_places']));
			
			$html .= '<div class="col-sm-3" title="Won '.$this->app->format_bignum($win_amt/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].', paid '.$fee_disp.' '.$game->blockchain->db_blockchain['coin_name_plural'].' in fees">';
			
			$html .= '<font class="';
			if ($payout_amt >= 0) $html .= 'greentext';
			else $html .= 'redtext';
			$html .= '">';
			if ($payout_amt >= 0) $html .= '+';
			$payout_disp = $this->app->format_bignum($payout_amt);
			$html .= $payout_disp.' ';
			if ($payout_disp == '1') $html .= $game->db_game['coin_name'];
			else $html .= $game->db_game['coin_name_plural'];
			$html .= '</font>';
			
			if ($my_votes_in_round['fee_amount'] > 0) {
				$html .= ' <font class="redtext">-'.$fee_disp.' ';
				if ($fee_disp == '1') $html .= $game->blockchain->db_blockchain['coin_name'];
				else $html .= $game->blockchain->db_blockchain['coin_name_plural'];
				$html .= '</font>';
			}
			
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
	
	public function wallet_text_stats(&$game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance, &$user_game) {
		/*$html = '<div class="row"><div class="col-sm-2">Pending&nbsp;winnings:</div><div class="col-sm-3 text-right">';
		$payout_sum = 0;
		
		$q = "SELECT * FROM events e JOIN event_outcomes eo ON e.event_id=eo.event_id WHERE e.game_id='".$game->db_game['game_id']."' ORDER BY e.event_index ASC;";
		$r = $this->app->run_query($q);
		
		while ($db_event = $r->fetch()) {
			$event = new Event($game, false, $db_event['event_id']);
			$my_votes_in_round = $event->my_votes_in_round($game->block_to_round($db_event['event_final_block']), $this->db_user['user_id'], false);
			$my_votes_r = $my_votes_in_round[0];
			$total_votes = $my_votes_in_round[4];
			
			if (empty($my_votes_r[$db_event['winning_option_id']])) $payout_est = 0;
			else {
				$my_votes = $my_votes_r[$db_event['winning_option_id']]['votes'];
				
				$total_votes = $event->option_votes_in_round($db_event['winning_option_id'], $game->block_to_round($db_event['event_final_block']));
				$total_votes = $total_votes['sum'];
				
				if ($total_votes > 0 && $my_votes > 0) {
					$my_pct = $my_votes/$total_votes;
					$total_payout = $event->event_pos_reward_in_round($game->block_to_round($db_event['event_starting_block']));
					$payout_est = $my_pct*$total_payout;
				}
				else $payout_est = 0;
			}
			
			$payout_sum += $payout_est;
		}
		$html .= $this->app->format_bignum($payout_sum/pow(10,$game->db_game['decimal_places']));
		
		$html .= '</div></div>'."\n";*/
		
		$html = '<div class="row"><div class="col-sm-2">Available&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="greentext">';
		$html .= $this->app->format_bignum($mature_balance/pow(10,$game->db_game['decimal_places']));
		$html .= "</font> ".$game->db_game['coin_name_plural']."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-2">Locked&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="redtext">'.$this->app->format_bignum($immature_balance/pow(10,$game->db_game['decimal_places'])).'</font> '.$game->db_game['coin_name_plural'].'</div>';
		if ($immature_balance > 0) $html .= '<div class="col-sm-1"><a href="" onclick="$(\'#lockedfunds_details\').toggle(\'fast\'); return false;">Details</a></div>';
		$html .= "</div>\n";
		
		if ($game->db_game['payout_weight'] != "coin") {
			$user_votes = $this->user_current_votes($game, $last_block_id, $current_round, $user_game);
			
			if ($game->db_game['inflation'] == "exponential") {
				$votes_per_coin = $game->blockchain->app->votes_per_coin($game->db_game);
				if ($votes_per_coin > 0) $votes_value = $user_votes/$votes_per_coin;
				else $votes_value = 0;
				$html .= '<div class="row"><div class="col-sm-2">Unrealized gain:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($votes_value/pow(10,$game->db_game['decimal_places'])).'</font> '.$game->db_game['coin_name_plural'].'</div></div>'."\n";
			}
			else {
				$html .= '<div class="row"><div class="col-sm-2">Votes:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($user_votes/pow(10,$game->db_game['decimal_places'])).'</font> votes available</div></div>'."\n";
			}
		}
		
		$html .= "Last block completed: <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$last_block_id."\">#".$last_block_id."</a>, currently mining <a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/unconfirmed\">#".($last_block_id+1)."</a><br/>\n";
		$html .= "Current votes count towards block ".$block_within_round."/".$game->db_game['round_length']." in round #".$game->round_to_display_round($current_round).".<br/>\n";
		//if ($game->db_game['vote_effectiveness_function'] != "constant") $html .= "Votes are ".round(100*$game->round_index_to_effectiveness_factor($block_within_round),1)."% effective right now.<br/>\n";
		
		if ($immature_balance > 0) {
			$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id LEFT JOIN options gvo ON gio.option_id=gvo.option_id WHERE gio.game_id='".$game->db_game['game_id']."' AND io.user_id='".$this->db_user['user_id']."' AND (io.create_block_id > ".($game->blockchain->last_block_id() - $game->db_game['maturity'])." OR io.create_block_id IS NULL) ORDER BY io.io_id ASC;";
			$r = $this->app->run_query($q);
			
			$html .= '<div class="lockedfunds_details" id="lockedfunds_details">';
			while ($next_transaction = $r->fetch()) {
				$avail_block = $game->db_game['maturity'] + $next_transaction['create_block_id'] + 1;
				$seconds_to_avail = round(($avail_block - $last_block_id - 1)*$game->blockchain->db_blockchain['seconds_per_block']);
				$minutes_to_avail = round($seconds_to_avail/60);
				
				if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
				$html .= '<font class="greentext">'.$this->app->format_bignum($next_transaction['colored_amount']/(pow(10,$game->db_game['decimal_places'])))."</font> ";
				
				if ($next_transaction['create_block_id'] == "") {
					$html .= $game->db_game['coin_name_plural']." were just ";
					if ($next_transaction['option_id'] > 0) {
						$html .= "voted for ".$next_transaction['name'];
					}
					else $html .= "spent";
					$html .= ". This transaction is not yet confirmed.";
				}
				else {
					if ($next_transaction['transaction_desc'] == "votebase") $html .= $game->db_game['coin_name_plural']." in block ".$next_transaction['create_block_id'].". Coins";
					else $html .= $game->db_game['coin_name_plural']." received in block #".$next_transaction['create_block_id'];
					
					$html .= " can be spent in block #".$avail_block.". (Approximately ";
					if ($minutes_to_avail > 1) $html .= $minutes_to_avail." minutes";
					else $html .= $seconds_to_avail." seconds";
					$html .= "). ";
					if ($next_transaction['option_id'] > 0) {
						$html .= "You voted for ".$next_transaction['name']." in round #".$game->block_to_round($next_transaction['create_block_id']).". ";
					}
				}
				$html .= '(tx: <a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$next_transaction['tx_hash'].'">'.$next_transaction['transaction_id']."</a>)<br/>\n";
			}
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function user_address_id($game, $option_index, $option_id, $account_id) {
		$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND k.account_id='".$account_id."'";
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

	public function ensure_user_in_game(&$game, $force_new) {
		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$this->db_user['user_id']."' AND ug.game_id='".$game->db_game['game_id']."' ORDER BY selected DESC;";
		$r = $this->app->run_query($q);
		
		if ($force_new || $r->rowCount() == 0) {
			$q = "INSERT INTO user_games SET user_id='".$this->db_user['user_id']."', game_id='".$game->db_game['game_id']."', api_access_code=".$this->app->quote_escape($this->app->random_string(32));
			if (!empty($this->db_user['payout_address_id'])) $q .= ", payout_address_id='".$this->db_user['payout_address_id']."'";
			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") $q .= ", payment_required=1";
			if (strpos($this->db_user['notification_email'], '@')) $q .= ", notification_preference='email'";
			$q .= ";";
			$r = $this->app->run_query($q);
			$user_game_id = $this->app->last_insert_id();
			
			$currency_id = $game->blockchain->currency_id();
			
			$q = "INSERT INTO currency_accounts SET user_id='".$this->db_user['user_id']."', game_id='".$game->db_game['game_id']."', currency_id='".$currency_id."', account_name=".$this->app->quote_escape(ucwords($game->blockchain->db_blockchain['coin_name_plural'])." for ".$game->db_game['name']).", time_created='".time()."';";
			$r = $this->app->run_query($q);
			$account_id = $this->app->last_insert_id();
			$account = $this->app->fetch_account_by_id($account_id);
			
			$address_key = $this->app->new_address_key($currency_id, $account);
			
			$q = "UPDATE currency_accounts SET current_address_id='".$address_key['address_id']."' WHERE account_id='".$account_id."';";
			$r = $this->app->run_query($q);
			
			$q = "UPDATE user_games SET account_id='".$account_id."' WHERE user_game_id='".$user_game_id."';";
			$r = $this->app->run_query($q);
			
			$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_game_id='".$user_game_id."';";
			$r = $this->app->run_query($q);
			$user_game = $r->fetch();
		}
		else {
			$user_game = $r->fetch();
		}
		
		if ($user_game['strategy_id'] > 0) {}
		else {
			if ($game->blockchain->db_blockchain['p2p_mode'] == "none") $tx_fee=0.00001;
			else $tx_fee=0.001;
			
			$q = "INSERT INTO user_strategies SET voting_strategy='manual', game_id='".$game->db_game['game_id']."', user_id='".$user_game['user_id']."'";
			$q .= ", transaction_fee=".$tx_fee;
			$q .= ";";
			$r = $this->app->run_query($q);
			$strategy_id = $this->app->last_insert_id();
			
			$q = "SELECT * FROM user_strategies WHERE strategy_id='".$strategy_id."';";
			$r = $this->app->run_query($q);
			$strategy = $r->fetch();
			
			for ($block=1; $block<=$game->db_game['round_length']; $block++) {
				$q = "INSERT INTO user_strategy_blocks SET strategy_id='".$strategy_id."', block_within_round='".$block."';";
				$r = $this->app->run_query($q);
			}
			
			$q = "UPDATE user_games SET strategy_id='".$strategy_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
			$r = $this->app->run_query($q);
		}
		
		if ($game->db_game['game_status'] == "published" && $game->db_game['start_condition'] == "num_players") {
			$num_players = $game->paid_players_in_game();
			if ($num_players >= $game->db_game['start_condition_players']) {
				$game->start_game();
			}
		}
		
		$this->generate_user_addresses($game, $user_game);
		
		return $user_game;
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
			$invite_game = false;
			$this->app->try_apply_invite_key($this->db_user['user_id'], $_REQUEST['invite_key'], $invite_game);
		}
		
		$this->ensure_currency_accounts();
		
		if (!empty($_REQUEST['redirect_key'])) {
			$redirect_key = $_REQUEST['redirect_key'];
			$q = "SELECT * FROM redirect_urls WHERE redirect_key=".$this->app->quote_escape($redirect_key).";";
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
	
	public function generate_user_addresses(&$game, &$user_game) {
		$option_index_range = $game->option_index_range();
		
		// Try to give the user voting addresses for all options in this game
		for ($option_index=$option_index_range[0]; $option_index<=$option_index_range[1]; $option_index++) {
			// Check if user already has a voting address for this option_index
			$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND a.option_index='".$option_index."' AND k.account_id='".$user_game['account_id']."';";
			$rr = $this->app->run_query($qq);
			
			if ($rr->rowCount() == 0) {
				// If not, check if there is an unallocated address available to give to the user
				$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND a.option_index='".$option_index."' AND a.is_mine=1 AND k.account_id IS NULL AND NOT EXISTS (SELECT * FROM transaction_ios io WHERE io.address_id=a.address_id);";
				$rr = $this->app->run_query($qq);
				
				if ($rr->rowCount() > 0) {
					$address = $rr->fetch();
					
					$qq = "UPDATE addresses SET user_id='".$this->db_user['user_id']."' WHERE address_id='".$address['address_id']."';";
					$rr = $this->app->run_query($qq);
					
					$qq = "UPDATE address_keys SET account_id='".$user_game['account_id']."' WHERE address_id='".$address['address_id']."';";
					$rr = $this->app->run_query($qq);
				}
				else if ($game->blockchain->db_blockchain['p2p_mode'] == "none") {
					$vote_identifier = $this->app->option_index_to_vote_identifier($option_index);
					$addr_text = "11".$vote_identifier;
					$addr_text .= $this->app->random_string(34-strlen($addr_text));
					
					$qq = "INSERT INTO addresses SET is_mine=1, user_id='".$this->db_user['user_id']."', primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."', option_index='".$option_index."', vote_identifier=".$this->app->quote_escape($vote_identifier).", address=".$this->app->quote_escape($addr_text).", time_created='".time()."';";
					$rr = $this->app->run_query($qq);
					$address_id = $this->app->last_insert_id();
					
					$qq = "INSERT INTO address_keys SET address_id='".$address_id."', account_id='".$user_game['account_id']."', save_method='fake', pub_key='".$addr_text."';";
					$rr = $this->app->run_query($qq);
				}
			}
		}
		
		// Make sure the user has a non-voting address
		$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND a.option_index IS NULL AND k.account_id='".$user_game['account_id']."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND a.option_index IS NULL AND a.is_mine=1 AND k.account_id IS NULL;";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$address = $r->fetch();
				
				$q = "UPDATE addresses SET user_id='".$game->db_game['game_id']."' WHERE address_id='".$address['address_id']."';";
				$r = $this->app->run_query($q);
				
				$q = "UPDATE address_keys SET account_id='".$user_game['account_id']."' WHERE address_id='".$address['address_id']."';";
				$r = $this->app->run_query($q);
			}
		}
	}

	public function set_user_active() {
		$q = "UPDATE users SET logged_in=1, last_active='".time()."' WHERE user_id='".$this->db_user['user_id']."';";
		$r = $this->app->run_query($q);
	}
	
	public function save_plan_allocations(&$game, $user_strategy, $from_round, $to_round) {
		if ($from_round > 0 && $to_round > 0 && $to_round >= $from_round) {
			$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
			$r = $this->app->run_query($q);
			
			$q = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id='".$game->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			while ($op = $r->fetch()) {
				$round_id = $game->block_to_round($op['event_starting_block']);
				$points = (int)$_REQUEST['poi_'.$op['option_id']];
				if ($points > 0) {
					$qq = "INSERT INTO strategy_round_allocations SET strategy_id='".$user_strategy['strategy_id']."', round_id='".$round_id."', option_id='".$op['option_id']."', points='".$points."';";
					$rr = $this->app->run_query($qq);
				}
			}
		}
	}
	
	public function ensure_currency_accounts() {
		$q = "SELECT * FROM currencies c JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE b.online=1;";
		$r = $this->app->run_query($q);
		
		while ($currency = $r->fetch()) {
			$qq = "SELECT * FROM currency_accounts WHERE game_id IS NULL AND user_id='".$this->db_user['user_id']."' AND currency_id='".$currency['currency_id']."';";
			$rr = $this->app->run_query($qq);
			
			if ($rr->rowCount() == 0) {
				$qq = "INSERT INTO currency_accounts SET user_id='".$this->db_user['user_id']."', currency_id='".$currency['currency_id']."', account_name='Primary ".$currency['name']." Account', time_created='".time()."';";
				$rr = $this->app->run_query($qq);
				$account_id = $this->app->last_insert_id();
				
				$account = $this->app->fetch_account_by_id($account_id);
				
				$address_key = $this->app->new_address_key($currency['currency_id'], $account);
				
				if ($address_key) {
					$qq = "UPDATE currency_accounts SET current_address_id='".$address_key['address_id']."' WHERE account_id='".$account_id."';";
					$rr = $this->app->run_query($qq);
				}
			}
		}
	}
	
	public function fetch_currency_account($currency_id) {
		$q = "SELECT * FROM currency_accounts WHERE game_id IS NULL AND currency_id='".$currency_id."' AND user_id='".$this->db_user['user_id']."' ORDER BY account_id DESC;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function set_selected_user_game(&$game, $user_game_id) {
		$q = "UPDATE user_games SET selected=1 WHERE user_game_id='".$user_game_id."';";
		$r = $this->app->run_query($q);
		
		$q = "UPDATE user_games SET selected=0 WHERE user_id='".$this->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."' AND user_game_id != ".$user_game_id.";";
		$r = $this->app->run_query($q);
	}
}
?>
