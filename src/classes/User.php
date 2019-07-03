<?php
class User {
	public $db_user;
	public $app;
	
	public function __construct(&$app, $user_id) {
		$this->app = $app;
		
		$this->db_user = $this->app->fetch_user_by_id($user_id);
		
		if (!$this->db_user) throw new Exception("Failed to load user #".$user_id);
	}

	public function immature_balance(&$game, &$user_game) {
		$query_params = [
			'game_id' => $game->db_game['game_id'],
			'account_id' => $user_game['account_id']
		];
		return (int)($this->app->run_query("SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND io.spend_status != 'spent' AND gio.is_resolved=0;", $query_params)->fetch(PDO::FETCH_NUM)[0]);
	}

	public function mature_balance(&$game, &$user_game) {
		$query_params = [
			'game_id' => $game->db_game['game_id'],
			'account_id' => $user_game['account_id']
		];
		return (int)($this->app->run_query("SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND gio.is_resolved=1 AND io.spend_status != 'spent';", $query_params)->fetch(PDO::FETCH_NUM)[0]);
	}

	public function user_current_votes(&$game, $last_block_id, $current_round, &$user_game) {
		$query_params = [
			'ref_block' => ($last_block_id+1),
			'ref_round' => $current_round,
			'account_id' => $user_game['account_id']
		];
		$info = $this->app->run_query("SELECT ROUND(SUM(gio.colored_amount)) coins, ROUND(SUM(gio.colored_amount*(:ref_block-gio.create_block_id))) coin_blocks, ROUND(SUM(gio.colored_amount*(:ref_round-gio.create_round_id))) coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status='unspent' AND k.account_id=:account_id;", $query_params)->fetch();
		$votes = (int) $info[$game->db_game['payout_weight']."s"];
		
		$coins_per_vote = $game->blockchain->app->coins_per_vote($game->db_game);
		$votes_value = $votes*$coins_per_vote;
		
		return [$votes, $votes_value];
	}
	
	public function wallet_text_stats(&$game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance, $user_votes, $votes_value, $pending_bets, &$user_game) {
		$html = '<div class="row"><div class="col-sm-2">Available&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="greentext">';
		$html .= $this->app->format_bignum($mature_balance/pow(10,$game->db_game['decimal_places']));
		$html .= "</font> ".$game->db_game['coin_name_plural']."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-2">Locked&nbsp;funds:</div>';
		$html .= '<div class="col-sm-3 text-right"><font class="redtext">'.$this->app->format_bignum($immature_balance/pow(10,$game->db_game['decimal_places'])).'</font> '.$game->db_game['coin_name_plural'].'</div>';
		$html .= "</div>\n";
		
		$html .= '<div class="row"><div class="col-sm-2">Pending bets:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($pending_bets/pow(10,$game->db_game['decimal_places'])).'</font> '.$game->db_game['coin_name_plural'].'</div></div>'."\n";
		
		if ($game->db_game['payout_weight'] != "coin") {
			if ($game->db_game['inflation'] == "exponential") {
				$html .= '<div class="row"><div class="col-sm-2">Unrealized gains:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($votes_value/pow(10,$game->db_game['decimal_places'])).'</font> '.$game->db_game['coin_name_plural'].'</div></div>'."\n";
			}
			else {
				$html .= '<div class="row"><div class="col-sm-2">Votes:</div><div class="col-sm-3 text-right"><font class="greentext">'.$this->app->format_bignum($user_votes/pow(10,$game->db_game['decimal_places'])).'</font> votes available</div></div>'."\n";
			}
		}
		
		$html .= "Last block completed: <a href=\"/explorer/games/".$game->db_game['url_identifier']."/blocks/".$last_block_id."\">#".$last_block_id."</a>, currently mining <a href=\"/explorer/games/".$game->db_game['url_identifier']."/transactions/unconfirmed\">#".($last_block_id+1)."</a><br/>\n";
		$html .= "Current bets count towards block ".$block_within_round."/".$game->db_game['round_length']." in round #".$game->round_to_display_round($current_round).".<br/>\n";
		
		return $html;
	}
	
	public function ensure_user_in_game(&$game, $force_new) {
		$existing_user_games = $this->app->run_query("SELECT *, ug.user_id AS user_id, ug.game_id AS game_id FROM user_games ug JOIN games g ON ug.game_id=g.game_id LEFT JOIN user_strategies us ON us.strategy_id=ug.strategy_id LEFT JOIN featured_strategies fs ON us.featured_strategy_id=fs.featured_strategy_id WHERE ug.user_id=:user_id AND ug.game_id=:game_id ORDER BY ug.selected DESC;", [
			'user_id' => $this->db_user['user_id'],
			'game_id' => $game->db_game['game_id']
		]);
		
		if ($force_new || $existing_user_games->rowCount() == 0) {
			$new_user_game_params = [
				'user_id' => $this->db_user['user_id'],
				'game_id' => $game->db_game['game_id'],
				'api_access_code' => $this->app->random_string(32),
				'display_currency_id' => $game->db_game['default_display_currency_id'],
				'buyin_currency_id' => $game->db_game['default_buyin_currency_id']
			];
			$new_user_game_q = "INSERT INTO user_games SET user_id=:user_id, game_id=:game_id, api_access_code=:api_access_code, show_intro_message=1, notification_preference='email', prompt_notification_preference=1, betting_mode='principal', display_currency_id=:display_currency_id, buyin_currency_id=:buyin_currency_id";
			if (!empty($this->db_user['payout_address_id'])) {
				$new_user_game_q .= ", payout_address_id=:payout_address_id";
				$new_user_game_params['payout_address_id'] = $this->db_user['payout_address_id'];
			}
			if ($game->db_game['giveaway_status'] == "public_pay" || $game->db_game['giveaway_status'] == "invite_pay") $new_user_game_q .= ", payment_required=1";
			$new_user_game_q .= ";";
			$this->app->run_query($new_user_game_q, $new_user_game_params);
			$user_game_id = $this->app->last_insert_id();
			
			$currency_id = $game->blockchain->currency_id();
			
			$account = $this->app->create_new_account([
				'user_id' => $this->db_user['user_id'],
				'game_id' => $game->db_game['game_id'],
				'currency_id' => $currency_id,
				'account_name' => ucwords($game->blockchain->db_blockchain['coin_name_plural'])." for ".$game->db_game['name']
			]);
			
			$address_key = $this->app->new_address_key($currency_id, $account);
			
			$this->app->run_query("UPDATE currency_accounts SET current_address_id=:current_address_id WHERE account_id=:account_id;", [
				'current_address_id' => $address_key['address_id'],
				'account_id' => $account['account_id']
			]);
			$this->app->run_query("UPDATE user_games SET account_id=:account_id WHERE user_game_id=:user_game_id;", [
				'account_id' => $account['account_id'],
				'user_game_id' => $user_game_id
			]);
			
			$user_game = $this->app->run_query("SELECT *, ug.user_id AS user_id, ug.game_id AS game_id FROM user_games ug JOIN games g ON ug.game_id=g.game_id LEFT JOIN user_strategies us ON ug.strategy_id=us.strategy_id LEFT JOIN featured_strategies fs ON us.featured_strategy_id=fs.featured_strategy_id WHERE ug.user_game_id=:user_game_id;", [
				'user_game_id' => $user_game_id
			])->fetch();
			
			$this->app->apply_address_set($game, $account['account_id']);
		}
		else $user_game = $existing_user_games->fetch();
		
		if ($user_game['strategy_id'] > 0) {}
		else {
			$tx_fee=0.0001;
			
			$this->app->run_query("INSERT INTO user_strategies SET voting_strategy='manual', game_id=:game_id, user_id=:user_id, transaction_fee=:tx_fee;", [
				'game_id' => $game->db_game['game_id'],
				'user_id' => $user_game['user_id'],
				'tx_fee' => $tx_fee
			]);
			$strategy_id = $this->app->last_insert_id();
			
			$strategy = $this->app->fetch_strategy_by_id($strategy_id);
			
			for ($block=1; $block<=$game->db_game['round_length']; $block++) {
				$this->app->run_query("INSERT INTO user_strategy_blocks SET strategy_id=:strategy_id, block_within_round=:block_within_round;", [
					'strategy_id' => $strategy_id,
					'block_within_round' => $block
				]);
			}
			
			$this->app->run_query("UPDATE user_games SET strategy_id=:strategy_id WHERE user_game_id=:user_game_id;", [
				'strategy_id' => $strategy_id,
				'user_game_id' => $user_game['user_game_id']
			]);
			
			$user_game['strategy_id'] = $strategy_id;
		}
		
		if ($game->db_game['game_status'] == "published" && $game->db_game['start_condition'] == "num_players") {
			$num_players = $game->paid_players_in_game();
			if ($num_players >= $game->db_game['start_condition_players']) {
				$game->start_game();
			}
		}
		
		return $user_game;
	}

	public function log_user_in(&$redirect_url, $viewer_id) {
		if (AppSettings::getParam('pageview_tracking_enabled')) {
			$viewer_connection = $this->app->run_query("SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id=:viewer_id AND to_id=:user_id;", [
				'viewer_id' => $viewer_id,
				'user_id' => $this->db_user['user_id']
			])->fetch();
			
			if (!$viewer_connection) {
				$this->app->run_query("INSERT INTO viewer_connections SET type='viewer2user', from_id=:viewer_id, to_id=:user_id;", [
					'viewer_id' => $viewer_id,
					'user_id' => $this->db_user['user_id']
				]);
			}
		}
		
		$session_key = $_COOKIE['my_session_global'];
		
		if (!empty($session_key)) {
			$expire_time = time()+3600*24;
			
			$new_session_params = [
				'user_id' => $this->db_user['user_id'],
				'session_key' => $session_key,
				'login_time' => time(),
				'expire_time' => $expire_time
			];
			$new_session_q = "INSERT INTO user_sessions SET user_id=:user_id, session_key=:session_key, login_time=:login_time, expire_time=:expire_time";
			if (AppSettings::getParam('pageview_tracking_enabled')) {
				$new_session_q .= ", ip_address=:ip_address";
				$new_session_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
			}
			$this->app->run_query($new_session_q, $new_session_params);
			
			$login_user_params = [
				'user_id' => $this->db_user['user_id']
			];
			$login_user_q = "UPDATE users SET logged_in=1";
			if (AppSettings::getParam('pageview_tracking_enabled')) {
				$login_user_q .= ", ip_address=:ip_address";
				$login_user_params['ip_address'] = $_SERVER['REMOTE_ADDR'];
			}
			$login_user_q .= " WHERE user_id=:user_id;";
			$this->app->run_query($login_user_q, $login_user_params);
			
			$this->app->run_query("UPDATE user_games ug JOIN users u ON ug.user_id=u.user_id SET ug.prompt_notification_preference=1 WHERE (ug.notification_preference='none' OR u.notification_email='') AND ug.user_id=:user_id AND ug.prompt_notification_preference=0;", [
				'user_id' => $this->db_user['user_id']
			]);
			
			if (!empty($_REQUEST['invite_key'])) {
				$user_game = false;
				$invite_game = false;
				$this->app->try_apply_invite_key($this->db_user['user_id'], $_REQUEST['invite_key'], $invite_game, $user_game);
			}
			
			$this->ensure_currency_accounts();
			
			if (!empty($_REQUEST['redirect_key'])) {
				$redirect_url = $this->app->get_redirect_by_key($_REQUEST['redirect_key']);
			}
			
			return true;
		}
		else return false;
	}
	
	public function user_in_game($game_id) {
		$user_game = $this->app->fetch_user_game($this->db_user['user_id'], $game_id);
		if ($user_game) return true;
		else return false;
	}
	
	public function user_can_invite_game($db_game) {
		if ($this->db_user['user_id'] == $db_game['creator_id']) return true;
		else return false;
	}
	
	public function count_user_games_created() {
		return (int)($this->app->run_query("SELECT * FROM games WHERE creator_id=:user_id;", [
			'user_id' => $this->db_user['user_id']
		])->rowCount());
	}
	
	public function new_game_permission() {
		$games_created_by_user = $this->count_user_games_created();
		if ((string)AppSettings::getParam('new_games_per_user') == "unlimited") return true;
		else if ($games_created_by_user < $this->db_user['authorized_games']) return true;
		else return false;
	}
	
	public function generate_user_addresses(&$game, &$user_game) {
		$option_index_range = $game->option_index_range();
		
		$this->app->dbh->beginTransaction();
		$account = $this->app->run_query("SELECT * FROM currency_accounts WHERE account_id=:account_id;", [
			'account_id' => $user_game['account_id']
		])->fetch();
		
		if ($account) {
			$start_option_index = $account['has_option_indices_until']+1;
			$has_option_indices_until = false;
			
			for ($option_index=$start_option_index; $option_index<=$option_index_range[1]; $option_index++) {
				$existing_address = $this->app->run_query("SELECT * FROM address_keys WHERE primary_blockchain_id=:blockchain_id AND option_index=:option_index AND account_id=:account_id;", [
					'blockchain_id' => $game->blockchain->db_blockchain['blockchain_id'],
					'option_index' => $option_index,
					'account_id' => $account['account_id']
				])->fetch();
				
				if (!$existing_address) {
					if ($game->blockchain->db_blockchain['p2p_mode'] != "rpc") {
						$this->app->gen_address_by_index($game->blockchain, $account, false, $option_index);
						
						$has_option_indices_until = $option_index;
					}
					else {
						$address = $this->app->run_query("SELECT * FROM address_keys WHERE primary_blockchain_id=:blockchain_id AND option_index=:option_index AND account_id IS NULL AND address_set_id IS NULL;", [
							'blockchain_id' => $game->blockchain->db_blockchain['blockchain_id'],
							'option_index' => $option_index
						])->fetch();
						
						if ($address) {
							$this->app->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
								'user_id' => $this->db_user['user_id'],
								'address_id' => $address['address_id']
							]);
							$this->app->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_id=:address_id;", [
								'account_id' => $account['account_id'],
								'address_id' => $address['address_id']
							]);
							
							$has_option_indices_until = $option_index;
						}
						else {
							$option_index = $option_index_range[1]+1;
						}
					}
				}
				else $has_option_indices_until = $option_index;
			}
			
			if ($has_option_indices_until !== false) {
				$this->app->run_query("UPDATE currency_accounts SET has_option_indices_until=:has_option_indices_until WHERE account_id=:account_id;", [
					'has_option_indices_until' => $has_option_indices_until,
					'account_id' => $user_game['account_id']
				]);
			}
		}
		$this->app->dbh->commit();
	}

	public function set_user_active() {
		$this->app->run_query("UPDATE users SET logged_in=1, last_active=:last_active_time WHERE user_id=:user_id;", [
			'last_active_time' => time(),
			'user_id' => $this->db_user['user_id']
		]);
	}
	
	public function save_plan_allocations(&$game, $user_strategy, $from_round, $to_round) {
		if ($from_round > 0 && $to_round > 0 && $to_round >= $from_round) {
			$this->app->run_query("DELETE FROM strategy_round_allocations WHERE strategy_id=:strategy_id AND round_id >= :from_round AND round_id <= :to_round;", [
				'strategy_id' => $user_strategy['strategy_id'],
				'from_round' => $from_round,
				'to_round' => $to_round
			]);
			
			$options_by_game = $this->app->run_query("SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=:game_id;", [
				'game_id' => $game->db_game['game_id']
			]);
			
			while ($op = $options_by_game->fetch()) {
				$round_id = $game->block_to_round($op['event_starting_block']);
				$points = (int)$_REQUEST['poi_'.$op['option_id']];
				
				if ($points > 0) {
					$this->app->run_query("INSERT INTO strategy_round_allocations SET strategy_id=:strategy_id, round_id=:round_id, option_id=:option_id, points=:points;", [
						'strategy_id' => $user_strategy['strategy_id'],
						'round_id' => $round_id,
						'option_id' => $op['option_id'],
						'points' => $points
					]);
				}
			}
		}
	}
	
	public function ensure_currency_accounts() {
		$required_currencies = $this->app->run_query("SELECT * FROM currencies c JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE b.online=1;");
		
		while ($currency = $required_currencies->fetch()) {
			$user_blockchain_account = $this->app->user_blockchain_account($this->db_user['user_id'], $currency['currency_id']);
			
			if (empty($user_blockchain_account)) {
				$account = $this->app->create_new_account([
					'user_id' => $this->db_user['user_id'],
					'currency_id' => $currency['currency_id'],
					'account_name' => "Primary ".$currency['name']." Account"
				]);
				
				$address_key = $this->app->new_address_key($currency['currency_id'], $account);
				
				if ($address_key) {
					$this->app->run_query("UPDATE currency_accounts SET current_address_id=:address_id WHERE account_id=:account_id;", [
						'address_id' => $address_key['address_id'],
						'account_id' => $account['account_id']
					]);
				}
			}
		}
	}
	
	public function fetch_currency_account($currency_id) {
		return $this->app->run_query("SELECT * FROM currency_accounts WHERE game_id IS NULL AND currency_id=:currency_id AND user_id=:user_id ORDER BY account_id DESC;", [
			'currency_id' => $currency_id,
			'user_id' => $this->db_user['user_id']
		])->fetch();
	}
	
	public function set_selected_user_game(&$game, $user_game_id) {
		$this->app->run_query("UPDATE user_games SET selected=1 WHERE user_game_id=:user_game_id;", [
			'user_game_id' => $user_game_id
		]);
		$this->app->run_query("UPDATE user_games SET selected=0 WHERE user_id=:user_id AND game_id=:game_id AND user_game_id != :user_game_id;", [
			'user_id' => $this->db_user['user_id'],
			'game_id' => $game->db_game['game_id'],
			'user_game_id' => $user_game_id
		]);
	}
	
	public function log_out(&$session) {
		$this->app->run_query("UPDATE user_sessions SET logout_time=:logout_time WHERE session_id=:session_id;", [
			'logout_time' => time(),
			'session_id' => $session['session_id']
		]);
		$this->app->run_query("UPDATE users SET logged_in=0 WHERE user_id=:user_id;", [
			'user_id' => $this->db_user['user_id']
		]);
		
		@session_regenerate_id();
	}
}
?>
