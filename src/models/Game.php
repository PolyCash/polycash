<?php
class Game {
	public $db_game;
	public $blockchain;
	public $current_events;
	public $genesis_hash;
	public $definitive_peer = false;
	
	public function __construct(&$blockchain, $game_id) {
		$this->blockchain = $blockchain;
		$this->game_id = $game_id;
		$this->update_db_game();
		
		if (!empty($this->db_game['module'])) {
			$module_class = $this->db_game['module'].'GameDefinition';
			$module_fname = dirname(__DIR__)."/modules/".$this->db_game['module']."/".$module_class.".php";
			if (is_file($module_fname)) {
				$this->module = new $module_class($this->blockchain->app);
			}
		}
	}
	
	public function update_db_game() {
		$this->db_game = $this->blockchain->app->run_query("SELECT g.*, b.p2p_mode, b.coin_name AS base_coin_name, b.coin_name_plural AS base_coin_name_plural FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_id=:game_id;", ['game_id'=>$this->game_id])->fetch();
		
		if (!$this->db_game) throw new Exception("Error, could not load game #".$this->game_id);
	}
	
	public function display_coins($amount_int, $as_abbreviation=false, $skip_name=false, $err_lower=true) {
		$amount_float = $amount_int/pow(10, $this->db_game['decimal_places']);
		$sigfigs = 1+floor(log10(abs($amount_float)))+$this->db_game['decimal_places'];
		$display_amount = $this->blockchain->app->format_bignum($amount_float, $err_lower, $sigfigs);
		$str = $display_amount;
		if (!$skip_name) {
			$str .= " ";
			if ($as_abbreviation) $str .= $this->db_game['coin_abbreviation'];
			else $str .= $display_amount=="1" ? $this->db_game['coin_name'] : $this->db_game['coin_name_plural'];
		}
		return $str;
	}
	
	public function fetch_extra_info() {
		if (empty($this->db_game['extra_info'])) return [];
		else return (array) json_decode($this->db_game['extra_info']);
	}
	
	public function set_extra_info($extra_info) {
		if ($extra_info == []) $extra_info_txt = "";
		else $extra_info_txt = json_encode($extra_info, JSON_PRETTY_PRINT);
		
		$this->blockchain->app->run_query("UPDATE games SET extra_info=:extra_info WHERE game_id=:game_id;", [
			'extra_info' => $extra_info_txt,
			'game_id' => $this->db_game['game_id']
		]);
	}
	
	public static function create_game(&$blockchain, $params) {
		$params['blockchain_id'] = $blockchain->db_blockchain['blockchain_id'];
		
		if (!empty($params['pow_reward_type']) && $params['pow_reward_type'] != "none") $params['bulk_add_blocks'] = 0;
		
		$params['keep_definitions_hours'] = isset($params['recommended_keep_definitions_hours']) ? $params['recommended_keep_definitions_hours'] : null;
		
		if (empty($params['default_buyin_currency_id'])) {
			$btc_currency = $blockchain->app->fetch_currency_by_abbreviation("BTC");
			if ($btc_currency) $params['default_buyin_currency_id'] = $btc_currency['currency_id'];
		}
		
		$blockchain->app->run_insert_query("games", $params);
		
		$game_id = $blockchain->app->last_insert_id();
		
		return new Game($blockchain, $game_id);
	}
	
	public function block_to_round($mining_block_id) {
		return ceil($mining_block_id/$this->db_game['round_length']);
	}
	
	public function update_option_votes() {
		$this->load_current_events();
		
		$last_block_id = $this->blockchain->last_block_id();
		$round_id = $this->block_to_round($last_block_id+1);
		
		$this->blockchain->app->dbh->beginTransaction();
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$this->current_events[$i]->update_option_votes($last_block_id, $round_id);
		}
		
		$this->blockchain->app->dbh->commit();
	}
	
	public function update_all_option_votes() {
		$last_block_id = $this->blockchain->last_block_id();
		$round_id = $this->block_to_round($last_block_id+1);
		
		$this->blockchain->app->dbh->beginTransaction();
		
		$all_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id ORDER BY event_index ASC;", ['game_id'=>$this->db_game['game_id']]);
		
		while ($db_event = $all_events->fetch()) {
			$this_event = new Event($this, $db_event, $db_event['event_id']);
			$this_event->update_option_votes($last_block_id, $round_id);
		}
		
		$this->blockchain->app->dbh->commit();
	}
	
	public function check_set_game_over() {
		if ($this->db_game['final_round'] > 0) {
			$this->update_db_game();
			if ($this->db_game['game_status'] != "completed") {
				$last_block_id = $this->blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				$current_round = $this->block_to_round($mining_block_id);
				if ($current_round > $this->db_game['final_round']) {
					$this->set_game_over();
				}
			}
		}
	}
	
	public function set_game_status($new_status) {
		if (in_array($new_status, ['completed','editable','running','published'])) {
			$this->blockchain->app->run_query("UPDATE games SET game_status=:new_status, completion_datetime=".AppSettings::sqlNow()." WHERE game_id=:game_id;", [
				'new_status' => $new_status,
				'game_id' => $this->db_game['game_id']
			]);
			$this->db_game['game_status'] = $new_status;
			return "";
		}
		else return "This status transition is not allowed.";
	}
	
	public function set_game_over() {
		$error_message = $this->set_game_status('completed');
	}
	
	public function apply_user_strategies($print_debug, $max_seconds) {
		$ref_time = microtime(true);
		$last_block_id = $this->blockchain->last_block_id();
		
		if ($this->last_block_id() == $last_block_id) {
			$mining_block_id = $last_block_id+1;
			$current_round_id = $this->block_to_round($mining_block_id);
			$block_of_round = $this->block_id_to_round_index($mining_block_id);
			
			$strategies_params = [
				'game_id' => $this->db_game['game_id'],
				'current_time' => time()
			];
			$strategies_q = "SELECT *, u.user_id, g.game_id FROM users u JOIN user_games g ON u.user_id=g.user_id JOIN user_strategies s ON g.strategy_id=s.strategy_id";
			$strategies_q .= " LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id";
			$strategies_q .= " WHERE g.game_id=:game_id";
			$strategies_q .= " AND (s.voting_strategy IN ('by_rank', 'by_entity', 'api', 'by_plan', 'featured','hit_url'))";
			$strategies_q .= " AND (s.time_next_apply IS NULL OR s.time_next_apply<:current_time)";
			$strategies_q .= " ORDER BY ".AppSettings::sqlRand().";";
			$apply_strategies = $this->blockchain->app->run_query($strategies_q, $strategies_params)->fetchAll();
			
			if ($print_debug) {
				$this->blockchain->app->print_debug("Applying user strategies for block #".$mining_block_id." of ".$this->db_game['name']." looping through ".count($apply_strategies)." users.");
			}
			
			$apply_strategies_pos = 0;
			while (microtime(true)-$ref_time < $max_seconds && !empty($apply_strategies[$apply_strategies_pos])) {
				$user_game = $apply_strategies[$apply_strategies_pos];
				
				$last_block_id = $this->blockchain->last_block_id();
				
				if ($this->last_block_id() == $last_block_id) {
					$mining_block_id = $last_block_id+1;
					$current_round_id = $this->block_to_round($mining_block_id);
					$block_of_round = $this->block_id_to_round_index($mining_block_id);
					
					$api_response = false;
					$this_log_text = "";
					$this->apply_user_strategy($this_log_text, $user_game, $mining_block_id, $current_round_id, $api_response, false);
					
					if (!$api_response) {
						$this->blockchain->app->set_strategy_time_next_apply($user_game['strategy_id'], time()+60*60);
					}
					
					if ($print_debug) {
						echo $this_log_text."\n";
						
						if ($api_response) echo "user #".$user_game['user_id'].": ".json_encode($api_response)."\n";
						else echo "no api response for user #".$user_game['user_id']."\n";
						$this->blockchain->app->flush_buffers();
					}
				}
				
				$apply_strategies_pos++;
			}
		}
		else if ($print_debug) echo "Game and blockchain are out of sync: not applying user strategies.";
	}
	
	public function apply_user_strategy(&$log_text, &$user_game, $mining_block_id, $current_round_id, &$api_response, $force_now) {
		$strategy_user = new User($this->blockchain->app, $user_game['user_id']);
		
		$unconfirmed_balance = $this->blockchain->account_balance($user_game['account_id'], true);
		$immature_amount = $this->blockchain->account_balance($user_game['account_id'], false, true);
		$spendable_balance = $unconfirmed_balance - $immature_amount;
		
		$last_block_id = $this->blockchain->last_block_id();
		
		list($available_votes, $votes_value) = $strategy_user->user_current_votes($this, $last_block_id, $current_round_id, $user_game);
		
		$log_text .= $strategy_user->db_user['username'].": ".$this->blockchain->app->format_bignum($spendable_balance/pow(10,$this->db_game['decimal_places']))." coins (".$spendable_balance.") ".$user_game['voting_strategy']."<br/>\n";
		
		if ($user_game['voting_strategy'] == "api" || $user_game['voting_strategy'] == "featured") {
			if ($user_game['voting_strategy'] == "api") $api_url = $user_game['api_url'];
			else {
				$api_url = $user_game['base_url'];
				if (substr($api_url, 0, 4) != "http") $api_url = AppSettings::getParam('base_url').$api_url;
				if (strpos($api_url, '?')) $api_url .= "&";
				else $api_url .= "?";
				$api_url .= "api_key=".$user_game['api_access_code'];
				if ($force_now) $api_url .= "&force=1";
			}
			
			$api_response = $this->blockchain->app->safe_fetch_url($api_url);
			
			if ($user_game['hit_url'] == 1) {
				$api_response = json_decode($api_response);
			}
			else {
				$api_obj = json_decode($api_response);
				
				if ($api_obj->recommendations && count($api_obj->recommendations) > 0 && in_array($api_obj->recommendation_unit, array('coin','percent'))) {
					$input_error = false;
					$input_io_ids = [];
					
					if ($api_obj->input_utxo_ids) {
						if (count($api_obj->input_utxo_ids) > 0) {
							for ($i=0; $i<count($api_obj->input_utxo_ids); $i++) {
								if (!$input_error) {
									$utxo_id = intval($api_obj->input_utxo_ids[$i]);
									if (strval($utxo_id) === strval($api_obj->input_utxo_ids[$i])) {
										$utxo = $this->blockchain->app->run_query("SELECT *, ca.user_id AS account_user_id FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id WHERE gio.game_io_id=:utxo_id;", ['utxo_id'=>$utxo_id])->fetch();
										if ($utxo) {
											if ($utxo['account_user_id'] == $strategy_user->db_user['user_id']) {
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
					
					$log_text .= $strategy_user->db_user['username']." has ".$spendable_balance/pow(10,$this->db_game['decimal_places'])." coins available, hitting url: ".$user_game['api_url']."<br/>\n";
					
					foreach ($api_obj->recommendations as $recommendation) {
						if ($recommendation->recommended_amount && $recommendation->recommended_amount > 0 && $this->blockchain->app->friendly_intval($recommendation->recommended_amount) == $recommendation->recommended_amount) $amount_sum += $recommendation->recommended_amount;
						else $amount_error = true;
						
						$db_option = $this->blockchain->app->run_query("SELECT * FROM options op JOIN events ev ON op.event_id=ev.event_id WHERE op.option_index=:option_index AND ev.game_id=:game_id AND ev.event_starting_block <= :ref_block_id AND ev.event_final_block >= :ref_block_id;", [
							'option_index' => $recommendation->option_index,
							'game_id' => $this->db_game['game_id'],
							'ref_block_id' => $mining_block_id
						])->fetch();
						
						if ($db_option) {
							$recommendation->option_id = $db_option['option_id'];
						}
						else $option_id_error = true;
					}
					
					if ($api_obj->recommendation_unit == "coin") {
						if ($amount_sum <= $spendable_balance) {}
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
						$vote_option_ids = [];
						$vote_amounts = [];
						
						foreach ($api_obj->recommendations as $recommendation) {
							if ($api_obj->recommendation_unit == "coin") $vote_amount = $recommendation->recommended_amount;
							else $vote_amount = floor($spendable_balance*$recommendation->recommended_amount/100);
							
							$vote_option_id = $recommendation->option_id;
							
							$vote_option_ids[count($vote_option_ids)] = $vote_option_id;
							$vote_amounts[count($vote_amounts)] = $vote_amount;
							
							$log_text .= "Vote ".$vote_amount." for ".$vote_option_id."<br/>\n";
						}
						
						$error_message = false;
						$transaction_id = $this->create_transaction($vote_option_ids, $vote_amounts, $user_game, false, 'transaction', $input_io_ids, false, false, $api_obj->recommended_fee, $error_message);
						
						if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
						else $log_text .= $error_message."<br/>\n";
					}
				}
			}
		}
		else if ($user_game['voting_strategy'] == "hit_url") {
			$api_response = $this->blockchain->app->safe_fetch_url($user_game['api_url']);
		}
		else {
			$log_text .= "user game #".$user_game['user_game_id'].", strategy: ".$user_game['voting_strategy']." ";
			
			$log_text .= "Dividing by plan for ".$strategy_user->db_user['username']."<br/>\n";
			
			$db_allocations = $this->blockchain->app->run_query("SELECT * FROM strategy_round_allocations WHERE strategy_id=:strategy_id AND round_id=:round_id AND applied=0;", [
				'strategy_id' => $user_game['strategy_id'],
				'round_id' => $current_round_id
			])->fetchAll();
			
			if (count($db_allocations) > 0) {
				$allocations = [];
				$point_sum = 0;
				
				foreach ($db_allocations as $allocation) {
					$allocations[count($allocations)] = $allocation;
					$point_sum += intval($allocation['points']);
				}
				
				$option_ids = [];
				$amounts = [];
				$amount_sum = 0;
				
				for ($i=0; $i<count($allocations); $i++) {
					$option_ids[$i] = $allocations[$i]['option_id'];
					$amount = floor(($spendable_balance-$user_game['transaction_fee'])*$allocations[$i]['points']/$point_sum);
					$amounts[$i] = $amount;
					$amount_sum += $amount;
				}
				if ($amount_sum < ($spendable_balance-$user_game['transaction_fee'])) $amounts[count($amounts)-1] += ($spendable_balance-$user_game['transaction_fee']) - $amount_sum;
				
				$error_message = false;
				$transaction_id = $this->create_transaction($option_ids, $amounts, $user_game, false, 'transaction', false, false, false, $user_game['transaction_fee'], $error_message);
				
				if ($transaction_id) {
					$log_text .= "Added transaction $transaction_id<br/>\n";
					
					for ($i=0; $i<count($allocations); $i++) {
						$this->blockchain->app->run_query("UPDATE strategy_round_allocations SET applied=1 WHERE allocation_id=:allocation_id;", ['allocation_id'=>$allocations[$i]['allocation_id']]);
					}
				}
				else $log_text .= $error_message."<br/>\n";
			}
		}
	}
	
	public function reset_blocks_from_block($block_height) {
		$this->blockchain->app->log_message("Resetting ".$this->db_game['name']." from block ".$block_height);

		$prev_block = $this->fetch_game_block_by_height($block_height-1);

		if (!$prev_block) {
			$this->blockchain->app->log_message("Failed to fetch block #".($block_height-1)." when resetting ".$this->db_game['name']." from block ".$block_height, true);
			return null;
		}

		$delete_from_gio_index = $prev_block['max_game_io_index'] + 1;

		$this->blockchain->app->dbh->beginTransaction();
		
		$this->blockchain->app->run_query("DELETE FROM game_blocks WHERE game_id=:game_id AND block_id >= :block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_height
		]);
		
		$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index IS NULL;", [
			'game_id' => $this->db_game['game_id']
		]);
		
		$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index >= :game_io_index;", [
			'game_id' => $this->db_game['game_id'],
			'game_io_index' => $delete_from_gio_index,
		]);
		
		$this->blockchain->app->run_query("UPDATE transaction_game_ios SET spend_round_id=NULL WHERE game_id=:game_id AND spend_round_id >= :round_id;", [
			'game_id' => $this->db_game['game_id'],
			'round_id' => $this->block_to_round($block_height)
		]);
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->blockchain->app->run_query("DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id AND ob.block_height >= :block_id;", [
				'game_id' => $this->db_game['game_id'],
				'block_id' => $block_height
			]);
		}
		else {
			$this->blockchain->app->run_query("DELETE FROM option_blocks WHERE block_height >= :block_id AND option_id IN (SELECT o.option_id FROM options o JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id);", [
				'game_id' => $this->db_game['game_id'],
				'block_id' => $block_height
			]);
		}
		$this->blockchain->app->run_query("UPDATE games SET loaded_until_block=NULL, coins_in_existence=0, cached_pending_bets=NULL, cached_vote_supply=NULL, current_pow_reward=NULL WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id']
		]);
		
		$user_game = false;
		
		list($genesis_error, $genesis_error_message) = $this->ensure_genesis_transaction();
		
		$this->blockchain->app->dbh->commit();
	}
	
	public function reset_events_from_index($event_index) {
		$this->blockchain->app->log_message("Deleting ".$this->db_game['name']." events from ".$event_index);
		
		$this->blockchain->app->dbh->beginTransaction();
		
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->blockchain->app->run_query("DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id=:game_id AND e.event_index >= :event_index;", [
				'game_id' => $this->db_game['game_id'],
				'event_index' => $event_index
			]);
		}
		else {
			$this->blockchain->app->run_query("DELETE FROM options WHERE event_id IN (SELECT event_id FROM events WHERE game_id=:game_id AND event_index >= :event_index);", [
				'game_id' => $this->db_game['game_id'],
				'event_index' => $event_index
			]);
			$this->blockchain->app->run_query("DELETE FROM events WHERE game_id=:game_id AND event_index >= :event_index;", [
				'game_id' => $this->db_game['game_id'],
				'event_index' => $event_index
			]);
		}
		
		$info = $this->blockchain->app->run_query("SELECT MAX(event_starting_block) FROM events WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id']
		])->fetch();
		
		$events_until_block = $info['MAX(event_starting_block)'];
		if ((string)$events_until_block == "") $events_until_block = "NULL";
		
		$this->blockchain->app->run_query("UPDATE games SET events_until_block=:events_until_block WHERE game_id=:game_id;", [
			'events_until_block' => $events_until_block,
			'game_id' => $this->db_game['game_id']
		]);
		
		$this->blockchain->app->dbh->commit();
	}
	
	public function reset_block_to_event_index($block_id) {
		$event_info = $this->blockchain->app->run_query("SELECT MIN(event_index) FROM events WHERE game_id=:game_id AND event_starting_block>=:ref_block;", [
			'game_id' => $this->db_game['game_id'],
			'ref_block' => $block_id
		])->fetch();
		
		if ($event_info && (string) $event_info['MIN(event_index)'] !== "") return (int) $event_info['MIN(event_index)'];
		else return false;
	}
	
	public function reset_event_index_to_block($event_index) {
		$event_info = $this->blockchain->app->run_query("SELECT MIN(event_starting_block) FROM events WHERE game_id=:game_id AND event_index>=:event_index;", [
			'game_id' => $this->db_game['game_id'],
			'event_index' => $event_index
		])->fetch();
		
		if ($event_info) return $event_info['MIN(event_starting_block)'];
		else return false;
	}
	
	public function delete_reset_game($delete_or_reset) {
		$this->blockchain->app->log_message("Resetting ".$this->db_game['name']." (".$delete_or_reset.")");
		
		$this->blockchain->app->dbh->beginTransaction();
		
		$this->blockchain->app->run_query("DELETE FROM game_blocks WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		
		$delete_limit = 20000;
		$max_io_index = $this->max_game_io_index();
		$delete_gio_queries = ceil(($max_io_index+1)/$delete_limit);
		$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index IS NULL;", ['game_id'=>$this->db_game['game_id']]);
		
		for ($d=0; $d<$delete_gio_queries; $d++) {
			$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index >= :from_index AND game_io_index <= :to_index;", [
				'game_id' => $this->db_game['game_id'],
				'from_index' => $d*$delete_limit,
				'to_index' => ($d+1)*$delete_limit
			]);
		}
		
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->blockchain->app->run_query("DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
			$this->blockchain->app->run_query("DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		}
		else {
			$this->blockchain->app->run_query("DELETE FROM option_blocks where option_id IN (SELECT o.option_id FROM options o JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id);", ['game_id'=>$this->db_game['game_id']]);
			$this->blockchain->app->run_query("DELETE FROM options WHERE event_id IN (SELECT event_id FROM events WHERE game_id=:game_id);", ['game_id'=>$this->db_game['game_id']]);
			$this->blockchain->app->run_query("DELETE FROM events WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		}

		$this->blockchain->app->run_query("UPDATE games SET events_until_block=NULL, loaded_until_block=NULL, min_option_index=NULL, max_option_index=NULL, current_pow_reward=NULL WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		
		if ($delete_or_reset == "reset") {
			$this->blockchain->app->run_query("UPDATE games SET coins_in_existence=0, cached_pending_bets=NULL, cached_vote_supply=NULL, cached_definition_hash=NULL, defined_cached_definition_hash=NULL, cached_definition_time=NULL, defined_cached_definition_time=NULL WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
			
			list($genesis_error, $genesis_error_message) = $this->ensure_genesis_transaction();
		}
		else {
			$this->blockchain->app->run_query("DELETE g.*, ug.* FROM games g, user_games ug WHERE g.game_id=:game_id AND ug.game_id=g.game_id;", ['game_id'=>$this->db_game['game_id']]);
			$this->blockchain->app->run_query("DELETE s.*, sra.* FROM user_strategies s LEFT JOIN strategy_round_allocations sra ON s.strategy_id=sra.strategy_id WHERE s.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		}
		$this->blockchain->app->dbh->commit();
	}
	
	public function event_outcomes_html($from_event_index, $to_event_index, $thisuser) {
		$html = "";
		
		$last_block_id = $this->blockchain->last_block_id();
		$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
		$show_initial = false;
		$reference_currency = $this->blockchain->app->get_reference_currency();
		
		$db_events = $this->blockchain->app->run_query("SELECT e.*, winner.name AS winner_name, ten.forex_pair_shows_nonstandard FROM events e LEFT JOIN options winner ON e.winning_option_id=winner.option_id LEFT JOIN entities ten ON e.track_entity_id=ten.entity_id WHERE e.game_id=:game_id AND e.event_index >= :from_event_index AND e.event_index <= :to_event_index ORDER BY e.event_index DESC;", [
			'game_id' => $this->db_game['game_id'],
			'from_event_index' => $from_event_index,
			'to_event_index' => $to_event_index
		]);
		
		$last_round_shown = 0;
		while ($db_event = $db_events->fetch()) {
			$event_total_bets = ($db_event['sum_score']+$db_event['sum_unconfirmed_score'])*$coins_per_vote + $db_event['destroy_score'] + $db_event['sum_unconfirmed_destroy_score'];
			$event_effective_bets = ($db_event['sum_votes']+$db_event['sum_unconfirmed_votes'])*$coins_per_vote + $db_event['effective_destroy_score'] + $db_event['sum_unconfirmed_effective_destroy_score'];
			
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-3"><a href="/explorer/games/'.$this->db_game['url_identifier'].'/events/'.$db_event['event_index'].'">'.$db_event['event_name'].'</a></div>';
			$html .= '<div class="col-sm-4">';
			
			if ($db_event['outcome_index'] == -1) $html .= '<font class="text-warning">Refunded</font>';
			else if ($db_event['payout_rule'] == "binary") {
				if ($db_event['winning_option_id'] > 0) {
					if (!empty($db_event['option_block_rule'])) {
						$options_by_event = $this->blockchain->app->fetch_options_by_event($db_event['event_id']);
						$score_label = "";
						while ($option = $options_by_event->fetch()) {
							if (empty($score_label)) $score_label = $option['option_block_score']."-";
							else $score_label .= $option['option_block_score'];
						}
						$html .= " ".$score_label." &nbsp;&nbsp; ";
					}
					
					$winning_effective_coins = $db_event['winning_votes']*$coins_per_vote + $db_event['winning_effective_destroy_score'];
					
					if ($event_effective_bets > 0) {
						$winner_pct = $winning_effective_coins/$event_effective_bets;
						$html .= $this->blockchain->app->round_to(100*$winner_pct, 0, EXCHANGE_RATE_SIGFIGS, true)."% &nbsp;&nbsp; ";
					}
					
					if ($winning_effective_coins > 0) {
						$winner_odds = $db_event['payout_rate']*$event_effective_bets/$winning_effective_coins;
						$html .= "x".$this->blockchain->app->round_to($winner_odds, 0, EXCHANGE_RATE_SIGFIGS, true)." &nbsp;&nbsp; ";
					}
					
					$html .= $db_event['winner_name'];
				}
				else $html .= "No winner";
			}
			else {
				$buy_option = $this->blockchain->app->fetch_option_by_event_option_index($db_event['event_id'], 0);
				
				$buy_stake = $buy_option['effective_destroy_score']+$buy_option['unconfirmed_effective_destroy_score'] + $coins_per_vote*($buy_option['votes']+$buy_option['unconfirmed_votes']);
				
				$ref_price_fresh = null;
				
				if ((string)$db_event['track_payout_price'] != "") {
					$ref_price_usd = $db_event['track_payout_price'];
					$ref_price_usd_round = $ref_price_usd;
					$ref_price_fresh = true;
					$html .= "<b>Paid</b>";
				}
				else {
					if ($last_block_id < $db_event['event_final_block']) $html .= "<font class='greentext'>Running</font>";
					else $html .= "<font class='yellowtext'>Not Paid</font>";
					
					$ref_currency = $this->blockchain->app->get_currency_by_abbreviation($db_event['track_name_short']);
					$ref_price_info = $this->blockchain->app->exchange_rate_between_currencies(1, $ref_currency['currency_id'], time(), $reference_currency['currency_id']);
					if (isset($ref_price_info['exchange_rate']) && $ref_price_info['time'] >= time() - AppSettings::exchangeRateFreshMaxSec()) {
						$ref_price_usd = max($db_event['track_min_price'], min($db_event['track_max_price'], $ref_price_info['exchange_rate']));
						$ref_price_usd_round = $this->blockchain->app->round_to($ref_price_usd, 0, EXCHANGE_RATE_SIGFIGS, false);
						$ref_price_fresh = true;
					}
					else $ref_price_fresh = false;
				}
				$html .= " &nbsp;&nbsp; ";
				
				$forex_pair = $db_event['forex_pair_shows_nonstandard'] ? $db_event['track_name_short']."/USD" : "USD/".$db_event['track_name_short'];
				$html .= "<div style='display: inline-block; min-width: 190px;'>".$forex_pair." &nbsp; ";
				if ($event_effective_bets > 0) {
					$our_buy_price = ($buy_stake/$event_effective_bets)*($db_event['track_max_price']-$db_event['track_min_price'])+$db_event['track_min_price'];
					if ($db_event['forex_pair_shows_nonstandard']) {
						$our_buy_price_round = $this->blockchain->app->round_to($our_buy_price, 0, EXCHANGE_RATE_SIGFIGS, false);
						$html .= $this->blockchain->app->round_to($our_buy_price, 0, EXCHANGE_RATE_SIGFIGS, true);
						if ($ref_price_fresh) $html .= " &rarr; ".$this->blockchain->app->round_to($ref_price_usd_round, 0, EXCHANGE_RATE_SIGFIGS, true);
					}
					else {
						$our_buy_price_round = $this->blockchain->app->round_to(1/$our_buy_price, 0, EXCHANGE_RATE_SIGFIGS, false);
						$html .= $this->blockchain->app->round_to($our_buy_price_round, 0, EXCHANGE_RATE_SIGFIGS, true);
						if ($ref_price_fresh) $html .= " &rarr; ".$this->blockchain->app->round_to(1/$ref_price_usd, 0, EXCHANGE_RATE_SIGFIGS, true);
					}
				}
				$html .= "</div>\n";
				
				if ($event_effective_bets > 0 && $ref_price_fresh) {
					if ($ref_price_usd_round == $our_buy_price_round) $pct_gain = 0;
					else $pct_gain = 100*($ref_price_usd/$our_buy_price - 1);
					$pct_gain_str = $this->blockchain->app->round_to(abs($pct_gain), 0, 4, true);
					
					if ($pct_gain >= 0) $html .= ' &nbsp; <font class="greentext">+'.$pct_gain_str."%</font>\n";
					else $html .= ' &nbsp; <font class="redtext">-'.$pct_gain_str."%</font>\n";
				}
			}
			$html .= "</div>";
			$html .= '<div class="col-sm-3">'.$this->display_coins($event_total_bets).' bet</div>';
			
			$html .= "</div>\n";
		}
		
		$returnvals[0] = $last_round_shown;
		$returnvals[1] = $html;
		
		return $returnvals;
	}
	
	public function option_index_range() {
		return [AppSettings::getParam('options_begin_at_index'), AppSettings::getParam('options_begin_at_index')+$this->db_game['max_simultaneous_options']-1];
	}
	
	public function option_index_to_current_option_id($option_index) {
		return $this->option_index_to_option_id_in_block($option_index, $this->blockchain->last_block_id()+1);
	}
	
	public function option_index_to_option_id_in_block($option_index, $block_id) {
		$first_option = $this->blockchain->app->run_query("SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=:game_id AND op.option_index=:option_index AND e.event_starting_block<=:block_id AND e.event_final_block>=:block_id ORDER BY e.event_index ASC, op.option_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'option_index' => $option_index,
			'block_id' => $block_id
		])->fetch();
		
		if ($first_option) return $first_option['option_id'];
		else return false;
	}
	
	public function option_indices_to_id_in_block($option_indices, $block_height, &$option_index_to_id) {
		$option_indices_csv = implode(",", array_keys($option_indices));
		
		$options = $this->blockchain->app->run_query("SELECT op.option_index, op.option_id FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=:game_id AND op.option_index IN (".$option_indices_csv.") AND e.event_starting_block<=:block_id AND e.event_final_block>=:block_id ORDER BY e.event_index ASC, op.option_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_height
		])->fetchAll();
		
		foreach ($options as $option) {
			if (!isset($option_index_to_id[$option['option_index']])) $option_index_to_id[$option['option_index']] = $option['option_id'];
		}
	}
	
	public function generate_invitation($inviter_id, &$invitation, $user_id) {
		$new_invitation_params = [
			'game_id' => $this->db_game['game_id'],
			'invitation_key' => strtolower($this->blockchain->app->random_string(32)),
			'time_created' => time()
		];
		if ($inviter_id > 0) {
			$new_invitation_params['inviter_id'] = $inviter_id;
		}
		if ($user_id) {
			$new_invitation_params['used_user_id'] = $user_id;
		}
		$this->blockchain->app->run_insert_query("game_invitations", $new_invitation_params);
		$invitation_id = $this->blockchain->app->last_insert_id();
		
		$invitation = $this->blockchain->app->run_query("SELECT * FROM game_invitations WHERE invitation_id=:invitation_id;", ['invitation_id'=>$invitation_id])->fetch();
	}
	
	public function get_user_strategy(&$user_game, &$user_strategy) {
		$user_strategy = $this->blockchain->app->fetch_strategy_by_id($user_game['strategy_id']);
		if ($user_strategy) return true;
		else return false;
	}
	
	public function plan_options_html($from_round, $to_round, $user_strategy) {
		$to_block_id = $to_round*$this->db_game['round_length']+1;
		$html = "
		<script type='text/javascript'>
		thisPageManager.plan_option_max_points = 5;
		thisPageManager.plan_option_increment = 1;
		thisPageManager.plan_rounds = [];
		thisPageManager.round_id2plan_round_id = {};
		</script>\n";
		$js = "";
		$round_i = 0;
		
		for ($round=$from_round; $round<=$to_round; $round++) {
			$js .= "var temp_plan_round = new PlanRound(".$round.");\n";
			$js .= "thisPageManager.round_id2plan_round_id[".$round."] = ".$round_i.";\n";
			
			$block_id = ($round-1)*$this->db_game['round_length']+1;
			$filter_arr = false;
			$events = $this->events_by_block($block_id, $filter_arr);
			
			$html .= '<div class="plan_row"><b>Round #'.$this->round_to_display_round($round)."</b><br/>\n";
			
			for ($event_i=0; $event_i<count($events); $event_i++) {
				$js .= "temp_plan_round.event_ids.push(".$events[$event_i]->db_event['event_id'].");\n";
				$option_index = 0;
				$html .= '<div class="planned_votes_event">'.$events[$event_i]->db_event['event_name'].'<br/>';
				
				$thisevent_options = $this->blockchain->app->fetch_options_by_event($events[$event_i]->db_event['event_id']);
				
				while ($game_option = $thisevent_options->fetch()) {
					$html .= '<div class="plan_option" id="plan_option_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" onclick="thisPageManager.plan_option_clicked('.$round.', '.$events[$event_i]->db_event['event_id'].', '.$game_option['option_id'].');">';
					$html .= '<div class="plan_option_label" id="plan_option_label_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'">'.$game_option['name']."</div>";
					$html .= '<div class="plan_option_amount" id="plan_option_amount_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'"></div>';
					$html .= '<input type="hidden" id="plan_option_input_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" name="poi_'.$round.'_'.$game_option['option_id'].'" value="" />';
					$html .= '</div>';
					$option_index++;
				}
				$html .= "</div>\n";
			}
			$js .= "thisPageManager.plan_rounds.push(temp_plan_round);\n";
			$html .= "</div>\n";
			$round_i++;
		}
		$html .= '<script type="text/javascript">'.$js."\n".$this->load_all_event_points_js(0, $user_strategy, $from_round, $to_round)."\n</script>\n";
		return $html;
	}
	
	public function start_game() {
		$any_error = null;
		$error_message = null;
		
		$start_block = $this->blockchain->fetch_block_by_id($this->db_game['game_starting_block']);
		
		if ($start_block && $start_block['locally_saved']) {
			list($genesis_error, $genesis_error_message) = $this->ensure_genesis_transaction();
			
			if ($genesis_error) {
				$any_error = true;
				$error_message = $genesis_error_message;
			}
			else {
				$this->set_game_status('running');
				$this->set_loaded_until_block(null);
				if (!empty($this->db_game['definitive_game_peer_id'])) $this->sync_with_definitive_peer(true);
				
				$any_error = false;
			}
		}
		else {
			$any_error = true;
			$error_message = "Blockchain hasn't yet loaded block #".$this->db_game['game_starting_block'];
		}
		
		return [$any_error, $error_message];
	}
	
	public function max_game_io_index() {
		$info = $this->blockchain->app->run_query("SELECT MAX(game_io_index) FROM transaction_game_ios WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch();
		if ($info && $info['MAX(game_io_index)'] != "") return (int)$info['MAX(game_io_index)'];
		else return null;
	}
	
	public function process_buyin_transaction($transaction) {
		if ((string)$this->db_game['game_starting_block'] !== "" && !empty($this->db_game['escrow_address'])) {
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], false, null);
			
			$io_out_count = $this->blockchain->app->run_query("SELECT COUNT(*) FROM transaction_ios WHERE create_transaction_id=:create_transaction_id AND address_id=:address_id;", [
				'create_transaction_id' => $transaction['transaction_id'],
				'address_id' => $escrow_address['address_id']
			])->fetch()['COUNT(*)'];
			
			$game_io_out_count = $this->blockchain->app->run_query("SELECT COUNT(*) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.create_transaction_id=:transaction_id;", [
				'game_id' => $this->db_game['game_id'],
				'transaction_id' => $transaction['transaction_id']
			])->fetch()['COUNT(*)'];
			
			if ($io_out_count > 0 && $game_io_out_count == 0) {
				$escrowed_coins = $this->blockchain->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id=:transaction_id AND address_id=:address_id;", [
					'transaction_id' => $transaction['transaction_id'],
					'address_id' => $escrow_address['address_id']
				])->fetch()['SUM(amount)'];
				
				$non_escrowed_coins = $this->blockchain->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id=:transaction_id AND address_id != :address_id;", [
					'transaction_id' => $transaction['transaction_id'],
					'address_id' => $escrow_address['address_id']
				])->fetch()['SUM(amount)'];
				
				if ($transaction['tx_hash'] == $this->db_game['genesis_tx_hash']) {
					$coins_generated = $this->db_game['genesis_amount']*pow(10, $this->db_game['decimal_places']);
				}
				else {
					$escrow_value = $this->escrow_value($transaction['block_id']-1);
					$coins_in_existence = $this->coins_in_existence($transaction['block_id']-1, false);
					
					$exchange_rate = $coins_in_existence/$escrow_value;
					$coins_generated = floor($exchange_rate*$escrowed_coins)*pow(10, $this->db_game['decimal_places']);
				}
				$game_io_index = $this->max_game_io_index();
				if ($game_io_index === null) $game_io_index = -1;
				$game_out_index = 0;
				
				$sum_colored_coins = 0;
				
				$create_round_id = $this->block_to_round($transaction['block_id']);
				
				$non_escrow_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.address_id != :address_id AND a.is_destroy_address=0 ORDER BY io.out_index ASC;", [
					'transaction_id' => $transaction['transaction_id'],
					'address_id' => $escrow_address['address_id']
				]);
				
				while ($non_escrow_io = $non_escrow_ios->fetch()) {
					$colored_coins = $coins_generated*$non_escrow_io['amount']/$non_escrowed_coins;
					
					if ($transaction['tx_hash'] == $this->db_game['genesis_tx_hash']) $colored_coins = round($colored_coins);
					else $colored_coins = floor($colored_coins);
					
					$sum_colored_coins += $colored_coins;
					
					$game_io_index++;
					
					$this->blockchain->app->run_insert_query("transaction_game_ios", [
						'io_id' => $non_escrow_io['io_id'],
						'address_id' => $non_escrow_io['address_id'],
						'game_id' => $this->db_game['game_id'],
						'colored_amount' => $colored_coins,
						'create_block_id' => $transaction['block_id'],
						'create_round_id' => $create_round_id,
						'game_out_index' => $game_out_index,
						'game_io_index' => $game_io_index,
						'is_game_coinbase' => 0,
						'coin_blocks_destroyed' => 0,
						'coin_rounds_destroyed' => 0,
						'is_resolved' => 1
					]);
					$game_out_index++;
				}
			}
		}
	}
	
	public function escrow_value($block_id) {
		if (!$block_id) $block_id = $this->blockchain->last_block_id();
		
		$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], false, null);
		
		$value = $this->blockchain->address_balance_at_block($escrow_address, $block_id);
		
		return $value;
	}
	
	public function account_value_html($account_value, &$user_game, $game_pending_bets) {
		$value_disp = $this->display_coins($account_value, false, true);
		$html = '<font class="greentext"><a href="/accounts/?account_id='.$user_game['account_id'].'">'.$value_disp.'</a></font> '.($value_disp=="1" ? $this->db_game['coin_name'] : $this->db_game['coin_name_plural']);
		
		$coins_in_existence = $this->coins_in_existence(false, true)+$game_pending_bets;
		
		$display_currency = $this->blockchain->app->fetch_currency_by_id($user_game['display_currency_id']);
		
		list($escrow_value, $exchange_rate_as_of) = $this->escrow_value_in_currency($display_currency['currency_id'], $coins_in_existence/pow(10, $this->db_game['decimal_places']));
		
		if ($coins_in_existence > 0) {
			$display_value = ($account_value/$coins_in_existence)*$escrow_value;
		}
		else $display_value = 0;
		
		if ($display_value > 0) {
			$html .= ' <font class="account_escrow_value">(';
			$escrow_display_value = $this->blockchain->app->format_bignum($display_value, false);
			$html .= $escrow_display_value." ".($escrow_display_value == "1" ? $display_currency['short_name'] : $display_currency['short_name_plural']);
			$html .= ")</font>";
		}
		
		return $html;
	}
	
	public function send_invitation_email($to_email, &$invitation) {
		$invite_currency = false;
		if ($this->db_game['invite_currency'] > 0) {
			$invite_currency = $this->blockchain->app->fetch_currency_by_id($this->db_game['invite_currency']);
		}
		
		$subject = "You've been invited to join ".$this->db_game['name'];
		
		if ($this->db_game['short_description'] != "") {
			$message .= "<p>".$this->db_game['short_description']."</p>";
		}
		
		$this->db_game['seconds_per_block'] = $this->blockchain->db_blockchain['seconds_per_block'];
		
		$table = str_replace('<div class="row"><div class="col-sm-5">', '<tr><td>', $this->blockchain->app->game_info_table($this->db_game));
		$table = str_replace('</div><div class="col-sm-7">', '</td><td>', $table);
		$table = str_replace('</div></div>', '</td></tr>', $table);
		$table = str_replace('href="', 'href="'.AppSettings::getParam('base_url'), $table);
		
		$message .= '<table>'.$table.'</table>';
		$message .= "<p>To start playing, accept your invitation by following <a href=\"".AppSettings::getParam('base_url')."/wallet/".$this->db_game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
		$message .= "<p>This message was sent to you by ".AppSettings::getParam('site_name')."</p>";
		
		$email_id = $this->blockchain->app->mail_async($to_email, AppSettings::getParam('site_name'), AppSettings::defaultFromEmailAddress(), $subject, $message, "", "", "");
		
		$this->blockchain->app->run_query("UPDATE game_invitations SET sent_email_id=:sent_email_id WHERE invitation_id=:invitation_id;", [
			'sent_email_id' => $email_id,
			'invitation_id' => $invitation['invitation_id']
		]);
		
		return $email_id;
	}
	
	public function game_status_explanation(&$user, &$user_game) {
		$last_block_id = $this->blockchain->last_block_id();
		
		$html = "";
		if ($this->db_game['game_status'] == "editable") $html .= "The game creator hasn't yet published this game; its parameters can still be changed. ";
		else if ($this->db_game['game_status'] == "published") {
			if ($this->db_game['start_condition'] == "fixed_block") {
				$html .= "This game starts ";
				if ($this->db_game['game_starting_block'] > $this->blockchain->last_block_id()) $html .= " in ".($this->db_game['game_starting_block']-$this->blockchain->last_block_id())." blocks. ";
				else $html .= " on block #".$this->db_game['game_starting_block'].". ";
			}
		}
		else if ($this->db_game['game_status'] == "completed") $html .= "This game is over. ";
		
		$private_game_message = "";
		
		if ($this->blockchain->db_blockchain['p2p_mode'] == "none") {
			$seconds_to_add = max(0, time()-$this->blockchain->db_blockchain['last_hash_time']);
			$link_show_cron = false;
			
			if (empty($this->blockchain->db_blockchain['last_hash_time'])) $link_show_cron = true;
			else if ($seconds_to_add > 10*$this->blockchain->db_blockchain['seconds_per_block']) {
				$private_game_message = "Mining new blocks... ".$this->blockchain->app->format_seconds($seconds_to_add)." left. \n";
				$link_show_cron = true;
			}
			if ($link_show_cron) $private_game_message .= "Please <a target=\"_blank\" href=\"/cron/minutely.php?key=\">ensure ".AppSettings::getParam('site_name')." is running</a>";
			
			if (!empty($private_game_message)) $html .= "<p>".$private_game_message."</p>\n";
		}
		
		$total_blocks = $last_block_id;
		
		$total_game_blocks = 1 + $last_block_id - $this->db_game['game_starting_block'];
		
		$missingheader_blocks = $this->blockchain->app->run_query("SELECT COUNT(*) FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :block_id AND block_hash IS NULL;", [
			'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
			'block_id' => $this->db_game['game_starting_block']
		])->fetch()['COUNT(*)'];
		
		$missing_blocks = $this->blockchain->app->run_query("SELECT COUNT(*) FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :block_id AND locally_saved=0;", [
			'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
			'block_id' => $this->db_game['game_starting_block']
		])->fetch()['COUNT(*)'];
		
		$last_block_loaded = $this->last_block_id();
		$missing_game_blocks = $last_block_id - max($this->db_game['game_starting_block']-1, $last_block_loaded);
		
		$loading_block = false;
		
		$block_fraction = 0;
		if ($missing_blocks > 0) {
			$loading_block_id = $this->blockchain->db_blockchain['last_complete_block']+1;
			
			$sample_size = 10;
			$time_data = $this->blockchain->app->run_query("SELECT SUM(load_time), COUNT(*) FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=1 AND block_id >= :from_block_id AND block_id<:to_block_id;", [
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
				'from_block_id' => $loading_block_id-$sample_size,
				'to_block_id' => $loading_block_id
			])->fetch();
			if ($time_data['COUNT(*)'] > 0) $time_per_block = $time_data['SUM(load_time)']/$time_data['COUNT(*)'];
			else $time_per_block = 0;
			
			$loading_block = $this->blockchain->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id=:block_id;", [
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
				'block_id' => $loading_block_id
			])->fetch();
			
			if ($loading_block) {
				$loading_transactions = $this->blockchain->set_block_stats($loading_block);
				if ($loading_block['num_transactions'] > 0) $block_fraction = $loading_transactions/$loading_block['num_transactions'];
				else $block_fraction = 0;
			}
		}
		else $time_per_block = 0;
		
		if ($total_game_blocks == 0) {
			$headers_pct_complete = 100;
			$blocks_pct_complete = 100;
		}
		else {
			$headers_pct_complete = 100*($total_game_blocks-$missingheader_blocks)/$total_game_blocks;
			$blocks_pct_complete = 100*($total_game_blocks-$missing_blocks)/$total_game_blocks;
		}
		$est_time_remaining = $missing_blocks*$time_per_block;
		
		if ($missing_blocks > 0) $html .= "<p>Loading blocks.. ".round($blocks_pct_complete, 2)."% complete (".number_format($missing_blocks)." blocks remain.. ".$this->blockchain->app->format_seconds($est_time_remaining)." left).</p>\n";
		if ($loading_block) {
			$html .= "<p>Loaded ".$loading_transactions."/".$loading_block['num_transactions']." in block <a href=\"/explorer/games/".$this->db_game['url_identifier']."/blocks/".$loading_block_id."\">#".$loading_block_id."</a>.</p>\n";
		}
		
		if ($this->db_game['events_until_block'] < $last_block_id) {
			if (empty($this->db_game['events_until_block'])) $events_until_block = $this->db_game['game_starting_block'];
			else $events_until_block = $this->db_game['events_until_block'];
			
			$events_pct_complete = $total_game_blocks > 0 ? (max($events_until_block, $this->db_game['game_starting_block'])-$this->db_game['game_starting_block'])/$total_game_blocks : 0;
			$html .= "<p>Loading events.. <a target=\"_blank\" href=\"/explorer/games/".$this->db_game['url_identifier']."/events/\">".round(100*$events_pct_complete, 2)."% complete</a>.</p>\n";
		}
		
		if ($total_game_blocks == 0) $game_blocks_pct_complete = 100;
		else $game_blocks_pct_complete = 100*($total_game_blocks-$missing_game_blocks)/$total_game_blocks;
		
		if ($missing_game_blocks > 0) {
			$sample_size = 100;
			$last_block = $this->fetch_game_block_by_height($last_block_loaded);
			$sample_block = $this->fetch_game_block_by_height(max($this->db_game['game_starting_block'], $last_block_loaded-100));
			
			if (empty($sample_block) || $last_block['block_id'] == $sample_block['block_id']) $time_per_block = 0;
			else $time_per_block = ($last_block['time_loaded']-$sample_block['time_loaded'])/($last_block['block_id']-$sample_block['block_id']);
			
			$html .= "<p>Synced to block <a target='_blank' href='/explorer/games/".$this->db_game['url_identifier']."/blocks/".$last_block_loaded."'>".$last_block_loaded."</a>, loading ".number_format($missing_game_blocks)." game block";
			if ($missing_game_blocks != 1) $html .= "s";
			
			if ($missing_game_blocks > 1) {
				$html .= " (".round(max(0, $game_blocks_pct_complete), 2)."% complete";
				$seconds_left = $time_per_block*$missing_game_blocks;
				$html .= ".. ".$this->blockchain->app->format_seconds($seconds_left)." remaining";
				$html .= ").</p>\n";
			}
		}
		
		return $html;
	}
	
	public function render_game_players() {
		$html = "";
		
		$user_games = $this->blockchain->app->run_query("SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id=:game_id GROUP BY ug.user_id ORDER BY u.user_id ASC;", ['game_id'=>$this->db_game['game_id']])->fetchAll();
		
		$html .= "<b>".count($user_games)." players</b><br/>\n";
		
		foreach ($user_games as $user_game) {
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-4">';
			$html .= '<a href="" onclick="thisPageManager.openChatWindow('.$user_game['user_id'].'); return false;">Player'.$user_game['user_id'].'</a>';
			$html .= '</div>';
			$html .= '</div>';
		}
		
		return $html;
	}
	
	public function scramble_plan_allocations($strategy, $weight_map, $from_round, $to_round) {
		if (!$weight_map) $weight_map[0] = 1;
		
		$this->blockchain->app->run_query("DELETE FROM strategy_round_allocations WHERE strategy_id=:strategy_id AND round_id >= :from_round AND round_id <= :to_round;", [
			'strategy_id' => $strategy['strategy_id'],
			'from_round' => $from_round,
			'to_round' => $to_round
		]);
		
		for ($round_id=$from_round; $round_id<=$to_round; $round_id++) {
			$block_id = ($round_id-1)*$this->db_game['round_length']+1;
			$filter_arr = false;
			$events = $this->events_by_block($block_id, $filter_arr);
			$option_list = [];
			
			for ($e=0; $e<count($events); $e++) {
				$options_by_event = $this->blockchain->app->fetch_options_by_event($events[$e]->db_event['event_id']);
				while ($option = $options_by_event->fetch()) {
					$option_list[count($option_list)] = $option;
				}
			}
			
			$used_option_ids = false;
			
			for ($i=0; $i<count($weight_map); $i++) {
				$option_index = rand(0, count($option_list)-1);
				
				if (empty($used_option_ids[$option_list[$option_index]['option_id']])) {
					$points = round($weight_map[$i]*rand(1, 5));
					
					$this->blockchain->app->run_insert_query("strategy_round_allocations", [
						'strategy_id' => $strategy['strategy_id'],
						'round_id' => $round_id,
						'option_id' => $option_list[$option_index]['option_id'],
						'points' => $points
					]);
					
					$used_option_ids[$option_list[$option_index]['option_id']] = true;
				}
			}
		}
	}
	
	public function last_block_id() {
		$game_block = $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_id=:game_id AND locally_saved=1 ORDER BY block_id DESC LIMIT 1;", ['game_id'=>$this->db_game['game_id']])->fetch();
		
		if ($game_block) return (int) $game_block['block_id'];
		else return $this->db_game['game_starting_block']-1;
	}
	
	public function coins_in_existence($block_id, $use_cache) {
		if ($use_cache && $this->db_game['coins_in_existence'] != 0) return $this->db_game['coins_in_existence'];
		else {
			$in_existence_params = [
				'game_id' => $this->db_game['game_id']
			];
			$in_existence_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id WHERE gio.game_id=:game_id";
			if ($block_id !== false) {
				$in_existence_q .= " AND gio.create_block_id <= :block_id AND (io.spend_block_id IS NULL OR io.spend_block_id>:block_id)";
				$in_existence_params['block_id'] = $block_id;
			}
			else $in_existence_q .= " AND io.spend_status IN ('unspent','unconfirmed')";
			$in_existence_q .= ";";
			$coins = (int)($this->blockchain->app->run_query($in_existence_q, $in_existence_params)->fetch(PDO::FETCH_NUM)[0]);
			
			if ($block_id !== false) {
				$doublecounted_amount = (int)($this->blockchain->app->run_query("SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN options op ON gio.option_id=op.option_id JOIN events ev ON op.event_id=ev.event_id WHERE gio.game_id=:game_id AND gio.create_block_id <= :block_id AND (io.spend_block_id IS NULL OR io.spend_block_id>:block_id) AND gio.is_game_coinbase=1 AND ev.event_payout_block > :block_id AND ev.event_payout_block <= :last_block_id AND ev.event_starting_block <= :block_id;", [
					'game_id' => $this->db_game['game_id'],
					'block_id' => $block_id,
					'last_block_id' => $this->last_block_id(),
				])->fetch(PDO::FETCH_NUM)[0]);
				if ($doublecounted_amount > 0) $coins -= $doublecounted_amount;
			}
			
			if ($block_id === false) {
				$this->blockchain->app->run_query("UPDATE games SET coins_in_existence=:coins_in_existence WHERE game_id=:game_id;", [
					'coins_in_existence' => $coins,
					'game_id' => $this->db_game['game_id']
				]);
				$this->db_game['coins_in_existence'] = $coins;
			}
			
			return $coins;
		}
	}
	
	public function fetch_user_strategy(&$user_game) {
		$user_strategy = $this->blockchain->app->fetch_strategy_by_id($user_game['strategy_id']);

		if (!$user_strategy) {
			$user_strategy = $this->blockchain->app->run_query("SELECT * FROM user_strategies WHERE user_id=:user_id AND game_id=:game_id;", [
				'user_id' => $user_game['user_id'],
				'game_id' => $user_game['game_id']
			]);
			
			if ($user_strategy) {
				$this->blockchain->app->run_query("UPDATE user_games SET strategy_id=:strategy_id WHERE user_game_id=:user_game_id;", [
					'strategy_id' => $user_strategy['strategy_id'],
					'user_game_id' => $user_game['user_game_id']
				]);
			}
			else {
				$this->blockchain->app->run_query("DELETE FROM user_games WHERE user_game_id=:user_game_id;", ['user_game_id'=>$user_game['user_game_id']]);
				die("No strategy!");
			}
		}
		return $user_strategy;
	}
	
	public function load_current_events() {
		$this->current_events = [];
		$mining_block_id = $this->blockchain->last_block_id()+1;
		
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block<=:ref_block_id AND event_final_block>=:ref_block_id ORDER BY event_id ASC;", [
			'game_id' => $this->db_game['game_id'],
			'ref_block_id' => $mining_block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($this->current_events, new Event($this, $db_event, false));
		}
	}
	
	public function current_events($mining_block_id, $filter_arr) {
		$events = [];
		$events_params = [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $mining_block_id
		];
		$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name, ten.forex_pair_shows_nonstandard FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id LEFT JOIN entities ten ON ev.track_entity_id=ten.entity_id WHERE ev.game_id=:game_id";
		if (!empty($filter_arr['date'])) {
			$events_q .= " AND DATE(ev.event_final_time)=:filter_date";
			$events_params['filter_date'] = $filter_arr['date'];
		}
		if (!empty($filter_arr['term'])) {
			$term = strtolower($this->blockchain->app->make_alphanumeric(strip_tags($filter_arr['term'])));
			$events_q .= " AND ev.searchtext LIKE '%".$term."%'";
		}
		if (!empty($filter_arr['require_option_block_rule'])) {
			$events_q .= " AND ev.option_block_rule IS NOT NULL";
		}
		$events_q .= " AND (ev.event_starting_time < ".AppSettings::sqlNow()." OR (ev.event_starting_time IS NULL AND ev.event_starting_block<=:block_id))";
		$events_q .= " AND (ev.event_final_time > ".AppSettings::sqlNow()." OR (ev.event_final_time IS NULL AND ev.event_final_block>=:block_id))";
		$order_by_q = " ORDER BY ev.event_final_time ASC, ev.event_index ASC";
		if (!empty($filter_arr['order_by'])) {
			if ($filter_arr['order_by'] == "volume") {
				$order_by_q = " ORDER BY ev.event_final_time ASC, (ev.destroy_score+ev.sum_unconfirmed_destroy_score";
				if ($this->db_game['exponential_inflation_rate'] > 0) $order_by_q .= "+((ev.sum_score+ev.sum_unconfirmed_score)*".$this->blockchain->app->coins_per_vote($this->db_game).")";
				$order_by_q .= ") DESC, ev.event_index ASC";
			}
		}
		$events_q .= $order_by_q;
		$db_events = $this->blockchain->app->run_query($events_q, $events_params);

		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		
		return $events;
	}

	public function events_by_block($block_id, $filter_arr) {
		$events = [];
		$events_params = [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		];
		$events_q = "SELECT *, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id";
		if (!empty($filter_arr['date'])) {
			$events_q .= " AND DATE(ev.event_final_time)=:filter_date";
			$events_params['filter_date'] = $filter_arr['date'];
		}
		if (!empty($filter_arr['term'])) {
			$term = strtolower($this->blockchain->app->make_alphanumeric(strip_tags($filter_arr['term'])));
			$events_q .= " AND ev.searchtext LIKE '%".$term."%'";
		}
		if (!empty($filter_arr['require_option_block_rule'])) {
			$events_q .= " AND ev.option_block_rule IS NOT NULL";
		}
		$events_q .= " AND ev.event_starting_block<=:block_id AND ev.event_final_block>=:block_id";
		$order_by_q = " ORDER BY ev.event_final_time ASC, ev.event_index ASC";
		if (!empty($filter_arr['order_by'])) {
			if ($filter_arr['order_by'] == "volume") {
				$order_by_q = " ORDER BY ev.event_final_time ASC, (ev.destroy_score+ev.sum_unconfirmed_destroy_score";
				if ($this->db_game['exponential_inflation_rate'] > 0) $order_by_q .= "+((ev.sum_score+ev.sum_unconfirmed_score)*".$this->blockchain->app->coins_per_vote($this->db_game).")";
				$order_by_q .= ") DESC, ev.event_index ASC";
			}
		}
		$events_q .= $order_by_q;
		$db_events = $this->blockchain->app->run_query($events_q, $events_params);

		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		
		return $events;
	}
	
	public function events_by_outcome_block($block_id) {
		$events = [];
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_determined_to_block=:block_id ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		return $events;
	}
	
	public function events_by_payout_block($block_id) {
		$events = [];
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_payout_block=:block_id ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		return $events;
	}

	public function events_pending_payout_in_block($block_id) {
		$events = [];
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_final_block<:block_id AND event_payout_block>:block_id ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id,
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		return $events;
	}
	
	public function events_by_starting_block($block_id) {
		$events = [];
		
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block=:block_id ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		
		return $events;
	}
	
	public function events_by_final_block($block_id) {
		$events = [];
		
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_final_block=:block_id AND event_final_block != event_payout_block ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		return $events;
	}
	
	
	public function events_being_determined_in_block($block_id) {
		$events = [];
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_determined_from_block <= :block_id AND event_determined_to_block >= :block_id ORDER BY event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		return $events;
	}
	
	public function event_ids() {
		$this->load_current_events();
		
		$event_ids = "";
		for ($i=0; $i<count($this->current_events); $i++) {
			$event_ids .= $this->current_events[$i]->db_event['event_id'].",";
		}
		$event_ids = substr($event_ids, 0, -1);
		
		return $event_ids;
	}
	
	public function new_event_js($game_index, &$user, &$filter_arr, &$event_ids, $include_content=false, $view_event_from_page=null) {
		$last_block_id = $this->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		$current_round = $this->block_to_round($mining_block_id);
		$html = "";
		$js = "";
		
		$user_id = false;
		$account = null;
		if ($user) {
			$user_game = $this->blockchain->app->fetch_user_game($user->db_user['user_id'], $this->game_id);
			if ($user_game) $account = $this->blockchain->app->fetch_account_by_id($user_game['account_id']);
		}
		
		if (!$include_content) {
			$js .= "for (var i=0; i<games[".$game_index."].events.length; i++) {\n";
			$js .= "\tgames[".$game_index."].events[i].deleted = true;\n";
			$js .= "\t$('#game".$game_index."_event'+i).remove();\n";
			$js .= "}\n";
			$js .= "games[".$game_index."].events.length = 0;\n";
			$js .= "games[".$game_index."].events = [];\n";
		}
		
		$these_events = $this->current_events($mining_block_id, $filter_arr);
		$event_ids = "";
		
		for ($i=0; $i<count($these_events); $i++) {
			$event = $these_events[$i];
			$event_ids .= $event->db_event['event_id'].",";
			
			$js .= '
			games['.$game_index.'].events['.$i.'] = new GameEvent(games['.$game_index.'], '.$i.', '.$event->db_event['event_id'].', '.$event->db_event['event_index'].', '.$event->db_event['num_options'].', "'.$event->db_event['vote_effectiveness_function'].'", "'.$event->db_event['effectiveness_param1'].'", "'.$event->db_event['option_block_rule'].'", '.$this->blockchain->app->quote_escape($event->db_event['event_name']).', '.$event->db_event['event_starting_block'].', '.$event->db_event['event_final_block'].', '.$event->db_event['payout_rate'].', \''.$event->db_event['payout_rule'].'\', \''.$event->db_event['track_min_price'].'\', \''.$event->db_event['track_max_price'].'\', \''.$event->db_event['track_name_short'].'\', '.json_encode($event->db_event['forex_pair_shows_nonstandard']).');'."\n";
			
			$options_by_event = $this->blockchain->app->fetch_options_by_event($event->db_event['event_id'], true);
			
			$j=0;
			while ($option = $options_by_event->fetch()) {
				$has_votingaddr = "true";
				$js .= "games[".$game_index."].events[".$i."].options.push(new EventOption(games[".$game_index."].events[".$i."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", ".$this->blockchain->app->quote_escape($option['name']).", 0, ".$has_votingaddr.", ".$this->blockchain->app->quote_escape($option['image_url'])."));\n";
				$j++;
			}
			$html .= "<div id='game".$game_index."_event".$i."' class='game_event_inner'><div id='game".$game_index."_event".$i."_display' class='game_event_display'>";
			
			$html .= $these_events[$i]->event_html($user, false, true, $game_index, $i, $account, $view_event_from_page);
			
			$html .= "</div><div id='game".$game_index."_event".$i."_my_current_votes'>";
			if ($user) $html .= $these_events[$i]->my_votes_table($current_round, $user_game);
			$html .= '</div></div>';
		}
		if ($event_ids != "") $event_ids = substr($event_ids, 0, -1);
		
		if (!$include_content) {
			$js .= 'document.getElementById("game'.$game_index.'_events").innerHTML = '.json_encode($html).';';
		}
		
		return [$js, $html];
	}
	
	public function block_id_to_round_index($block_id) {
		return (($block_id-1)%$this->db_game['round_length'])+1;
	}
	
	public function mature_io_ids_csv($user_game) {
		$ids_csv = "";
		$last_block_id = $this->blockchain->last_block_id();
		
		$mature_ios = $this->blockchain->app->spendable_ios_in_account($user_game['account_id'], $this->db_game['game_id'], false, false);
		
		foreach ($mature_ios as $io) {
			$ids_csv .= $io['io_id'].",";
		}
		if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, -1);
		return $ids_csv;
	}
	
	public function select_input_buttons($user_game) {
		$mature_ios = $this->blockchain->app->spendable_ios_in_account($user_game['account_id'], $this->db_game['game_id'], false, false);
		
		$js = "thisPageManager.chain_ios.length = 0;\n";
		$html = "<p>";
		if (count($mature_ios) == 0) {
			$html .= "You need ".$this->db_game['coin_name_plural']." to bet. To deposit ".$this->db_game['coin_name_plural'].", visit <a href=\"/accounts/?account_id=".$user_game['account_id']."\">your accounts page</a> to see a list of your addresses.";
			if ($this->db_game['buyin_policy'] != "none") {
				$html .= '<br/><button class="btn btn-sm btn-success" style="margin-top: 8px;" onclick="thisPageManager.manage_buyin(\'initiate\');"><i class="fas fa-arrow-down"></i> &nbsp; Get '.$this->db_game['coin_name_plural'].'</button>';
			}
		}
		$html .= "</p>\n";
		$input_buttons_html = "";
		
		$io_i = 0;
		
		foreach ($mature_ios as $io) {
			$gios_by_io = $this->fetch_game_ios_by_io($io['io_id'])->fetchAll();
			
			$js .= "thisPageManager.chain_ios[".$io_i."] = new ChainIO(".$io_i.", ".$io['io_id'].", ".$io['amount'].", '".$io['create_block_id']."');\n";
			
			foreach ($gios_by_io as $gio) {
				$js .= "thisPageManager.chain_ios[".$io_i."].game_ios.push(new GameIO(".$gio['game_io_id'].", ".$gio['colored_amount'].", '".$io['create_block_id']."'));\n";
			}
			
			$input_buttons_html .= '<div id="select_utxo_'.$io['io_id'].'" class="btn btn-primary btn-sm select_utxo';
			if ($this->db_game['logo_image_id'] > 0) $input_buttons_html .= ' select_utxo_image';
			$input_buttons_html .= '" onclick="thisPageManager.add_utxo_to_vote('.$io_i.');">';
			$input_buttons_html .= '</div>'."\n";
			
			$js .= "\n";
			$io_i++;
		}
		$js .= "thisPageManager.refresh_mature_io_btns();\n";
		
		$html .= '<div id="select_input_buttons_msg"></div>'."\n";
		$html .= $input_buttons_html;
		$html .= '<script type="text/javascript">'.$js."</script>\n";
		
		return $html;
	}
	
	public function load_all_event_points_js($game_index, $user_strategy, $from_round_id, $to_round_id) {
		$js = "";
		$from_block_id = ($from_round_id-1)*$this->db_game['round_length']+1;
		$to_block_id = ($to_round_id-1)*$this->db_game['round_length']+1;
		$i=0;
		
		$relevant_events = $this->blockchain->app->run_query("SELECT ev.*, en.forex_pair_shows_nonstandard FROM events ev LEFT JOIN entities en ON ev.track_entity_id=en.entity_id WHERE ev.game_id=:game_id AND ev.event_starting_block >= :from_block_id AND ev.event_starting_block <= :to_block_id ORDER BY ev.event_id ASC;", [
			'game_id' => $this->db_game['game_id'],
			'from_block_id' => $from_block_id,
			'to_block_id' => $to_block_id
		]);
		
		while ($db_event = $relevant_events->fetch()) {
			$js .= "if (typeof games[".$game_index."].all_events[".$db_event['event_index']."] == 'undefined') {";
			$js .= "games[".$game_index."].all_events[".$db_event['event_index']."] = new GameEvent(games[".$game_index."], ".$i.", ".$db_event['event_id'].", ".$db_event['num_options'].', "'.$db_event['vote_effectiveness_function'].'", "'.$db_event['effectiveness_param1'].'", "'.$db_event['option_block_rule'].'", '.$this->blockchain->app->quote_escape($db_event['event_name']).', '.$db_event['event_starting_block'].', '.$db_event['event_final_block'].', '.$db_event['payout_rate'].', \''.$db_event['payout_rule'].'\', \''.$db_event['track_min_price'].'\', \''.$db_event['track_max_price'].'\', \''.$db_event['track_name_short'].'\', '.json_encode($db_event['forex_pair_shows_nonstandard']).');';
			$js .= "}\n";
			
			$options_by_event = $this->blockchain->app->fetch_options_by_event($db_event['event_id']);
			$j=0;
			
			while ($option = $options_by_event->fetch()) {
				$sra = $this->blockchain->app->run_query("SELECT * FROM strategy_round_allocations WHERE strategy_id=:strategy_id AND option_id=:option_id;", [
					'strategy_id' => $user_strategy['strategy_id'],
					'option_id' => $option['option_id']
				])->fetch();
				
				if ($sra) $points = $sra['points'];
				else $points = 0;
				
				$has_votingaddr = "false";
				
				$js .= "if (typeof games[".$game_index."].all_events[".$db_event['event_index']."].options[".$j."] == 'undefined') {";
				$js .= "games[".$game_index."].all_events[".$db_event['event_index']."].options[".$j."] = new EventOption(games[".$game_index."].all_events[".$db_event['event_index']."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", ".$this->blockchain->app->quote_escape($option['name']).", 0, ".$has_votingaddr.");\n";
				$js .= "games[".$game_index."].all_events_db_id_to_index[".$db_event['event_id']."] = ".$db_event['event_index'].";\n";
				$js .= "}\n";
				
				$js .= "games[".$game_index."].all_events[".$db_event['event_index']."].options[".$j."].points = ".$points.";\n";
				$j++;
			}
			$i++;
		}
		return $js;
	}
	
	public function logo_image_url() {
		if ($this->db_game['logo_image_id'] > 0) {
			$db_image = $this->blockchain->app->fetch_image_by_id($this->db_game['logo_image_id']);
			return $this->blockchain->app->image_url($db_image);
		}
		else return "";
	}
	
	public function vote_effectiveness_function() {
		return $this->db_game['default_vote_effectiveness_function'];
	}
	
	public function effectiveness_param1() {
		return $this->db_game['default_effectiveness_param1'];
	}
	
	public function latest_event() {
		return $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id ORDER BY event_index DESC LIMIT 1;", [
			'game_id' => $this->db_game['game_id']
		])->fetch();
	}
	
	public function ensure_events_until_block($block_id, $print_debug) {
		if ((string)$this->db_game['events_until_block'] === "" || $block_id > $this->db_game['events_until_block']) {
			$this->blockchain->app->dbh->beginTransaction();
			
			$ensure_from_block = (string)$this->db_game['events_until_block'] === "" ? $this->db_game['game_starting_block'] : $this->db_game['events_until_block']+1;
			
			$options_begin_at_index = AppSettings::getParam('options_begin_at_index');
			
			$round_id = $this->block_to_round($block_id);
			$game_starting_round = $this->block_to_round($this->db_game['game_starting_block']);
			$add_count = 0;
			$from_event_index = false;
			
			$prev_event = $this->latest_event();
			
			if ($prev_event) {
				$prev_option = $this->blockchain->app->run_query("SELECT * FROM options WHERE event_id=:event_id ORDER BY event_option_index DESC LIMIT 1;", [
					'event_id' => $prev_event['event_id']
				])->fetch();
				$option_offset = $prev_option['option_index']+1-$options_begin_at_index;
				$from_event_index = $prev_event['event_index']+1;
			}
			else {
				$from_event_index = 0;
				$option_offset = 0;
			}
			
			if (!empty($this->db_game['module']) && !$this->get_definitive_peer() && !empty($this->module)) {
				$event_verbatim_vars = $this->blockchain->app->event_verbatim_vars();
				
				$gdes_to_add = $this->module->events_starting_between_blocks($this, $ensure_from_block, $block_id);
				
				if ($print_debug) $this->blockchain->app->print_debug("Resetting ".count($gdes_to_add)." game defined events by module for blocks ".$ensure_from_block.":".$block_id);
				
				$sports_entity_type = $this->blockchain->app->check_set_entity_type("sports");
				$leagues_entity_type = $this->blockchain->app->check_set_entity_type("leagues");
				$general_entity_type = $this->blockchain->app->check_set_entity_type("general entity");
				
				if (count($gdes_to_add) > 0) {
					$init_event_index = $gdes_to_add[0]['event_index'];
					$final_event_index = $gdes_to_add[count($gdes_to_add)-1]['event_index'];
					
					$this->blockchain->app->run_query("DELETE FROM game_defined_options WHERE game_id=:game_id AND event_index>=:init_event_index AND event_index<=:final_event_index;", [
						'game_id' => $this->db_game['game_id'],
						'init_event_index' => $init_event_index,
						'final_event_index' => $final_event_index
					]);
					
					$this->blockchain->app->run_query("DELETE FROM game_defined_events WHERE game_id=:game_id AND event_index>=:init_event_index AND event_index<=:final_event_index;", [
						'game_id' => $this->db_game['game_id'],
						'init_event_index' => $init_event_index,
						'final_event_index' => $final_event_index
					]);
					
					$i = 0;
					for ($event_index=$init_event_index; $event_index<$init_event_index+count($gdes_to_add); $event_index++) {
						$this->blockchain->app->check_set_gde($this, $gdes_to_add[$i], $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id'], $general_entity_type['entity_type_id']);
						$i++;
					}
				}
			}
			
			$last_used_starting_block = false;
			
			if (in_array($this->db_game['event_rule'], ["", "game_definition"])) {
				$optional_event_fields = ['sport_entity_id','league_entity_id','next_event_index','outcome_index','event_starting_time','event_final_time','event_payout_time','track_max_price','track_min_price','track_payout_price','track_name_short','track_entity_id','option_block_rule','external_identifier'];
				
				$to_event_index = $this->blockchain->app->run_query("SELECT MAX(event_index) FROM game_defined_events WHERE game_id=:game_id AND event_starting_block <= :ref_block;", [
					'game_id' => $this->db_game['game_id'],
					'ref_block' => $block_id
				])->fetch()['MAX(event_index)'];
				
				$searchtext_leagues = $this->blockchain->app->run_query("SELECT le.entity_id, le.entity_name FROM game_defined_events gde JOIN entities le ON gde.league_entity_id=le.entity_id WHERE gde.game_id=:game_id AND gde.event_index >= :from_event_index AND gde.event_index <= :to_event_index ORDER BY gde.event_index ASC;", [
					'game_id' => $this->db_game['game_id'],
					'from_event_index' => $from_event_index,
					'to_event_index' => $to_event_index
				])->fetchAll();
				$searchtext_leagues_by_id = (array) AppSettings::arrayToMapOnKey($searchtext_leagues, "entity_id");
				
				$change_gdes = $this->blockchain->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_index >= :from_event_index AND event_index <= :to_event_index ORDER BY event_index ASC;", [
					'game_id' => $this->db_game['game_id'],
					'from_event_index' => $from_event_index,
					'to_event_index' => $to_event_index
				])->fetchAll();
				
				if ($print_debug) $this->blockchain->app->print_debug("Ensuring ".count($change_gdes)." events from game definition (".$from_event_index.":".$to_event_index.")");
				
				foreach ($change_gdes as $game_defined_event) {
					$event_start_time = microtime(true);
					
					$db_event = $this->fetch_event_by_index($game_defined_event['event_index']);
					
					if ($db_event) {
						$option_offset += $db_event['num_options'];
					}
					else {
						$gdo_r = $this->blockchain->app->fetch_game_defined_options($this->db_game['game_id'], $game_defined_event['event_index'], false, false);
						$game_defined_options = $gdo_r->fetchAll();
						$num_options = count($game_defined_options);
						
						$event_searchtext = $game_defined_event['event_name'];
						foreach ($game_defined_options as $game_defined_option) {
							$event_searchtext .= $game_defined_option['name'];
						}
						if (!empty($game_defined_event['league_entity_id'])) $event_searchtext .= $searchtext_leagues_by_id[$game_defined_event['league_entity_id']]->entity_name;
						$event_searchtext = strtolower($this->blockchain->app->make_alphanumeric($event_searchtext, ""));
						
						$event_determined_to_block = $game_defined_event['event_determined_to_block'] ? $game_defined_event['event_determined_to_block'] : null;
						
						$new_event_params = [
							'game_id' => $this->db_game['game_id'],
							'event_index' => $game_defined_event['event_index'],
							'season_index' => $game_defined_event['season_index'],
							'event_starting_block' => $game_defined_event['event_starting_block'],
							'event_final_block' => $game_defined_event['event_final_block'],
							'event_determined_from_block' => $game_defined_event['event_determined_from_block'],
							'event_determined_to_block' => $event_determined_to_block,
							'event_payout_block' => $game_defined_event['event_payout_block'],
							'payout_rule' => $game_defined_event['payout_rule'],
							'payout_rate' => $game_defined_event['payout_rate'],
							'event_name' => $game_defined_event['event_name'],
							'option_name' => $game_defined_event['option_name'],
							'option_name_plural' => $game_defined_event['option_name_plural'],
							'num_options' => $num_options,
							'option_max_width' => $this->db_game['default_option_max_width'],
							'searchtext' => $event_searchtext
						];
						
						foreach ($optional_event_fields as $optional_event_field) {
							if ((string)$game_defined_event[$optional_event_field] != "") {
								$new_event_params[$optional_event_field] = $game_defined_event[$optional_event_field];
							}
						}
						$this->blockchain->app->run_insert_query("events", $new_event_params);
						$event_id = $this->blockchain->app->last_insert_id();
						
						$option_i = 0;
						foreach ($game_defined_options as $game_defined_option) {
							$option_index = $option_i + $option_offset;
							if ($this->db_game['max_simultaneous_options']) $option_index = $option_index%$this->db_game['max_simultaneous_options'];
							$option_index += $options_begin_at_index;
							
							$vote_identifier = $this->blockchain->app->option_index_to_vote_identifier($option_index);
							$new_option_params = [
								'event_id' => $event_id,
								'name' => $game_defined_option['name'],
								'vote_identifier' => $vote_identifier,
								'option_index' => $option_index,
								'event_option_index' => $option_i,
								'entity_id' => empty($game_defined_option['entity_id']) ? null : $game_defined_option['entity_id'],
								'target_probability' => empty($game_defined_option['target_probability']) ? null : $game_defined_option['target_probability']
							];
							$new_option_params['image_id'] = null;
							if (!empty($game_defined_option['entity_id'])) {
								$entity = $this->blockchain->app->fetch_entity_by_id($game_defined_option['entity_id']);
								if (!empty($entity['default_image_id'])) {
									$new_option_params['image_id'] = $entity['default_image_id'];
								}
							}
							
							$this->blockchain->app->run_insert_query("options", $new_option_params);
							$option_i++;
						}
						
						$option_offset += $num_options;
						$add_count++;
					}
				}
			}
			
			if ($print_debug) $this->blockchain->app->print_debug("Added ".$add_count." events");
			
			$this->set_events_until_block($block_id);
			
			if ($ensure_from_block == $this->db_game['game_starting_block']) $this->set_target_scores_at_block($this->db_game['game_starting_block']);
			
			$this->blockchain->app->dbh->commit();
		}
	}
	
	public function set_events_until_block($block_id) {
		$this->blockchain->app->run_query("UPDATE games SET events_until_block=:block_id WHERE game_id=:game_id;", [
			'block_id' => $block_id,
			'game_id' => $this->db_game['game_id']
		]);
		$this->db_game['events_until_block'] = $block_id;
	}
	
	public function event_next_prev_links($event) {
		$html = "";
		if ($event->db_event['event_index'] > 0) $html .= "<a href=\"/explorer/games/".$this->db_game['url_identifier']."/events/".($event->db_event['event_index']-1)."\" style=\"margin-right: 30px;\">&larr; Previous Event</a>";
		$html .= "<a href=\"/explorer/games/".$this->db_game['url_identifier']."/events/".($event->db_event['event_index']+1)."\">Next Event &rarr;</a>";
		return $html;
	}
	
	public function set_loaded_until_block($block_id) {
		if ($block_id === null) {
			$last_block_loaded = $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_id=:game_id AND locally_saved=1 ORDER BY block_id DESC LIMIT 1;", [
				'game_id' => $this->db_game['game_id']
			])->fetch();
			
			if ($last_block_loaded) $block_id = $last_block_loaded['block_id'];
			else $block_id = $this->db_game['game_starting_block']-1;
		}
		
		$this->blockchain->app->run_query("UPDATE games SET loaded_until_block=:loaded_until_block WHERE game_id=:game_id;", [
			'loaded_until_block' => $block_id,
			'game_id' => $this->db_game['game_id']
		]);
		
		$this->db_game['loaded_until_block'] = $block_id;
	}
	
	public function set_option_images_from_definitive_peer() {
		$error_message = "";
		
		$definitive_peer = $this->get_definitive_peer();
		
		if ($definitive_peer) {
			$imageless_options = $this->blockchain->app->run_query("SELECT en.*, op.event_option_index, ev.event_index, op.name FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE ev.game_id=:game_id AND op.image_id IS NULL GROUP BY en.entity_id;", [
				'game_id' => $this->db_game['game_id']
			]);
			
			while ($imageless_option = $imageless_options->fetch()) {
				if (empty($imageless_option['default_image_id'])) {
					$api_url = $definitive_peer['base_url']."/api/".$this->db_game['url_identifier']."/events/".$imageless_option['event_index']."/options/".$imageless_option['event_option_index'];
					$api_response = json_decode($this->blockchain->app->safe_fetch_url($api_url));
					
					if ($api_response->status_code == 1 && !empty($api_response->option->image_url)) {
						$db_image = $this->blockchain->app->set_entity_image_from_url($api_response->option->image_url, $imageless_option['entity_id'], $error_message);
					}
					else $error_message .= "Failed to set image for ".$imageless_option['name'].": ".$api_url."\n";
				}
				else $error_message .= $imageless_option['name']." already has an image.\n";
			}
		}
		else $error_message .= "This game does not have a definitive peer.\n";
		
		return $error_message;
	}
	
	public function schedule_game_reset($from_block, $from_index=null, $migration_id=null, $from_reset_time=null) {
		$extra_info = $this->fetch_extra_info();
		
		unset($extra_info['reset_from_block']);
		unset($extra_info['reset_from_event_index']);
		unset($extra_info['from_reset_time']);
		$extra_info['pending_reset'] = 1;
		if ($from_reset_time) $extra_info['from_reset_time'] = $from_reset_time;
		
		if ($from_block !== null) {
			$reset_from_event_index = $this->blockchain->app->min_excluding_false([$this->reset_block_to_event_index($from_block), $from_index]);
			
			if ($reset_from_event_index !== false) {
				$extra_info['reset_from_event_index'] = $reset_from_event_index;
				
				$adjusted_from_block = $this->reset_event_index_to_block($reset_from_event_index);
				if ((string)$adjusted_from_block != "" && $adjusted_from_block < $from_block) $from_block = $adjusted_from_block;
			}
			
			$extra_info['reset_from_block'] = $from_block;
		}
		$this->set_extra_info($extra_info);
		
		if ($migration_id) {
			$this->blockchain->app->run_query("UPDATE game_definition_migrations SET extra_info=:extra_info WHERE migration_id=:migration_id;", [
				'extra_info' => json_encode(['reset_from_block' => $from_block, 'reset_from_event_index' => $reset_from_event_index], JSON_PRETTY_PRINT),
				'migration_id' => $migration_id
			]);
		}
	}
	
	public function sync_with_definitive_peer($print_debug) {
		$error_message = "";
		$definitive_peer = $this->get_definitive_peer();

		if ($print_debug) $this->blockchain->app->print_debug("Syncing with definitive peer..");

		if ($definitive_peer) {
			$send_hash = $this->db_game['cached_definition_hash'];

			if (empty($send_hash)) {
				GameDefinition::set_cached_definition_hashes($this);
				$send_hash = $this->db_game['cached_definition_hash'];
			}

			$api_url = $definitive_peer['base_url']."/api/".$this->db_game['url_identifier']."/definition/?definition_hash=".$send_hash;

			if ($print_debug) $this->blockchain->app->print_debug($api_url);
			
			$ref_time = microtime(true);
			$api_response_raw = $this->blockchain->app->safe_fetch_url($api_url);

			if ($api_response_raw) {
				$fetch_time = round(microtime(true)-$ref_time, 6);
				$ref_time = microtime(true);

				$api_response = json_decode($api_response_raw);

				if ($api_response) {
					$decode_time = round(microtime(true)-$ref_time, 6);
					$ref_time = microtime(true);
					
					if (!empty($api_response->url_identifier)) {
						if ($api_response->url_identifier == $this->db_game['url_identifier']) {
							$returned_def_hash = GameDefinition::game_def_to_hash($api_response_raw);
							$compute_hash_time = round(microtime(true)-$ref_time, 6);
							$ref_time = microtime(true);
							
							if ($returned_def_hash == $send_hash) $error_message .= $this->blockchain->app->log_message($this->db_game['name'].": fetched in ".$fetch_time.", decoded in ".$decode_time.", got hash in ".$compute_hash_time." but not applying game def from peer; Already in sync.");
							else {
								GameDefinition::check_set_game_definition($this->blockchain->app, $returned_def_hash, $api_response_raw, $this);
								$ensure_def_time = round(microtime(true)-$ref_time, 6);
								$ref_time = microtime(true);

								$this->blockchain->app->log_message($this->db_game['name'].": fetched in ".$fetch_time.", decoded in ".$decode_time.", got hash in ".$compute_hash_time.", ensured def in ".$ensure_def_time.", now syncing to ".$returned_def_hash." from ".$api_url);
								$ref_user = false;
								$db_new_game = false;
								list($mod_game, $mod_game_is_new, $set_game_error) = GameDefinition::set_game_from_definition($this->blockchain->app, $api_response, $ref_user, $error_message, $db_new_game, true);
								
								$this->blockchain->app->log_message($this->db_game['name'].": set game from definition in ".round(microtime(true)-$ref_time, 6).(!empty($set_game_error) ? ": ".$set_game_error : ""));
							}
						}
						else $error_message .= $this->blockchain->app->log_message($this->db_game['name'].": fetched in ".$fetch_time.", decoded in ".$decode_time." but sync canceled because definitive peer tried to change the game identifier.");
					}
					else {
						$already_in_sync = isset($api_response->status_code) && $api_response->status_code == 3;
						$message = $this->db_game['name'].": fetched in ".$fetch_time.", decoded in ".$decode_time." and response is not a game definition: ".(isset($api_response->message) ? $api_response->message : "");
						if (!$already_in_sync) $error_message .= $this->blockchain->app->log_message($message);
						else if ($print_debug) $error_message .= $message;
					}
				}
				else {
					$message = $this->db_game['name'].": fetched in ".$fetch_time." but failed to decode response from definitive peer: ".$this->blockchain->app->json_decode_error_code_to_string(json_last_error());
					$message .= ": ".substr($api_response_raw, 0, min(253-strlen($message), strlen($api_response_raw)));
					$error_message .= $this->blockchain->app->log_message($message);
				}
			}
			else $error_message .= $this->blockchain->app->log_message($this->db_game['name'].": failed to fetch game definition from definitive peer.");

			$error_message .= $this->set_option_images_from_definitive_peer();
		}
		else $error_message .= $this->blockchain->app->log_message($this->db_game['name']." does not have a definitive peer.");

		if ($print_debug) $this->blockchain->app->print_debug($error_message);

		return $error_message;
	}
	
	public function sync($print_debug, $max_load_seconds) {
		$sync_start_time = microtime(true);
		$last_set_loaded_time = microtime(true);
		
		// Set loaded until block if needed
		if ((string) $this->db_game['loaded_until_block'] == "") $this->set_loaded_until_block(null);
		
		// Reset game if there's a reset scheduled
		$extra_info = $this->fetch_extra_info();
		if (!empty($extra_info['pending_reset'])) {
			if (empty($extra_info['from_reset_time'])) $reset_now = true;
			else {
				if (time() >= $extra_info['from_reset_time']) $reset_now = true;
				else {
					$reset_now = false;
					if ($print_debug) $this->blockchain->app->print_debug("Game will reset ".(isset($extra_info['reset_from_block']) ? "to block #".$extra_info['reset_from_block'] : "")." in ".$this->blockchain->app->format_seconds($extra_info['from_reset_time'] - time()));
				}
			}

			if ($reset_now) {
				if (array_key_exists("reset_from_block", $extra_info) && $extra_info['reset_from_block'] > $this->db_game['game_starting_block']) {
					if ($print_debug) $this->blockchain->app->print_debug("Resetting the game from block #".$extra_info['reset_from_block']);

					if ($extra_info['reset_from_block']-1 <= $this->blockchain->last_block_id()) {
						$reset_from_block = $extra_info['reset_from_block'];
						$this->reset_blocks_from_block($reset_from_block);
						$this->set_loaded_until_block($reset_from_block-1);
						$this->set_events_until_block($reset_from_block-1);
					}
					else $this->blockchain->app->log_message("Game #".$this->db_game['game_id']." tried to reset to future block ".$extra_info['reset_from_block']." but last block was ".$this->blockchain->last_block_id().", skipping block reloading.");
					
					unset($extra_info['reset_from_block']);

					if (array_key_exists("reset_from_event_index", $extra_info)) {
						$this->reset_events_from_index($extra_info['reset_from_event_index']);
						unset($extra_info['reset_from_event_index']);
					}

					$this->ensure_events_until_block($this->blockchain->last_complete_block_id()+1, $print_debug);
					$this->set_target_scores_at_block($reset_from_block);
				}
				else {
					if ($print_debug) $this->blockchain->app->print_debug("Fully resetting the game...");

					$this->delete_reset_game('reset');
					$this->set_loaded_until_block($this->db_game['game_starting_block']-1);
					$this->set_events_until_block($this->db_game['game_starting_block']-1);
				}

				unset($extra_info['pending_reset']);

				$this->set_extra_info($extra_info);
				$this->update_db_game();
			}
		}

		$load_block_height = $this->db_game['loaded_until_block']+1;
		$to_block_height = $this->blockchain->last_complete_block_id();
		$ensure_block_id = $this->blockchain->last_block_id()+1;
		
		// Load events
		$this->ensure_events_until_block($ensure_block_id, $print_debug);
		
		// Sync with peer
		if (!empty($this->db_game['definitive_game_peer_id']) && $this->db_game['loaded_until_block'] == $this->blockchain->last_block_id()) {
			$sync_definitive_message = $this->sync_with_definitive_peer($print_debug);
			if ($this->db_game['finite_events'] == 1) $ensure_block_id = max($ensure_block_id, $this->max_gde_starting_block());
			$this->ensure_events_until_block($ensure_block_id, $print_debug);
		}
		else if ($this->db_game['finite_events'] == 1) $ensure_block_id = max($ensure_block_id, $this->max_gde_starting_block());
		
		// Load events
		$this->ensure_events_until_block($ensure_block_id, $print_debug);
		
		// Load blocks
		if ($to_block_height >= $load_block_height) {
			if ($print_debug) $this->blockchain->app->print_debug($this->db_game['name'].".. loading blocks ".$load_block_height." to ".$to_block_height);
			
			if ($load_block_height == $this->db_game['game_starting_block']) $game_io_index = $this->max_game_io_index();
			else {
				$prev_block = $this->fetch_game_block_by_height($load_block_height-1);
				$game_io_index = $prev_block['max_game_io_index'];
			}
			
			for ($block_height=$load_block_height; $block_height<=$to_block_height; $block_height++) {
				list($successful, $bulk_to_block) = $this->add_block($block_height, $game_io_index, $print_debug);
				if ($bulk_to_block) $block_height = $bulk_to_block;
				
				if ($successful) $this->set_loaded_until_block($block_height);
				
				if (microtime(true)-$last_set_loaded_time >= 3) {
					if ($max_load_seconds && microtime(true)-$sync_start_time >= $max_load_seconds) {
						$block_height = $to_block_height+1;
					}
					else $last_set_loaded_time = microtime(true);
				}
				if (!$successful) $block_height = $to_block_height+1;
			}
		}
		else if ($print_debug) $this->blockchain->app->print_debug($this->db_game['name']." is already fully loaded.");
		
		$this->update_option_votes();
	}
	
	public function fetch_game_block_by_height($height) {
		return $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_id=:game_id AND block_id=:block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $height
		])->fetch();
	}
	
	public function exponential_pow_reward($adjustment_block, $genesis_tx, $print_debug=false) {
		if ($print_debug) $this->blockchain->app->print_debug("Adjusting exponential pow reward on block #".$adjustment_block);
		
		if ($adjustment_block <= $genesis_tx['block_id']) $new_pow_reward = $this->db_game['initial_pow_reward'];
		else {
			$firstround_pos_rewards = $this->db_game['genesis_amount']*$this->db_game['exponential_inflation_rate'];
			$firstround_pow_rewards = $this->db_game['initial_pow_reward']*$this->db_game['round_length'];
			$pow_inflation_per_pos_inflation = $firstround_pow_rewards/$firstround_pos_rewards;
			$est_amt_burned_as_frac_of_inflation = 0.2;
			$rounds_per_reward_period = (int) ($this->db_game['blocks_per_pow_reward_ajustment']/$this->db_game['round_length']);
			$inflation_per_round = ($this->db_game['exponential_inflation_rate']*(1+$pow_inflation_per_pos_inflation))*(1-$est_amt_burned_as_frac_of_inflation);
			$inflation_per_reward_period = pow(1+$inflation_per_round, $rounds_per_reward_period)-1;
			$this_reward_period = ($adjustment_block-$this->db_game['game_starting_block'])/$this->db_game['blocks_per_pow_reward_ajustment'];
			$inflation_total = pow(1+$inflation_per_reward_period, $this_reward_period)-1;
			$new_pow_reward = round((1+$inflation_total)*$this->db_game['initial_pow_reward'], $this->db_game['decimal_places']);
		}
		
		return $new_pow_reward;
	}
	
	public function pegged_pow_reward($adjustment_block, $genesis_tx, $print_debug=false) {
		$ref_supply_subtract_blocks = 1;
		$ref_supply_block = $adjustment_block-$ref_supply_subtract_blocks;
		
		if ($print_debug) $this->blockchain->app->print_debug("Adjusting pow reward on block #".$adjustment_block.", ref block #".$ref_supply_block);
		
		if ($ref_supply_block <= $genesis_tx['block_id']) $new_pow_reward = $this->db_game['initial_pow_reward'];
		else {
			$this->blockchain->app->dbh->beginTransaction();
			
			$initial_reward_per_supply = $this->db_game['initial_pow_reward']/$this->db_game['genesis_amount'];
			$ref_supply = $this->coins_in_existence($ref_supply_block, false)/pow(10, $this->db_game['decimal_places']);
			
			$unpaid_bets_info = $this->blockchain->app->run_query("SELECT SUM(p.destroy_amount) AS destroy_amount, SUM(p.".$this->db_game['payout_weight']."s_destroyed) as inflation_score FROM events ev JOIN options op ON ev.event_id=op.event_id JOIN transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id WHERE gio.option_id=op.option_id AND ev.game_id=:game_id AND ev.event_starting_block <= :ref_supply_block AND ev.event_payout_block > :ref_supply_block AND gio.create_block_id <= :ref_supply_block;", [
				'game_id' => $this->db_game['game_id'],
				'ref_supply_block' => $ref_supply_block,
			])->fetch();
			if ($unpaid_bets_info) {
				$pending_bets = ((int)$unpaid_bets_info['inflation_score'])*$this->blockchain->app->coins_per_vote($this->db_game)/pow(10, $this->db_game['decimal_places']);
				$pending_bets += ((int)$unpaid_bets_info['destroy_amount'])/pow(10, $this->db_game['decimal_places']);
			}
			else $pending_bets = 0;
			
			$this->blockchain->app->dbh->commit();
			
			if ($print_debug) $this->blockchain->app->print_debug("Coins in existence: ".$ref_supply.", pending bets: ".$pending_bets);
			
			$new_pow_reward = round(($ref_supply+$pending_bets)*$initial_reward_per_supply, $this->db_game['decimal_places']);
			
			$this->blockchain->app->log_message("Determined POW reward on block #".$adjustment_block.": (".$ref_supply."+".$pending_bets.")*".$initial_reward_per_supply."=".$new_pow_reward);
		}
		
		return $new_pow_reward;
	}
	
	public function add_block($block_height, &$game_io_index, $print_debug) {
		$successful = true;
		$start_time = microtime(true);
		$benchmarks_on = $print_debug;
		if ($benchmarks_on) {
			$benchmarks = [];
			$benchmark_sec_precision = 6;
		}
		if ($print_debug) $this->blockchain->app->print_debug("Adding block ".$block_height." to ".$this->db_game['name']);
		$bulk_to_block = false;
		
		$db_block = $this->blockchain->fetch_block_by_id($block_height);
		
		if ($db_block && $db_block['locally_saved'] == 1) {
			$skip = false;
			
			$round_id = $this->block_to_round($block_height);
			
			if ($this->db_game['game_status'] == "published" && $this->db_game['game_starting_block'] == $block_height) $this->start_game();
			
			$check_game_block = $this->fetch_game_block_by_height($block_height);
			
			if ($check_game_block) {
				// The game block already exists. There was an error in a previous load or multiple processes are loading games simultaneously
				if (empty(AppSettings::getParam('fix_game_blocks_disabled')) && $block_height > 1) {
					$first_missing_info = $this->blockchain->app->run_query("SELECT gb.* FROM game_blocks gb WHERE gb.game_id=:game_id AND NOT EXISTS (SELECT 1 FROM game_blocks gbb WHERE gbb.game_id=:game_id AND gbb.block_id=gb.block_id+1) ORDER BY gb.block_id ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
					])->fetch(PDO::FETCH_ASSOC);

					if ($first_missing_info && $first_missing_info['block_id']+1 < $block_height) {
						$message = $this->blockchain->app->log_message("Need to reset ".$this->db_game['name']." due to missing block at height #".($first_missing_info['block_id']+1));
						if ($print_debug) $this->blockchain->app->print_debug($message);

						$this->reset_blocks_from_block($first_missing_info['block_id']+1);
					}
					else {
						$message = $this->blockchain->app->print_debug("Tried to load game block #".$block_height." but it already exists: resetting from ".($block_height-1));
						if ($print_debug) $this->blockchain->app->print_debug($message);

						$this->reset_blocks_from_block($block_height-1);
					}
				}
				else if ($print_debug) $this->blockchain->app->print_debug("Failed: game block already exists.");
				
				$successful = false;
				return array($successful, $bulk_to_block);
			}
			else {
				$this->blockchain->app->run_insert_query("game_blocks", [
					'game_id' => $this->db_game['game_id'],
					'block_id' => $block_height,
					'time_created' => time(),
					'locally_saved' => 0,
					'num_transactions' => 0
				]);
				$game_block_id = $this->blockchain->app->last_insert_id();
				
				$game_block = $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_block_id=:game_block_id;", ['game_block_id'=>$game_block_id])->fetch();
			}

			if ($benchmarks_on) {
				$benchmarks['ensure_block'] = round(microtime(true)-$start_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			if (!in_array($this->db_game['buyin_policy'], ["none","for_sale"])) {
				$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], false, null);
				
				$buyin_transactions = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.create_block_id=:block_height AND io.address_id=:escrow_address_id GROUP BY t.transaction_id;", [
					'block_height' => $block_height,
					'escrow_address_id' => $escrow_address['address_id']
				])->fetchAll();
				
				if ($print_debug) $this->blockchain->app->print_debug("Looping through ".count($buyin_transactions)." buyin transactions");
				
				foreach ($buyin_transactions as $buyin_tx) {
					// Check if buy-in transaction has already been paid
					$existing_buyin_payout = $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.create_transaction_id=:transaction_id;", [
						'game_id' => $this->db_game['game_id'],
						'transaction_id' => $buyin_tx['transaction_id']
					])->fetch();
					
					if (!$existing_buyin_payout) {
						if ($this->db_game['sellout_policy'] == "off") {
							$this->process_buyin_transaction($buyin_tx);
						}
						else {
							// Check if any colored coins are being deposited to the escrow address
							// If so, this is a sell-out rather than buy-in tx, so skip the buy-in
							$deposits_to_escrow = $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.spend_transaction_id='".$buyin_tx['transaction_id']."';")->fetchAll();
							
							if (count($deposits_to_escrow) == 0) {
								$this->process_buyin_transaction($buyin_tx);
							}
						}
					}
				}
			}

			if ($benchmarks_on) {
				$benchmarks['process_buyins'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			if (in_array($this->db_game['pow_reward_type'], ["fixed","exponential","pegged_to_supply"]) && $this->db_game['initial_pow_reward'] > 0) {
				if ($this->db_game['pow_reward_type'] == "fixed") $pow_reward_int = (int)($this->db_game['initial_pow_reward']*pow(10, $this->db_game['decimal_places']));
				else {
					if ((string)$this->db_game['current_pow_reward'] === "") {
						$adjustment_block = (floor(($block_height-$this->db_game['game_starting_block'])/$this->db_game['blocks_per_pow_reward_ajustment'])*$this->db_game['blocks_per_pow_reward_ajustment'])+$this->db_game['game_starting_block'];
					}
					else if (($block_height-$this->db_game['game_starting_block'])%$this->db_game['blocks_per_pow_reward_ajustment'] == 0) {
						$adjustment_block = $block_height;
					}
					else {
						$adjustment_block = null;
						$pow_reward_int = $this->db_game['current_pow_reward']*pow(10, $this->db_game['decimal_places']);
					}
					
					if ($adjustment_block !== null) {
						$genesis_tx = $this->blockchain->fetch_transaction_by_hash($this->db_game['genesis_tx_hash']);
						
						if ($this->db_game['pow_reward_type'] == "exponential") $new_pow_reward = $this->exponential_pow_reward($adjustment_block, $genesis_tx, $print_debug);
						else if ($this->db_game['pow_reward_type'] == "pegged_to_supply") $new_pow_reward = $this->pegged_pow_reward($adjustment_block, $genesis_tx, $print_debug);
						
						if ($print_debug) $this->blockchain->app->print_debug("Changed POW reward to ".$new_pow_reward." based on block #".$adjustment_block);
						
						$this->blockchain->app->run_query("UPDATE games SET current_pow_reward=:new_pow_reward WHERE game_id=:game_id;", [
							'new_pow_reward' => $new_pow_reward,
							'game_id' => $this->db_game['game_id'],
						]);
						$this->db_game['current_pow_reward'] = $new_pow_reward;
						
						$pow_reward_int = $new_pow_reward*pow(10, $this->db_game['decimal_places']);
					}
				}
				
				$coinbase_tx = $this->blockchain->fetch_tx_by_position_in_block($db_block['block_id'], 0);
				$coinbase_io = $this->blockchain->fetch_io_by_position_in_tx($coinbase_tx, 0);
				$game_io_index++;
				$this->blockchain->app->run_insert_query("transaction_game_ios", [
					'io_id' => $coinbase_io['io_id'],
					'address_id' => $coinbase_io['address_id'],
					'game_id' => $this->db_game['game_id'],
					'game_out_index' => 0,
					'game_io_index' => $game_io_index,
					'colored_amount' => $pow_reward_int,
					'is_game_coinbase' => 0,
					'is_resolved' => 1,
					'resolved_before_spent' => 1,
					'votes' => 0,
					'create_block_id' => $db_block['block_id'],
					'create_round_id' => $round_id,
				]);
			}
			
			if ($benchmarks_on) {
				$benchmarks['process_genesis'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$events_by_option_id = [];
			$option_indices_this_block = [];
			
			$transactions = $this->blockchain->app->run_query("SELECT * FROM transactions WHERE blockchain_id=:blockchain_id AND block_id=:block_id ORDER BY position_in_block ASC;", [
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
				'block_id' => $block_height,
			])->fetchAll(PDO::FETCH_ASSOC);
			
			foreach ($transactions as $db_transaction) {
				if ($db_transaction['tx_hash'] == $this->db_game['genesis_tx_hash']) {
					continue;
				}
				
				$input_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id;", [
					'transaction_id' => $db_transaction['transaction_id'],
					'game_id' => $this->db_game['game_id']
				])->fetchAll();
				
				if (count($input_ios) > 0) {
					$tx_game_input_sum = 0;
					$crd_in = 0;
					$cbd_in = 0;
					$game_out_index = 0;
					
					$these_option_ids = [];
					foreach ($input_ios as $input_io) {
						if ($input_io['is_game_coinbase'] == 1 && empty($events_by_option_id[$input_io['option_id']])) {
							array_push($these_option_ids, $input_io['option_id']);
						}
					}
					if (count($these_option_ids) > 0) {
						$these_db_events = $this->blockchain->app->run_query("SELECT op.option_id, ev.* FROM events ev JOIN options op ON ev.event_id=op.event_id WHERE op.option_id IN (".implode(",", $these_option_ids).");")->fetchAll();
						foreach ($these_db_events as $this_db_event) {
							$events_by_option_id[$this_db_event['option_id']] = new Event($this, $this_db_event, false);
						}
					}
					
					$gio_ids_in = [];
					$resolved_before_spent_gio_ids = [];
					$unresolved_before_spent_gio_ids = [];
					
					foreach ($input_ios as $input_io) {
						$tx_game_input_sum += $input_io['colored_amount'];
						
						$gio_in_coin_blocks = $input_io['colored_amount']*($block_height - $input_io['create_block_id']);
						$gio_in_coin_rounds = $input_io['colored_amount']*($round_id - $input_io['create_round_id']);
						$cbd_in += $gio_in_coin_blocks;
						$crd_in += $gio_in_coin_rounds;
						
						array_push($gio_ids_in, $input_io['game_io_id']);
						
						if ($input_io['is_game_coinbase'] == 1) {
							if ($block_height < $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
								if ($input_io['resolved_before_spent'] != 0) {
									array_push($unresolved_before_spent_gio_ids, $input_io['game_io_id']);
								}
							}
							else if ($block_height >= $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
								if ($input_io['resolved_before_spent'] != 1) {
									array_push($resolved_before_spent_gio_ids, $input_io['game_io_id']);
								}
							}
						}
					}
					
					$this->blockchain->app->run_query("UPDATE transaction_game_ios SET spend_round_id=".$round_id.", coin_blocks_created=colored_amount*(".$block_height."-create_block_id), coin_rounds_created=colored_amount*(".$round_id."-create_round_id) WHERE game_io_id IN (".implode(",", $gio_ids_in).");");
					
					if (count($resolved_before_spent_gio_ids) > 0) {
						$this->blockchain->app->run_query("UPDATE transaction_game_ios SET resolved_before_spent=1 WHERE game_io_id IN (".implode(",", $resolved_before_spent_gio_ids).");");
					}
					
					if (count($unresolved_before_spent_gio_ids) > 0) {
						$this->blockchain->app->run_query("UPDATE transaction_game_ios SET resolved_before_spent=0 WHERE game_io_id IN (".implode(",", $unresolved_before_spent_gio_ids).");");
					}
					
					$this->blockchain->app->dbh->beginTransaction();
					$output_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id;", [
						'create_transaction_id' => $db_transaction['transaction_id']
					])->fetchAll();
					
					$tx_chain_output_sum = 0;
					$tx_chain_destroy_sum = 0;
					$tx_chain_separator_sum = 0;
					$tx_chain_passthrough_sum = 0;
					$tx_chain_receiver_sum = 0;
					
					foreach ($output_ios as $output_io) {
						$tx_chain_output_sum += $output_io['amount'];
						if ($output_io['is_destroy_address'] == 1) $tx_chain_destroy_sum += $output_io['amount'];
						if ($output_io['is_separator_address'] == 1) $tx_chain_separator_sum += $output_io['amount'];
						if ($output_io['is_passthrough_address'] == 1) $tx_chain_passthrough_sum += $output_io['amount'];
						if ($output_io['is_receiver'] == 1) $tx_chain_receiver_sum += $output_io['amount'];
					}
					$tx_chain_regular_sum = $tx_chain_output_sum - $tx_chain_destroy_sum - $tx_chain_separator_sum - $tx_chain_passthrough_sum - $tx_chain_receiver_sum;
					
					$tx_game_nondestroy_amount = $tx_chain_output_sum > 0 ? floor($tx_game_input_sum*(($tx_chain_regular_sum+$tx_chain_separator_sum+$tx_chain_passthrough_sum+$tx_chain_receiver_sum)/$tx_chain_output_sum)) : 0;
					$tx_game_destroy_amount = $tx_game_input_sum-$tx_game_nondestroy_amount;
					
					$game_destroy_sum = 0;
					
					$separator_outputs = $this->blockchain->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND a.is_separator_address=1 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction['transaction_id']])->fetchAll();
					$next_separator_i = 0;
					
					$regular_outputs = $this->blockchain->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND a.is_destroy_address=0 AND a.is_separator_address=0 AND a.is_passthrough_address=0 AND io.is_receiver=0 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction['transaction_id']])->fetchAll();
					$output_i = 0;
					
					if (count($regular_outputs) > 0) {
						$new_option_indices = [];
						foreach ($regular_outputs as $regular_output) {
							if (empty($option_indices_this_block[$regular_output['option_index']])) $new_option_indices[$regular_output['option_index']] = true;
						}
						
						if (count($new_option_indices) > 0) {
							$this->option_indices_to_id_in_block($new_option_indices, $block_height, $option_indices_this_block);
						}
						
						$new_option_ids = [];
						foreach ($regular_outputs as $regular_output) {
							if ($regular_output['option_index'] != "") {
								if (!empty($option_indices_this_block[$regular_output['option_index']])) {
									$option_id = $option_indices_this_block[$regular_output['option_index']];
									
									if (empty($events_by_option_id[$option_id])) {
										array_push($new_option_ids, $option_id);
									}
								}
							}
						}
						
						if (count($new_option_ids) > 0) {
							$these_db_events = $this->blockchain->app->run_query("SELECT op.option_id, ev.* FROM events ev JOIN options op ON ev.event_id=op.event_id WHERE op.option_id IN (".implode(",", $new_option_ids).");")->fetchAll();
							foreach ($these_db_events as $this_db_event) {
								$events_by_option_id[$this_db_event['option_id']] = new Event($this, $this_db_event, false);
							}
						}
						
						$insert_q = "INSERT INTO transaction_game_ios (game_id, io_id, address_id, game_out_index, game_io_index, is_game_coinbase, coin_blocks_destroyed, coin_rounds_destroyed, create_block_id, create_round_id, colored_amount, destroy_amount, option_id, contract_parts, event_id, effectiveness_factor, votes, effective_destroy_amount, is_resolved, resolved_before_spent) VALUES ";
						
						foreach ($regular_outputs as $regular_output) {
							$payout_insert_q = "";
							
							$gio_amount = floor($tx_game_nondestroy_amount*$regular_output['amount']/$tx_chain_regular_sum);
							$cbd = floor($cbd_in*$regular_output['amount']/$tx_chain_regular_sum);
							$crd = floor($crd_in*$regular_output['amount']/$tx_chain_regular_sum);
							
							if ($output_i == count($regular_outputs)-1) $this_destroy_amount = $tx_game_destroy_amount-$game_destroy_sum;
							else $this_destroy_amount = floor($tx_game_destroy_amount*$regular_output['amount']/$tx_chain_regular_sum);
							
							$game_destroy_sum += $this_destroy_amount;
							
							$game_io_index++;
							$insert_q .= "('".$this->db_game['game_id']."', '".$regular_output['io_id']."', '".$regular_output['address_id']."', '".$game_out_index."', '".$game_io_index."', 0, '".$cbd."', '".$crd."', '".$block_height."', '".$round_id."', ";
							$game_out_index++;
							
							if ($regular_output['option_index'] != "") {
								if (!empty($option_indices_this_block[$regular_output['option_index']])) {
									$option_id = $option_indices_this_block[$regular_output['option_index']];
									
									$using_separator = false;
									if (!empty($separator_outputs[$next_separator_i])) {
										$payout_io_id = $separator_outputs[$next_separator_i]['io_id'];
										$payout_address_id = $separator_outputs[$next_separator_i]['address_id'];
										$next_separator_i++;
										$using_separator = true;
									}
									else {
										$payout_io_id = $regular_output['io_id'];
										$payout_address_id = $regular_output['address_id'];
									}
									
									$effectiveness_factor = $events_by_option_id[$option_id]->block_id_to_effectiveness_factor($block_height);
									
									if ($this->db_game['payout_weight'] == "coin_block") $votes = floor($effectiveness_factor*$cbd);
									else if ($this->db_game['payout_weight'] == "coin_round") $votes = floor($effectiveness_factor*$crd);
									else $votes = floor($effectiveness_factor*$gio_amount);
									
									$effective_destroy_amount = floor($this_destroy_amount*$effectiveness_factor);
									
									$payout_is_resolved = 0;
									if ($this_destroy_amount == 0 && $this->db_game['exponential_inflation_rate'] == 0) $payout_is_resolved=1;
									$this_is_resolved = $payout_is_resolved;
									if ($using_separator) $this_is_resolved = 1;
									
									$insert_q .= "'".$gio_amount."', '".$this_destroy_amount."', '".$option_id."', '".$this->db_game['default_contract_parts']."', '".$events_by_option_id[$option_id]->db_event['event_id']."', '".$effectiveness_factor."', '".$votes."', '".$effective_destroy_amount."', ".$this_is_resolved.", null";
									
									$game_io_index++;
									$payout_insert_q = "('".$this->db_game['game_id']."', '".$payout_io_id."', '".$payout_address_id."', '".$game_out_index."', '".$game_io_index."', 1, 0, 0, '".$block_height."', '".$round_id."', 0, 0, '".$option_id."', '".$this->db_game['default_contract_parts']."', '".$events_by_option_id[$option_id]->db_event['event_id']."', null, 0, 0, ".$payout_is_resolved.", 1), ";
									$game_out_index++;
								}
								else $insert_q .= "'".($gio_amount+$this_destroy_amount)."', 0, null, null, null, null, null, 0, 1, null";
							}
							else $insert_q .= "'".$gio_amount."', '".$this_destroy_amount."', null, null, null, null, null, 0, 1, null";
							
							$insert_q .= "), ";
							if ($payout_insert_q != "") $insert_q .= $payout_insert_q;
						}
						
						$insert_q = substr($insert_q, 0, -2).";";
						$this->blockchain->app->run_query($insert_q);
						if (empty(AppSettings::getParam('sqlite_db'))) {
							$this->blockchain->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.parent_io_id=gio.game_io_id-1 WHERE io.create_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=1;", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
							$this->blockchain->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.payout_io_id=gio.game_io_id+1 WHERE gio.event_id IS NOT NULL AND io.create_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=0;", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
						}
						else {
							$this->blockchain->app->run_query("UPDATE transaction_game_ios SET parent_io_id=game_io_id-1 WHERE game_id=:game_id AND is_game_coinbase=1 AND io_id IN (SELECT io_id FROM transaction_ios WHERE create_transaction_id=:transaction_id);", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
							$this->blockchain->app->run_query("UPDATE transaction_game_ios SET payout_io_id=game_io_id+1 WHERE event_id IS NOT NULL AND game_id=:game_id AND is_game_coinbase=0 AND io_id IN (SELECT io_id FROM transaction_ios WHERE create_transaction_id=:transaction_id);", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
						}
					}
					
					$unresolved_inputs = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=1 AND gio.resolved_before_spent=0 ORDER BY io.in_index ASC;", [
						'transaction_id' => $db_transaction['transaction_id'],
						'game_id' => $this->db_game['game_id']
					])->fetchAll();
					
					$receiver_outputs = $this->blockchain->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.is_receiver=1 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction['transaction_id']])->fetchAll();
					
					if (count($unresolved_inputs) > 0 && count($receiver_outputs) > 0) {
						$insert_q = "INSERT INTO transaction_game_ios (parent_io_id, game_id, io_id, address_id, game_out_index, game_io_index, is_game_coinbase, colored_amount, destroy_amount, coin_blocks_destroyed, coin_rounds_destroyed, create_block_id, create_round_id, option_id, contract_parts, event_id, is_resolved, resolved_before_spent) VALUES ";
						
						if (count($receiver_outputs)%count($unresolved_inputs) == 0) {
							$outputs_per_unresolved_input = count($receiver_outputs)/count($unresolved_inputs);
						}
						else $outputs_per_unresolved_input = 1;
						
						$receiver_output_index = 0;
						
						foreach ($unresolved_inputs as &$unresolved_input) {
							$outputs_io_amount_sum = 0;
							for ($this_in_output_i=0; $this_in_output_i<$outputs_per_unresolved_input; $this_in_output_i++) {
								if (isset($receiver_outputs[$receiver_output_index+$this_in_output_i])) {
									$this_receiver_output = $receiver_outputs[$receiver_output_index+$this_in_output_i];
									$outputs_io_amount_sum += $this_receiver_output['amount'];
								}
							}
							
							for ($this_in_output_i=0; $this_in_output_i<$outputs_per_unresolved_input; $this_in_output_i++) {
								if (isset($receiver_outputs[$receiver_output_index+$this_in_output_i])) {
									$this_receiver_output = $receiver_outputs[$receiver_output_index+$this_in_output_i];
									
									$contract_parts = floor($unresolved_input['contract_parts']*$this_receiver_output['amount']/$outputs_io_amount_sum);
									
									$game_io_index++;
									$insert_q .= "('".$unresolved_input['parent_io_id']."', '".$this->db_game['game_id']."', '".$this_receiver_output['io_id']."', '".$unresolved_input['address_id']."', '".$game_out_index."', '".$game_io_index."', 1, 0, 0, 0, 0, '".$block_height."', '".$round_id."', '".$unresolved_input['option_id']."', '".$contract_parts."', '".$unresolved_input['event_id']."', 0, 1), ";
									$game_out_index++;
								}
							}
							
							$receiver_output_index += $outputs_per_unresolved_input;
						}
						
						$insert_q = substr($insert_q, 0, -2).";";
						$this->blockchain->app->run_query($insert_q);
					}
					
					$this->blockchain->app->dbh->commit();
				}
			}

			if ($benchmarks_on) {
				$benchmarks['process_transactions'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$in_progress_events = $this->events_being_determined_in_block($block_height);
			if (count($in_progress_events) > 0) {
				$in_progress_first_event_index = $in_progress_events[0]->db_event['event_index'];
				foreach ($in_progress_events as $in_progress_event) {
					if (!empty($in_progress_event->db_event['option_block_rule'])) {
						$in_progress_event->process_option_blocks($game_block, count($in_progress_events), $in_progress_first_event_index);
					}
				}
			}
			
			$this->set_target_scores_at_block($block_height+1);

			if ($benchmarks_on) {
				$benchmarks['process_option_blocks'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$finalblock_events = $this->events_by_final_block($block_height);
			
			foreach ($finalblock_events as $finalblock_event) {
				$finalblock_event->update_option_votes($block_height, false);
			}

			if ($benchmarks_on) {
				$benchmarks['update_option_votes'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$set_outcome_events = $this->events_by_outcome_block($block_height);
			
			foreach ($set_outcome_events as $set_outcome_event) {
				if (!empty($this->module) && method_exists($this->module, "set_event_outcome")) {
					if ($this->blockchain->db_blockchain['p2p_mode'] == "rpc") {
						$this->blockchain->load_coin_rpc();
					}
					
					$this->module->set_event_outcome($this, $set_outcome_event);
				}
				if (!empty($this->module) && method_exists($this->module, "event_index_to_next_event_index")) {
					$event_index = $this->module->event_index_to_next_event_index($set_outcome_event->db_event['event_index']);
					$this->set_event_labels_by_gde($event_index);
				}
			}
			
			if ($benchmarks_on) {
				$benchmarks['set_event_outcomes'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$payout_events = $this->events_by_payout_block($block_height);
			
			if (count($payout_events) > 0) {
				$payout_resolved_event_ids = [];

				foreach ($payout_events as $payout_event) {
					$payout_event->pay_out_event();

					if ((string)$payout_event->db_event['outcome_index'] !== "" || (string)$payout_event->db_event['track_payout_price'] !== "") {
						array_push($payout_resolved_event_ids, $payout_event->db_event['event_id']);
					}
				}

				if (count($payout_resolved_event_ids) > 0) {
					$this->blockchain->app->run_query("UPDATE transaction_game_ios SET is_resolved=1 WHERE event_id IN (".implode(",", $payout_resolved_event_ids).");");
				}
			}

			if ($benchmarks_on) {
				$benchmarks['process_payouts'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$this->set_block_stats($game_block);

			if ($benchmarks_on) {
				$benchmarks['set_block_stats'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			// If nothing was added this block & it's allowed, add game blocks in bulk
			if ($this->db_game['bulk_add_blocks'] && in_array($this->db_game['buyin_policy'], ["none", "for_sale"]) && $this->db_game['pow_reward_type'] == "none") {
				$last_block_id = $this->blockchain->last_block_id();
				
				if ($last_block_id > $block_height+5) {
					$next_required_block_id = $last_block_id;
					
					$next_spend_block = $this->blockchain->app->run_query("SELECT MIN(io.spend_block_id) AS block_id FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND io.spend_block_id>:block_id AND io.blockchain_id=:blockchain_id", [
						'game_id' => $this->db_game['game_id'],
						'block_id' => $block_height,
						'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id']
					])->fetch();
					
					if ($next_spend_block && !empty($next_spend_block['block_id'])) {
						if ($next_spend_block['block_id'] < $next_required_block_id) $next_required_block_id = $next_spend_block['block_id'];
					}
					
					$next_event_final_block = $this->blockchain->app->run_query("SELECT event_final_block FROM events WHERE game_id=:game_id AND event_final_block > :block_height ORDER BY event_final_block ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'block_height' => $block_height
					])->fetch();
					
					if ($next_event_final_block && !empty($next_event_final_block['event_final_block'])) {
						if ($next_event_final_block['event_final_block'] < $next_required_block_id) $next_required_block_id = $next_event_final_block['event_final_block'];
					}
					
					$next_event_payout_block = $this->blockchain->app->run_query("SELECT event_payout_block FROM events WHERE game_id=:game_id AND event_payout_block > :block_height ORDER BY event_payout_block ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'block_height' => $block_height
					])->fetch();
					
					if ($next_event_payout_block && !empty($next_event_payout_block['event_payout_block'])) {
						if ($next_event_payout_block['event_payout_block'] < $next_required_block_id) $next_required_block_id = $next_event_payout_block['event_payout_block'];
					}
					
					$bulk_from_block = $block_height+1;
					$bulk_to_block = $next_required_block_id-1;
					$ref_time = time();
					
					if ($bulk_from_block < $bulk_to_block) {
						if ($print_debug) $this->blockchain->app->print_debug("Adding ".($bulk_to_block-$bulk_from_block)." game blocks in bulk.. ".$bulk_from_block." to ".$bulk_to_block);
						
						$bulk_insert_q = "INSERT INTO game_blocks (game_id, block_id, locally_saved, num_transactions, time_created, time_loaded, load_time, max_game_io_index) VALUES ";
						for ($bulk_block_id=$bulk_from_block; $bulk_block_id<=$bulk_to_block; $bulk_block_id++) {
							$bulk_insert_q .= "(".$this->db_game['game_id'].", ".$bulk_block_id.", 1, 0, ".$ref_time.", ".$ref_time.", 0, ".(int)$game_io_index."), ";
						}
						$bulk_insert_q = substr($bulk_insert_q, 0, -2).";";
						$this->blockchain->app->run_query($bulk_insert_q);
					}
					else $bulk_to_block = false;
				}
			}

			if ($benchmarks_on) {
				$benchmarks['process_bulk'] = round(microtime(true)-$ref_time, $benchmark_sec_precision);
				$ref_time = microtime(true);
			}

			$block_load_time = (microtime(true)-$start_time);

			$this->blockchain->app->run_query("UPDATE game_blocks SET locally_saved=1, time_loaded=:current_time, load_time=load_time+:add_load_time, max_game_io_index=:max_game_io_index WHERE game_block_id=:game_block_id;", [
				'current_time' => time(),
				'add_load_time' => $block_load_time,
				'max_game_io_index' => $game_io_index,
				'game_block_id' => $game_block['game_block_id']
			]);

			if ($benchmarks_on) $this->blockchain->app->print_debug("Benchmarks (".$block_load_time." sec total): ".json_encode($benchmarks, JSON_PRETTY_PRINT));
		}
		else {
			$successful = false;
			if ($print_debug) $this->blockchain->app->print_debug("Skipping.. block ".$block_height." does not exist on ".$this->blockchain->db_blockchain['url_identifier']);
		}
		
		return array($successful, $bulk_to_block);
	}
	
	public function set_event_labels_by_gde($event_index) {
		$gde = $this->blockchain->app->fetch_game_defined_event_by_index($this->db_game['game_id'], $event_index);
		
		if ($gde) {
			$db_event = $this->fetch_event_by_index($event_index);
			
			if ($db_event) {
				$this->blockchain->app->run_query("UPDATE events SET event_name=:event_name WHERE event_id=:event_id;", [
					'event_name' => $gde['event_name'],
					'event_id' => $db_event['event_id']
				]);
				
				$gdos_by_gde = $this->blockchain->app->fetch_game_defined_options($this->db_game['game_id'], $gde['event_index'], false, false);
				$option_offset = 0;
				
				while ($gdo = $gdos_by_gde->fetch()) {
					$options_by_event_params = [
						'event_id' => $db_event['event_id']
					];
					$options_by_event_q = "SELECT * FROM options WHERE event_id=:event_id ORDER BY option_index ASC LIMIT 1";
					if ($option_offset > 0) {
						$options_by_event_q .= " OFFSET ".((int)$option_offset);
					}
					$db_option = $this->blockchain->app->run_query($options_by_event_q, $options_by_event_params)->fetch();
					
					if ($db_option) {
						$db_entity = $this->blockchain->app->fetch_entity_by_id($gdo['entity_id']);
						
						$option_update_params = [
							'entity_id' => $gdo['entity_id'],
							'name' => $gdo['name'],
							'option_id' => $db_option['option_id']
						];
						$option_update_q = "UPDATE options SET entity_id=:entity_id";
						if ($db_entity && !empty($db_entity['default_image_id'])) {
							$option_update_q .= ", image_id=:image_id";
							$option_update_params['image_id'] = $db_entity['default_image_id'];
						}
						$option_update_q .= ", name=:name WHERE option_id=:option_id;";
						$this->blockchain->app->run_query($option_update_q, $option_update_params);
					}
					$option_offset++;
				}
			}
		}
	}
	
	public function set_game_defined_outcome($event_index, $outcome_index) {
		$this->update_game_defined_event($event_index, [
			'outcome_index' => $outcome_index,
		]);
	}
	
	public function update_game_defined_event($event_index, $params, $update_events_too=false) {
		$params['game_id'] = $this->db_game['game_id'];
		$params['event_index'] = $event_index;
		$update_q = "";
		foreach ($params as $var => $value) {
			$update_q .= $var."=:".$var.", ";
		}
		$update_q = substr($update_q, 0, strlen($update_q)-2);
		$update_q .= " WHERE game_id=:game_id AND event_index=:event_index;";
		$this->blockchain->app->run_query("UPDATE game_defined_events SET ".$update_q, $params);
		if ($update_events_too) $this->blockchain->app->run_query("UPDATE events SET ".$update_q, $params);
	}
	
	public function render_transaction(&$transaction, $selected_address_id, $selected_game_io_id, $coins_per_vote, $last_block_id) {
		$html = '<div class="row bordered_row"><div class="col-md-12">';
		
		if ((string) $transaction['block_id'] !== "") {
			if ($transaction['position_in_block'] == "") $html .= "Confirmed";
			else $html .= "#".(int)$transaction['position_in_block'];
			$html .= " in block <a href=\"/explorer/games/".$this->db_game['url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>, ";
		}
		$html .= (int)$transaction['num_inputs']." inputs, ".(int)$transaction['num_outputs']." outputs";
		
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase") {
			$fee_disp = $this->blockchain->app->format_bignum($transaction_fee/pow(10,$this->blockchain->db_blockchain['decimal_places']));
			$html .= ", ".$fee_disp." ".$this->blockchain->db_blockchain['coin_name'];
			$html .= " tx fee";
		}
		if (empty($transaction['block_id'])) $html .= ", not yet confirmed";
		
		$html .= '. <br/><a href="/explorer/games/'.$this->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a>';
		
		$html .= '</div><div class="col-md-6">';
		$html .= $this->render_ios_in_transaction("in", $transaction, $selected_game_io_id, $selected_address_id, $coins_per_vote, $last_block_id);
		$html .= '</div><div class="col-md-6">';
		$html .= $this->render_ios_in_transaction("out", $transaction, $selected_game_io_id, $selected_address_id, $coins_per_vote, $last_block_id);
		$html .= '</div></div>'."\n";
		
		return $html;
	}
	
	public function render_ios_in_transaction($in_out, &$db_transaction, $selected_game_io_id, $selected_address_id, $coins_per_vote, $last_block_id) {
		$html = '<div class="explorer_ios">';
		
		$ios_q = "SELECT a.*, p.*, a.address_id AS address_id, gio.contract_parts, gio.is_game_coinbase, gio.colored_amount AS colored_amount, gio.is_resolved AS is_resolved, gio.game_io_id, gio.game_out_index, gio.game_io_id AS game_io_id, op.*, ev.*, en.forex_pair_shows_nonstandard, p.contract_parts AS total_contract_parts, p.votes, op.votes AS option_votes, op.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, io.spend_status, io.is_destroy, io.is_separator, io.is_passthrough, io.is_receiver";
		if ($in_out == "in") $ios_q .= ", t.tx_hash FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id";
		else $ios_q .= " FROM transaction_ios io";
		$ios_q .= " JOIN transaction_game_ios gio ON io.io_id=gio.io_id LEFT JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN addresses a ON io.address_id=a.address_id LEFT JOIN options op ON gio.option_id=op.option_id LEFT JOIN events ev ON op.event_id=ev.event_id LEFT JOIN options w ON ev.winning_option_id=w.option_id LEFT JOIN entities en ON ev.track_entity_id=en.entity_id WHERE gio.game_id=:game_id AND io.";
		if ($in_out == "out") $ios_q .= "create_transaction_id";
		else $ios_q .= "spend_transaction_id";
		$ios_q .= "=:transaction_id ORDER BY io.out_index ASC;";
		$ios = $this->blockchain->app->run_query($ios_q, [
			'game_id' => $this->db_game['game_id'],
			'transaction_id' => $db_transaction['transaction_id']
		]);
		
		while ($io = $ios->fetch()) {
			$html .= '<p>';
			$html .= '<a class="display_address" style="';
			if ($selected_address_id && $io['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= '" href="/explorer/games/'.$this->db_game['url_identifier'].'/addresses/'.$io['address'].'">';
			if ($io['is_destroy'] == 1) $html .= '[D] ';
			if ($io['is_separator'] == 1) $html .= '[S] ';
			if ($io['is_passthrough'] == 1) $html .= '[P] ';
			if ($io['is_receiver'] == 1) $html .= '[R] ';
			$html .= $io['address']."</a>";
			if (!empty($io['option_id'])) $html .= " (<a href=\"/explorer/games/".$this->db_game['url_identifier']."/events/".$io['event_index']."\">".$io['name']."</a>)";
			$html .= "<br/>\n";
			
			if ($selected_game_io_id == $io['game_io_id']) $html .= "<b>";
			else {
				$html .= "<a href=\"/explorer/games/".$this->db_game['url_identifier']."/utxo/";
				if ($in_out == "in") $html .= $io['tx_hash'];
				else $html .= $db_transaction['tx_hash'];
				$html .= "/".$io['game_out_index']."\">";
			}
			$html .= $this->display_coins($io['colored_amount'], false, false, false);
			if ($selected_game_io_id == $io['game_io_id']) $html .= "</b>";
			else $html .= "</a>\n";
			
			$html .= " &nbsp; ".ucwords($io['spend_status'])."<br/>\n";
			
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees, $coin_stake) = $this->get_payout_info($io, $coins_per_vote, $last_block_id);
			
			if (empty($io['option_id']) && $io['destroy_amount']+$inflation_stake > 0) {
				$html .= $this->display_coins($io['destroy_amount']+$inflation_stake);
			}
			
			if ($io['is_game_coinbase'] == 1) {
				$frac_of_contract = $io['contract_parts']/$io['total_contract_parts'];
				
				if ($io['payout_rule'] == "binary") {
					$html .= $this->display_coins($frac_of_contract*($io['destroy_amount']+$inflation_stake), false, false, false);
					
					$html .= " &nbsp;&nbsp; x".$this->blockchain->app->round_to($odds, 2, 4, true)." ";
					
					$html .= '&nbsp;&nbsp;';
					if ($io['outcome_index'] != -1) {
						$html .= '<font class="';
						if ($io['is_resolved'] == 1) {
							if ($io['winning_option_id'] == $io['option_id']) $html .= 'greentext';
							else $html .= 'redtext';
						}
						else $html .= 'yellowtext';
						$html .= '">';
					}
					if ($io['payout_rule'] == "binary") $html .= '+';
					$html .= $this->blockchain->app->format_bignum($max_payout/pow(10,$this->db_game['decimal_places']));
					if ($io['outcome_index'] != -1) $html .= "</font>";
				}
				else {
					if ($io['event_option_index'] != 0) $html .= '-';
					$html .= $this->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->db_game['decimal_places']), false).' '.$io['track_name_short'].' ';
					
					if ($borrow_delta != 0) {
						if ($borrow_delta > 0) $html .= '<font class="greentext">+ ';
						else $html .= '<font class="redtext">- ';
						$html .= $this->blockchain->app->format_bignum(abs($borrow_delta/pow(10, $this->db_game['decimal_places'])), false);
						$html .= "</font> USD\n";
					}

					$html .= ' = <font class="greentext">'.$this->display_coins($fair_io_value-$payout_fees, true)."</font>";
				}
				
				if ($io['payout_rule'] == "linear") {
					$html .= " &nbsp; <a href=\"\" onclick=\"$('#gio_details_".$in_out."_".$io['game_io_id']."').toggle('fast'); return false;\">Details</a>";
					$html .= '<div style="display: none; border: 1px solid #ccc; padding: 5px;" id="gio_details_'.$in_out.'_'.$io['game_io_id'].'">';
					
					$html .= "Paid ".$this->display_coins($io['destroy_amount']+$inflation_stake);
					$html .= ' @ $'.$this->blockchain->app->format_bignum($asset_price_usd, false)." / contract (";

					if ($io['forex_pair_shows_nonstandard']) {
						$html .= $this->blockchain->app->round_to($bought_price_usd, 0, EXCHANGE_RATE_SIGFIGS, true)." ".$io['track_name_short']."/USD";
					}
					else {
						$html .= $this->blockchain->app->round_to(1/$bought_price_usd, 0, EXCHANGE_RATE_SIGFIGS, true)." USD/".$io['track_name_short'];
					}

					$html .= ')<br/>'.$this->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->db_game['decimal_places']), false).' '.$io['track_name_short'];

					if ($borrow_delta != 0) {
						$html .= ' ';
						if ($borrow_delta > 0) $html .= '<font class="greentext">+ ';
						else $html .= '<font class="redtext">- ';
						$html .= $this->blockchain->app->format_bignum(abs($borrow_delta/pow(10, $this->db_game['decimal_places'])), false);
						$html .= "</font> USD\n";
					}

					$html .= '<br/>'.$io['track_name_short']." between $".$this->blockchain->app->format_bignum($io['track_min_price'], false)." and $".$this->blockchain->app->format_bignum($io['track_max_price'], false)." (".$this->blockchain->app->format_bignum($bought_leverage, false).'X leverage)';
					$html .= '<br/><br/>';
					
					if ($io['is_resolved'] == 1) $html .= 'Paid out';
					else $html .= 'Now valued';
					$html .= ' at <font class="greentext">'.$this->display_coins($fair_io_value-$payout_fees)."</font>\n";
					$html .= "@ ";
					if ($io['forex_pair_shows_nonstandard']) {
						$html .= $this->blockchain->app->format_bignum($track_pay_price, false)." ".$io['track_name_short']."/USD";
						if ($track_price_usd != $track_pay_price) $html .= " (".$this->blockchain->app->format_bignum($track_price_usd, false).")";
					}
					else {
						$html .= $this->blockchain->app->round_to(1/$track_pay_price, 0, EXCHANGE_RATE_SIGFIGS, true)." USD/".$io['track_name_short'];
						if ($track_price_usd != $track_pay_price) $html .= " (".$this->blockchain->app->round_to(1/$track_price_usd, 0, EXCHANGE_RATE_SIGFIGS, true).")";
					}
					$html .= "<br/>\n";
					
					if ($payout_fees > 0) {
						$payout_fees_disp = $this->display_coins($payout_fees, false, true, false);
						$html .= "<font class=\"redtext\">".$payout_fees_disp."</font> ".($payout_fees_disp=="1" ? $this->db_game['coin_name'] : $this->db_game['coin_name_plural'])." in fees<br/>\n";
					}
					
					if ($io['destroy_amount']+$inflation_stake > 0) $pct_gain = 100*($net_delta/($io['destroy_amount']+$inflation_stake));
					else $pct_gain = 0;
					
					if ($net_delta < 0) $html .= '<font class="redtext">Net loss of ';
					else $html .= '<font class="greentext">Net gain of ';
					$html .= $this->display_coins(abs($net_delta));
					$html .= " &nbsp; ";
					if ($pct_gain >= 0) $html .= "+";
					else $html .= "-";
					$html .= round(abs($pct_gain), 2)."%";
					$html .= '</font>';
					
					$html .= "</div>\n";
				}
			}
			
			$html .= "</p>\n";
		}
		
		$html .= "</div>\n";
		
		return $html;
	}
	
	public function get_payout_info(&$io, &$coins_per_vote, &$last_block_id) {
		$track_entity = false;
		$track_price_usd = false;
		$track_pay_price = false;
		$position_price = false;
		$bought_price_usd = false;
		$fair_io_value = false;
		$inflation_stake = 0;
		$effective_stake = false;
		$unconfirmed_votes = 0;
		$max_payout = false;
		$odds = false;
		$bought_leverage = false;
		$current_leverage = false;
		$borrow_delta = false;
		$net_delta = false;
		$payout_fees = 0;
		$coin_stake = 0;
		
		$effective_paid = 0;
		$equivalent_contracts = 0;
		$event_equivalent_contracts = 0;
		$track_position_price = false;
		
		if ($io['is_game_coinbase'] == 1) {
			if ($coins_per_vote == 0) {
				$inflation_stake = 0;
			}
			else {
				if ($io['spend_status'] == "unconfirmed") {
					$this_round_id = $this->block_to_round($last_block_id+1);
					$unconfirmed_votes = $io['ref_'.$this->db_game['payout_weight']."s"];
					if ($this_round_id != $io['ref_round_id']) {
						$unconfirmed_votes += $io['colored_amount']*($this_round_id-$io['ref_round_id']);
					}
					$inflation_stake = $unconfirmed_votes*$coins_per_vote;
				}
				else {
					$inflation_stake = $io[$this->db_game['payout_weight']."s_destroyed"]*$coins_per_vote;
				}
			}
			
			$coin_stake = (($io['contract_parts']/$io['total_contract_parts'])*$io['destroy_amount']) + $inflation_stake;
			
			$frac_of_contract = $io['contract_parts']/$io['total_contract_parts'];
			
			$event_payout = $io['sum_destroy_score']+$io['sum_unconfirmed_destroy_score']+($io['sum_score']+$io['sum_unconfirmed_score'])*$coins_per_vote;
			$option_effective_stake = $io['option_effective_destroy_score']+$io['unconfirmed_effective_destroy_score']+($io['option_votes']+$io['unconfirmed_votes'])*$coins_per_vote;
			$event_effective_stake = $io['sum_effective_destroy_score']+$io['sum_unconfirmed_effective_destroy_score']+($io['sum_votes']+$io['sum_unconfirmed_votes'])*$coins_per_vote;
			
			if ($io['spend_status'] == "unconfirmed") {
				$ref_event = new Event($this, false, $io['event_id']);
				$effectiveness = $ref_event->block_id_to_effectiveness_factor($last_block_id+1);
				$effective_unconfirmed_votes = floor($unconfirmed_votes*$effectiveness);
				$effective_stake = floor($io['destroy_amount']*$effectiveness) + floor($effective_unconfirmed_votes*$coins_per_vote);
			}
			else {
				$effective_stake = $io['effective_destroy_amount']+$io['votes']*$coins_per_vote;
			}
			
			if ($option_effective_stake > 0) $max_payout = $frac_of_contract*$io['payout_rate']*$event_payout*$effective_stake/$option_effective_stake;
			else $max_payout = 0;
			
			if ($io['destroy_amount']+$inflation_stake > 0) $odds = $max_payout/($frac_of_contract*($io['destroy_amount']+$inflation_stake));
			else $odds = 0;
			$fair_io_value = false;
			$track_price_usd = false;
			
			if ($io['payout_rule'] == "linear") {
				$track_entity = $this->blockchain->app->fetch_entity_by_id($io['entity_id']);
				
				if ((string)$io['track_payout_price'] == "") {
					$track_price_info = $this->blockchain->app->exchange_rate_between_currencies(1, $track_entity['currency_id'], time(), $this->blockchain->app->get_reference_currency()['currency_id']);
					$track_price_usd = $track_price_info['exchange_rate'];
				}
				else {
					$track_price_usd = $io['track_payout_price'];
				}
				
				if ($track_price_usd > $io['track_max_price']) $track_pay_price = $io['track_max_price'];
				else if ($track_price_usd < $io['track_min_price']) $track_pay_price = $io['track_min_price'];
				else $track_pay_price = $track_price_usd;
				
				$contract_price_size = $io['track_max_price']-$io['track_min_price'];
				if ($event_effective_stake > 0) $position_price = $contract_price_size*$option_effective_stake/$event_effective_stake;
				else $position_price = 0;
				
				if ($io['event_option_index'] == 0) {
					$track_position_price = $track_price_usd-$io['track_min_price'];
					$bought_price_usd = $io['track_min_price']+$position_price;
				}
				else {
					$track_position_price = $io['track_max_price']-$track_price_usd;
					$bought_price_usd = $io['track_max_price']-$position_price;
				}
				$track_position_price = max(0, min($contract_price_size, $track_position_price));
				
				if ($event_effective_stake > 0) $effective_paid = $frac_of_contract*$event_payout*$effective_stake/$event_effective_stake;
				else $effective_paid = 0;
				
				if ($position_price > 0) $equivalent_contracts = $frac_of_contract*$effective_paid/$position_price;
				else $equivalent_contracts = 0;
				
				if ($position_price == 0) {
					$bought_leverage = false;
					$current_leverage = false;
					$borrow_delta = 0;
				}
				else {
					if ($io['event_option_index'] == 0) {
						$bought_leverage = ($position_price+$io['track_min_price'])/$position_price;
						if ($track_position_price == 0) $current_leverage = false;
						else $current_leverage = $track_price_usd/$track_position_price;
						$borrow_delta = (-1)*$equivalent_contracts*$io['track_min_price'];
					}
					else {
						$bought_leverage = ($io['track_max_price']-$position_price)/$position_price;
						if ($track_position_price > 0 && $track_position_price > 0) $current_leverage = $track_price_usd/$track_position_price;
						else $current_leverage = false;
						$borrow_delta = $equivalent_contracts*$io['track_max_price'];
					}
				}
				
				$fair_io_value = $track_position_price*$equivalent_contracts;
				$payout_fees = round($fair_io_value*(1-$io['payout_rate']));
				$net_delta = $fair_io_value - ($io['destroy_amount']+$inflation_stake) - $payout_fees;
			}
			else {
				if ($option_effective_stake > 0) $payout_fees = round((1-$io['payout_rate'])*$event_payout*$effective_stake/$option_effective_stake);
				else $payout_fees = 0;
			}
		}
		
		return array($track_entity, $track_price_usd, $track_pay_price, $position_price, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees, $coin_stake);
	}
	
	public function explorer_block_list($from_block_id, $to_block_id) {
		return $this->blockchain->explorer_block_list($from_block_id, $to_block_id, $this, false);
	}
	
	public function set_block_stats(&$game_block) {
		$out_stats = $this->blockchain->app->run_query("SELECT COUNT(*) ios_out, SUM(gio.colored_amount) coins_out FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND t.block_id=:block_id GROUP BY t.transaction_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $game_block['block_id']
		])->fetchAll();
		
		$num_ios_out = 0;
		$sum_coins_out = 0;
		$num_transactions = count($out_stats);
		
		foreach ($out_stats as $out_stat) {
			$num_ios_out += (int)$out_stat['ios_out'];
			$sum_coins_out += (int)$out_stat['coins_out'];
		}
		
		$in_stat = $this->blockchain->app->run_query("SELECT COUNT(*) ios_in, SUM(gio.colored_amount) coins_in FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND t.block_id=:block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $game_block['block_id']
		])->fetch();
		
		$num_ios_in = (int)$in_stat['ios_in'];
		$sum_coins_in = (int)$in_stat['coins_in'];
		
		$this->blockchain->app->run_query("UPDATE game_blocks SET num_transactions=:num_transactions, num_ios_in=:num_ios_in, num_ios_out=:num_ios_out, sum_coins_in=:sum_coins_in, sum_coins_out=:sum_coins_out WHERE game_block_id=:game_block_id;", [
			'num_transactions' => $num_transactions,
			'num_ios_in' => $num_ios_in,
			'num_ios_out' => $num_ios_out,
			'sum_coins_in' => $sum_coins_in,
			'sum_coins_out' => $sum_coins_out,
			'game_block_id' => $game_block['game_block_id']
		]);
		
		$game_block['num_transactions'] = $num_transactions;
		$game_block['num_ios_in'] = $num_ios_in;
		$game_block['num_ios_out'] = $num_ios_out;
		$game_block['sum_coins_in'] = $sum_coins_in;
		$game_block['sum_coins_out'] = $sum_coins_out;
	}
	
	public function total_paid_to_address(&$db_address, $confirmed_only) {
		$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.address_id=:address_id";
		if ($confirmed_only) $balance_q .= " AND io.spend_status IN ('spent','unspent')";
		
		return (int)($this->blockchain->app->run_query($balance_q, [
			'game_id' => $this->db_game['game_id'],
			'address_id' => $db_address['address_id']
		])->fetch()['SUM(gio.colored_amount)']);
	}
	
	public function address_balance_at_block(&$db_address, $block_id) {
		$balance_params = [
			'game_id' => $this->db_game['game_id'],
			'address_id' => $db_address['address_id']
		];
		if ($block_id !== false) {
			$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.address_id=:address_id AND io.create_block_id <= :block_id AND ((io.spend_block_id IS NULL AND io.spend_status='unspent') OR io.spend_block_id > :block_id);";
			$balance_params['block_id'] = $block_id;
		}
		else {
			$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.address_id=:address_id AND io.spend_block_id IS NULL AND io.spend_status='unspent';";
		}
		return (int)($this->blockchain->app->run_query($balance_q, $balance_params)->fetch()['SUM(gio.colored_amount)']);
	}
	
	public function account_balance($account_id, $extra_params=[]) {
		$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE k.account_id=:account_id AND gio.game_id=:game_id";
		
		if (empty($extra_params['include_immature'])) {
			$balance_q .= " AND io.is_mature=1";
		}
		$balance_q .= " AND (io.spend_status='unspent'";
		if (empty($extra_params['confirmed_only'])) {
			$balance_q .= " || io.spend_status='unconfirmed'";
		}
		$balance_q .= ")";
		
		$balance_params = [
			'account_id' => $account_id,
			'game_id' => $this->db_game['game_id']
		];
		
		return (int)($this->blockchain->app->run_query($balance_q, $balance_params)->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function account_balance_at_block($account_id, $block_id, $include_coinbase) {
		$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND io.create_block_id <= :block_id AND ((io.spend_block_id IS NULL AND io.spend_status='unspent') OR io.spend_block_id > :block_id)";
		if (!$include_coinbase) $balance_q .= " AND gio.is_game_coinbase=0";
		return (int)($this->blockchain->app->run_query($balance_q, [
			'game_id' => $this->db_game['game_id'],
			'account_id' => $account_id,
			'block_id' => $block_id
		])->fetch()['SUM(gio.colored_amount)']);
	}
	
	public function block_next_prev_links($block, $explore_mode) {
		$html = "";
		$prev_link_target = false;
		if ($explore_mode == "unconfirmed") $prev_link_target = "blocks/".$this->blockchain->last_block_id();
		else if ($block['block_id'] > 1) $prev_link_target = "blocks/".($block['block_id']-1);
		if ($prev_link_target) $html .= '<a href="/explorer/games/'.$this->db_game['url_identifier'].'/'.$prev_link_target.'" style="margin-right: 30px;">&larr; Previous Block</a>';
		
		$next_link_target = false;
		if ($explore_mode == "unconfirmed") {}
		else if ($block['block_id'] == $this->blockchain->last_block_id()) $next_link_target = "transactions/unconfirmed";
		else if ($block['block_id'] < $this->blockchain->last_block_id()) $next_link_target = "blocks/".($block['block_id']+1);
		if ($next_link_target) $html .= '<a href="/explorer/games/'.$this->db_game['url_identifier'].'/'.$next_link_target.'">Next Block &rarr;</a>';
		
		return $html;
	}
	
	public function round_to_display_round($round_id) {
		$game_starting_round = $this->block_to_round($this->db_game['game_starting_block']);
		$diff_rounds = $round_id - $game_starting_round;
		return $diff_rounds+1;
	}
	
	public function display_round_to_round($display_round_id) {
		$game_starting_round = $this->block_to_round($this->db_game['game_starting_block']);
		return $game_starting_round+$display_round_id-1;
	}
	
	public function transaction_coins_in($transaction_id) {
		return (int)($this->blockchain->app->run_query("SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id=:game_id AND io.spend_transaction_id=:transaction_id;", [
			'game_id' => $this->db_game['game_id'],
			'transaction_id' => $transaction_id
		])->fetch(PDO::FETCH_NUM)[0]);
	}

	public function transaction_coins_out($transaction_id, $exclude_coinbase) {
		$coins_out_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id=:game_id AND io.create_transaction_id=:transaction_id";
		if ($exclude_coinbase) $coins_out_q .= " AND gio.is_game_coinbase=0";
		return (int)($this->blockchain->app->run_query($coins_out_q, [
			'game_id' => $this->db_game['game_id'],
			'transaction_id' => $transaction_id
		])->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function check_set_faucet_account() {
		$faucet_account = $this->blockchain->app->run_query("SELECT * FROM currency_accounts WHERE is_faucet=1 AND game_id=:game_id ORDER BY account_id DESC;", ['game_id'=>$this->db_game['game_id']])->fetch();
		
		if ($faucet_account) return $faucet_account;
		else {
			return $this->blockchain->app->create_new_account([
				'currency_id' => $this->blockchain->currency_id(),
				'game_id' => $this->db_game['game_id'],
				'account_name' => $this->db_game['name'].' Faucet',
				'is_faucet' => 1
			]);
		}
	}
	
	public function check_set_game_sale_account(&$admin_user) {
		$game_sale_account = $this->blockchain->app->run_query("SELECT * FROM currency_accounts WHERE is_game_sale_account=1 AND game_id=:game_id ORDER BY account_id DESC;", [
			'game_id' => $this->db_game['game_id']
		])->fetch();
		
		if ($game_sale_account) return $game_sale_account;
		else {
			return $this->blockchain->app->create_new_account([
				'currency_id' => $this->blockchain->currency_id(),
				'game_id' => $this->db_game['game_id'],
				'account_name' => $this->db_game['name'].' '.$this->db_game['coin_name_plural'].' for sale',
				'is_game_sale_account' => 1,
				'user_id' => $admin_user ? $admin_user->db_user['user_id'] : null
			]);
		}
	}
	
	public function check_set_blockchain_sale_account(&$admin_user, &$currency) {
		$blockchain_sale_account = $this->blockchain->app->run_query("SELECT * FROM currency_accounts WHERE is_blockchain_sale_account=1 AND currency_id=:currency_id AND game_id=:game_id ORDER BY account_id DESC;", [
			'currency_id' => $currency['currency_id'],
			'game_id' => $this->db_game['game_id']
		])->fetch();
		
		if ($blockchain_sale_account) return $blockchain_sale_account;
		else {
			return $this->blockchain->app->create_new_account([
				'currency_id' => $currency['currency_id'],
				'game_id' => $this->db_game['game_id'],
				'account_name' => $this->db_game['name'].' '.$currency['short_name_plural'].' for sale',
				'is_blockchain_sale_account' => 1,
				'user_id' => $admin_user ? $admin_user->db_user['user_id'] : null
			]);
		}
	}
	
	public function earliest_join_time($user_id, $game_id) {
		$joined_at = $this->blockchain->app->run_query("SELECT MIN(created_at) FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
			'user_id' => $user_id,
			'game_id' => $game_id
		])->fetch();
		
		if ($joined_at) {
			return (int) $joined_at['MIN(created_at)'];
		}
		else return false;
	}
	
	public function most_recent_faucet_claim($user_id, $game_id) {
		$claim_info = $this->blockchain->app->run_query("SELECT MAX(latest_claim_time) FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
			'user_id' => $user_id,
			'game_id' => $game_id
		])->fetch();
		
		if ($claim_info) {
			return (int) $claim_info['MAX(latest_claim_time)'];
		}
		else return false;
	}
	
	public function user_faucet_claims($user_id, $game_id) {
		$user_faucet_claims = $this->blockchain->app->run_query("SELECT SUM(faucet_claims) FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
			'user_id' => $user_id,
			'game_id' => $game_id
		])->fetch();
		
		return (int) $user_faucet_claims['SUM(faucet_claims)'];
	}
	
	public function user_faucet_info($user_id, $game_id) {
		$earliest_join_time = $this->earliest_join_time($user_id, $game_id);
		$most_recent_claim_time = $this->most_recent_faucet_claim($user_id, $game_id);
		$user_faucet_claims = $this->user_faucet_claims($user_id, $game_id);
		$eligible_for_faucet = false;
		$time_available = false;
		
		$sec_per_faucet_claim = $this->db_game['sec_per_faucet_claim'];
		$min_sec_between_claims = $this->db_game['min_sec_between_claims'];
		
		if ($earliest_join_time) {
			$sec_since_joined = time() - $earliest_join_time;
			
			$seeking_claim = $user_faucet_claims+1;
			$seeking_claim_after_bonus = $seeking_claim - $this->db_game['bonus_claims'];
			
			$time_claim_available = $earliest_join_time + ($seeking_claim_after_bonus*$sec_per_faucet_claim);
			
			if (time() >= $time_claim_available) {
				$sec_since_last_claim = time() - $most_recent_claim_time;
				
				if ($sec_since_last_claim >= $min_sec_between_claims) {
					$time_available = time();
					$eligible_for_faucet = true;
				}
				else {
					$time_available = $most_recent_claim_time + $min_sec_between_claims;
					$eligible_for_faucet = false;
				}
			}
			else {
				$eligible_for_faucet = false;
				$time_available = $time_claim_available;
			}
		}
		
		return [
			$earliest_join_time,
			$most_recent_claim_time,
			$user_faucet_claims,
			$eligible_for_faucet,
			$time_available
		];
	}
	
	public function check_faucet(&$user_game) {
		if ($this->db_game['faucet_policy'] == "on") {
			$eligible_for_faucet = false;
			
			if ($user_game) {
				list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available) = $this->user_faucet_info($user_game['user_id'], $user_game['game_id']);
			}
			
			if (empty($user_game) || $eligible_for_faucet) {
				// Only give out coins when the game is fully loaded
				if (empty($user_game) || $this->last_block_id() >= $this->blockchain->last_block_id()-1) {
					$faucet_account = $this->check_set_faucet_account();
					
					$faucet_io = $this->blockchain->app->run_query("SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM address_keys k JOIN transaction_game_ios gio ON gio.address_id=k.address_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND io.spend_status IN ('unspent', 'unconfirmed') GROUP BY k.address_id ORDER BY colored_amount_sum DESC, gio.game_io_index ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'account_id' => $faucet_account['account_id']
					])->fetch();
					
					return $faucet_io;
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function ensure_genesis_transaction() {
		$any_error = null;
		$error_message = null;
		
		$genesis_tx = $this->blockchain->fetch_transaction_by_hash($this->db_game['genesis_tx_hash']);
		
		if ($genesis_tx) {
			if ((string) $genesis_tx['block_id'] != "") {
				$this->process_buyin_transaction($genesis_tx);
				$any_error = false;
			}
			else {
				$any_error = true;
				$error_message = "Genesis transaction must be confirmed for the game to start.";
			}
		}
		else {
			$any_error = true;
			$error_message = "Tried to process genesis tx when not present in blockchain.";
		}
		
		return [$any_error, $error_message];
	}
	
	public function time_to_block_in_game($time) {
		if ($time < time()) {
			$db_block = $this->blockchain->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND time_mined <= :time ORDER BY time_mined DESC LIMIT 1;", [
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
				'time' => $time
			])->fetch();
			
			if ($db_block) {
				$block_id = max($this->db_game['game_starting_block']+1, $db_block['block_id']);
			}
			else $block_id = $this->db_game['game_starting_block']+1;
		}
		else {
			$sec_to_add = $time - time();
			$add_blocks = floor($sec_to_add/$this->blockchain->seconds_per_block('average'));
			$block_id = $this->blockchain->last_block_id()+$add_blocks;
		}
		return $block_id;
	}
	
	public function set_gde_blocks_by_time(&$gde, &$time_to_block_cache) {
		if (!empty($gde['event_starting_time'])) {
			if (isset($time_to_block_cache[$gde['event_starting_time']])) $start_block = $time_to_block_cache[$gde['event_starting_time']];
			else {
				$start_block = $this->time_to_block_in_game(strtotime($gde['event_starting_time']));
				$time_to_block_cache[$gde['event_starting_time']] = $start_block;
			}
			
			if (isset($time_to_block_cache[$gde['event_final_time']])) $final_block = $time_to_block_cache[$gde['event_final_time']];
			else {
				$final_block = $this->time_to_block_in_game(strtotime($gde['event_final_time']));
				$time_to_block_cache[$gde['event_final_time']] = $final_block;
			}
			
			if ($gde['event_payout_time'] == "" || $gde['event_payout_time'] == $gde['event_final_time']) $payout_block = $final_block;
			else {
				if (isset($time_to_block_cache[$gde['event_payout_time']])) $payout_block = $time_to_block_cache[$gde['event_payout_time']];
				else {
					$payout_block = $this->time_to_block_in_game(strtotime($gde['event_payout_time']));
					$time_to_block_cache[$gde['event_payout_time']] = $payout_block;
				}
			}
			
			$this->blockchain->app->run_query("UPDATE game_defined_events SET event_starting_block=:event_starting_block, event_final_block=:event_final_block, event_payout_block=:event_payout_block WHERE game_id=:game_id AND event_index=:event_index;", [
				'event_starting_block' => $start_block,
				'event_final_block' => $final_block,
				'event_payout_block' => $payout_block,
				'game_id' => $this->db_game['game_id'],
				'event_index' => $gde['event_index']
			]);
		}
	}
	
	public function event_filter_html($initial_filter_term=null) {
		$show_date_filter = false;
		
		$html = '
		<form class="form-inline" onsubmit="return false;">
			<div class="form-group" style="margin-right: 15px;">
				<input type="text" id="filter_by_term" class="form-control input-sm" placeholder="Search '.$this->db_game['event_type_name_plural'].'" value="'.(isset($initial_filter_term) ? $initial_filter_term : '').'" />
			</div>';
		if ($show_date_filter) {
			$html .= '
			<div class="form-group">
				<label for="filter_by_date">Date:</label> &nbsp;&nbsp; 
				<select class="form-control input-sm" id="filter_by_date" onchange="thisPageManager.filter_changed(\'date\');">
					<option value="">Select a Date</option>';
					
					$ref_date = date("Y-m-d", time());
					$ref_time = strtotime($ref_date);
					$display_days = 5;
					
					for ($day_i=0; $day_i<$display_days; $day_i++) {
						$this_time = strtotime($ref_date." +".$day_i." days");
						$this_date = date("Y-m-d", $this_time);
						$html .= '<option value="'.$this_date.'">'.date("M j, Y", $this_time).'</option>';
					}
					$html .= '
				</select>
			</div>';
		}
		$html .= '
		</form>';
		
		return $html;
	}
	
	public function set_event_blocks($user_id, $game_defined_event_id, $avoid_changing_completed_events = false, $skip_record_migration=false) {
		$log_text = "";
		$last_block_id = $this->blockchain->last_block_id();
		
		$event_params = [
			'game_id' => $this->db_game['game_id']
		];
		$event_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_starting_time IS NOT NULL";
		if ($game_defined_event_id) {
			$event_q .= " AND game_defined_event_id=:game_defined_event_id";
			$event_params['game_defined_event_id'] = $game_defined_event_id;
		}
		else if ($avoid_changing_completed_events) {
			$event_q .= " AND ((event_starting_block <= :block_id AND event_payout_block >= :block_id) OR event_starting_block IS NULL OR event_final_block IS NULL)";
			$event_params['block_id'] = $last_block_id;
		}
		$event_q .= " ORDER BY event_index ASC;";
		$event_arr = $this->blockchain->app->run_query($event_q, $event_params)->fetchAll();
		
		$log_text .= "Set blocks for ".count($event_arr)." events in ".$this->db_game['name'];
		
		if (count($event_arr) > 0) {
			$show_internal_params = false;
			
			if (!$skip_record_migration) {
				list($initial_game_def_hash, $initial_game_def) = GameDefinition::export_game_definition($this, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->blockchain->app, $initial_game_def_hash, $initial_game_def, $this);
			}
			
			$time_to_block_cache = [];
			foreach ($event_arr as $gde) {
				$this->set_gde_blocks_by_time($gde, $time_to_block_cache);
			}
			
			if (!$skip_record_migration) {
				list($final_game_def_hash, $final_game_def) = GameDefinition::export_game_definition($this, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->blockchain->app, $final_game_def_hash, $final_game_def, $this);
				
				GameDefinition::record_migration($this, $user_id, "set_blocks_by_ui", $show_internal_params, $initial_game_def, $final_game_def);
			}
		}
		return $log_text;
	}
	
	public function escrow_value_in_currency($currency_id, $coins_in_existence) {
		$reference_currency = $this->blockchain->app->get_reference_currency();
		
		$total_value = 0;
		
		$escrow_amounts = EscrowAmount::fetch_escrow_amounts_in_game($this, "actual");
		
		$exchange_rate_as_of = null;
		
		while ($escrow_amount = $escrow_amounts->fetch()) {
			$exchange_rate_info = $this->blockchain->app->exchange_rate_between_currencies($currency_id, $escrow_amount['currency_id'], time(), $reference_currency['currency_id']);
			
			if ($exchange_rate_as_of === null || $exchange_rate_info['time'] < $exchange_rate_as_of) $exchange_rate_as_of = $exchange_rate_info['time'];
			
			if ($escrow_amount['escrow_type'] == "fixed") {
				$total_value += $escrow_amount['amount']*$exchange_rate_info['exchange_rate'];
			}
			else {
				$total_value += $escrow_amount['relative_amount']*$coins_in_existence*$exchange_rate_info['exchange_rate'];
			}
		}
		
		$total_value = $this->blockchain->app->to_significant_digits($total_value, 10);
		
		return [$total_value, $exchange_rate_as_of];
	}
	
	public function pending_bets($use_cache) {
		if ($use_cache && (string)$this->db_game['cached_pending_bets'] != "") return $this->db_game['cached_pending_bets'];
		else {
			$pending_bets_q = "SELECT SUM(p.destroy_amount) as destroy_amount";
			
			if ($this->db_game['payout_weight'] != "coin") $pending_bets_q .= ", SUM(p.".$this->db_game['payout_weight']."s_destroyed) as inflation_score";
			
			$pending_bets_q .= " FROM transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND gio.is_resolved=0 AND gio.option_id IS NOT NULL;";
			
			$info = $this->blockchain->app->run_query($pending_bets_q)->fetch();
			
			$pending_bets = $info['destroy_amount'];
			if ($this->db_game['payout_weight'] != "coin") {
				$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
				$pending_bets += round($info['inflation_score']*$coins_per_vote);
			}
			
			$this->blockchain->app->run_query("UPDATE games SET cached_pending_bets='".$pending_bets."' WHERE game_id='".$this->db_game['game_id']."';");
			
			$this->db_game['cached_pending_bets'] = $pending_bets;
			
			return $pending_bets;
		}
	}
	
	public function user_pending_bets(&$user_game) {
		$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
		$info = $this->blockchain->app->run_query("SELECT SUM(p.destroy_amount) as destroy_amount, SUM(p.".$this->db_game['payout_weight']."s_destroyed) as inflation_score, SUM(p.ref_".$this->db_game['payout_weight']."s) as ref_votes FROM transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND gio.is_resolved=0 AND k.account_id=:account_id AND io.spend_status IN ('unspent', 'unconfirmed');", [
			'game_id' => $this->db_game['game_id'],
			'account_id' => $user_game['account_id']
		])->fetch();
		return $info['destroy_amount'] + round(($info['inflation_score']+$info['ref_votes'])*$coins_per_vote);
	}
	
	public function vote_supply(&$last_block_id, &$current_round, &$coins_per_vote, $use_cache) {
		if ($use_cache && (string)$this->db_game['cached_vote_supply'] != "") $vote_supply = $this->db_game['cached_vote_supply'];
		else {
			$info = $this->blockchain->app->run_query("SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount*(:ref_block_id-io.create_block_id)) AS coin_blocks, SUM(gio.colored_amount*(:current_round-gio.create_round_id)) AS coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.spend_status='unspent';", [
				'ref_block_id' => ($last_block_id+1),
				'current_round' => $current_round,
				'game_id' => $this->db_game['game_id']
			])->fetch();
			
			$vote_supply = $info[$this->db_game['payout_weight']."s"];
			$this->blockchain->app->run_query("UPDATE games SET cached_vote_supply=:vote_supply WHERE game_id=:game_id;", [
				'vote_supply' => $vote_supply,
				'game_id' => $this->db_game['game_id']
			]);
			$this->db_game['cached_vote_supply'] = $vote_supply;
		}
		$vote_supply_value = $coins_per_vote*$vote_supply;
		
		return [$vote_supply, $vote_supply_value];
	}
	
	public function destroyed_coins_by_account($account_id) {
		return (int)($this->blockchain->app->run_query("SELECT SUM(gio.destroy_amount) FROM transaction_game_ios gio JOIN address_keys k ON gio.address_id=k.address_id WHERE gio.destroy_amount>0 AND gio.option_id IS NULL AND k.account_id=:account_id AND gio.game_id=:game_id;", [
			'account_id' => $account_id,
			'game_id' => $this->db_game['game_id']
		])->fetch()['SUM(gio.destroy_amount)']);
	}
	
	public function max_gde_starting_block() {
		$info = $this->blockchain->app->run_query("SELECT MAX(event_starting_block) FROM game_defined_events WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id']
		])->fetch();
		if ((string)$info['MAX(event_starting_block)'] != "") return (int)$info['MAX(event_starting_block)'];
		else return (int)$this->db_game['game_starting_block'];
	}
	
	public function max_gde_index() {
		return (string) $this->blockchain->app->run_query("SELECT MAX(event_index) FROM game_defined_events WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id'],
		])->fetch()['MAX(event_index)'];
	}
	
	public function get_game_peer_by_id($game_peer_id) {
		return $this->blockchain->app->run_query("SELECT * FROM peers p JOIN game_peers gp ON p.peer_id=gp.peer_id WHERE gp.game_peer_id=:game_peer_id;", [
			'game_peer_id' => $game_peer_id
		])->fetch();
	}
	
	public function fetch_all_peers() {
		$game_peers = $this->blockchain->app->run_query("SELECT * FROM peers p JOIN game_peers gp ON p.peer_id=gp.peer_id WHERE gp.game_id=:game_id AND gp.disabled_at IS NULL ORDER BY p.peer_id ASC;", [
			'game_id' => $this->db_game['game_id']
		])->fetchAll();
		
		foreach ($game_peers as &$game_peer) {
			$game_peer['in_sync'] = null;
			$game_peer['expired'] = null;
			$game_peer['out_of_sync'] = null;
			$game_peer['never_checked'] = null;
			
			if ($game_peer['last_check_in_sync'] == 1) {
				if ($game_peer['last_sync_check_at'] >= time()-(60*60)) $game_peer['in_sync'] = true;
				else $game_peer['expired'] = true;
			}
			else if ((string)$game_peer['last_check_in_sync'] === "0") $game_peer['out_of_sync'] = true;
			else $game_peer['never_checked'] = true;
		}
		
		return $game_peers;
	}
	
	public function get_definitive_peer() {
		if ($this->definitive_peer) return $this->definitive_peer;
		else {
			if (empty($this->db_game['definitive_game_peer_id'])) return false;
			else {
				$this->definitive_peer = $this->get_game_peer_by_id($this->db_game['definitive_game_peer_id']);
				return $this->definitive_peer;
			}
		}
	}
	
	public function get_game_peer_by_peer($peer) {
		return $this->blockchain->app->run_query("SELECT * FROM game_peers WHERE game_id=:game_id AND peer_id=:peer_id;", [
			'game_id' => $this->db_game['game_id'],
			'peer_id' => $peer['peer_id']
		])->fetch();
	}
	
	public function create_game_peer($peer) {
		$this->blockchain->app->run_insert_query("game_peers", [
			'game_id' => $this->db_game['game_id'],
			'peer_id' => $peer['peer_id']
		]);
		return $this->get_game_peer_by_id($this->blockchain->app->last_insert_id());
	}
	
	public function get_game_peer_by_server_name($server_name) {
		$game_peer = false;
		$peer = $this->blockchain->app->get_peer_by_server_name($server_name, true);
		
		if ($peer) {
			$game_peer = $this->get_game_peer_by_peer($peer);
			
			if (empty($game_peer)) $game_peer = $this->create_game_peer($peer);
		}
		
		return $game_peer;
	}
	
	public function set_cached_fields() {
		$this->coins_in_existence(false, false);
		$this->pending_bets(false);
		$this->blockchain->app->run_query("UPDATE games SET set_cached_fields_at=:set_cached_fields_at WHERE game_id=:game_id;", [
			'set_cached_fields_at' => time(),
			'game_id' => $this->db_game['game_id'],
		]);

		if ($this->db_game['exponential_inflation_rate'] != 0) {
			$last_block_id = $this->blockchain->last_block_id();
			$current_round = $this->block_to_round($last_block_id+1);
			$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
			$this->vote_supply($last_block_id, $current_round, $coins_per_vote, false);
		}

		$this->set_minmax_payout_rates();
	}

	public function set_minmax_payout_rates() {
		$minmax = $this->blockchain->app->run_query("SELECT MIN(payout_rate), MAX(payout_rate) FROM events WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch();
		$this->blockchain->app->run_query("UPDATE games SET min_payout_rate=:min_payout_rate, max_payout_rate=:max_payout_rate WHERE game_id=:game_id;", [
			'min_payout_rate' => $minmax['MIN(payout_rate)'],
			'max_payout_rate' => $minmax['MAX(payout_rate)'],
			'game_id' => $this->db_game['game_id']
		]);
	}
	
	public function display_buyins_by_user_game($user_game_id, $buyin_blockchain) {
		$html = "";
		
		$html .= '<div class="row header_row"><div class="col-sm-3">Amount Deposited</div><div class="col-sm-3">Amount Received</div><div class="col-sm-6">Deposit Address</div></div>'."\n";
		
		$invoices = $this->blockchain->app->run_query("SELECT * FROM currency_invoices i JOIN addresses a ON i.address_id=a.address_id JOIN currencies c ON i.pay_currency_id=c.currency_id JOIN blockchains b ON  c.blockchain_id=b.blockchain_id WHERE i.invoice_type IN ('sale_buyin','join_buyin','buyin') AND i.user_game_id=:user_game_id AND c.blockchain_id=:blockchain_id ORDER BY i.invoice_id DESC;", [
			'user_game_id' => $user_game_id,
			'blockchain_id' => $buyin_blockchain->db_blockchain['blockchain_id'],
		])->fetchAll();
		$num_invoices = count($invoices);
		
		if ($num_invoices == 0) {
			$html .= '<div class="row"><div class="col-sm-12">You don\'t have any buyin addresses yet.</div></div>'."\n";
		}
		else {
			$link_to_addresses = $buyin_blockchain->db_blockchain['sync_mode'] == "full";
			foreach ($invoices as $invoice) {
				$html .= '<div class="row content_row">';
				$html .= '<div class="col-sm-3">';
				$unconfirmed_amount_paid = $buyin_blockchain->total_paid_to_address($invoice, false)/pow(10, $buyin_blockchain->db_blockchain['decimal_places']);
				if ($invoice['confirmed_amount_paid'] == $unconfirmed_amount_paid) $html .= $this->blockchain->app->format_bignum($invoice['confirmed_amount_paid']);
				else $html .= '<font class="text-warning">'.$this->blockchain->app->format_bignum($unconfirmed_amount_paid);
				$html .= ' '.$invoice['coin_name_plural'];
				if ($invoice['confirmed_amount_paid'] != $unconfirmed_amount_paid) $html .= '</font>';
				$html .= '</div>';
				
				$invoice_ios = $this->blockchain->app->invoice_ios_by_invoice($invoice['invoice_id']);
				
				$html .= '<div class="col-sm-3">';
				if (count($invoice_ios) == 0) {
					if ($invoice['confirmed_amount_paid'] == 0) $html .= 'Awaiting&nbsp;Payment';
					else $html .= ucwords($invoice['status']);
				}
				else {
					foreach ($invoice_ios as $invoice_io) {
						$io = $this->blockchain->app->fetch_io_by_hash_out_index($this->blockchain->db_blockchain['blockchain_id'], $invoice_io['tx_hash'], $invoice_io['out_index']);
						$game_amount = $this->game_amount_by_io($io['io_id']);
						if ($link_to_addresses) $html .= '<a target="_blank" href="/explorer/games/'.$this->db_game['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['game_out_index'].'/">';
						$html .= $this->display_coins($game_amount);
						if ($link_to_addresses) $html .= "</a>";
						$html .= "<br/>\n";
					}
				}
				$html .= '</div>';
				
				$html .= '<div class="col-sm-6">';
				if (time() > $invoice['expire_time'] - 3600*2) $html .= '<font class="redtext">Expired</font> &nbsp; ';
				if ($link_to_addresses) $html .= '<a target="_blank" href="/explorer/blockchains/'.$invoice['url_identifier'].'/addresses/'.$invoice['address'].'/">';
				$html .= $invoice['address'];
				if ($link_to_addresses) $html .= '</a>';
				$html .= '</div>';
				$html .= "</div>\n";
			}
		}
		
		return [$num_invoices, $html];
	}
	
	public function display_sellouts_by_user_game($user_game_id) {
		$html = "";
		$html .= '<div class="row header_row"><div class="col-sm-3">Change Amount</div><div class="col-sm-3">Amount Received</div><div class="col-sm-3">Deposit '.ucfirst($this->db_game['coin_name']).' Address</div><div class="col-sm-3">Receive Address</div></div>'."\n";
		
		$invoices = $this->blockchain->app->run_query("SELECT i.*, b.blockchain_id, b.decimal_places, b.url_identifier, b.coin_name_plural, b.sync_mode, a.address AS invoice_address, ra.address AS receiver_address FROM currency_invoices i JOIN addresses a ON i.address_id=a.address_id JOIN currencies c ON i.pay_currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id JOIN addresses ra ON i.receive_address_id=ra.address_id WHERE i.invoice_type='sellout' AND i.user_game_id=:user_game_id ORDER BY i.invoice_id DESC;", ['user_game_id'=>$user_game_id])->fetchAll();
		$num_invoices = count($invoices);
		
		if ($num_invoices == 0) {
			$html .= '<div class="row content_row"><div class="col-sm-12">You don\'t have any withdrawal addresses yet.</div></div>'."\n";
		}
		else {
			foreach ($invoices as $invoice) {
				$html .= '<div class="row content_row">';
				if ($invoice['confirmed_amount_paid'] == 0) $display_amount_sold = $this->blockchain->app->format_bignum($invoice['buyin_amount']);
				else $display_amount_sold = $this->blockchain->app->format_bignum($invoice['confirmed_amount_paid']);
				$html .= '<div class="col-sm-3">'.$display_amount_sold.' '.($display_amount_sold=="1" ? $this->db_game['coin_name'] : $this->db_game['coin_name_plural']).' sold</div>';
				
				$invoice_ios = $this->blockchain->app->invoice_ios_by_invoice($invoice['invoice_id']);
				
				$html .= '<div class="col-sm-3">';
				if (count($invoice_ios) == 0) {
					if ($invoice['confirmed_amount_paid'] == 0) $html .= 'Pending';
					else $html .= ucwords($invoice['status']);
				}
				else {
					foreach ($invoice_ios as $invoice_io) {
						$fetch_io_error = false;
						
						if ($invoice['sync_mode'] == "full") {
							$io = $this->blockchain->app->fetch_io_by_hash_out_index($invoice['blockchain_id'], $invoice_io['tx_hash'], $invoice_io['out_index']);
							
							if ($io) $invoice_io_disp = $this->blockchain->app->format_bignum($io['amount']/pow(10, $invoice['decimal_places']));
							else $fetch_io_error = true;
						}
						else {
							$sellout_blockchain = new Blockchain($this->blockchain->app, $invoice['blockchain_id']);
							
							list($vout_info, $fetch_io_error) = $sellout_blockchain->rpc_load_txo_by_sellout_invoice_io($invoice_io);
							
							if (!$fetch_io_error) $invoice_io_disp = $vout_info['value'];
						}
						
						if (!$fetch_io_error && $invoice['sync_mode'] == "full") $html .= '<a target="_blank" href="/explorer/blockchains/'.$invoice['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['out_index'].'/">';
						
						if ($fetch_io_error) $html .= "Unknown";
						else $html .= $invoice_io_disp." ".($invoice_io_disp=="1" ? $invoice['coin_name'] : $invoice['coin_name_plural']);
						
						if (!$fetch_io_error && $invoice['sync_mode'] == "full") $html .= "</a>";
						$html .= "<br/>\n";
					}
				}
				$html .= '</div>';
				
				$html .= '<div class="col-sm-3" style="overflow: hidden;">';
				if (time() > $invoice['expire_time'] - 3600*2) $html .= '<font class="redtext">Expired</font> &nbsp; ';
				$html .= '<a target="_blank" href="/explorer/games/'.$this->db_game['url_identifier'].'/addresses/'.$invoice['invoice_address'].'/">'.$invoice['invoice_address'].'</a>';
				$html .= '</div>';
				
				$html .= '<div class="col-sm-3" style="overflow: hidden;">';
				if ($invoice['sync_mode'] != "no_db") $html .= '<a target="_blank" href="/explorer/blockchains/'.$invoice['url_identifier'].'/addresses/'.$invoice['receiver_address'].'/">';
				$html .= $invoice['receiver_address'];
				if ($invoice['sync_mode'] != "no_db") $html .= '</a>';
				$html .= "</div>\n";
				
				$html .= "</div>\n";
			}
		}
		
		return [$num_invoices, $html];
	}
	
	public function game_amount_by_io($io_id) {
		return (int)($this->blockchain->app->run_query("SELECT SUM(colored_amount) FROM transaction_game_ios WHERE game_id=:game_id AND io_id=:io_id;", [
			'game_id' => $this->db_game['game_id'],
			'io_id' => $io_id
		])->fetch()['SUM(colored_amount)']);
	}
	
	public function entities_by_game() {
		return $this->blockchain->app->run_query("SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id=:game_id GROUP BY en.entity_id ORDER BY en.entity_id ASC;", ['game_id'=>$this->db_game['game_id']]);
	}
	
	public function fetch_game_ios_by_io($io_id) {
		return $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id WHERE io.io_id=:io_id AND gio.game_id=:game_id ORDER BY gio.game_out_index ASC;", [
			'io_id' => $io_id,
			'game_id' => $this->db_game['game_id']
		]);
	}
	
	public function fetch_event_by_index($event_index) {
		return $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_index=:event_index;", [
			'game_id' => $this->db_game['game_id'],
			'event_index' => $event_index
		])->fetch();
	}
	
	public function fetch_featured_strategies() {
		return $this->blockchain->app->run_query("SELECT * FROM featured_strategies fs LEFT JOIN currency_accounts ca ON fs.reference_account_id=ca.account_id WHERE fs.game_id=:game_id;", [
			'game_id' => $this->db_game['game_id']
		]);
	}
	
	public function event_index_to_affected_block($event_index) {
		$info = $this->blockchain->app->run_query("SELECT MIN(event_starting_block) FROM events WHERE game_id=:game_id AND event_index >= :event_index;", [
			'game_id' => $this->db_game['game_id'],
			'event_index' => $event_index
		])->fetch();
		
		if ($info) {
			$affected_block = $info['MIN(event_starting_block)'];
			if ($affected_block <= $this->blockchain->last_block_id()) return $affected_block;
			else return false;
		}
		else return false;
	}
	
	public function claim_max_from_faucet(&$user_game) {
		$keep_claiming = true;
		$claim_count = 0;
		$max_claims = 100;
		
		do {
			$faucet_io = $this->check_faucet($user_game);
			
			if ($faucet_io) {
				$this->blockchain->app->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
					'account_id' => $user_game['account_id'],
					'address_key_id' => $faucet_io['address_key_id']
				]);
				$this->blockchain->app->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
					'user_id' => $user_game['user_id'],
					'address_id' => $faucet_io['address_id']
				]);
				$this->blockchain->app->run_query("UPDATE user_games SET faucet_claims=faucet_claims+1, latest_claim_time=:latest_claim_time WHERE user_game_id=:user_game_id;", [
					'user_game_id' => $user_game['user_game_id'],
					'latest_claim_time' => time()
				]);
				
				$claim_count++;
				
				if ($claim_count >= $max_claims) $keep_claiming = false;
			}
			else $keep_claiming = false;
		}
		while ($keep_claiming);
		
		return $claim_count;
	}
	
	public function set_target_scores_at_block($block_id) {
		$starting_events = $this->events_by_starting_block($block_id);
		
		foreach ($starting_events as $starting_event) {
			if ($starting_event->db_event['option_block_rule'] == "basketball_game") {
				$starting_event->set_target_scores($block_id);
			}
		}
	}
	
	public function my_bets_in_event($event_id, $account_id, $confirmed) {
		$my_bets_q = "SELECT p.*, p.contract_parts AS total_contract_parts, gio.contract_parts, gio.is_game_coinbase, gio.game_out_index AS game_out_index, op.*, ev.*, p.votes, op.votes AS option_votes, op.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, t.transaction_id, t.tx_hash, t.fee_amount, io.spend_status FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN options op ON gio.option_id=op.option_id JOIN events ev ON op.event_id=ev.event_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.event_id=:event_id AND k.account_id=:account_id AND gio.resolved_before_spent=1";
		
		if ($confirmed) $my_bets_q .= " AND io.create_block_id IS NOT NULL";
		else $my_bets_q .= " AND io.create_block_id IS NULL";
		
		$my_bets_q .= " ORDER BY op.event_option_index ASC;";
		
		return $this->blockchain->app->run_query($my_bets_q, [
			'event_id' => $event_id,
			'account_id' => $account_id
		])->fetchAll();
	}
	
	public function allow_game_def_changes() {
		if ($this->db_game['finite_events']) return true;
		else return false;
	}
	
	public function fetch_unclaimed_coins($unspent_only) {
		$unclaimed_q = "SELECT gio.* FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id AND gio.game_id=:game_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN address_keys ak ON io.address_id=ak.address_id WHERE t.blockchain_id=:blockchain_id AND ak.account_id IS NULL";
		
		$unclaimed_params = [
			'blockchain_id' => $this->db_game['blockchain_id'],
			'game_id' => $this->db_game['game_id'],
		];
		
		if ($unspent_only) {
			$unclaimed_q .= " AND io.spend_status='unspent'";
		}
		
		$unclaimed_q .= " ORDER BY t.block_id ASC";
		
		return $this->blockchain->app->run_query($unclaimed_q, $unclaimed_params)->fetchAll();
	}
	
	public function fetch_events_by_entity_and_season($entity_id, $season_index, $event_starting_block) {
		$events = $this->blockchain->app->run_query("SELECT ev.*, w.entity_id AS winning_entity_id FROM events ev JOIN options op ON ev.event_id=op.event_id JOIN options w ON ev.winning_option_id=w.option_id WHERE ev.game_id=:game_id AND ev.season_index=:season_index AND op.entity_id=:entity_id AND ev.event_determined_to_block<:event_starting_block GROUP BY ev.event_id ORDER BY ev.event_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'season_index' => $season_index,
			'entity_id' => $entity_id,
			'event_starting_block' => $event_starting_block,
		])->fetchAll(PDO::FETCH_ASSOC);
		
		$events_by_id = [];
		
		foreach ($events as $event) {
			$events_by_id[$event['event_id']] = $event;
		}
		
		$options = $this->blockchain->app->run_query("SELECT op.* FROM events ev JOIN options eop ON ev.event_id=eop.event_id JOIN options op ON op.event_id=ev.event_id WHERE ev.game_id=:game_id AND ev.season_index=:season_index AND eop.entity_id=:entity_id AND ev.event_determined_to_block<:event_starting_block ORDER BY ev.event_index ASC, op.event_option_index ASC;", [
			'game_id' => $this->db_game['game_id'],
			'season_index' => $season_index,
			'entity_id' => $entity_id,
			'event_starting_block' => $event_starting_block,
		])->fetchAll(PDO::FETCH_ASSOC);
		
		foreach ($options as $option) {
			if (empty($events_by_id[$option['event_id']]['options'])) {
				$events_by_id[$option['event_id']]['options'] = [];
			}
			$events_by_id[$option['event_id']]['options'][$option['option_id']] = $option;
		}
		
		return $events_by_id;
	}
	
	public function make_auto_donations($print_debug=false) {
		$donate_from_accounts = $this->blockchain->app->run_query("SELECT * FROM currency_accounts ca WHERE ca.game_id=:game_id AND faucet_donations_on=1;", [
			'game_id' => $this->db_game['game_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
		
		$faucet_account = $this->check_set_faucet_account();
		
		if ($print_debug) $this->blockchain->app->print_debug("Checking ".count($donate_from_accounts)." donating accounts in ".$this->db_game['name'].".");
		
		foreach ($donate_from_accounts as $donate_from_account) {
			$faucet_balance_int = $this->account_balance($faucet_account['account_id']);
			$faucet_balance_float = $faucet_balance_int/pow(10, $this->db_game['decimal_places']);
			
			if ($print_debug) $this->blockchain->app->print_debug("Account #".$donate_from_account['account_id'].", current balance of ".$faucet_balance_float." vs target of ".$donate_from_account['faucet_target_balance']);
			
			if ($faucet_balance_float < 0.9*$donate_from_account['faucet_target_balance']) {
				$quantity_donations = floor(($donate_from_account['faucet_target_balance'] - $faucet_balance_float)/$donate_from_account['faucet_amount_each']);
				
				if ($quantity_donations > 0) {
					if ($print_debug) $this->blockchain->app->print_debug("Making ".$quantity_donations." donations of ".$donate_from_account['faucet_amount_each']);
					
					$user_game = $this->blockchain->app->fetch_user_game_by_account_id($donate_from_account['account_id']);
					$strategy = $this->blockchain->app->fetch_strategy_by_id($user_game['strategy_id']);
					
					$game_cost_int = $quantity_donations*$donate_from_account['faucet_amount_each']*pow(10, $this->db_game['decimal_places']);
					$fee_int = $strategy['transaction_fee']*pow(10, $this->blockchain->db_blockchain['decimal_places']);
					
					$spendable_ios = $this->blockchain->app->spendable_ios_in_account($donate_from_account['account_id'], $this->db_game['game_id'], false, false);
					
					$gio_input_sum = 0;
					$io_input_sum = 0;
					$spend_io_ids = [];
					$input_io_pos = 0;
					
					while ($input_io_pos < count($spendable_ios) && $gio_input_sum < 1.1*$game_cost_int) {
						array_push($spend_io_ids, $spendable_ios[$input_io_pos]['io_id']);
						$gio_input_sum += $spendable_ios[$input_io_pos]['coins'];
						$io_input_sum += $spendable_ios[$input_io_pos]['amount'];
						$input_io_pos++;
					}
					
					$gio_per_io = $gio_input_sum/($io_input_sum-$fee_int);
					$io_amount_per_donation = ceil($donate_from_account['faucet_amount_each']*pow(10, $this->db_game['decimal_places'])/$gio_per_io);
					$amounts = [];
					$io_output_sum = 0;
					$to_address_ids = [];
					
					for ($i=0; $i<$quantity_donations; $i++) {
						$address_key = $this->blockchain->app->new_normal_address_key($faucet_account['currency_id'], $faucet_account);
						array_push($amounts, $io_amount_per_donation);
						array_push($to_address_ids, $address_key['address_id']);
						$io_output_sum += $io_amount_per_donation;
					}
					
					$io_leftover_amount = $io_input_sum-$io_output_sum-$fee_int;
					
					if ($io_leftover_amount > 0) {
						$address_key = $this->blockchain->app->new_normal_address_key($donate_from_account['currency_id'], $donate_from_account);
						array_push($amounts, $io_leftover_amount);
						array_push($to_address_ids, $address_key['address_id']);
						$io_output_sum += $io_leftover_amount;
					}
					
					$io_output_sum += $fee_int;
					
					if ($io_output_sum == $io_input_sum) {
						$error_message = null;
						$transaction_id = $this->blockchain->create_transaction('transaction', $amounts, false, $spend_io_ids, $to_address_ids, $fee_int, $error_message);
						
						if ($transaction_id) {
							$transaction = $this->blockchain->app->fetch_transaction_by_id($transaction_id);
							$this->blockchain->app->print_debug("Successfully donated to the faucet with tx ".$transaction['tx_hash']);
						}
						else if ($print_debug) $this->blockchain->app->print_debug("Donation failed: ".$error_message);
					}
					else if ($print_debug) $this->blockchain->app->print_debug("Account balance is too low to donate to the faucet.");
				}
			}
		}
	}
	
	public function fetch_game_io_by_index($game_io_index) {
		return $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index=:game_io_index;", [
			'game_id' => $this->db_game['game_id'],
			'game_io_index' => $game_io_index,
		])->fetch();
	}
	
	public function min_unpaid_block($last_block_id) {
		$info = $this->blockchain->app->run_query("SELECT MIN(event_starting_block) AS min_unpaid_block FROM events WHERE game_id=:game_id AND event_payout_block>=:last_block_id;", [
			'game_id' => $this->db_game['game_id'],
			'last_block_id' => $last_block_id,
		])->fetch(PDO::FETCH_ASSOC);
		
		if (empty($info['min_unpaid_block'])) return null;
		else return ((int) $info['min_unpaid_block']);
	}
	
	public function checksum_partition_to_txo_index($partition, $txos_per_partition) {
		return ($partition+1)*$txos_per_partition-1;
	}
	
	public function txo_pos_to_max_set_partition($txo_pos, $txos_per_partition) {
		if ($txo_pos+1 < $txos_per_partition) return null;
		else return (floor(($txo_pos+1)/$txos_per_partition)-1);
	}
	
	public function find_peer_out_of_sync_partition($game_peer, $from_partition, $to_partition, $txos_per_partition, $print_debug) {
		if ($print_debug) $this->blockchain->app->print_debug("Finding out of sync partition from ".$from_partition.":".$to_partition);
		
		if ($to_partition-$from_partition <= 1) {
			$from_txo_pos = $this->checksum_partition_to_txo_index($from_partition, $txos_per_partition);
			$from_partition_peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txo_checksum/".$this->db_game['url_identifier']."/".$from_txo_pos);
			$check_from_txo = $this->fetch_game_io_by_index($from_txo_pos);
			
			$to_txo_pos = $this->checksum_partition_to_txo_index($to_partition, $txos_per_partition);
			$to_partition_peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txo_checksum/".$this->db_game['url_identifier']."/".$to_txo_pos);
			$check_to_txo = $this->fetch_game_io_by_index($to_txo_pos);
			
			if (empty($check_from_txo) || empty($check_to_txo) || empty($from_partition_peer_response['status_code']) || empty($to_partition_peer_response['status_code']) || $from_partition_peer_response['status_code'] != 1 || $to_partition_peer_response['status_code'] != 1) return null;
			else {
				if ($from_partition_peer_response['checksum'] != $check_from_txo['partition_checksum']) return $from_partition;
				else if ($to_partition_peer_response['checksum'] != $check_to_txo['partition_checksum']) return $to_partition;
				else return false;
			}
		}
		else {
			$mid_partition = floor(($from_partition+$to_partition)/2);
			
			$mid_txo_pos = $this->checksum_partition_to_txo_index($mid_partition, $txos_per_partition);
			$mid_partition_peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txo_checksum/".$this->db_game['url_identifier']."/".$mid_txo_pos);
			$check_mid_txo = $this->fetch_game_io_by_index($mid_txo_pos);
			
			if (empty($check_mid_txo) || empty($mid_partition_peer_response) || $mid_partition_peer_response['status_code'] != 1) return null;
			else {
				if ($print_debug) $this->blockchain->app->print_debug("Partition ".$mid_partition.", peer: ".$mid_partition_peer_response['checksum']." vs local: ".$check_mid_txo['partition_checksum']);
				
				if ($mid_partition_peer_response['checksum'] != $check_mid_txo['partition_checksum']) {
					$lower_out_of_sync_partition = $this->find_peer_out_of_sync_partition($game_peer, $from_partition, $mid_partition-1, $txos_per_partition, $print_debug);
					if ($lower_out_of_sync_partition === null) return null;
					else if ($lower_out_of_sync_partition === false) return $mid_partition;
					else return $lower_out_of_sync_partition;
				}
				else {
					return $this->find_peer_out_of_sync_partition($game_peer, $mid_partition+1, $to_partition, $txos_per_partition, $print_debug);
				}
			}
		}
	}
	
	public function max_set_checksum_partition($lower_partition, $upper_partition, $txos_per_partition, $print_debug=false) {
		if ($print_debug) $this->blockchain->app->print_debug("Checking ".$lower_partition."(".$this->checksum_partition_to_txo_index($lower_partition, $txos_per_partition).") to ".$upper_partition."(".$this->checksum_partition_to_txo_index($upper_partition, $txos_per_partition).")");
		
		if ($upper_partition-$lower_partition <= 1) {
			$lower_gio = $this->fetch_game_io_by_index($this->checksum_partition_to_txo_index($lower_partition, $txos_per_partition));
			$upper_gio = $this->fetch_game_io_by_index($this->checksum_partition_to_txo_index($upper_partition, $txos_per_partition));
			
			if (!empty($upper_gio['partition_checksum'])) return $upper_partition;
			else if (!empty($lower_gio['partition_checksum'])) return $lower_partition;
			else return null;
		}
		else {
			$mid_partition = floor(($upper_partition+$lower_partition)/2);
			$mid_gio = $this->fetch_game_io_by_index($this->checksum_partition_to_txo_index($mid_partition, $txos_per_partition));
			
			if ($print_debug) $this->blockchain->app->print_debug("Checking midpoint ".$mid_partition."(".$this->checksum_partition_to_txo_index($mid_partition, $txos_per_partition).")");
			
			if (!empty($mid_gio['partition_checksum'])) {
				$max_partition = $this->max_set_checksum_partition($mid_partition+1, $upper_partition, $txos_per_partition, $print_debug);
				if ($max_partition == null) return $mid_partition;
				else return $max_partition;
			}
			else {
				return $this->max_set_checksum_partition($lower_partition, $mid_partition-1, $txos_per_partition, $print_debug);
			}
		}
	}
	
	public function set_partition_checksum($partition, $txos_per_partition, $print_debug=false) {
		$start_time = microtime(true);
		
		$any_error = false;
		$error_message = null;
		$checksum = null;
		
		$to_txo_index = $this->checksum_partition_to_txo_index($partition, $txos_per_partition);
		$from_txo_index = $to_txo_index-$txos_per_partition+1;
		
		$txos = PeerVerifier::fetchTxosByIndex($this, $from_txo_index, $to_txo_index, true);
		
		if ($print_debug) $this->blockchain->app->print_debug("Fetch took ".round(microtime(true)-$start_time, 4)." sec");
		$ref_time = microtime(true);
		
		if ($partition == 0) $previous_checksum = null;
		else {
			$prev_partition_txo_index = $this->checksum_partition_to_txo_index($partition-1, $txos_per_partition);
			$prev_partition_txo = $this->fetch_game_io_by_index($prev_partition_txo_index);
			if ($prev_partition_txo) $previous_checksum = $prev_partition_txo['partition_checksum'];
			else {
				$any_error = true;
				$error_message = "Failed to fetch TXO #".$prev_partition_txo_index.", cannot set partition checksums.";
				return [$any_error, $error_message, $checksum];
			}
		}
		
		$checksum = hash("sha256", json_encode([
			'previous_checksum' => $previous_checksum,
			'txos' => $txos,
		], JSON_PRETTY_PRINT));
		
		if ($print_debug) $this->blockchain->app->print_debug("Checksum generation took ".round(microtime(true)-$ref_time, 4)." sec");
		$ref_time = microtime(true);
		
		$this->blockchain->app->run_query("UPDATE transaction_game_ios SET partition_checksum=:partition_checksum WHERE game_id=:game_id AND game_io_index=:to_txo_index;", [
			'game_id' => $this->db_game['game_id'],
			'partition_checksum' => $checksum,
			'to_txo_index' => $to_txo_index,
		]);
		
		if ($print_debug) $this->blockchain->app->print_debug("Update took ".round(microtime(true)-$ref_time, 4)." sec");
		
		return [$any_error, $error_message, $checksum];
	}
	
	public function find_peer_out_of_sync_block_by_array_scan($peer_txos, $from_txo_pos, $to_txo_pos) {
		$txos = PeerVerifier::fetchTxosByIndex($this, $from_txo_pos, $to_txo_pos);
		
		for ($txo_pos=$from_txo_pos; $txo_pos<=$to_txo_pos; $txo_pos++) {
			if (empty($txos[$txo_pos]) || empty($peer_txos[$txo_pos])) {
				if (empty($txos[$txo_pos]) && empty($peer_txos[$txo_pos])) {
					$check_txo_pos = $txo_pos-1;
					do {
						if ($check_txo_pos < 0) return $this->db_game['game_starting_block'];
						$check_txo = $this->fetch_game_io_by_index($check_txo_pos);
						if ($check_txo) return [$check_txo['create_block_id'], $check_txo_pos];
						else $check_txo_pos--;
					}
					while (true);
				}
				else if (empty($txos[$txo_pos])) return [$peer_txos[$txo_pos][2], $txo_pos];
				else if (empty($peer_txos[$txo_pos])) return [$txos[$txo_pos][2], $txo_pos];
			}
			else if (json_encode($peer_txos[$txo_pos]) != json_encode($txos[$txo_pos])) {
				return [min($peer_txos[$txo_pos][2], $txos[$txo_pos][2]), $txo_pos];
			}
		}
		
		return [false, false];
	}
	
	public function set_partition_checksums($txos_per_partition, $print_debug) {
		$any_error = false;
		$error_message = null;
		
		$last_block_id = $this->blockchain->last_block_id();
		$last_block = $this->fetch_game_block_by_height($last_block_id);
		$max_game_io_index = $last_block['max_game_io_index'];
		$min_unpaid_block = $this->min_unpaid_block($last_block_id);
		
		if (!empty($min_unpaid_block)) {
			$max_paid_block = $this->fetch_game_block_by_height($min_unpaid_block-1);
			
			if ($max_paid_block && !empty($max_paid_block['max_game_io_index'])) {
				$to_partition = $this->txo_pos_to_max_set_partition($max_paid_block['max_game_io_index'], $txos_per_partition);

				if ($print_debug) $this->blockchain->app->print_debug("Max paid block is ".$max_paid_block['block_id'].", setting checksums to TXO ".$max_paid_block['max_game_io_index']." (partition ".$to_partition.")");
				
				$max_set_checksum_partition = $this->max_set_checksum_partition(0, $to_partition, $txos_per_partition, false);
				
				if ($max_set_checksum_partition === null) $from_partition=0;
				else $from_partition=$max_set_checksum_partition+1;
				
				$num_checksums_to_set = $to_partition-$from_partition+1;
				
				if ($print_debug) $this->blockchain->app->print_debug("Setting checksums for ".$num_checksums_to_set." partitions ".$from_partition."(".$this->checksum_partition_to_txo_index($from_partition, $txos_per_partition)."):".$to_partition."(".$this->checksum_partition_to_txo_index($to_partition, $txos_per_partition).")");
				
				$set_checksums_start_time = microtime(true);
				$last_debug_output_time = microtime(true);
				$last_debug_output_partition = $from_partition-1;
				$sec_per_debug_output = 1;
				
				for ($partition=$from_partition; $partition <= $to_partition; $partition++) {
					list($set_partition_error, $set_partition_error_message, $checksum) = $this->set_partition_checksum($partition, $txos_per_partition, false);
					
					if ($set_partition_error) {
						$any_error = $set_partition_error;
						$error_message = $set_partition_error_message;
						$partition = $to_partition;
					}
					
					if ($print_debug && microtime(true)-$last_debug_output_time >= $sec_per_debug_output) {
						$checksums_remaining = $to_partition-$partition;
						$sec_per_checksum = (microtime(true)-$set_checksums_start_time)/($partition-$from_partition+1);
						$est_sec_remaining = $sec_per_checksum*($to_partition-$partition);
						$this->blockchain->app->print_debug("Set ".($partition-$last_debug_output_partition)." checksums in ".round(microtime(true)-$last_debug_output_time, 4)." sec, ".$this->blockchain->app->format_seconds($est_sec_remaining)." remaining");
						$last_debug_output_time = microtime(true);
						$last_debug_output_partition = $partition;
					}
				}
				if ($print_debug) $this->blockchain->app->print_debug("Set ".$num_checksums_to_set." checksums in ".round(microtime(true)-$set_checksums_start_time, 4)." sec");
			}
			else $error_message = "No partition checksums were set. Max paid block #".($min_unpaid_block-1)." could not be fetched.";
		}
		else $error_message = "No partition checksums needed to be set because there was no min unpaid block.";
		
		return [$any_error, $error_message];
	}
	
	public function peer_out_of_sync_block($game_peer, $txos_per_partition, $last_block, $print_debug) {
		$min_unpaid_block = $this->min_unpaid_block($last_block['block_id']);
		
		$max_set_checksum_partition = null;
		$peer_missing_checksum = false;
		
		if ($min_unpaid_block !== null) {
			$max_paid_block = $this->fetch_game_block_by_height($min_unpaid_block-1);
			
			if ($max_paid_block && !empty($max_paid_block['max_game_io_index'])) {
				$to_partition = $this->txo_pos_to_max_set_partition($max_paid_block['max_game_io_index'], $txos_per_partition);
				
				$max_set_checksum_partition = $this->max_set_checksum_partition(0, $to_partition, $txos_per_partition, false);
				
				if ($max_set_checksum_partition !== null) {
					$txo_pos = $this->checksum_partition_to_txo_index($max_set_checksum_partition, $txos_per_partition);
					
					if ($print_debug) $this->blockchain->app->print_debug("Checking partition ".$max_set_checksum_partition."(TXO ".$txo_pos.")");
					
					$peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txo_checksum/".$this->db_game['url_identifier']."/".$txo_pos);
					
					if (!empty($peer_response['checksum'])) {
						$check_txo = $this->fetch_game_io_by_index($txo_pos);
						
						if ($check_txo['partition_checksum'] == $peer_response['checksum']) $checksum_section_ok = true;
						else $checksum_section_ok = false;
					}
					else {
						if ($print_debug) $this->blockchain->app->print_debug("Peer is missing checksum for TXO #".$txo_pos.", skipping sync check.");
						return [null, null];
					}
				}
				else $checksum_section_ok = true;
			}
			else $checksum_section_ok = true;
		}
		else $checksum_section_ok = true;
		
		$out_of_sync_block = null;
		$out_of_sync_txo_pos = null;
		
		if ($checksum_section_ok) {
			$array_scan_from_txo_pos = $max_set_checksum_partition === null ? 0 : $this->checksum_partition_to_txo_index($max_set_checksum_partition, $txos_per_partition)+1;
			$array_scan_to_txo_pos = $last_block['max_game_io_index'];
			
			if ($array_scan_to_txo_pos >= $array_scan_from_txo_pos) {
				if ($print_debug) $this->blockchain->app->print_debug("No errors in checksum section, running array scan on TXOs ".$array_scan_from_txo_pos.":".$array_scan_to_txo_pos);
				
				$peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txos_by_index/".$this->db_game['url_identifier']."/".$array_scan_from_txo_pos.":".$array_scan_to_txo_pos);
				
				if ($peer_response['status_code'] == 1) {
					list($out_of_sync_block, $out_of_sync_txo_pos) = $this->find_peer_out_of_sync_block_by_array_scan($peer_response['txos'], $array_scan_from_txo_pos, $array_scan_to_txo_pos);
					
					if ($print_debug) $this->blockchain->app->print_debug("Array scan found out of sync block ".json_encode($out_of_sync_block)." and txo pos: ".json_encode($out_of_sync_txo_pos));
				}
				else if ($print_debug) $this->blockchain->app->print_debug("Failed to fetch TXOs from peer.");
			}
			else {
				$out_of_sync_block = false;
				if ($print_debug) $this->blockchain->app->print_debug("No array scan needed for TXOs ".$array_scan_from_txo_pos.":".$array_scan_to_txo_pos);
			}
		}
		else {
			if ($print_debug) $this->blockchain->app->print_debug("Error identified in checksum section, finding out-of-sync block on partitions 0:".$max_set_checksum_partition);
			
			$out_of_sync_partition = $this->find_peer_out_of_sync_partition($game_peer, 0, $max_set_checksum_partition, $txos_per_partition, $print_debug);
			
			if ($out_of_sync_partition === null) $out_of_sync_block = null;
			else if ($out_of_sync_partition === false) $out_of_sync_block = false;
			else {
				$array_scan_to_txo_pos = $this->checksum_partition_to_txo_index($out_of_sync_partition, $txos_per_partition);
				$array_scan_from_txo_pos = $array_scan_to_txo_pos - $txos_per_partition + 1;
				
				$peer_response = PeerVerifier::peerApiCall($game_peer, "/api/txos_by_index/".$this->db_game['url_identifier']."/".$array_scan_from_txo_pos.":".$array_scan_to_txo_pos);
				
				if (!empty($peer_response['status_code']) && $peer_response['status_code'] == 1) {
					list($out_of_sync_block, $out_of_sync_txo_pos) = $this->find_peer_out_of_sync_block_by_array_scan($peer_response['txos'], $array_scan_from_txo_pos, $array_scan_to_txo_pos);
					
					if ($print_debug) $this->blockchain->app->print_debug("Out of sync from block ".json_encode($out_of_sync_block)." and txo pos: ".json_encode($out_of_sync_txo_pos));
				}
				else $out_of_sync_block = null;
			}
			
			if ($print_debug) $this->blockchain->app->print_debug("Out of sync partition: ".json_encode($out_of_sync_partition));
		}
		
		if ($print_debug) $this->blockchain->app->print_debug("Out of sync block: ".json_encode($out_of_sync_block));
		
		return [$out_of_sync_block, $out_of_sync_txo_pos];
	}
	
	public function reset_partition_checksums($print_debug) {
		if ($print_debug) $this->blockchain->app->print_debug("Clearing partition checksums for ".$this->db_game['name']);
		
		$this->blockchain->app->run_query("UPDATE transaction_game_ios SET partition_checksum=NULL WHERE game_id=:game_id AND partition_checksum IS NOT NULL;", [
			'game_id' => $this->db_game['game_id'],
		]);
		
		$this->blockchain->app->run_query("UPDATE games SET last_reset_checksums_at=:time WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id'],
			'time' => time(),
		]);
	}
	
	public function lock_game_definition() {
		$this->blockchain->app->set_site_constant("game_definition_locked_".$this->db_game['game_id'], time());
	}
	
	public function unlock_game_definition() {
		$this->blockchain->app->set_site_constant("game_definition_locked_".$this->db_game['game_id'], 0);
	}
	
	public function game_definition_is_locked() {
		$lock_time = (int) $this->blockchain->app->get_site_constant("game_definition_locked_".$this->db_game['game_id']);
		
		if ($lock_time == 0 || $lock_time < time()-(60*10)) return false;
		else return true;
	}
}
?>
