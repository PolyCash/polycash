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
			$this->module = new $module_class($this->blockchain->app);
		}
	}
	
	public function update_db_game() {
		$this->db_game = $this->blockchain->app->run_query("SELECT g.*, b.p2p_mode, b.coin_name AS base_coin_name, b.coin_name_plural AS base_coin_name_plural FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_id=:game_id;", ['game_id'=>$this->game_id])->fetch();
		
		if (!$this->db_game) throw new Exception("Error, could not load game #".$this->game_id);
	}
	
	public static function create_game(&$blockchain, $params) {
		$params['blockchain_id'] = $blockchain->db_blockchain['blockchain_id'];
		$new_game_q = "INSERT INTO games SET ";
		foreach ($params as $var => $val) {
			$new_game_q .= $var."=:".$var.", ";
		}
		$new_game_q = substr($new_game_q, 0, -2).";";
		$blockchain->app->run_query($new_game_q, $params);
		$game_id = $blockchain->app->last_insert_id();
		
		return new Game($blockchain, $game_id);
	}
	
	public function block_to_round($mining_block_id) {
		return ceil($mining_block_id/$this->db_game['round_length']);
	}
	
	public function create_transaction($option_ids, $amounts, $user_game, $block_id, $type, $io_ids, $address_ids, $remainder_address_id, $transaction_fee, &$error_message) {
		if (!$type || $type == "") $type = "transaction";
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			$amount += $amounts[$i];
		}
		
		if ($user_game) {
			$from_user = new User($this->blockchain->app, $user_game['user_id']);
			$account_value = $this->account_balance($user_game['account_id']);
			$immature_balance = $from_user->immature_balance($this, $user_game);
			$mature_balance = $from_user->mature_balance($this, $user_game);
		}
		else {
			$from_user = false;
			$account_value = 0;
			$immature_balance = 0;
			$mature_balance = 0;
		}
		
		$utxo_balance = false;
		if ($io_ids) {
			$utxo_balance = $this->blockchain->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", array_map("intval", $io_ids)).");")->fetch(PDO::FETCH_NUM)[0];
		}
		
		$raw_txin = [];
		$raw_txout = [];
		$affected_input_ids = [];
		$created_input_ids = [];
		
		if ($type == "coinbase") $amount_ok = true;
		else if ($utxo_balance == $amount || (!$io_ids && $amount <= $mature_balance)) $amount_ok = true;
		else $amount_ok = false;
		
		if ($amount_ok && (($option_ids === false && count($amounts) == count($address_ids)) || count($option_ids) == count($amounts))) {
			// For rpc games, don't insert a tx record, it will come in via walletnotify
			if ($this->blockchain->db_blockchain['p2p_mode'] != "rpc") {
				$new_tx_hash = $this->blockchain->app->random_string(64);
				$new_tx_params = [
					'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
					'fee_amount' => $transaction_fee,
					'num_inputs' => count($io_ids),
					'num_outputs' => count($amounts),
					'tx_hash' => $new_tx_hash,
					'transaction_desc' => $type,
					'amount' => $amount,
					'time_created' => time()
				];
				$new_tx_q = "INSERT INTO transactions SET blockchain_id=:blockchain_id, fee_amount=:fee_amount, has_all_inputs=1, has_all_outputs=1, num_inputs=:num_inputs, num_outputs=:num_outputs, tx_hash=:tx_hash, transaction_desc=:transaction_desc, amount=:amount";
				if ($block_id !== false) {
					$new_tx_q .= ", block_id=:block_id, round_id=:round_id";
					$new_tx_params['block_id'] = $block_id;
					$new_tx_params['round_id'] = $this->block_to_round($block_id);
				}
				$new_tx_q .= ", time_created=:time_created;";
				$this->blockchain->app->run_query($new_tx_q, $new_tx_params);
				$transaction_id = $this->blockchain->app->last_insert_id();
			}
			
			$input_sum = 0;
			$remainder_amount = 0;
			
			if ($type == "coinbase") {}
			else {
				$tx_inputs_params = [
					'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id']
				];
				$tx_inputs_q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status IN ('unspent','unconfirmed') AND io.blockchain_id=:blockchain_id";
				if ($this->db_game['maturity'] > 0) {
					$tx_inputs_q .= " AND io.create_block_id <= :ref_block_id";
					$tx_inputs_params['ref_block_id'] = $this->blockchain->last_block_id()-$this->db_game['maturity'];
				}
				if ($io_ids) {
					$tx_inputs_q .= " AND io.io_id IN (".implode(",", array_map("intval", $io_ids)).")";
				}
				else {
					$tx_inputs_q .= " AND gio.game_io_id IN (".implode(",", array_map("intval", $this->mature_io_ids_csv($user_game))).")";
				}
				$tx_inputs_q .= " GROUP BY io.io_id ORDER BY io.amount ASC;";
				$tx_inputs = $this->blockchain->app->run_query($tx_inputs_q, $tx_inputs_params);
				
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				$ref_block_id = $this->blockchain->last_block_id()+1;
				$ref_cbd = 0;
				
				while ($transaction_input = $tx_inputs->fetch()) {
					if ($input_sum < $amount) {
						if ($this->blockchain->db_blockchain['p2p_mode'] != "rpc") {
							$update_input_params = [
								'transaction_id' => $transaction_id,
								'io_id' => $transaction_input['io_id']
							];
							$update_input_q = "UPDATE transaction_ios SET spend_transaction_id=:transaction_id";
							if ($block_id !== false) {
								$update_input_q .= ", spend_status='spent', spend_block_id=:spend_block_id";
								$update_input_params['spend_block_id'] = $block_id;
							}
							$update_input_q .= " WHERE io_id=:io_id;";
							$this->blockchain->app->run_query($update_input_q, $update_input_params);
						}
						
						$input_sum += $transaction_input['amount'];
						$ref_cbd += ($ref_block_id-$transaction_input['create_block_id'])*$transaction_input['amount'];
						
						if ($block_id !== false) {
							$coin_blocks_destroyed += ($block_id - $transaction_input['create_block_id'])*$transaction_input['amount'];
						}
						
						$affected_input_ids[count($affected_input_ids)] = $transaction_input['io_id'];
						
						$raw_txin[count($raw_txin)] = array(
							"txid"=>$transaction_input['tx_hash'],
							"vout"=>intval($transaction_input['out_index'])
						);
					}
				}
				
				$remainder_amount = $input_sum - $amount;
			}
			
			$output_error = false;
			$out_index = 0;
			$first_passthrough_index = false;
			
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					if ($address_ids) {
						if (count($address_ids) == count($amounts)) $address_id = $address_ids[$out_index];
						else $address_id = $address_ids[0];
						$address = $this->blockchain->app->fetch_address_by_id($address_id);
					}
					else {
						$db_option = $this->blockchain->app->fetch_option_by_id($option_ids[$out_index]);
						$address = $this->blockchain->app->fetch_addresses_in_account($user_game, $db_option['option_index'], 1)[0];
					}
					
					if ($address) {
						if ($first_passthrough_index == false && $address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
						
						if ($this->blockchain->db_blockchain['p2p_mode'] != "rpc") {
							$new_output_params = [
								'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
								'out_index' => $out_index,
								'is_destroy' => $address['is_destroy_address'],
								'is_separator' => $address['is_separator_address'],
								'is_passthrough' => $address['is_passthrough_address'],
								'is_receiver' => ($first_passthrough_index !== false && $address['is_destroy_address']+$address['is_separator_address']+$address['is_passthrough_address'] == 0) ? 1 : 0,
								'address_id' => $address_id,
								'option_index' => $address['option_index'],
								'transaction_id' => $transaction_id,
								'amount' => $amounts[$out_index]
							];
							$new_output_q = "INSERT INTO transaction_ios SET blockchain_id=:blockchain_id, script_type='pubkeyhash', spend_status='unconfirmed', out_index=:out_index, ";
							if (!empty($address['user_id'])) {
								$new_output_q .= "user_id=:user_id, ";
								$new_output_params['user_id'] = $address['user_id'];
							}
							$new_output_q .= "is_destroy=:is_destroy, is_separator=:is_separator, is_passthrough=:is_passthrough, is_receiver=:is_receiver, address_id=:address_id, option_index=:option_index, ";
							
							if ($block_id !== false) {
								if ($input_sum == 0) $output_cbd = 0;
								else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								
								if ($input_sum == 0) $output_crd = 0;
								else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								
								$new_output_q .= "coin_blocks_destroyed=:output_cbd, coin_rounds_destroyed=:output_crd, ";
								$new_output_params['output_cbd'] = $output_cbd;
								$new_output_params['output_crd'] = $output_crd;
							}
							if ($block_id !== false) {
								$new_output_q .= "create_block_id=:block_id, create_round_id=:round_id, ";
								$new_output_params['block_id'] = $block_id;
								$new_output_params['round_id'] = $this->block_to_round($block_id);
							}
							$new_output_q .= "create_transaction_id=:transaction_id, amount=:amount;";
							
							$this->blockchain->app->run_query($new_output_q, $new_output_params);
							$created_input_ids[count($created_input_ids)] = $this->blockchain->app->last_insert_id();
						}
						
						$raw_txout[$address['address']] = $amounts[$out_index]/pow(10,$this->blockchain->db_blockchain['decimal_places']);
					}
					else $output_error = true;
				}
			}
			
			if ($output_error) {
				$error_message = "Transaction failed: an invalid output was specified.";
				$this->blockchain->app->cancel_transaction($transaction_id, $affected_input_ids, false);
				return false;
			}
			else {
				$rpc_error = false;
				
				if ($this->blockchain->db_blockchain['p2p_mode'] == "rpc") {
					$this->blockchain->load_coin_rpc();
					
					try {
						$raw_transaction = $this->blockchain->coin_rpc->createrawtransaction($raw_txin, $raw_txout);
						$signed_raw_transaction = $this->blockchain->coin_rpc->signrawtransaction($raw_transaction);
						$decoded_transaction = $this->blockchain->coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
						$tx_hash = $decoded_transaction['txid'];
					}
					catch (Exception $e) {
						$error_message = "RPC call failed: createrawtransaction";
						return false;
					}
					
					if (!empty($tx_hash)) {
						try {
							$verified_tx_hash = $this->blockchain->coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
						}
						catch (Exception $e) {
							$rpc_error = true;
						}
						
						if (!$rpc_error) {
							$this->blockchain->walletnotify($verified_tx_hash, FALSE);
							$this->update_option_votes();
							
							$db_transaction = $this->blockchain->app->run_query("SELECT * FROM transactions WHERE tx_hash=:tx_hash;", ['tx_hash'=>$tx_hash])->fetch();
							
							return $db_transaction['transaction_id'];
						}
						else {
							$error_message = "RPC called failed: sendrawtransaction ".$signed_raw_transaction['hex'].".";
							return false;
						}
					}
					else {
						$error_message = "Failed to sign the transaction.";
						return false;
					}
				}
				else {
					$successful = false;
					$this->blockchain->load_coin_rpc();
					$this->blockchain->add_transaction($new_tx_hash, $block_id, true, $successful, 0, [false], false);
					
					if ($this->blockchain->db_blockchain['p2p_mode'] == "web_api") {
						$this->blockchain->web_api_push_transaction($transaction_id);
					}
					return $transaction_id;
				}
			}
		}
		else {
			$error_message = "Transaction failed: there was a problem with the amounts or addresses.";
			return false;
		}
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
		
		$all_events = $this->blockchain->app->run_query("SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE game_id=:game_id ORDER BY event_index ASC;", ['game_id'=>$this->db_game['game_id']]);
		
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
			$this->blockchain->app->run_query("UPDATE games SET game_status=:new_status, completion_datetime=NOW() WHERE game_id=:game_id;", [
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
		
		if ($this->db_game['game_winning_rule'] == "event_points") {
			$entity_score_info = $this->entity_score_info();
			
			if (!empty($entity_score_info['winning_entity_id'])) {
				$coins_in_existence = $this->coins_in_existence(false, false);
				$payout_amount = floor(((float)$coins_in_existence)*$this->db_game['game_winning_inflation']);
				if ($payout_amount > 0) {
					$game_votes_total = $this->blockchain->app->run_query("SELECT SUM(gio.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch()['SUM(io.votes)'];
					
					$winner_votes_total = $this->blockchain->app->run_query("SELECT SUM(gio.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id=:game_id AND e.entity_id=:entity_id;", [
						'game_id' => $this->db_game['game_id'],
						'entity_id' => $entity_score_info['winning_entity_id']
					])->fetch()['SUM(io.votes)'];
					
					echo "payout ".$this->blockchain->app->format_bignum($payout_amount/pow(10,$this->db_game['decimal_places']))." coins to ".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['entity_name']." (".$this->blockchain->app->format_bignum($winner_votes_total/pow(10,$this->db_game['decimal_places']))." total votes)<br/>\n";
					
					$amounts = [];
					$address_ids = [];
					$payout_ios = $this->blockchain->app->run_query("SELECT * FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id=:game_id AND e.entity_id=:entity_id;", [
						'game_id' => $this->db_game['game_id'],
						'entity_id' => $entity_score_info['winning_entity_id']
					]);
					
					while ($payout_io = $payout_ios->fetch()) {
						$payout_frac = round(pow(10,$this->db_game['decimal_places'])*$payout_io['votes']/$winner_votes_total)/pow(10,$this->db_game['decimal_places']);
						$payout_io_amount = floor($payout_frac*$payout_amount);
						
						if ($payout_io_amount > 0) {
							$vout = count($amounts);
							$amounts[$vout] = $payout_io_amount;
							$address_ids[$vout] = $payout_io['address_id'];
							echo "pay ".$this->blockchain->app->format_bignum($payout_io_amount/pow(10,$this->db_game['decimal_places']))." to ".$payout_io['address']."<br/>\n";
						}
					}
					$last_block_id = $this->blockchain->last_block_id();
					$error_message = false;
					$transaction_id = $this->create_transaction(false, $amounts, false, false, "coinbase", false, $address_ids, false, 0, $error_message);
					$this->blockchain->app->run_query("UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET t.block_id=:block_id, io.spend_status='unspent', io.create_block_id=:block_id, gio.create_round_id=:round_id WHERE t.transaction_id=:transaction_id;", [
						'block_id' => $last_block_id,
						'round_id' => $this->block_to_round($last_block_id),
						'transaction_id' => $transaction_id
					]);

					$this->blockchain->app->run_query("UPDATE games SET game_winning_transaction_id=:transaction_id, winning_entity_id=:entity_id WHERE game_id=:game_id;", [
						'transaction_id' => $transaction_id,
						'entity_id' => $entity_score_info['winning_entity_id'],
						'game_id' => $this->db_game['game_id']
					]);
				}
			}
		}
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
				'block_of_round' => $block_of_round,
				'current_time' => time()
			];
			$strategies_q = "SELECT *, u.user_id, g.game_id FROM users u JOIN user_games g ON u.user_id=g.user_id JOIN user_strategies s ON g.strategy_id=s.strategy_id";
			$strategies_q .= " JOIN user_strategy_blocks usb ON s.strategy_id=usb.strategy_id";
			$strategies_q .= " LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id";
			$strategies_q .= " WHERE g.game_id=:game_id AND usb.block_within_round=:block_of_round";
			$strategies_q .= " AND (s.voting_strategy IN ('by_rank', 'by_entity', 'api', 'by_plan', 'featured','hit_url'))";
			$strategies_q .= " AND (s.time_next_apply IS NULL OR s.time_next_apply<:current_time)";
			$strategies_q .= " ORDER BY RAND();";
			$apply_strategies = $this->blockchain->app->run_query($strategies_q, $strategies_params);
			
			if ($print_debug) {
				echo "Applying user strategies for block #".$mining_block_id." of ".$this->db_game['name']." looping through ".$apply_strategies->rowCount()." users.<br/>\n";
				$this->blockchain->app->flush_buffers();
			}
			
			while (microtime(true)-$ref_time < $max_seconds && $user_game = $apply_strategies->fetch()) {
				$last_block_id = $this->blockchain->last_block_id();
				
				if ($this->last_block_id() == $last_block_id) {
					$mining_block_id = $last_block_id+1;
					$current_round_id = $this->block_to_round($mining_block_id);
					$block_of_round = $this->block_id_to_round_index($mining_block_id);
					
					$api_response = false;
					$this_log_text = "";
					$this->apply_user_strategy($this_log_text, $user_game, $mining_block_id, $current_round_id, $api_response, false);
					
					if (!$api_response || $api_response->status_code > 3) {
						$this->blockchain->app->set_strategy_time_next_apply($user_game['strategy_id'], time()+60*60);
					}
					else {
						//$this->load_current_events();
						//$this->update_option_votes();
					}
					
					if ($print_debug) {
						echo $this_log_text."\n";
						
						if ($api_response) echo "user #".$user_game['user_id'].": ".json_encode($api_response)."\n";
						else echo "no api response for user #".$user_game['user_id']."\n";
						$this->blockchain->app->flush_buffers();
					}
				}
			}
		}
		else if ($print_debug) echo "Game and blockchain are out of sync: not applying user strategies.";
	}
	
	public function apply_user_strategy(&$log_text, &$user_game, $mining_block_id, $current_round_id, &$api_response, $force_now) {
		$strategy_user = new User($this->blockchain->app, $user_game['user_id']);
		
		$user_balance = $this->blockchain->account_balance($user_game['account_id']);
		$mature_balance = $this->blockchain->user_mature_balance($user_game);
		$free_balance = $mature_balance;
		$last_block_id = $this->blockchain->last_block_id();
		
		list($available_votes, $votes_value) = $strategy_user->user_current_votes($this, $last_block_id, $current_round_id, $user_game);
		
		$log_text .= $strategy_user->db_user['username'].": ".$this->blockchain->app->format_bignum($free_balance/pow(10,$this->db_game['decimal_places']))." coins (".$free_balance.") ".$user_game['voting_strategy']."<br/>\n";
		
		if ($free_balance > 0) {
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
						
						$log_text .= $strategy_user->db_user['username']." has ".$free_balance/pow(10,$this->db_game['decimal_places'])." coins available, hitting url: ".$user_game['api_url']."<br/>\n";
						
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
							if ($amount_sum <= $free_balance) {}
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
								else $vote_amount = floor($free_balance*$recommendation->recommended_amount/100);
								
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
				$pct_free = 100*$free_balance/$user_balance;
				
				if ($pct_free >= $user_game['aggregate_threshold']) {
					$entity_pct_sum = 0;
					$skipped_pct_points = 0;
					$skipped_options = "";
					$num_options_skipped = 0;
					$strategy_entity_points = false;

					$strategy_entities = $this->blockchain->app->run_query("SELECT * FROM user_strategy_entities WHERE strategy_id=:strategy_id;", [
						'strategy_id'=>$user_game['strategy_id']
					]);
					while ($strategy_entity = $strategy_entities->fetch()) {
						$strategy_entity_points[$strategy_entity['entity_id']] = intval($strategy_entity['pct_points']);
					}
					
					$entities = $this->entities_by_game();
					while ($entity = $entities->fetch()) {
						if ($user_game['voting_strategy'] == "by_entity") {
							$by_entity_pct_points = 0;
							if (empty($strategy_entity_points[$entity['entity_id']])) $by_entity_pct_points = 0;
							else $by_entity_pct_points = $strategy_entity_points[$entity['entity_id']];
							$entity_pct_sum += $by_entity_pct_points;
						}
					}
					
					if ($user_game['voting_strategy'] == "by_entity") {
						$log_text .= "Dividing by entity for ".$strategy_user->db_user['username']." (".(($free_balance-$user_game['transaction_fee'])/pow(10,$this->db_game['decimal_places']))." coins)<br/>\n";
						
						$mult_factor = 1;
						if ($skipped_pct_points > 0) {
							$mult_factor = floor(pow(10,6)*$entity_pct_sum/($entity_pct_sum-$skipped_pct_points))/pow(10,6);
						}
						
						if ($entity_pct_sum == 100) {
							$option_ids = [];
							$amounts = [];
							$amount_sum = 0;
							
							for ($i=0; $i<count($this->current_events); $i++) {
								$thisevent_entities = $this->blockchain->app->run_query("SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id=:game_id AND e.event_id=:event_id GROUP BY en.entity_id ORDER BY en.entity_id;", [
									'game_id' => $this->db_game['game_id'],
									'event_id' => $this->current_events[$i]->db_event['event_id']
								]);
								
								while ($entity = $thisevent_entities->fetch()) {
									$by_entity_pct_points = 0;
									if (!empty($strategy_entity_points[$entity['entity_id']])) $by_entity_pct_points = $strategy_entity_points[$entity['entity_id']];
									if (empty($skipped_entities[$entity['entity_id']]) && $by_entity_pct_points > 0) {
										$effective_frac = floor((1/count($this->current_events))*pow(10,4)*$by_entity_pct_points*$mult_factor)/pow(10,6);
										$coin_amount = floor($effective_frac*($free_balance-$user_game['transaction_fee']));
										
										$log_text .= "Vote ".$by_entity_pct_points."% (".($coin_amount/pow(10,$this->db_game['decimal_places']))." coins) for ".$entity['entity_name']."<br/>\n";
										
										$option_ids[count($option_ids)] = $entity['option_id'];
										$amounts[count($amounts)] = $coin_amount;
										$amount_sum += $coin_amount;
									}
								}
							}
							if ($amount_sum < ($free_balance-$user_game['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$user_game['transaction_fee']) - $amount_sum;
							
							$error_message = false;
							$transaction_id = $this->create_transaction($option_ids, $amounts, $user_game, false, 'transaction', false, false, false, $user_game['transaction_fee'], $error_message);
							if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
							else $log_text .= $error_message."<br/>\n";
						}
					}
					else { // by_plan
						$log_text .= "Dividing by plan for ".$strategy_user->db_user['username']."<br/>\n";
						
						$db_allocations = $this->blockchain->app->run_query("SELECT * FROM strategy_round_allocations WHERE strategy_id=:strategy_id AND round_id=:round_id AND applied=0;", [
							'strategy_id' => $user_game['strategy_id'],
							'round_id' => $current_round_id
						]);
						
						if ($db_allocations->rowCount() > 0) {
							$allocations = [];
							$point_sum = 0;
							
							while ($allocation = $db_allocations->fetch()) {
								$allocations[count($allocations)] = $allocation;
								$point_sum += intval($allocation['points']);
							}
							
							$option_ids = [];
							$amounts = [];
							$amount_sum = 0;
							
							for ($i=0; $i<count($allocations); $i++) {
								$option_ids[$i] = $allocations[$i]['option_id'];
								$amount = floor(($free_balance-$user_game['transaction_fee'])*$allocations[$i]['points']/$point_sum);
								$amounts[$i] = $amount;
								$amount_sum += $amount;
							}
							if ($amount_sum < ($free_balance-$user_game['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$user_game['transaction_fee']) - $amount_sum;
							
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
			}
		}
	}
	
	public function reset_blocks_from_block($block_height) {
		$this->blockchain->app->log_message("Resetting ".$this->db_game['name']." from block ".$block_height);
		
		$this->blockchain->app->dbh->beginTransaction();
		
		$this->blockchain->app->run_query("DELETE FROM game_blocks WHERE game_id=:game_id AND block_id >= :block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_height
		]);
		$this->blockchain->app->run_query("DELETE FROM game_sellouts WHERE game_id=:game_id AND in_block_id >= :block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_height
		]);
		
		$prev_block = $this->fetch_game_block_by_height($block_height-1);
		if ($prev_block) {
			$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND (game_io_index > :game_io_index OR game_io_index IS NULL);", [
				'game_id' => $this->db_game['game_id'],
				'game_io_index' => $prev_block['max_game_io_index']
			]);
		}
		
		$this->blockchain->app->run_query("UPDATE transaction_game_ios SET spend_round_id=NULL WHERE game_id=:game_id AND spend_round_id >= :round_id;", [
			'game_id' => $this->db_game['game_id'],
			'round_id' => $this->block_to_round($block_height)
		]);
		$this->blockchain->app->run_query("DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id AND ob.block_height >= :block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_height
		]);
		$this->blockchain->app->run_query("UPDATE games SET loaded_until_block=NULL, coins_in_existence=0, cached_pending_bets=NULL, cached_vote_supply=NULL WHERE game_id=:game_id;", [
			'game_id' => $this->db_game['game_id']
		]);
		
		$user_game = false;
		$this->add_genesis_transaction($user_game);
		
		$this->blockchain->app->dbh->commit();
	}
	
	public function reset_events_from_index($event_index) {
		$this->blockchain->app->log_message("Deleting ".$this->db_game['name']." events from ".$event_index);
		
		$this->blockchain->app->dbh->beginTransaction();
		
		$this->blockchain->app->run_query("DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id=:game_id AND e.event_index >= :event_index;", [
			'game_id' => $this->db_game['game_id'],
			'event_index' => $event_index
		]);
		
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
		]);
		
		if ($event_info->rowCount() > 0) {
			$event_info = $event_info->fetch();
			return (int) $event_info['MIN(event_index)'];
		}
		else return false;
	}
	
	public function delete_reset_game($delete_or_reset) {
		$this->blockchain->app->log_message("Resetting ".$this->db_game['name']." (".$delete_or_reset.")");
		
		$this->blockchain->app->dbh->beginTransaction();
		
		$this->blockchain->app->run_query("DELETE FROM game_blocks WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		$this->blockchain->app->run_query("DELETE FROM game_sellouts WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		
		$delete_limit = 20000;
		$max_io_index = $this->max_game_io_index();
		$delete_gio_queries = ceil($max_io_index/$delete_limit);
		$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index IS NULL;", ['game_id'=>$this->db_game['game_id']]);
		
		for ($d=0; $d<$delete_gio_queries; $d++) {
			$this->blockchain->app->run_query("DELETE FROM transaction_game_ios WHERE game_id=:game_id AND game_io_index >= :from_index AND game_io_index <= :to_index;", [
				'game_id' => $this->db_game['game_id'],
				'from_index' => $d*$delete_limit,
				'to_index' => ($d+1)*$delete_limit
			]);
		}
		
		$this->blockchain->app->run_query("DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		$this->blockchain->app->run_query("DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		$this->blockchain->app->run_query("UPDATE games SET events_until_block=NULL, loaded_until_block=NULL, min_option_index=NULL, max_option_index=NULL WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
		
		if ($delete_or_reset == "reset") {
			$this->blockchain->app->run_query("UPDATE games SET coins_in_existence=0, cached_pending_bets=NULL, cached_vote_supply=NULL, cached_definition_hash=NULL, defined_cached_definition_hash=NULL, cached_definition_time=NULL WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']]);
			
			$user_game = false;
			$this->add_genesis_transaction($user_game);
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
		
		$db_events = $this->blockchain->app->run_query("SELECT e.*, winner.name AS winner_name FROM events e LEFT JOIN options winner ON e.winning_option_id=winner.option_id WHERE e.game_id=:game_id AND e.event_index >= :from_event_index AND e.event_index <= :to_event_index ORDER BY e.event_index DESC;", [
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
			
			if ($db_event['outcome_index'] == -1) $html .= '<font class="redtext">Canceled</font>';
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
						$html .= round(100*$winner_pct, 2)."% &nbsp;&nbsp; ";
					}
					
					if ($winning_effective_coins > 0) {
						$winner_odds = $db_event['payout_rate']*$event_effective_bets/$winning_effective_coins;
						$html .= "x".$this->blockchain->app->round_to($winner_odds, 2, 4, true)." &nbsp;&nbsp; ";
					}
					
					$html .= $db_event['winner_name'];
				}
				else $html .= "No winner";
			}
			else {
				$buy_option = $this->blockchain->app->fetch_option_by_event_option_index($db_event['event_id'], 0);
				
				$buy_stake = $buy_option['effective_destroy_score']+$buy_option['unconfirmed_effective_destroy_score'] + $coins_per_vote*($buy_option['votes']+$buy_option['unconfirmed_votes']);
				
				if ((string)$db_event['track_payout_price'] != "") {
					$ref_price_usd = $db_event['track_payout_price'];
					$html .= "<b>Paid</b>";
				}
				else {
					if ($last_block_id < $db_event['event_final_block']) $html .= "<font class='greentext'>Running</font>";
					else $html .= "<font class='yellowtext'>Not Paid</font>";
					
					$ref_currency = $this->blockchain->app->get_currency_by_abbreviation($db_event['track_name_short']);
					$ref_price_info = $this->blockchain->app->exchange_rate_between_currencies(1, $ref_currency['currency_id'], time(), 6);
					$ref_price_usd = max($db_event['track_min_price'], min($db_event['track_max_price'], $ref_price_info['exchange_rate']));
				}
				$html .= " &nbsp;&nbsp; ";
				
				$html .= "<div style='display: inline-block; min-width: 190px;'>".$db_event['track_name_short']." &nbsp; ";
				if ($event_effective_bets > 0) {
					$our_buy_price = ($buy_stake/$event_effective_bets)*($db_event['track_max_price']-$db_event['track_min_price'])+$db_event['track_min_price'];
					$html .= "$".$this->blockchain->app->round_to($our_buy_price, 2, 4, true)." &rarr; \n";
				}
				$html .= "$".$this->blockchain->app->round_to($ref_price_usd, 2, 4, true);
				$html .= "</div>\n";
				
				if ($event_effective_bets > 0) {
					$pct_gain = round(100*($ref_price_usd/$our_buy_price - 1), 2);
					
					if ($pct_gain >= 0) $html .= ' &nbsp; <font class="greentext">+'.$pct_gain."%</font>\n";
					else $html .= ' &nbsp; <font class="redtext">-'.abs($pct_gain)."%</font>\n";
				}
			}
			$html .= "</div>";
			$html .= '<div class="col-sm-3">'.$this->blockchain->app->format_bignum($event_total_bets/pow(10,$this->db_game['decimal_places'])).' '.$this->db_game['coin_name_plural'].' bet</div>';
			
			$html .= "</div>\n";
		}
		
		$returnvals[0] = $last_round_shown;
		$returnvals[1] = $html;
		
		return $returnvals;
	}
	
	public function option_index_range() {
		if (false && !empty($this->db_game['max_option_index']) && $this->db_game['min_option_index'] !== "") {
			return array($this->db_game['min_option_index'], $this->db_game['max_option_index']);
		}
		else {
			$range_row = $this->blockchain->app->run_query("SELECT MAX(o.option_index), MIN(o.option_index) FROM options o JOIN events e ON o.event_id=e.event_id WHERE e.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch();
			
			if ($range_row) {
				$min = (int) $range_row['MIN(o.option_index)'];
				$max = (int) $range_row['MAX(o.option_index)'];
				
				$this->blockchain->app->run_query("UPDATE games SET max_option_index=:max_option_index, min_option_index=:min_option_index WHERE game_id=:game_id;", [
					'max_option_index' => $max,
					'min_option_index' => $min,
					'game_id' => $this->db_game['game_id']
				]);
				$this->db_game['max_option_index'] = $max;
				$this->db_game['min_option_index'] = $min;
				
				return [$min, $max];
			}
			else return [false, false];
		}
	}
	
	public function option_index_to_current_option_id($option_index) {
		return $this->option_index_to_option_id_in_block($option_index, $this->blockchain->last_block_id()+1);
	}
	
	public function option_index_to_option_id_in_block($option_index, $block_id) {
		$first_option = $this->blockchain->app->run_query("SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id=:game_id AND op.option_index=:option_index AND e.event_starting_block<=:block_id AND e.event_final_block>=:block_id;", [
			'game_id' => $this->db_game['game_id'],
			'option_index' => $option_index,
			'block_id' => $block_id
		])->fetch();
		
		if ($first_option) return $first_option['option_id'];
		else return false;
	}
	
	public function generate_invitation($inviter_id, &$invitation, $user_id) {
		$new_invitation_params = [
			'game_id' => $this->db_game['game_id'],
			'invitation_key' => strtolower($this->blockchain->app->random_string(32)),
			'time_created' => time()
		];
		$new_invitation_q = "INSERT INTO game_invitations SET game_id=:game_id";
		if ($inviter_id > 0) {
			$new_invitation_q .= ", inviter_id=:inviter_id";
			$new_invitation_params['inviter_id'] = $inviter_id;
		}
		$new_invitation_q .= ", invitation_key=:invitation_key, time_created=:time_created";
		if ($user_id) {
			$new_invitation_q .= ", used_user_id=:user_id";
			$new_invitation_params['user_id'] = $user_id;
		}
		$new_invitation_q .= ";";
		$this->blockchain->app->run_query($new_invitation_q, $new_invitation_params);
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
	
	public function paid_players_in_game() {
		return (int)($this->blockchain->app->run_query("SELECT COUNT(*) FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id=:game_id AND ug.payment_required=0;", ['game_id'=>$this->db_game['game_id']])->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function start_game() {
		$start_block = $this->blockchain->fetch_block_by_id($this->db_game['game_starting_block']);
		
		if ($this->blockchain->db_blockchain['p2p_mode'] == "rpc") {
			try {
				$this->blockchain->load_coin_rpc();
				$last_block_hash = $this->blockchain->coin_rpc->getblockhash((int) $this->db_game['game_starting_block']);
				$rpc_block = $this->blockchain->coin_rpc->getblock($last_block_hash);
				$game_start_time = $rpc_block['time'];
			}
			catch (Exception $e) {
				echo "Error, failed to load RPC connection for ".$this->blockchain->db_blockchain['blockchain_name'].".<br/>\n";
			}
		}
		$this->blockchain->app->run_query("UPDATE games SET initial_coins=:initial_coins, game_status='running', start_time=:start_time, start_datetime=:start_datetime WHERE game_id=:game_id;", [
			'initial_coins' => $this->coins_in_existence(false, false),
			'start_time' => $start_block['time_mined'],
			'start_datetime' => date("Y-m-d g:ia", $start_block['time_mined']),
			'game_id' => $this->db_game['game_id']
		]);
		
		$this->db_game['seconds_per_block'] = $this->blockchain->db_blockchain['seconds_per_block'];
		
		$this->set_loaded_until_block(null);
	}
	
	public function max_game_io_index() {
		return (int)($this->blockchain->app->run_query("SELECT MAX(game_io_index) FROM transaction_game_ios WHERE game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch()['MAX(game_io_index)']);
	}
	
	public function process_buyin_transaction($transaction) {
		if ((string)$this->db_game['game_starting_block'] !== "" && !empty($this->db_game['escrow_address'])) {
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
			
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
					$colored_coins_generated = $this->db_game['genesis_amount'];
					$game_io_index = 0;
				}
				else {
					$escrow_value = $this->escrow_value($transaction['block_id']-1);
					$coins_in_existence = $this->coins_in_existence($transaction['block_id']-1, false);
					
					$exchange_rate = $coins_in_existence/$escrow_value;
					$colored_coins_generated = floor($exchange_rate*$escrowed_coins);
					
					$game_io_index = $this->max_game_io_index()+1;
				}
				$game_out_index = 0;
				
				$sum_colored_coins = 0;
				
				$create_round_id = $this->block_to_round($transaction['block_id']);
				
				$non_escrow_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.address_id != :address_id AND a.is_destroy_address=0 ORDER BY io.out_index ASC;", [
					'transaction_id' => $transaction['transaction_id'],
					'address_id' => $escrow_address['address_id']
				]);
				
				while ($non_escrow_io = $non_escrow_ios->fetch()) {
					$colored_coins = floor($colored_coins_generated*$non_escrow_io['amount']/$non_escrowed_coins);
					$sum_colored_coins += $colored_coins;
					
					$this->blockchain->app->run_query("INSERT INTO transaction_game_ios SET io_id=:io_id, address_id=:address_id, game_id=:game_id, is_coinbase=0, colored_amount=:amount, create_block_id=:block_id, create_round_id=:round_id, coin_blocks_destroyed=0, coin_rounds_destroyed=0, is_resolved=1, game_out_index=:game_out_index, game_io_index=:game_io_index;", [
						'io_id' => $non_escrow_io['io_id'],
						'address_id' => $non_escrow_io['address_id'],
						'game_id' => $this->db_game['game_id'],
						'amount' => $colored_coins,
						'block_id' => $transaction['block_id'],
						'round_id' => $create_round_id,
						'game_out_index' => $game_out_index,
						'game_io_index' => $game_io_index
					]);
					
					$game_io_index++;
					$game_out_index++;
				}
			}
		}
	}
	
	public function escrow_value($block_id) {
		if (!$block_id) $block_id = $this->blockchain->last_block_id();
		
		$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
		
		$value = $this->blockchain->address_balance_at_block($escrow_address, $block_id);
		
		$liabilities = (int)($this->blockchain->app->run_query("SELECT SUM(amount_out) FROM game_sellouts WHERE game_id=:game_id AND (out_block_id IS NULL OR out_block_id > :block_id);", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		])->fetch()['SUM(amount_out)']);
		
		$value = $value - $liabilities;
		
		return $value;
	}
	
	public function account_value_html($account_value, &$user_game, $game_pending_bets, $vote_supply_value) {
		$html = '<font class="greentext"><a href="/accounts/?account_id='.$user_game['account_id'].'">'.$this->blockchain->app->format_bignum($account_value/pow(10,$this->db_game['decimal_places']), 2).'</a></font> '.$this->db_game['coin_name_plural'];
		
		$html .= ' <font style="font-size: 12px;">(';
		
		$coins_in_existence = $this->coins_in_existence(false, true)+$game_pending_bets;
		if ($coins_in_existence > 0) $html .= $this->blockchain->app->format_bignum(100*$account_value/$coins_in_existence)."%";
		else $html .= "0%";
		
		$display_currency = $this->blockchain->app->fetch_currency_by_id($user_game['display_currency_id']);
		
		$escrow_value = $this->escrow_value_in_currency($display_currency['currency_id']);
		
		if ($coins_in_existence > 0) {
			$display_value = floor(($account_value/$coins_in_existence)*$escrow_value);
		}
		else $display_value = 0;
		
		if ($display_value > 0) {
			$html .= "&nbsp;=&nbsp;".$this->blockchain->app->format_bignum($display_value)." ".$display_currency['short_name_plural'];
		}
		
		$html .= ")</font>";
		
		return $html;
	}
	
	public function send_invitation_email($to_email, &$invitation) {
		$blocks_per_hour = 3600/$this->blockchain->db_blockchain['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,$this->db_game['decimal_places']);
		$rounds_per_hour = 3600/($this->blockchain->db_blockchain['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->blockchain->db_blockchain['seconds_per_block']*$this->db_game['round_length'];
		
		if ($this->db_game['inflation'] == "linear") $miner_pct = 100*($this->db_game['pow_reward']*$this->db_game['round_length'])/($round_reward*pow(10,$this->db_game['decimal_places']));
		else $miner_pct = 100*$this->db_game['exponential_inflation_minershare'];
		
		$invite_currency = false;
		if ($this->db_game['invite_currency'] > 0) {
			$invite_currency = $this->blockchain->app->fetch_currency_by_id($this->db_game['invite_currency']);
		}
		
		$subject = "You've been invited to join ".$this->db_game['name'];
		if ($this->db_game['giveaway_status'] == "invite_pay" || $this->db_game['giveaway_status'] == "public_pay") {
			$subject .= ". Join by paying ".$this->blockchain->app->format_bignum($this->db_game['invite_cost'])." ".$invite_currency['short_name']."s for ".$this->blockchain->app->format_bignum($this->db_game['giveaway_amount']/pow(10,$this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural'].".";
		}
		
		$message = "<p>";
		if ($this->db_game['short_description'] != "") {
			$message .= "<p>".$this->db_game['short_description']."</p>";
		}
		else {
			if ($this->db_game['inflation'] == "exponential") {}
			else if ($this->db_game['inflation'] == "linear") $message .= $this->db_game['name']." is a cryptocurrency which generates ".$coins_per_hour." ".$this->db_game['coin_name_plural']." per hour. ";
			else $message .= $this->db_game['name']." is a cryptocurrency with ".($this->db_game['exponential_inflation_rate']*100)."% inflation every ".$this->blockchain->app->format_seconds($seconds_per_round).". ";
			$message .= $miner_pct."% is given to miners for securing the network and the remaining ".(100-$miner_pct)."% is given to players for casting winning votes. ";
			if ($this->db_game['final_round'] > 0) {
				$game_total_seconds = $seconds_per_round*$this->db_game['final_round'];
				$message .= "Once this game starts, it will last for ".$this->blockchain->app->format_seconds($game_total_seconds)." (".$this->db_game['final_round']." rounds). ";
				$message .= "At the end, all ".$invite_currency['short_name']."s that have been paid in will be divided up and given out to all players in proportion to players' final balances.";
			}
			$message .= "Team up with other players and cast your votes strategically to win coins and destroy your competitors. ";
		}
		$message .= "</p>";
		
		$this->db_game['seconds_per_block'] = $this->blockchain->db_blockchain['seconds_per_block'];
		
		$table = str_replace('<div class="row"><div class="col-sm-5">', '<tr><td>', $this->blockchain->app->game_info_table($this->db_game));
		$table = str_replace('</div><div class="col-sm-7">', '</td><td>', $table);
		$table = str_replace('</div></div>', '</td></tr>', $table);
		$table = str_replace('href="', 'href="'.AppSettings::getParam('base_url'), $table);
		
		$message .= '<table>'.$table.'</table>';
		$message .= "<p>To start playing, accept your invitation by following <a href=\"".AppSettings::getParam('base_url')."/wallet/".$this->db_game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
		$message .= "<p>This message was sent to you by ".AppSettings::getParam('site_name')."</p>";
		
		$email_id = $this->blockchain->app->mail_async($to_email, AppSettings::getParam('site_name'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message, "", "", "");
		
		$this->blockchain->app->run_query("UPDATE game_invitations SET sent_email_id=:sent_email_id WHERE invitation_id=:invitation_id;", [
			'sent_email_id' => $email_id,
			'invitation_id' => $invitation['invitation_id']
		]);
		
		return $email_id;
	}
	
	public function entity_score_info($user) {
		$return_obj = false;
		
		if ($user) {
			$user_entity_votes_total = $this->blockchain->app->run_query("SELECT SUM(gio.votes), COUNT(*) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN options o ON gio.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id=:game_id AND a.user_id=:user_id;", [
				'game_id' => $this->db_game['game_id'],
				'user_id' => $user->db_user['user_id']
			])->fetch();
			$return_obj['user_entity_votes_total'] = $user_entity_votes_total['SUM(gio.votes)'];

			$entity_votes_total = $this->blockchain->app->run_query("SELECT SUM(gio.votes) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON gio.io_id=io.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id=:game_id;", ['game_id'=>$this->db_game['game_id']])->fetch();
			$return_obj['entity_votes_total'] = $entity_votes_total['SUM(gio.votes)'];
		}
		
		$return_rows = false;
		$this_game_entities = $this->entities_by_game();
		
		while ($entity = $this_game_entities->fetch()) {
			$info = $this->blockchain->app->run_query("SELECT COUNT(*), SUM(:game_winning_field) points FROM events ev JOIN options op ON ev.winning_option_id=op.option_id JOIN event_types et ON ev.event_type_id=et.event_type_id JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id=:game_id AND op.entity_id=:entity_id;", [
				'game_winning_field' => "en.".$this->db_game['game_winning_field'],
				'game_id' => $this->db_game['game_id'],
				'entity_id' => $entity['entity_id']
			])->fetch();
			
			$return_rows[$entity['entity_id']]['points'] = (int) $info['points'];
			$return_rows[$entity['entity_id']]['entity_name'] = $entity['entity_name'];
			
			$entity_my_pct = false;
			if ($user) {
				$user_entity_votes = $this->blockchain->app->run_query("SELECT SUM(gio.votes), COUNT(*) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id=:game_id AND a.user_id=:user_id AND o.entity_id=:entity_id;", [
					'game_id' => $this->db_game['game_id'],
					'user_id' => $user->db_user['user_id'],
					'entity_id' => $entity['entity_id']
				])->fetch();
				
				$return_rows[$entity['entity_id']]['my_votes'] = $user_entity_votes['SUM(gio.votes)'];
				if ($return_obj['user_entity_votes_total'] > 0) $my_pct = 100*$user_entity_votes['SUM(gio.votes)']/$return_obj['user_entity_votes_total'];
				else $my_pct = 0;
				$return_rows[$entity['entity_id']]['my_pct'] = $my_pct;
				
				$entity_votes_info = $this->blockchain->app->run_query("SELECT SUM(gio.votes), COUNT(*) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id=:game_id AND o.entity_id=:entity_id;", [
					'game_id' => $this->db_game['game_id'],
					'entity_id' => $entity['entity_id']
				])->fetch();
				$return_rows[$entity['entity_id']]['entity_votes'] = $entity_votes_info['SUM(gio.votes)'];
			}
		}
		$returnvals['entities'] = $return_rows;

		if ($this->db_game['game_winning_rule'] == "event_points") {
			$max_points = 0;
			$winning_entity_ids = [];
			foreach ($return_rows as $entity_id => $entity_info) {
				if ($entity_info['points'] > 0) {
					if ($entity_info['points'] == $max_points) {
						$winning_entity_ids[count($winning_entity_ids)] = $entity_id;
					}
					else if ($entity_info['points'] > $max_points) {
						$winning_entity_ids = array($entity_id);
						$max_points = $entity_info['points'];
					}
				}
			}
			if (count($winning_entity_ids) == 1) {
				$returnvals['winning_entity_id'] = $winning_entity_ids[0];
				$returnvals['winning_entity_points'] = $max_points;
			}
		}
		
		return $returnvals;
	}
	
	public function game_status_explanation(&$user, &$user_game) {
		$last_block_id = $this->blockchain->last_block_id();
		
		$html = "";
		if ($this->db_game['game_status'] == "editable") $html .= "The game creator hasn't yet published this game; its parameters can still be changed. ";
		else if ($this->db_game['game_status'] == "published") {
			if ($this->db_game['start_condition'] == "players_joined") {
				$num_players = $this->paid_players_in_game();
				$players_needed = ($this->db_game['start_condition_players']-$num_players);
				if ($players_needed > 0) {
					$html .= $num_players."/".$this->db_game['start_condition_players']." players have already joined, waiting for ".$players_needed." more players. ";
				}
			}
			else if ($this->db_game['start_condition'] == "fixed_block") {
				$html .= "This game starts ";
				if ($this->db_game['game_starting_block'] > $this->blockchain->last_block_id()) $html .= " in ".($this->db_game['game_starting_block']-$this->blockchain->last_block_id())." blocks. ";
				else $html .= " on block #".$this->db_game['game_starting_block'].". ";
			}
			else if (!empty($this->db_game['start_datetime'])) $html .= "This game starts in ".$this->blockchain->app->format_seconds(strtotime($this->db_game['start_datetime'])-time())." at ".$this->db_game['start_datetime'].". ";
			else $html .= "This game hasn't started yet. ";
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
			if ($link_show_cron) $private_game_message .= "Please <a target=\"_blank\" href=\"/cron/minutely.php?key=\">ensure ".AppSettings::getParam('site_name_short')." is running</a>";
			
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
			$blocks_pct_complete = 100*($total_game_blocks-($missing_blocks-$block_fraction))/$total_game_blocks;
		}
		$est_time_remaining = $missing_blocks*$time_per_block;
		
		if ($missing_blocks > 0) $html .= "<p>Loading blocks.. ".round($blocks_pct_complete, 2)."% complete (".number_format($missing_blocks)." blocks remain.. ".$this->blockchain->app->format_seconds($est_time_remaining)." left).</p>\n";
		if ($loading_block) {
			$html .= "<p>Loaded ".$loading_transactions."/".$loading_block['num_transactions']." in block <a href=\"/explorer/games/".$this->db_game['url_identifier']."/blocks/".$loading_block_id."\">#".$loading_block_id."</a>.</p>\n";
		}
		
		if ($this->db_game['events_until_block'] < $last_block_id) {
			if (empty($this->db_game['events_until_block'])) $events_until_block = $this->db_game['game_starting_block'];
			else $events_until_block = $this->db_game['events_until_block'];
			
			$events_pct_complete = ($events_until_block-$this->db_game['game_starting_block'])/$total_game_blocks;
			$html .= "<p>Loading events.. <a target=\"_blank\" href=\"/explorer/games/".$this->db_game['url_identifier']."/events/\">".round(100*$events_pct_complete, 2)."% complete</a>.</p>\n";
		}
		
		if ($total_game_blocks == 0) $game_blocks_pct_complete = 100;
		else $game_blocks_pct_complete = 100*($total_game_blocks-$missing_game_blocks)/$total_game_blocks;
		
		if ($missing_game_blocks > 0) {
			$sample_size = 100;
			$last_block = $this->fetch_game_block_by_height($last_block_loaded);
			$sample_block = $this->fetch_game_block_by_height(max($this->db_game['game_starting_block'], $last_block_loaded-100));
			
			if ($last_block['block_id'] == $sample_block['block_id']) $time_per_block = 0;
			else $time_per_block = ($last_block['time_loaded']-$sample_block['time_loaded'])/($last_block['block_id']-$sample_block['block_id']);
			
			$html .= "<p>Loading ".$this->blockchain->app->format_bignum($missing_game_blocks)." game block";
			if ($missing_game_blocks != 1) $html .= "s";
			
			if ($missing_game_blocks > 1) {
				$html .= " (".round(max(0, $game_blocks_pct_complete), 2)."% complete";
				$seconds_left = $time_per_block*$missing_game_blocks;
				$html .= ".. ".$this->blockchain->app->format_seconds($seconds_left)." remaining";
				$html .= ").</p>\n";
			}
		}
		
		if ($this->db_game['game_winning_rule'] == "event_points") {
			$entity_score_info = $this->entity_score_info($user);
			if (!empty($entity_score_info['winning_entity_id'])) {
				$html .= "<h3>".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['entity_name']." ";
				if ($this->db_game['game_status'] == "completed") $html .= "wins";
				else $html .= "is winning";
				$html .= " with ".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['points']." electoral votes</h3>";
			}
			else $html .= "<h3>Current Scores</h3>";
			
			if ($user && !empty($this->db_game['game_winning_transaction_id'])) {
				$game_winning_amount = $this->blockchain->app->run_query("SELECT SUM(amount) FROM addresses a JOIN address_keys k ON a.address_id=k.address_id JOIN transaction_ios io ON a.address_id=io.address_id WHERE k.account_id=:account_id AND io.create_transaction_id=:game_winning_transaction_id;", [
					'account_id' => $user_game['account_id'],
					'game_winning_transaction_id' => $this->db_game['game_winning_transaction_id']
				])->fetch()['SUM(amount)'];
				$html .= "You won <font class=\"greentext\">".$this->blockchain->app->format_bignum($game_winning_amount/pow(10,$this->db_game['decimal_places']))."</font> ".$this->db_game['coin_name_plural']." in the end-of-game payout.<br/>\n";
			}
			
			foreach ($entity_score_info['entities'] as $entity_id => $entity_info) {
				$html .= "<div class=\"row\"><div class=\"col-sm-3\">".$entity_info['entity_name']."</div><div class=\"col-sm-3\">".$entity_info['points']." electoral votes</div>";
				if ($user) {
					$coins_in_existence = $this->coins_in_existence(false, true);
					$add_coins = floor($coins_in_existence*$this->db_game['game_winning_inflation']);
					$new_coins_in_existence = $coins_in_existence + $add_coins;
					$account_value = $this->account_balance($user_game['account_id']);
					if ($coins_in_existence > 0) $account_pct = $account_value/$coins_in_existence;
					else $account_pct = 0;
					if ($entity_info['entity_votes'] > 0) $payout_amount = floor($add_coins*($entity_info['my_votes']/$entity_info['entity_votes']));
					else $payout_amount = 0;
					$new_account_value = $account_value+$payout_amount;
					if ($new_coins_in_existence > 0) $new_account_pct = $new_account_value/$new_coins_in_existence;
					else $new_account_pct = 0;
					if ($account_pct > 0) $change_frac = $new_account_pct/$account_pct-1;
					else $change_frac = 0;
					$html .= "<div class=\"col-sm-3\">".$this->blockchain->app->format_bignum($entity_info['my_pct'])."% of my votes</div>";
					if ($this->db_game['game_winning_inflation'] > 0) {
						$html .= "<div class=\"col-sm-3";
						if ($change_frac >= 0) $html .= " greentext";
						else $html .= " redtext";
						$html .= "\">";
						if ($change_frac >= 0) {
							$html .= "+".$this->blockchain->app->format_bignum(100*$change_frac)."%";
						}
						else {
							$html .= "-".$this->blockchain->app->format_bignum((-1)*100*$change_frac)."%";
						}
						$html .= "</div>";
					}
				}
				$html .= "</div>\n";
			}
			$html .= "<br/>\n";
		}
		return $html;
	}
	
	public function game_description() {
		$html = "";
		$blocks_per_hour = 3600/$this->blockchain->db_blockchain['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,$this->db_game['decimal_places']);
		$rounds_per_hour = 3600/($this->blockchain->db_blockchain['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->blockchain->db_blockchain['seconds_per_block']*$this->db_game['round_length'];
		$coins_per_block = $this->blockchain->app->format_bignum($this->db_game['pow_reward']/pow(10,$this->db_game['decimal_places']));
		
		if ($this->db_game['game_status'] == "running") {
			$html .= "This game started ".$this->blockchain->app->format_seconds(time()-$this->db_game['start_time'])." ago; ".$this->blockchain->app->format_bignum($this->coins_in_existence(false, true)/pow(10,$this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural']."  are already in circulation. ";
		}
		else {
			if ($this->db_game['start_condition'] == "fixed_time") {
				$unix_starttime = strtotime($this->db_game['start_datetime']);
				
				$html .= "This game starts in ".$this->blockchain->app->format_seconds($unix_starttime-time())." at ".date("M j, Y g:ia", $unix_starttime).". ";
			}
			else if ($this->db_game['start_condition'] == "fixed_block") {
				$html .= "This game starts in ".($this->db_game['game_starting_block']-$this->blockchain->last_block_id())." blocks.";
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
			$html .= "This game will last ".$this->db_game['final_round']." rounds (".$this->blockchain->app->format_seconds($game_total_seconds)."). ";
		}
		else $html .= "This game doesn't end, but you can sell out at any time. ";

		$html .= '';
		if ($this->db_game['inflation'] == "linear") {
			$html .= "This coin has linear inflation: ".$this->blockchain->app->format_bignum($round_reward)." ".$this->db_game['coin_name_plural']." are minted approximately every ".$this->blockchain->app->format_seconds($seconds_per_round);
			$html .= " (".$this->blockchain->app->format_bignum($coins_per_hour)." coins per hour)";
			$html .= ". In each round, ".$this->blockchain->app->format_bignum($this->db_game['pos_reward']/pow(10,$this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural']." are given to voters and ".$this->blockchain->app->format_bignum($this->db_game['pow_reward']*$this->db_game['round_length']/pow(10,$this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural']." are given to miners";
			$html .= " (".$coins_per_block." coin";
			if ($coins_per_block != 1) $html .= "s";
			$html .= " per block). ";
		}
		else if ($this->db_game['inflation'] == "fixed_exponential") $html .= "This currency grows by ".(100*$this->db_game['exponential_inflation_rate'])."% per round. ".(100 - 100*$this->db_game['exponential_inflation_minershare'])."% is given to voters and ".(100*$this->db_game['exponential_inflation_minershare'])."% is given to miners every ".$this->blockchain->app->format_seconds($seconds_per_round).". ";
		
		$html .= "Each round consists of ".$this->db_game['round_length'].", ".str_replace(" ", "-", rtrim($this->blockchain->app->format_seconds($this->blockchain->db_blockchain['seconds_per_block']), 's'))." blocks. ";
		if ($this->db_game['maturity'] > 0) {
			$html .= ucwords($this->db_game['coin_name_plural'])." are locked for ";
			$html .= $this->db_game['maturity']." block";
			if ($this->db_game['maturity'] != 1) $html .= "s";
			$html .= " when spent. ";
		}
		
		return $html;
	}
	
	public function render_game_players() {
		$networth_sum = 0;
		$html = "";
		
		$user_games = $this->blockchain->app->run_query("SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id=:game_id AND ug.payment_required=0 GROUP BY ug.user_id ORDER BY u.user_id ASC;", ['game_id'=>$this->db_game['game_id']]);
		
		$html .= "<b>".$user_games->rowCount()." players</b><br/>\n";
		
		while ($user_game = $user_games->fetch()) {
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-4"><a href="" onclick="thisPageManager.openChatWindow('.$user_game['user_id'].'); return false;">Player'.$user_game['user_id'].'</a></div>';
			$html .= '</div>';
		}
		
		$html .= "<br/>\nSum: ".$this->blockchain->app->format_bignum($networth_sum)." ".$this->db_game['coin_name_plural'];
		
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
					
					$this->blockchain->app->run_query("INSERT INTO strategy_round_allocations SET strategy_id=:strategy_id, round_id=:round_id, option_id=:option_id, points=:points;", [
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
				$in_existence_q .= " AND gio.create_block_id <= :block_id AND ((io.spend_block_id IS NULL AND io.spend_status IN ('unspent','unconfirmed')) OR io.spend_block_id>:block_id)";
				$in_existence_params['block_id'] = $block_id;
			}
			else $in_existence_q .= " AND io.spend_status IN ('unspent','unconfirmed')";
			$in_existence_q .= ";";
			$coins = (int)($this->blockchain->app->run_query($in_existence_q, $in_existence_params)->fetch(PDO::FETCH_NUM)[0]);
			
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
		
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id=:game_id AND ev.event_starting_block<=:ref_block_id AND ev.event_final_block>=:ref_block_id ORDER BY ev.event_id ASC;", [
			'game_id' => $this->db_game['game_id'],
			'ref_block_id' => $mining_block_id
		]);
		
		while ($db_event = $db_events->fetch()) {
			array_push($this->current_events, new Event($this, $db_event, false));
		}
	}
	
	public function events_by_block($block_id, &$filter_arr) {
		$events = [];
		$events_params = [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $block_id
		];
		$events_q = "SELECT *, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id AND ev.event_starting_block<=:block_id AND ev.event_final_block>=:block_id";
		if (!empty($filter_arr['date'])) {
			$events_q .= " AND DATE(ev.event_final_time)=:filter_date";
			$events_params['filter_date'] = $filter_arr['date'];
		}
		if (!empty($filter_arr['require_option_block_rule'])) {
			$events_q .= " AND ev.option_block_rule IS NOT NULL";
		}
		$events_q .= " AND (ev.event_starting_time IS NULL OR ev.event_starting_time < NOW()) AND (ev.event_final_time IS NULL OR ev.event_final_time > NOW()) ORDER BY ev.event_id ASC;";
		$db_events = $this->blockchain->app->run_query($events_q, $events_params);
		
		while ($db_event = $db_events->fetch()) {
			array_push($events, new Event($this, $db_event, false));
		}
		
		return $events;
	}
	
	public function events_by_outcome_block($block_id) {
		$events = [];
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id=:game_id AND ev.event_outcome_block=:block_id ORDER BY ev.event_index ASC;", [
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
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id=:game_id AND ev.event_payout_block=:block_id ORDER BY ev.event_index ASC;", [
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
		
		$db_events = $this->blockchain->app->run_query("SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id=:game_id AND ev.event_final_block=:block_id AND ev.event_final_block != ev.event_payout_block ORDER BY ev.event_index ASC;", [
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
	
	public function new_event_js($game_index, &$user, &$filter_arr, &$event_ids, $include_content=false) {
		$last_block_id = $this->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		$current_round = $this->block_to_round($mining_block_id);
		$html = "";
		$js = "";
		
		$user_id = false;
		if ($user) $user_game = $this->blockchain->app->fetch_user_game($user->db_user['user_id'], $this->game_id);
		
		if (!$include_content) {
			$js .= "for (var i=0; i<games[".$game_index."].events.length; i++) {\n";
			$js .= "\tgames[".$game_index."].events[i].deleted = true;\n";
			$js .= "\t$('#game".$game_index."_event'+i).remove();\n";
			$js .= "}\n";
			$js .= "games[".$game_index."].events.length = 0;\n";
			$js .= "games[".$game_index."].events = [];\n";
		}
		
		$these_events = $this->events_by_block($last_block_id, $filter_arr);
		$event_ids = "";
		
		for ($i=0; $i<count($these_events); $i++) {
			$event = $these_events[$i];
			$event_ids .= $event->db_event['event_id'].",";
			
			$js .= '
			games['.$game_index.'].events['.$i.'] = new GameEvent(games['.$game_index.'], '.$i.', '.$event->db_event['event_id'].', '.$event->db_event['event_index'].', '.$event->db_event['num_voting_options'].', "'.$event->db_event['vote_effectiveness_function'].'", "'.$event->db_event['effectiveness_param1'].'", "'.$event->db_event['option_block_rule'].'", '.$this->blockchain->app->quote_escape($event->db_event['event_name']).', '.$event->db_event['event_starting_block'].', '.$event->db_event['event_final_block'].', '.$event->db_event['payout_rate'].');'."\n";
			
			$options_by_event = $this->blockchain->app->fetch_options_by_event($event->db_event['event_id'], true);
			
			$j=0;
			while ($option = $options_by_event->fetch()) {
				$has_votingaddr = "true";
				$js .= "games[".$game_index."].events[".$i."].options.push(new EventOption(games[".$game_index."].events[".$i."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", ".$this->blockchain->app->quote_escape($option['name']).", 0, ".$has_votingaddr.", ".$this->blockchain->app->quote_escape($option['image_url'])."));\n";
				$j++;
			}
			$html .= "<div id='game".$game_index."_event".$i."' class='game_event_inner'><div id='game".$game_index."_event".$i."_display' class='game_event_display'>";
			
			$html .= $these_events[$i]->event_html($user, false, true, $game_index, $i);
			
			$html .= "</div><div id='game".$game_index."_event".$i."_my_current_votes'>";
			if ($user) $html .= $these_events[$i]->my_votes_table($current_round, $user_game);
			$html .= '</div></div>';
			
			if ($event->db_event['option_block_rule'] == "football_match") $js .= 'games['.$game_index.'].events['.$i.'].refresh_time_estimate();'."\n";
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
		
		while ($io = $mature_ios->fetch()) {
			$ids_csv .= $io['io_id'].",";
		}
		if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, -1);
		return $ids_csv;
	}
	
	public function select_input_buttons($user_game) {
		$mature_ios = $this->blockchain->app->spendable_ios_in_account($user_game['account_id'], $this->db_game['game_id'], false, false);
		
		$js = "thisPageManager.chain_ios.length = 0;\n";
		$html = "<p>";
		if ($mature_ios->rowCount() == 0) {
			$html .= "You need ".$this->db_game['coin_name_plural']." to bet. To deposit ".$this->db_game['coin_name_plural'].", visit <a href=\"/accounts/?account_id=".$user_game['account_id']."\">your accounts page</a> to see a list of your addresses.";
			if ($this->db_game['buyin_policy'] != "none") {
				$html .= '<br/><button class="btn btn-sm btn-success" style="margin-top: 8px;" onclick="thisPageManager.manage_buyin(\'initiate\');"><i class="fas fa-shopping-cart"></i> &nbsp; Buy '.$this->db_game['coin_name_plural'].'</button>';
			}
		}
		$html .= "</p>\n";
		$input_buttons_html = "";
		
		$io_i = 0;
		
		while ($io = $mature_ios->fetch()) {
			$gios_by_io = $this->fetch_game_ios_by_io($io['io_id']);
			
			$js .= "thisPageManager.chain_ios[".$io_i."] = new ChainIO(".$io_i.", ".$io['io_id'].", ".$io['amount'].", '".$io['create_block_id']."');\n";
			
			while ($gio = $gios_by_io->fetch()) {
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
		
		$relevant_events = $this->blockchain->app->run_query("SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id=:game_id AND e.event_starting_block >= :from_block_id AND e.event_starting_block <= :to_block_id ORDER BY e.event_id ASC;", [
			'game_id' => $this->db_game['game_id'],
			'from_block_id' => $from_block_id,
			'to_block_id' => $to_block_id
		]);
		
		while ($db_event = $relevant_events->fetch()) {
			$js .= "if (typeof games[".$game_index."].all_events[".$db_event['event_index']."] == 'undefined') {";
			$js .= "games[".$game_index."].all_events[".$db_event['event_index']."] = new GameEvent(games[".$game_index."], ".$i.", ".$db_event['event_id'].", ".$db_event['num_voting_options'].', "'.$db_event['vote_effectiveness_function'].'", "'.$db_event['effectiveness_param1'].'", "'.$db_event['option_block_rule'].'", '.$this->blockchain->app->quote_escape($db_event['event_name']).', '.$db_event['event_starting_block'].', '.$db_event['event_final_block'].', '.$db_event['payout_rate'].');';
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
	
	public function all_pairs_points_to_index($num_options) {
		$points_to_index = [];
		$min_points = 1;
		$max_points = $num_options*2-3;
		$total_pairs = 0;
		$midpoint_num_pairs = floor($num_options/2);
		$midpoint_points = ceil($max_points/2);
		
		for ($points=$min_points; $points<=$max_points; $points++) {
			$dist_from_midpoint = abs($midpoint_points - $points);
			if ($num_options%2 == 0) $pairs_less_than_midpoint = ceil($dist_from_midpoint/2);
			else $pairs_less_than_midpoint = floor($dist_from_midpoint/2);
			$pairs_here = $midpoint_num_pairs - $pairs_less_than_midpoint;
			$points_to_index[$points] = (int) $total_pairs;
			$total_pairs += $pairs_here;
		}
		return $points_to_index;
	}
	
	public function event_index_to_all_pairs_points($points_to_index, $event_index) {
		for ($points=1; $points<count($points_to_index); $points++) {
			if ($points_to_index[$points] <= $event_index && $points_to_index[$points+1] > $event_index) return $points;
		}
		return count($points_to_index);
	}
	
	public function latest_event() {
		return $this->blockchain->app->run_query("SELECT * FROM events WHERE game_id=:game_id ORDER BY event_index DESC LIMIT 1;", [
			'game_id' => $this->db_game['game_id']
		])->fetch();
	}
	
	public function ensure_events_until_block($block_id) {
		$msg = "";
		$ensured_block = max((int)$this->db_game['events_until_block'], $this->db_game['game_starting_block']);
		
		if ($block_id > $ensured_block) {
			$this->blockchain->app->dbh->beginTransaction();
			
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
			
			if (!empty($this->db_game['module']) && !$this->get_definitive_peer()) {
				$db_last_gde = $this->blockchain->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_starting_block < :block_id ORDER BY event_index DESC LIMIT 1;", [
					'game_id' => $this->db_game['game_id'],
					'block_id' => $block_id
				])->fetch();
				
				$event_verbatim_vars = $this->blockchain->app->event_verbatim_vars();
				
				$gdes_to_add = $this->module->events_starting_between_blocks($this, $ensured_block, $block_id);
				
				$msg .= "Resetting ".count($gdes_to_add)." game defined events by module for blocks ".$ensured_block.":".$block_id."\n";
				
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
						if ((string)$gdes_to_add[$i]['event_outcome_block'] == "") $gdes_to_add[$i]['event_outcome_block'] = $gdes_to_add[$i]['event_payout_block'];
						$this->blockchain->app->check_set_gde($this, $gdes_to_add[$i], $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id'], $general_entity_type['entity_type_id']);
						$i++;
					}
				}
			}
			
			$last_used_starting_block = false;
			
			if (in_array($this->db_game['event_rule'], ["", "game_definition"])) {
				$optional_event_fields = ['sport_entity_id','league_entity_id','next_event_index','outcome_index','event_starting_time','event_final_time','event_payout_time','track_max_price','track_min_price','track_payout_price','track_name_short','track_entity_id','option_block_rule','external_identifier'];
				
				$change_gdes = $this->blockchain->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_index >= :from_event_index AND event_starting_block <= :block_id ORDER BY event_index ASC;", [
					'game_id' => $this->db_game['game_id'],
					'from_event_index' => $from_event_index,
					'block_id' => $block_id
				]);
				
				$msg .= "Ensuring ".$change_gdes->rowCount()." events from game definition.\n";
				
				while ($game_defined_event = $change_gdes->fetch()) {
					$event_start_time = microtime(true);
					
					$db_event = $this->fetch_event_by_index($game_defined_event['event_index']);
					
					if ($db_event) {
						$option_offset += $db_event['num_options'];
					}
					else {
						$gdo_r = $this->blockchain->app->fetch_game_defined_options($this->db_game['game_id'], $game_defined_event['event_index'], false, false);
						$num_options = $gdo_r->rowCount();
						
						$etype_url_id = $this->blockchain->app->normalize_username($game_defined_event['event_name']);
						
						$existing_event_type_r = $this->blockchain->app->run_query("SELECT * FROM event_types WHERE game_id=:game_id AND url_identifier=:url_identifier;", [
							'game_id' => $this->db_game['game_id'],
							'url_identifier' => $etype_url_id
						]);
						
						if ($existing_event_type_r->rowCount() == 0) {
							$this->blockchain->app->run_query("INSERT INTO event_types SET url_identifier=:url_identifier, name=:name, event_winning_rule=:event_winning_rule, vote_effectiveness_function=:vote_effectiveness_function, effectiveness_param1=:effectiveness_param1, max_voting_fraction=:max_voting_fraction, num_voting_options=:num_voting_options, default_option_max_width=:default_option_max_width;", [
								'url_identifier' => $etype_url_id,
								'name' => $game_defined_event['event_name'],
								'event_winning_rule' => $this->db_game['event_winning_rule'],
								'vote_effectiveness_function' => $this->db_game['default_vote_effectiveness_function'],
								'effectiveness_param1' => $this->db_game['default_effectiveness_param1'],
								'max_voting_fraction' => $this->db_game['default_max_voting_fraction'],
								'num_voting_options' => $gdo_r->rowCount(),
								'default_option_max_width' => $this->db_game['default_option_max_width']
							]);
							$event_type_id = $this->blockchain->app->last_insert_id();
							
							$event_type = $this->blockchain->app->run_query("SELECT * FROM event_types WHERE event_type_id=:event_type_id;", ['event_type_id' => $event_type_id])->fetch();
						}
						else $event_type = $existing_event_type_r->fetch();
						
						$event_outcome_block = $game_defined_event['event_outcome_block'] ? $game_defined_event['event_outcome_block'] : $game_defined_event['event_payout_block'];
						
						$new_event_params = [
							'game_id' => $this->db_game['game_id'],
							'event_type_id' => $event_type['event_type_id'],
							'event_index' => $game_defined_event['event_index'],
							'event_starting_block' => $game_defined_event['event_starting_block'],
							'event_final_block' => $game_defined_event['event_final_block'],
							'event_outcome_block' => $event_outcome_block,
							'event_payout_block' => $game_defined_event['event_payout_block'],
							'payout_rule' => $game_defined_event['payout_rule'],
							'payout_rate' => $game_defined_event['payout_rate'],
							'event_name' => $game_defined_event['event_name'],
							'option_name' => $game_defined_event['option_name'],
							'option_name_plural' => $game_defined_event['option_name_plural'],
							'num_options' => $num_options,
							'option_max_width' => $event_type['default_option_max_width']
						];
						$new_event_q = "INSERT INTO events SET game_id=:game_id, event_type_id=:event_type_id, event_index=:event_index, event_starting_block=:event_starting_block, event_final_block=:event_final_block, event_outcome_block=:event_outcome_block, event_payout_block=:event_payout_block, payout_rule=:payout_rule, payout_rate=:payout_rate, event_name=:event_name, option_name=:option_name, option_name_plural=:option_name_plural, num_options=:num_options, option_max_width=:option_max_width";
						
						foreach ($optional_event_fields as $optional_event_field) {
							if ((string)$game_defined_event[$optional_event_field] != "") {
								$new_event_q .= ", ".$optional_event_field."=:".$optional_event_field;
								$new_event_params[$optional_event_field] = $game_defined_event[$optional_event_field];
							}
						}
						$new_event_q .= ";";
						$this->blockchain->app->run_query($new_event_q, $new_event_params);
						$event_id = $this->blockchain->app->last_insert_id();
						
						$option_i = 0;
						while ($game_defined_option = $gdo_r->fetch()) {
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
							$new_option_q = "INSERT INTO options SET event_id=:event_id, name=:name, vote_identifier=:vote_identifier, option_index=:option_index, event_option_index=:event_option_index, entity_id=:entity_id, target_probability=:target_probability, image_id=:image_id";
							
							$new_option_params['image_id'] = null;
							if (!empty($game_defined_option['entity_id'])) {
								$entity = $this->blockchain->app->fetch_entity_by_id($game_defined_option['entity_id']);
								if (!empty($entity['default_image_id'])) {
									$new_option_params['image_id'] = $entity['default_image_id'];
								}
							}
							
							$this->blockchain->app->run_query($new_option_q, $new_option_params);
							$option_i++;
						}
						
						$option_offset += $num_options;
						$add_count++;
					}
				}
			}
			
			if ($this->db_game['event_rule'] == "entity_type_option_group" || $this->db_game['event_rule'] == "single_event_series" || $this->db_game['event_rule'] == "all_pairs") {
				if ($this->db_game['event_rule'] == "entity_type_option_group") {
					$entity_type = $this->blockchain->app->fetch_entity_type_by_id($this->db_game['event_entity_type_id']);
					
					if (!$entity_type) die("Error: game type ".$this->db_game['game_type_id']." requires an event_entity_type_id.\n");
				}
				
				$option_group = $this->blockchain->app->fetch_group_by_id($this->db_game['option_group_id']);
				
				$db_option_entities = $this->blockchain->app->run_query("SELECT * FROM entities e JOIN option_group_memberships mem ON e.entity_id=mem.entity_id WHERE mem.option_group_id=:option_group_id ORDER BY e.entity_id ASC;", ['option_group_id'=>$this->db_game['option_group_id']])->fetchAll();
				
				if ($this->db_game['event_rule'] == "all_pairs") {
					$all_pairs_points_to_index = $this->all_pairs_points_to_index(count($db_option_entities));
				}
				
				$event_i = 0;
				$round_option_i = 1;
				
				if ($ensured_round > 0) $start_round = $ensured_round+1;
				else $start_round = $this->block_to_round($this->db_game['game_starting_block']);
				
				if ($this->db_game['event_rule'] == "entity_type_option_group") {
					$num_event_types = (int)($this->blockchain->app->run_query("SELECT COUNT(*) FROM entities WHERE entity_type_id=:entity_type_id ORDER BY entity_id ASC;", ['entity_type_id'=>$entity_type['entity_type_id']])->fetch()['COUNT(*)']);
					
					for ($i=$start_round; $i<=$round_id; $i++) {
						$round_first_event_i = $this->db_game['events_per_round']*($i-$this->block_to_round($this->db_game['game_starting_block']));
						$offset = $round_first_event_i%$num_event_types;
						$entities_by_round_params = [
							'entity_type_id' => $entity_type['entity_type_id'],
							'events_per_round' => $this->db_game['events_per_round']
						];
						$entities_by_round_q = "SELECT * FROM entities WHERE entity_type_id=:entity_type_id ORDER BY entity_id ASC LIMIT :events_per_round";
						if ($offset > 0) {
							$entities_by_round_q .= " OFFSET ".((int)$offset);
						}
						$entities_by_round = $this->blockchain->app->run_limited_query($entities_by_round_q, $entities_by_round_params);
						
						for ($j=0; $j<$this->db_game['events_per_round']; $j++) {
							$event_i = $round_first_event_i+$j;
							$event_entity = $entities_by_round->fetch();
							$event_type = $this->add_event_type($db_option_entities, $event_entity, $event_i);
							$this->add_event_by_event_type($event_type, $db_option_entities, $option_group, $round_option_i, $event_i, $event_type['name'], $event_entity);
							$add_count++;
						}
					}
				}
				else if ($this->db_game['event_rule'] == "all_pairs") {
					$max_points = count($db_option_entities)-1;
					$num_pairs = (pow($max_points, 2) + $max_points)/2;
					
					for ($i=$start_round; $i<=$round_id; $i++) {
						$round_first_event_i = $this->db_game['events_per_round']*($i-$this->block_to_round($this->db_game['game_starting_block']));
						$offset = $round_first_event_i%$num_pairs;
						
						for ($j=0; $j<$this->db_game['events_per_round']; $j++) {
							$event_i = $round_first_event_i+$j;
							$event_ii = $event_i%$num_pairs;
							$points = $this->event_index_to_all_pairs_points($all_pairs_points_to_index, $event_ii);
							$this_points_start_index = $all_pairs_points_to_index[$points];
							$index_within_points = $event_ii-$this_points_start_index;
							if ($points <= $max_points) $first_entity_index = $index_within_points;
							else $first_entity_index = $points - $max_points + $index_within_points;
							$second_entity_index = $points - $first_entity_index;
							
							$option_entities[0] = $db_option_entities[$first_entity_index];
							$option_entities[1] = $db_option_entities[$second_entity_index];
							$event_type = $this->add_event_type($option_entities, false, $event_i);
							$this->add_event_by_event_type($event_type, $option_entities, $option_group, $round_option_i, $event_i, $event_type['name'], false);
							$add_count++;
						}
					}
				}
				else {
					$event_type = $this->add_event_type($db_option_entities, false, false);
					for ($i=$start_round-1; $i<=$round_id; $i++) {
						$event_i = $i-$this->block_to_round($this->db_game['game_starting_block']);
						$event_name = $event_type['name']." #".($event_i+1);
						$this->add_event_by_event_type($event_type, $db_option_entities, $option_group, $round_option_i, $event_i, $event_name, false);
						$event_i++;
						$add_count++;
					}
				}
			}
			
			$msg .= "Added ".$add_count." events\n";
			
			$this->set_events_until_block($block_id);
			
			$this->blockchain->app->dbh->commit();
		}
		return $msg;
	}
	
	public function set_events_until_block($block_id) {
		$this->blockchain->app->run_query("UPDATE games SET events_until_block=:block_id WHERE game_id=:game_id;", [
			'block_id' => $block_id,
			'game_id' => $this->db_game['game_id']
		]);
	}
	
	public function add_event_type($db_option_entities, $event_entity, $event_i) {
		$head_to_head = count($db_option_entities) == 2 && !empty($db_option_entities[0]['last_name']);
		
		if ($head_to_head) {
			$event_type_name = $db_option_entities[0]['last_name']." vs ".$db_option_entities[1]['last_name'];
			if ($event_entity) $event_type_name .= " in ".$event_entity['entity_name'];
			$event_type_identifier = strtolower($db_option_entities[0]['last_name']."-vs-".$db_option_entities[1]['last_name']);
			if ($event_entity) $event_type_identifier .= "-".strtolower($event_entity['entity_name']);
		}
		else {
			if ($event_entity) {
				$event_type_name = $event_entity['entity_name']." ".ucwords($this->db_game['event_type_name']);
				$event_type_identifier = strtolower($event_entity['entity_name']."-".$this->db_game['event_type_name']);
			}
			else {
				$event_type_name = $this->db_game['event_type_name'];
				if (!empty($event_i)) $event_type_name .= " - Round #".($event_i+1);
				$event_type_identifier = str_replace(" ", "-", strtolower($this->db_game['event_type_name']));
				if (!empty($event_i)) $event_type_identifier .= "-round-".($event_i+1);
			}
		}
		
		$event_type = $this->blockchain->app->run_query("SELECT * FROM event_types WHERE game_id=:game_id AND url_identifier=:url_identifier;", [
			'game_id' => $this->db_game['game_id'],
			'url_identifier' => $event_type_identifier
		])->fetch();
		
		if (!$event_type) {
			$new_event_type_params = [
				'game_id' => $this->db_game['game_id'],
				'option_group_id' => $this->db_game['option_group_id'],
				'name' => $event_type_name,
				'url_identifier' => $event_type_identifier,
				'num_voting_options' => count($db_option_entities),
				'vote_effectiveness_function' => $this->db_game['default_vote_effectiveness_function'],
				'effectiveness_param1' => $this->db_game['default_effectiveness_param1'],
				'max_voting_fraction' => $this->db_game['default_max_voting_fraction']
			];
			$new_event_type_q = "INSERT INTO event_types SET game_id=:game_id, event_winning_rule='max_below_cap', option_group_id=:option_group_id";
			if ($head_to_head) {
				$new_event_type_q .= ", primary_entity_id=:primary_entity_id, secondary_entity_id=:secondary_entity_id";
				$new_event_type_params['primary_entity_id'] = $db_option_entities[0]['entity_id'];
				$new_event_type_params['secondary_entity_id'] = $db_option_entities[1]['entity_id'];
			}
			if ($event_entity) {
				$new_event_type_q .= ", entity_id=:entity_id";
				$new_event_type_params['entity_id'] = $event_entity['entity_id'];
			}
			$new_event_type_q .= ", name=:name, url_identifier=:url_identifier, num_voting_options=:num_voting_options, vote_effectiveness_function=:vote_effectiveness_function, effectiveness_param1=:effectiveness_param1, max_voting_fraction=:max_voting_fraction;";
			$this->blockchain->app->run_query($new_event_type_q, $new_event_type_params);
			$event_type_id = $this->blockchain->app->last_insert_id();
			
			$event_type = $this->blockchain->app->run_query("SELECT * FROM event_types WHERE event_type_id=:event_type_id;", ['event_type_id'=>$event_type_id])->fetch();
		}
		return $event_type;
	}
	
	public function add_event_by_event_type(&$event_type, &$db_option_entities, &$option_group, &$round_option_i, &$event_i, $event_name, $event_entity) {
		$skip_blocks = ceil(($this->db_game['game_starting_block']-1)/$this->db_game['round_length'])*$this->db_game['round_length'];
		$starting_round = floor($event_i/$this->db_game['events_per_round'])+1;
		$event_starting_block = $skip_blocks+($starting_round-1)*$this->db_game['round_length']+1;
		$event_final_block = $skip_blocks+$starting_round*$this->db_game['round_length'];
		
		if ($event_i%$this->db_game['events_per_round'] == 0) $round_option_i = 0;
		
		$existing_event = $this->fetch_event_by_index($event_i);
		
		if (!$existing_event) {
			$this->blockchain->app->run_query("INSERT INTO events SET game_id=:game_id, event_index=:event_index, event_type_id=:event_type_id, event_starting_block=:event_starting_block, event_final_block=:event_final_block, event_payout_block=:event_payout_block, payout_rule=:payout_rule, event_name=:event_name, option_name=:option_name, option_name_plural=:option_name_plural, option_max_width=:option_max_width;", [
				'game_id' => $this->db_game['game_id'],
				'event_index' => $event_i,
				'event_type_id' => $event_type['event_type_id'],
				'event_starting_block' => $event_starting_block,
				'event_final_block' => $event_final_block,
				'event_payout_block' => ($this->db_game['default_payout_block_delay']+$event_final_block),
				'payout_rule' => $this->db_game['default_payout_rule'],
				'event_name' => $event_name,
				'option_name' => $option_group['option_name'],
				'option_name_plural' => $option_group['option_name_plural'],
				'option_max_width' => $this->db_game['default_option_max_width']
			]);
			$event_id = $this->blockchain->app->last_insert_id();
			
			for ($i=0; $i<count($db_option_entities); $i++) {
				if (!empty($event_entity)) $option_name = $db_option_entities[$i]['last_name']." wins ".$event_entity['entity_name'];
				else $option_name = $db_option_entities[$i]['entity_name'];
				$vote_identifier = $this->blockchain->app->option_index_to_vote_identifier($round_option_i);
				
				$this->blockchain->app->run_query("INSERT INTO options SET event_id=:event_id, entity_id=:entity_id, membership_id=:membership_id, image_id=:image_id, name=:name, vote_identifier=:vote_identifier, option_index=:option_index, event_option_index=:event_option_index;", [
					'event_id' => $event_id,
					'entity_id' => $db_option_entities[$i]['entity_id'],
					'membership_id' => $db_option_entities[$i]['membership_id'],
					'image_id' => $db_option_entities[$i]['default_image_id'],
					'name' => $option_name,
					'vote_identifier' => $vote_identifier,
					'option_index' => $round_option_i,
					'event_option_index' => $i
				]);
				
				$round_option_i++;
			}
		}
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
			$imageless_options = $this->blockchain->app->run_query("SELECT * FROM options op JOIN events ev ON op.event_id=ev.event_id LEFT JOIN entities en ON op.entity_id=en.entity_id WHERE ev.game_id=:game_id AND op.image_id IS NULL GROUP BY en.entity_id;", [
				'game_id' => $this->db_game['game_id']
			]);
			
			while ($imageless_option = $imageless_options->fetch()) {
				$api_url = $definitive_peer['base_url']."/api/".$this->db_game['url_identifier']."/events/".$imageless_option['event_index']."/options/".$imageless_option['event_option_index'];
				$api_response = json_decode($this->blockchain->app->safe_fetch_url($api_url));
				
				if ($api_response->status_code == 1) {
					$recommended_entity_type = $this->blockchain->app->check_set_entity_type($api_response->option->entity_type);
					$recommended_entity = $this->blockchain->app->check_set_entity($recommended_entity_type['entity_type_id'], $api_response->option->entity);
					
					if (empty($recommended_entity['default_image_id'])) {
						$db_image = $this->blockchain->app->set_entity_image_from_url($api_response->option->image_url, $recommended_entity['entity_id'], $error_message);
					}
					else $error_message .= $imageless_option['name']." already has an image.\n";
				}
				else $error_message .= "Failed to set image for ".$imageless_option['name'].": ".$api_url."\n";
			}
		}
		else $error_message .= "This game does not have a definitive peer.\n";
		
		return $error_message;
	}
	
	public function sync_with_definitive_peer($print_debug) {
		$error_message = "";
		$definitive_peer = $this->get_definitive_peer();
		
		if ($print_debug) {
			echo "Syncing with definitive peer..\n";
			$this->blockchain->app->flush_buffers();
		}
		
		if ($definitive_peer) {
			$send_hash = $this->db_game['cached_definition_hash'];
			
			if (empty($send_hash)) {
				GameDefinition::set_cached_definition_hashes($this);
				$send_hash = $this->db_game['cached_definition_hash'];
			}
			
			$api_url = $definitive_peer['base_url']."/api/".$this->db_game['url_identifier']."/definition/?definition_hash=".$send_hash;
			
			if ($print_debug) {
				echo $api_url."\n";
				$this->blockchain->app->flush_buffers();
			}
			
			$api_response = json_decode($this->blockchain->app->safe_fetch_url($api_url));

			if ($api_response->status_code == 1) {
				if ($api_response->definition->url_identifier == $this->db_game['url_identifier']) {
					if ($api_response->definition_hash == $send_hash) $error_message = "Already in sync.\n";
					else {
						$this->blockchain->app->log_message("Syncing ".$this->db_game['name']." from ".$api_url);
						$ref_user = false;
						$db_new_game = false;
						GameDefinition::set_game_from_definition($this->blockchain->app, $api_response->definition, $ref_user, $error_message, $db_new_game, true);
					}
				}
				else $error_message .= "Sync canceled: definitive peer tried to change the game identifier.\n";
			}
			else $error_message .= $api_response->message."\n";
			
			$error_message .= $this->set_option_images_from_definitive_peer();
		}
		else $error_message .= "This game does not have a definitive peer.\n";
		
		if ($print_debug) {
			echo $error_message;
			$this->blockchain->app->flush_buffers();
		}
		
		return $error_message;
	}
	
	public function sync($show_debug, $max_load_seconds) {
		$sync_start_time = microtime(true);
		$last_set_loaded_time = microtime(true);
		
		if ((string) $this->db_game['loaded_until_block'] == "") $this->set_loaded_until_block(null);
		
		$load_block_height = $this->db_game['loaded_until_block']+1;
		$to_block_height = $this->blockchain->last_block_id();
		
		$ensure_block_id = $to_block_height+1;
		
		if (!empty($this->db_game['definitive_game_peer_id']) && $this->db_game['loaded_until_block'] == $this->blockchain->last_block_id()) {
			$sync_definitive_message = $this->sync_with_definitive_peer($show_debug);
			
			if ($this->db_game['finite_events'] == 1) $ensure_block_id = max($ensure_block_id, $this->max_gde_starting_block());
			$ensure_events_debug_text = $this->ensure_events_until_block($ensure_block_id);
			
			GameDefinition::set_cached_definition_hashes($this);
			
			if ($show_debug) {
				echo $sync_definitive_message;
				echo $ensure_events_debug_text;
				$this->blockchain->app->flush_buffers();
			}
		}
		else if ($this->db_game['finite_events'] == 1) $ensure_block_id = max($ensure_block_id, $this->max_gde_starting_block());
		
		$ensure_events_debug_text = $this->ensure_events_until_block($ensure_block_id);
		if ($show_debug) {
			echo $ensure_events_debug_text;
			$this->blockchain->app->flush_buffers();
		}
		
		if ($to_block_height >= $load_block_height) {
			if ($show_debug) {
				echo $this->db_game['name'].".. loading blocks ".$load_block_height." to ".$to_block_height."\n";
				$this->blockchain->app->flush_buffers();
			}
			
			if ($load_block_height == $this->db_game['game_starting_block']) $game_io_index = 0;
			else {
				$prev_block = $this->fetch_game_block_by_height($load_block_height-1);
				$game_io_index = $prev_block['max_game_io_index'];
			}
			
			for ($block_height=$load_block_height; $block_height<=$to_block_height; $block_height++) {
				list($successful, $log_text, $bulk_to_block) = $this->add_block($block_height, $game_io_index);
				if ($bulk_to_block) $block_height = $bulk_to_block;
				
				if ($successful) $this->set_loaded_until_block($block_height);
				
				if ($show_debug) {
					echo $log_text;
					$this->blockchain->app->flush_buffers();
				}
				
				if (microtime(true)-$last_set_loaded_time >= 3) {
					if ($max_load_seconds && microtime(true)-$sync_start_time >= $max_load_seconds) {
						$block_height = $to_block_height+1;
					}
					else $last_set_loaded_time = microtime(true);
				}
				if (!$successful) $block_height = $to_block_height+1;
			}
			
			$this->blockchain->app->flush_buffers();
		}
		else if ($show_debug) {
			echo $this->db_game['name']." is already fully loaded.\n";
			$this->blockchain->app->flush_buffers();
		}
		
		$this->update_option_votes();
	}
	
	public function fetch_game_block_by_height($height) {
		return $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_id=:game_id AND block_id=:block_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $height
		])->fetch();
	}
	
	public function add_block($block_height, &$game_io_index) {
		$successful = true;
		$start_time = microtime(true);
		$msg = "Adding block ".$block_height." to ".$this->db_game['name']."\n";
		$log_text = $msg;
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
					$log_text .= "Game block already exists: resetting from ".($block_height-1)."\n";
					$this->reset_blocks_from_block($block_height-1);
					$this->blockchain->app->log_message("Reset ".$this->db_game['name']." due to error on block #".$block_height);
				}
				else $log_text .= "Failed: game block already exists.\n";
				
				$successful = false;
				return array($successful, $log_text, $bulk_to_block);
			}
			else {
				$msg = "Creating new game block #".$block_height."\n";
				$log_text .= $msg;
				
				$this->blockchain->app->run_query("INSERT INTO game_blocks SET game_id=:game_id, block_id=:block_id, locally_saved=0, num_transactions=0, time_created=:time_created;", [
					'game_id' => $this->db_game['game_id'],
					'block_id' => $block_height,
					'time_created' => time()
				]);
				$game_block_id = $this->blockchain->app->last_insert_id();
				
				$game_block = $this->blockchain->app->run_query("SELECT * FROM game_blocks WHERE game_block_id=:game_block_id;", ['game_block_id'=>$game_block_id])->fetch();
			}
			
			if (!in_array($this->db_game['buyin_policy'], ["none","for_sale"])) {
				$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
				
				$buyin_transactions = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.create_block_id=:block_height AND io.address_id=:escrow_address_id GROUP BY t.transaction_id;", [
					'block_height' => $block_height,
					'escrow_address_id' => $escrow_address['address_id']
				]);
				
				$msg = "Looping through ".$buyin_transactions->rowCount()." buyin transactions\n";
				$log_text .= $msg;
				
				while ($buyin_tx = $buyin_transactions->fetch()) {
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
							$deposits_to_escrow = $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.spend_transaction_id='".$buyin_tx['transaction_id']."';");
							
							if ($deposits_to_escrow->rowCount() == 0) {
								$this->process_buyin_transaction($buyin_tx);
							}
						}
					}
				}
			}
			
			$keep_looping = true;
			$relevant_tx_count = 0;
			$events_by_option_id = [];
			
			do {
				$relevant_transactions = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND t.block_id=:block_id AND t.blockchain_id=:blockchain_id AND gio.spend_round_id IS NULL GROUP BY t.transaction_id ORDER BY t.position_in_block ASC;", [
					'game_id' => $this->db_game['game_id'],
					'block_id' => $block_height,
					'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id']
				]);
				
				if ($relevant_transactions->rowCount() > 0) {
					$relevant_tx_count += $relevant_transactions->rowCount();
					
					while ($db_transaction = $relevant_transactions->fetch()) {
						$tx_game_input_sum = 0;
						$crd_in = 0;
						$cbd_in = 0;
						$game_out_index = 0;
						
						$input_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id;", [
							'transaction_id' => $db_transaction['transaction_id'],
							'game_id' => $this->db_game['game_id']
						]);
						
						while ($input_io = $input_ios->fetch()) {
							$tx_game_input_sum += $input_io['colored_amount'];
							
							$gio_in_coin_blocks = $input_io['colored_amount']*($block_height - $input_io['create_block_id']);
							$gio_in_coin_rounds = $input_io['colored_amount']*($round_id - $input_io['create_round_id']);
							$cbd_in += $gio_in_coin_blocks;
							$crd_in += $gio_in_coin_rounds;
							
							$update_input_q = "UPDATE transaction_game_ios SET spend_round_id=:spend_round_id, coin_blocks_created=:coin_blocks_created, coin_rounds_created=:coin_rounds_created";
							$update_input_params = [
								'spend_round_id' => $round_id,
								'coin_blocks_created' => $gio_in_coin_blocks,
								'coin_rounds_created' => $gio_in_coin_rounds,
								'game_io_id' => $input_io['game_io_id']
							];
							
							if ($input_io['is_coinbase'] == 1) {
								if (empty($events_by_option_id[$input_io['option_id']])) {
									$db_event = $this->blockchain->app->run_query("SELECT ev.*, et.* FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE ev.event_id=:event_id;", ['event_id'=>$input_io['event_id']])->fetch();
									$events_by_option_id[$input_io['option_id']] = new Event($this, $db_event, false);
								}
								
								$resolved_before_spent = $input_io['resolved_before_spent'];
								
								if ($block_height < $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
									$resolved_before_spent = 0;
								}
								else if ($block_height >= $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
									$resolved_before_spent = 1;
								}
								
								if ($resolved_before_spent != $input_io['resolved_before_spent']) {
									$update_input_q .= ", resolved_before_spent=:resolved_before_spent";
									$update_input_params['resolved_before_spent'] = $resolved_before_spent;
								}
							}
							
							$update_input_q .= " WHERE game_io_id=:game_io_id;";
							
							$this->blockchain->app->run_query($update_input_q, $update_input_params);
						}
						
						$output_ios = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id;", [
							'create_transaction_id' => $db_transaction['transaction_id']
						]);
						
						$tx_chain_output_sum = 0;
						$tx_chain_destroy_sum = 0;
						$tx_chain_separator_sum = 0;
						$tx_chain_passthrough_sum = 0;
						$tx_chain_receiver_sum = 0;
						
						while ($output_io = $output_ios->fetch()) {
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
						
						$regular_outputs = $this->blockchain->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND a.is_destroy_address=0 AND a.is_separator_address=0 AND a.is_passthrough_address=0 AND io.is_receiver=0 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction['transaction_id']]);
						$num_regular_outputs = $regular_outputs->rowCount();
						$output_i = 0;
						
						$this->blockchain->app->dbh->beginTransaction();
						$this->blockchain->app->run_query("DELETE gio.* FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.create_transaction_id=:transaction_id;", ['transaction_id'=>$db_transaction['transaction_id']]);
						
						if ($num_regular_outputs > 0) {
							$insert_q = "INSERT INTO transaction_game_ios (game_id, io_id, address_id, game_out_index, game_io_index, is_coinbase, coin_blocks_destroyed, coin_rounds_destroyed, create_block_id, create_round_id, colored_amount, destroy_amount, option_id, contract_parts, event_id, effectiveness_factor, votes, effective_destroy_amount, is_resolved, resolved_before_spent) VALUES ";
							
							while ($regular_output = $regular_outputs->fetch()) {
								$payout_insert_q = "";
								
								$gio_amount = floor($tx_game_nondestroy_amount*$regular_output['amount']/$tx_chain_regular_sum);
								$cbd = floor($cbd_in*$regular_output['amount']/$tx_chain_regular_sum);
								$crd = floor($crd_in*$regular_output['amount']/$tx_chain_regular_sum);
								
								if ($output_i == $num_regular_outputs-1) $this_destroy_amount = $tx_game_destroy_amount-$game_destroy_sum;
								else $this_destroy_amount = floor($tx_game_destroy_amount*$regular_output['amount']/$tx_chain_regular_sum);
								
								$game_destroy_sum += $this_destroy_amount;
								
								$game_io_index++;
								$insert_q .= "('".$this->db_game['game_id']."', '".$regular_output['io_id']."', '".$regular_output['address_id']."', '".$game_out_index."', '".$game_io_index."', 0, '".$cbd."', '".$crd."', '".$block_height."', '".$round_id."', ";
								$game_out_index++;
								
								if ($regular_output['option_index'] != "") {
									$option_id = $this->option_index_to_option_id_in_block($regular_output['option_index'], $block_height);
									if ($option_id) {
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
										
										if (empty($events_by_option_id[$option_id])) {
											$db_event = $this->blockchain->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id=:option_id;", ['option_id'=>$option_id])->fetch();
											$events_by_option_id[$option_id] = new Event($this, $db_event, false);
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
							$this->blockchain->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.parent_io_id=gio.game_io_id-1 WHERE io.create_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=1;", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
							$this->blockchain->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.payout_io_id=gio.game_io_id+1 WHERE gio.event_id IS NOT NULL AND io.create_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=0;", [
								'transaction_id' => $db_transaction['transaction_id'],
								'game_id' => $this->db_game['game_id']
							]);
						}
						
						$unresolved_inputs = $this->blockchain->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=1 AND gio.resolved_before_spent=0 ORDER BY io.in_index ASC;", [
							'transaction_id' => $db_transaction['transaction_id'],
							'game_id' => $this->db_game['game_id']
						])->fetchAll();
						
						$receiver_outputs = $this->blockchain->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.is_receiver=1 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction['transaction_id']])->fetchAll();
						
						if (count($unresolved_inputs) > 0 && count($receiver_outputs) > 0) {
							$insert_q = "INSERT INTO transaction_game_ios (parent_io_id, game_id, io_id, address_id, game_out_index, game_io_index, is_coinbase, colored_amount, destroy_amount, coin_blocks_destroyed, coin_rounds_destroyed, create_block_id, create_round_id, option_id, contract_parts, event_id, is_resolved, resolved_before_spent) VALUES ";
							
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
				else $keep_looping = false;
			}
			while ($keep_looping);
			
			if (!in_array($this->db_game['buyin_policy'], ["none","for_sale",""])) $this->process_sellouts_in_block($block_height);
			
			$filter_arr = ['require_option_block_rule'=>true];
			$events = $this->events_by_block($block_height, $filter_arr);
			
			for ($i=0; $i<count($events); $i++) {
				$events[$i]->process_option_blocks($game_block, count($events), $events[0]->db_event['event_index']);
			}
			
			$finalblock_events = $this->events_by_final_block($block_height);
			
			foreach ($finalblock_events as $finalblock_event) {
				$finalblock_event->update_option_votes($block_height, false);
			}
			
			$set_outcome_events = $this->events_by_outcome_block($block_height);
			
			foreach ($set_outcome_events as $set_outcome_event) {
				if (!empty($this->module) && method_exists($this->module, "set_event_outcome")) {
					if ($this->blockchain->db_blockchain['p2p_mode'] == "rpc") {
						$this->blockchain->load_coin_rpc();
					}
					
					$log_text .= $this->module->set_event_outcome($this, $set_outcome_event);
				}
				if (!empty($this->module) && method_exists($this->module, "event_index_to_next_event_index")) {
					$event_index = $this->module->event_index_to_next_event_index($set_outcome_event->db_event['event_index']);
					$this->set_event_labels_by_gde($event_index);
				}
			}
			
			$payout_events = $this->events_by_payout_block($block_height);
			
			foreach ($payout_events as $payout_event) {
				$payout_event->pay_out_event();
			}
			
			$this->blockchain->app->run_query("UPDATE transaction_game_ios gio JOIN events ev ON gio.event_id=ev.event_id SET gio.is_resolved=1 WHERE ev.game_id=:game_id AND ev.event_payout_block=:block_id AND (ev.outcome_index IS NOT NULL OR ev.track_payout_price IS NOT NULL);", [
				'game_id' => $this->db_game['game_id'],
				'block_id' => $block_height
			]);
			
			$this->blockchain->app->run_query("UPDATE game_blocks SET locally_saved=1, time_loaded=:current_time, load_time=load_time+:add_load_time, max_game_io_index=:max_game_io_index WHERE game_block_id=:game_block_id;", [
				'current_time' => time(),
				'add_load_time' => (microtime(true)-$start_time),
				'max_game_io_index' => $game_io_index,
				'game_block_id' => $game_block['game_block_id']
			]);
			
			$this->set_block_stats($game_block);
			
			// If nothing was added this block & it's allowed, add game blocks in bulk
			if ($relevant_tx_count == 0 && in_array($this->db_game['buyin_policy'], ["none", "for_sale"])) {
				$last_block_id = $this->blockchain->last_block_id();
				
				if ($last_block_id > $block_height+5) {
					$next_required_block_id = $last_block_id;
					
					$next_spend_block = $this->blockchain->app->run_query("SELECT t.block_id FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND t.block_id>:block_id AND t.blockchain_id=:blockchain_id ORDER BY t.block_id ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'block_id' => $block_height,
						'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id']
					])->fetch();
					
					if ($next_spend_block) {
						if ($next_spend_block['block_id'] < $next_required_block_id) $next_required_block_id = $next_spend_block['block_id'];
					}
					
					$next_event_final_block = $this->blockchain->app->run_query("SELECT event_final_block FROM events WHERE game_id=:game_id AND event_final_block > :block_height ORDER BY event_final_block ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'block_height' => $block_height
					])->fetch();
					
					if ($next_event_final_block) {
						if ($next_event_final_block['event_final_block'] < $next_required_block_id) $next_required_block_id = $next_event_final_block['event_final_block'];
					}
					
					$next_event_payout_block = $this->blockchain->app->run_query("SELECT event_payout_block FROM events WHERE game_id=:game_id AND event_payout_block > :block_height ORDER BY event_payout_block ASC LIMIT 1;", [
						'game_id' => $this->db_game['game_id'],
						'block_height' => $block_height
					])->fetch();
					
					if ($next_event_payout_block) {
						if ($next_event_payout_block['event_payout_block'] < $next_required_block_id) $next_required_block_id = $next_event_payout_block['event_payout_block'];
					}
					
					$bulk_from_block = $block_height+1;
					$bulk_to_block = $next_required_block_id-1;
					$ref_time = time();
					
					if ($bulk_from_block < $bulk_to_block) {
						$log_text .= "Adding ".($bulk_to_block-$bulk_from_block)." game blocks in bulk.. ".$bulk_from_block." to ".$bulk_to_block."\n";
						
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
		}
		else {
			$successful = false;
			$msg = "Skipping.. block ".$block_height." does not exist on ".$this->blockchain->db_blockchain['url_identifier']."\n";
			$log_text .= $msg;
		}
		
		return array($successful, $log_text, $bulk_to_block);
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
		$this->blockchain->app->run_query("UPDATE game_defined_events SET outcome_index=:outcome_index WHERE game_id=:game_id AND event_index=:event_index;", [
			'game_id' => $this->db_game['game_id'],
			'event_index' => $event_index,
			'outcome_index' => $outcome_index
		]);
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
		
		$ios_q = "SELECT a.*, p.*, a.address_id AS address_id, gio.contract_parts, gio.is_coinbase AS is_coinbase, gio.colored_amount AS colored_amount, gio.is_resolved AS is_resolved, gio.game_io_id, gio.game_out_index, gio.game_io_id AS game_io_id, op.*, ev.*, p.contract_parts AS total_contract_parts, p.votes, op.votes AS option_votes, op.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, io.spend_status, io.is_destroy, io.is_separator, io.is_passthrough, io.is_receiver";
		if ($in_out == "in") $ios_q .= ", t.tx_hash FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id";
		else $ios_q .= " FROM transaction_ios io";
		$ios_q .= " JOIN transaction_game_ios gio ON io.io_id=gio.io_id LEFT JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN addresses a ON io.address_id=a.address_id LEFT JOIN options op ON gio.option_id=op.option_id LEFT JOIN events ev ON op.event_id=ev.event_id LEFT JOIN options w ON ev.winning_option_id=w.option_id WHERE gio.game_id=:game_id AND io.";
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
			$amount_disp = $this->blockchain->app->format_bignum($io['colored_amount']/pow(10,$this->db_game['decimal_places']));
			$html .= $amount_disp." ";
			if ($amount_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			if ($selected_game_io_id == $io['game_io_id']) $html .= "</b>";
			else $html .= "</a>\n";
			
			$html .= " &nbsp; ".ucwords($io['spend_status']);
			$html .= "<br/>\n";
			
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees) = $this->get_payout_info($io, $coins_per_vote, $last_block_id, $html);
			
			if (empty($io['option_id']) && $io['destroy_amount']+$inflation_stake > 0) {
				$destroy_amount_disp = $this->blockchain->app->format_bignum(($io['destroy_amount']+$inflation_stake)/pow(10,$this->db_game['decimal_places']));
				$html .= $destroy_amount_disp." ";
				if ($destroy_amount_disp == '1') $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
			}
			
			if ($io['is_coinbase'] == 1) {
				$frac_of_contract = $io['contract_parts']/$io['total_contract_parts'];
				
				if ($io['payout_rule'] == "binary") {
					$destroy_amount_disp = $this->blockchain->app->format_bignum($frac_of_contract*($io['destroy_amount']+$inflation_stake)/pow(10,$this->db_game['decimal_places']));
					$html .= $destroy_amount_disp." ";
					if ($destroy_amount_disp == '1') $html .= $this->db_game['coin_name'];
					else $html .= $this->db_game['coin_name_plural'];
					
					$this_payout_disp = $max_payout;
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
					$html .= $this->blockchain->app->format_bignum($this_payout_disp/pow(10,$this->db_game['decimal_places']));
					if ($io['outcome_index'] != -1) $html .= "</font>";
				}
				else {
					if ($io['event_option_index'] != 0) $html .= '-';
					$html .= $this->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->db_game['decimal_places'])).' '.$io['track_name_short'].' ';
					
					$this_payout_disp = $fair_io_value;
					
					if ($borrow_delta != 0) {
						if ($borrow_delta > 0) $html .= '<font class="greentext">+ ';
						else $html .= '<font class="redtext">- ';
						$html .= $this->blockchain->app->format_bignum(abs($borrow_delta/pow(10, $this->db_game['decimal_places'])));
						$html .= "</font>\n";
					}
				}
				
				if ($io['payout_rule'] == "linear") {
					$html .= " &nbsp; <a href=\"\" onclick=\"$('#gio_details_".$io['game_io_id']."').toggle('fast'); return false;\">Details</a>";
					$html .= '<div style="display: none; border: 1px solid #ccc; padding: 5px;" id="gio_details_'.$io['game_io_id'].'">';
					
					$html .= "Paid ".$this->blockchain->app->format_bignum(($io['destroy_amount']+$inflation_stake)/pow(10,$this->db_game['decimal_places']));
					$html .= ' '.$this->db_game['coin_name_plural'];
					$html .= ' @ $'.$this->blockchain->app->format_bignum($asset_price_usd);
					$html .= '<br/>'.$this->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->db_game['decimal_places'])).' '.$io['track_name_short'].' @ $'.$this->blockchain->app->format_bignum($bought_price_usd);
					if ($bought_leverage != 1) $html .= ' &nbsp; ('.$this->blockchain->app->format_bignum($bought_leverage).'X leverage)';
					$html .= '<br/><br/>';
					
					if ($io['is_resolved'] == 1) $html .= 'Paid out';
					else $html .= 'Now valued';
					$html .= ' at <font class="greentext">'.$this->blockchain->app->format_bignum(($fair_io_value-$payout_fees)/pow(10,$this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural']."</font>\n";
					$html .= "@ ";
					$html .= "$".$this->blockchain->app->format_bignum($track_pay_price);
					if ($track_price_usd != $track_pay_price) $html .= " ($".$this->blockchain->app->format_bignum($track_price_usd).")";
					$html .= "<br/>\n";
					if ($io['event_option_index'] != 0) $html .= '-';
					$html .= $this->blockchain->app->format_bignum($equivalent_contracts/pow(10, $this->db_game['decimal_places'])).' '.$io['track_name_short'].' ';
					
					if ($borrow_delta != 0) {
						if ($borrow_delta > 0) $html .= '<font class="greentext">+ ';
						else $html .= '<font class="redtext">- ';
						$html .= $this->blockchain->app->format_bignum(abs($borrow_delta/pow(10, $this->db_game['decimal_places'])));
						$html .= "</font>\n";
					}
					if ($current_leverage && $current_leverage != 1) $html .= " &nbsp; (".$this->blockchain->app->format_bignum($current_leverage)."X leverage)\n";
					$html .= "<br/>\n";
					
					if ($payout_fees > 0) {
						$html .= "<font class=\"redtext\">".$this->blockchain->app->format_bignum($payout_fees/pow(10, $this->db_game['decimal_places']))."</font> ".$this->db_game['coin_name_plural']." in fees<br/>\n";
					}
					
					if ($io['destroy_amount']+$inflation_stake > 0) $pct_gain = 100*($net_delta/($io['destroy_amount']+$inflation_stake));
					else $pct_gain = 0;
					
					if ($net_delta < 0) $html .= '<font class="redtext">Net loss of ';
					else $html .= '<font class="greentext">Net gain of ';
					$html .= $this->blockchain->app->format_bignum(abs($net_delta)/pow(10, $this->db_game['decimal_places'])).' '.$this->db_game['coin_name_plural'];
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
	
	public function get_payout_info(&$io, &$coins_per_vote, &$last_block_id, &$html) {
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
		
		$effective_paid = 0;
		$equivalent_contracts = 0;
		$event_equivalent_contracts = 0;
		$track_position_price = false;
		
		if ($io['is_coinbase'] == 1) {
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
					$track_price_info = $this->blockchain->app->exchange_rate_between_currencies(1, $track_entity['currency_id'], time(), 6);
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
		
		return array($track_entity, $track_price_usd, $track_pay_price, $position_price, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees);
	}
	
	public function explorer_block_list($from_block_id, $to_block_id) {
		return $this->blockchain->explorer_block_list($from_block_id, $to_block_id, $this, false);
	}
	
	public function set_block_stats(&$game_block) {
		$out_stats = $this->blockchain->app->run_query("SELECT COUNT(*) ios_out, SUM(gio.colored_amount) coins_out FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND t.block_id=:block_id GROUP BY t.transaction_id;", [
			'game_id' => $this->db_game['game_id'],
			'block_id' => $game_block['block_id']
		]);
		
		$num_ios_out = 0;
		$sum_coins_out = 0;
		$num_transactions = $out_stats->rowCount();
		
		while ($out_stat = $out_stats->fetch()) {
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
	
	public function account_balance($account_id) {
		return (int)($this->blockchain->app->run_query("SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE (io.spend_status='unspent' || io.spend_status='unconfirmed') AND k.account_id=:account_id;", ['account_id'=>$account_id])->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function account_balance_at_block($account_id, $block_id, $include_coinbase) {
		$balance_q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND io.create_block_id <= :block_id AND ((io.spend_block_id IS NULL AND io.spend_status='unspent') OR io.spend_block_id > :block_id)";
		if (!$include_coinbase) $balance_q .= " AND gio.is_coinbase=0";
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
	
	public function process_sellouts_in_block($block_id) {
		if ($this->db_game['sellout_policy'] == "on" && !in_array($this->db_game['buyin_policy'], ['for_sale', 'none', ''])) {
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
			
			// Identify sellout transactions paid into escrow & create records in game_sellouts table
			$sellout_transactions = $this->blockchain->app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE io.blockchain_id=:blockchain_id AND t.block_id = :block_id AND io.address_id=:address_id GROUP BY t.transaction_id;", [
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id'],
				'block_id' => $block_id,
				'address_id' => $escrow_address['address_id']
			]);
			
			if ($sellout_transactions->rowCount() > 0) {
				$escrow_balance = $this->blockchain->address_balance_at_block($escrow_address, $block_id);
				$coins_in_existence = $this->coins_in_existence($block_id, false);
				
				while ($transaction = $sellout_transactions->fetch()) {
					$game_sellouts = $this->blockchain->app->run_query("SELECT * FROM game_sellouts WHERE game_id=:game_id AND in_tx_hash=:tx_hash;", [
						'game_id' => $this->db_game['game_id'],
						'tx_hash' => $transaction['tx_hash']
					]);
					
					if ($game_sellouts->rowCount() == 0) {
						$stats_in = $this->blockchain->app->run_query("SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id;", [
							'transaction_id' => $transaction['transaction_id'],
							'game_id' => $this->db_game['game_id']
						])->fetch();
						
						$stats_out = $this->blockchain->app->run_query("SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.create_transaction_id=:transaction_id AND gio.game_id=:game_id;", [
							'transaction_id' => $transaction['transaction_id'],
							'game_id' => $this->db_game['game_id']
						])->fetch();
						
						if ($stats_in['COUNT(*)'] > 0) {
							$exchange_rate = round($coins_in_existence/$escrow_balance*pow(10,6))/pow(10,6);
							$coloredcoins_destroyed = $stats_in['SUM(gio.colored_amount)'] - $stats_out['SUM(gio.colored_amount)'];
							
							$value_destroyed_coins = floor($coloredcoins_destroyed/$exchange_rate);
							
							$coins_into_escrow = (int)($this->blockchain->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id=:transaction_id AND address_id=:address_id;", [
								'transaction_id' => $transaction['transaction_id'],
								'address_id' => $escrow_address['address_id']
							])->fetch()['SUM(amount)']);
							
							$fee_amount = (int)(0.0001*pow(10,$this->blockchain->db_blockchain['decimal_places']));
							
							$refund_amount = ($coins_into_escrow+$value_destroyed_coins) - $fee_amount;
							
							$in_io_sum = (int)($this->blockchain->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE spend_transaction_id=:transaction_id;", [
								'transaction_id' => $transaction['transaction_id']
							])->fetch()['SUM(amount)']);
							
							$ios_in = $this->blockchain->app->run_query("SELECT * FROM transaction_ios WHERE spend_transaction_id=:transaction_id ORDER BY out_index ASC;", [
								'transaction_id' => $transaction['transaction_id']
							]);
							$num_ios_in = $ios_in->rowCount();
							$in_io_i=0;
							$refund_sum = 0;
							$out_amounts = [];
							while ($io_in = $ios_in->fetch()) {
								$refund_amount = floor($refund_amount*$io_in['amount']/$in_io_sum);
								if ($in_io_i == $num_ios_in-1) $refund_amount = $refund_amount - $refund_sum;
								array_push($out_amounts, $refund_amount);
								$refund_sum += $refund_amount;
								$in_io_i++;
							}
							
							$this->blockchain->app->run_query("INSERT INTO game_sellouts SET game_id=:game_id, in_block_id=:block_id, in_tx_hash=:tx_hash, color_amount_in=:color_amount_in, exchange_rate=:exchange_rate, amount_in=:amount_in, amount_out=:amount_out, out_amounts=:out_amounts, fee_amount=:fee_amount;", [
								'game_id' => $this->db_game['game_id'],
								'block_id' => $block_id,
								'tx_hash' => $transaction['tx_hash'],
								'color_amount_in' => $coloredcoins_destroyed,
								'exchange_rate' => $exchange_rate,
								'amount_in' => $coins_into_escrow,
								'amount_out' => ($coins_into_escrow+$value_destroyed_coins),
								'out_amounts' => implode(",", $out_amounts),
								'fee_amount' => $fee_amount
							]);
						}
					}
				}
			}
			
			$pending_sellouts = $this->blockchain->app->run_query("SELECT * FROM game_sellouts WHERE game_id=:game_id AND out_tx_hash IS NULL;", ['game_id'=>$this->db_game['game_id']]);
			
			while ($pending_sellout = $pending_sellouts->fetch()) {
				$in_transaction = $this->blockchain->fetch_transaction_by_hash($pending_sellout['in_tx_hash']);
				
				if ($in_transaction) {
					$matching_tx_id = false;
					$matching_tx_error = false;
					
					$expected_amounts = explode(",", $pending_sellout['out_amounts']);
					$expected_addr_ids = [];
					
					$ios_in = $this->blockchain->app->run_query("SELECT * FROM transaction_ios WHERE spend_transaction_id=:transaction_id ORDER BY out_index ASC;", [
						'transaction_id' => $in_transaction['transaction_id']
					]);
					
					for ($i=0; $i<count($expected_amounts); $i++) {
						$in_io = $ios_in->fetch();
						
						$matching_transactions = $this->blockchain->app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE io.amount=:amount AND io.address_id=:address_id;", [
							'amount' => $expected_amounts[$i],
							'address_id' => $in_io['address_id']
						]);
						
						if ($matching_transactions->rowCount() == 1) {
							$matching_tx = $matching_transactions->fetch();
							
							if ($matching_tx_id == false || $matching_tx_id == $matching_tx['transaction_id']) {
								$matching_tx_id = $matching_tx['transaction_id'];
							}
							else {
								$matching_tx_error = true;
							}
						}
						else $matching_tx_error = true;
					}
					
					if ($matching_tx_id && !$matching_tx_error) {
						$this->blockchain->app->run_query("UPDATE game_sellouts SET out_tx_hash=:tx_hash WHERE sellout_id=:sellout_id;", [
							'tx_hash' => $matching_tx['tx_hash'],
							'sellout_id' => $pending_sellout['sellout_id']
						]);
					}
				}
			}
			
			$this->blockchain->app->run_query("UPDATE game_sellouts s JOIN transactions t ON s.out_tx_hash=t.tx_hash SET s.out_block_id=t.block_id WHERE s.game_id=:game_id AND t.blockchain_id=:blockchain_id;", [
				'game_id' => $this->db_game['game_id'],
				'blockchain_id' => $this->blockchain->db_blockchain['blockchain_id']
			]);
		}
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
		if ($exclude_coinbase) $coins_out_q .= " AND gio.is_coinbase=0";
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
		]);
		
		if ($joined_at->rowCount() > 0) {
			return (int) $joined_at->fetch()['MIN(created_at)'];
		}
		else return false;
	}
	
	public function most_recent_faucet_claim($user_id, $game_id) {
		$claim_info = $this->blockchain->app->run_query("SELECT MAX(latest_claim_time) FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
			'user_id' => $user_id,
			'game_id' => $game_id
		]);
		
		if ($claim_info->rowCount() > 0) {
			return (int) $claim_info->fetch()['MAX(latest_claim_time)'];
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
			
			if (time() > $time_claim_available) {
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
					
					$faucet_io = $this->blockchain->app->run_query("SELECT *, SUM(gio.colored_amount) AS colored_amount_sum FROM address_keys k JOIN transaction_game_ios gio ON gio.address_id=k.address_id WHERE gio.game_id=:game_id AND k.account_id=:account_id AND gio.option_id IS NULL GROUP BY k.address_id ORDER BY colored_amount_sum DESC LIMIT 1;", [
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
	
	public function give_faucet_to_user(&$user_game) {
		$this->blockchain->app->dbh->beginTransaction();
		
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
			$this->blockchain->app->run_query("UPDATE user_games SET faucet_claims=faucet_claims+1 WHERE user_game_id=:user_game_id;", ['user_game_id'=>$user_game['user_game_id']]);
		}
		
		$this->blockchain->app->dbh->commit();
		
		if ($faucet_io) return true;
		else return false;
	}
	
	public function add_genesis_transaction(&$user_game) {
		$genesis_tx = $this->blockchain->fetch_transaction_by_hash($this->db_game['genesis_tx_hash']);
		
		if ($genesis_tx) {
			$this->process_buyin_transaction($genesis_tx);
			return true;
		}
		else return false;
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
	
	public function set_gde_blocks_by_time(&$gde) {
		if (!empty($gde['event_starting_time'])) {
			$start_block = $this->time_to_block_in_game(strtotime($gde['event_starting_time']));
			
			$final_block = $this->time_to_block_in_game(strtotime($gde['event_final_time']));
			
			if ($gde['event_payout_time'] == "" || $gde['event_payout_time'] == $gde['event_final_time']) $payout_block = $final_block;
			else $payout_block = $this->time_to_block_in_game(strtotime($gde['event_payout_time']));
			
			$this->blockchain->app->run_query("UPDATE game_defined_events SET event_starting_block=:event_starting_block, event_final_block=:event_final_block, event_payout_block=:event_payout_block, event_outcome_block=:event_outcome_block WHERE game_id=:game_id AND event_index=:event_index;", [
				'event_starting_block' => $start_block,
				'event_final_block' => $final_block,
				'event_payout_block' => $payout_block,
				'event_outcome_block' => $payout_block,
				'game_id' => $this->db_game['game_id'],
				'event_index' => $gde['event_index']
			]);
		}
	}
	
	public function event_filter_html() {
		$html = '
		<form class="form-inline">
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
			</div>
		</form>';
		
		return $html;
	}
	
	public function set_event_blocks($user_id, $game_defined_event_id) {
		$log_text = "";
		$last_block_id = $this->blockchain->last_block_id();
		$avoid_changing_completed_events = true;
		
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
		$event_r = $this->blockchain->app->run_query($event_q, $event_params);
		
		$log_text .= "Set blocks for ".$event_r->rowCount()." events in ".$this->db_game['name'];
		
		if ($event_r->rowCount() > 0) {
			$show_internal_params = true;
			
			list($initial_game_def_hash, $initial_game_def) = GameDefinition::fetch_game_definition($this, "defined", $show_internal_params, false);
			GameDefinition::check_set_game_definition($this->blockchain->app, $initial_game_def_hash, $initial_game_def);
			
			while ($gde = $event_r->fetch()) {
				$this->set_gde_blocks_by_time($gde);
			}
			
			list($final_game_def_hash, $final_game_def) = GameDefinition::fetch_game_definition($this, "defined", $show_internal_params, false);
			GameDefinition::check_set_game_definition($this->blockchain->app, $final_game_def_hash, $final_game_def);
			
			GameDefinition::record_migration($this, $user_id, "set_blocks_by_ui", $show_internal_params, $initial_game_def, $final_game_def);
			
			GameDefinition::set_cached_definition_hashes($this);
		}
		return $log_text;
	}
	
	public function escrow_value_in_currency($currency_id) {
		$total_value = 0;
		
		$escrow_amounts = $this->blockchain->app->run_query("SELECT * FROM game_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id;", [
			'game_id'=>$this->db_game['game_id']
		]);
		
		while ($escrow_amount = $escrow_amounts->fetch()) {
			$conversion_rate = $this->blockchain->app->currency_conversion_rate($currency_id, $escrow_amount['currency_id']);
			$value_in_currency = $escrow_amount['amount']*$conversion_rate['conversion_rate'];
			$total_value += $value_in_currency;
		}
		
		return $total_value;
	}
	
	public function pending_bets($use_cache) {
		if ($use_cache && (string)$this->db_game['cached_pending_bets'] != "") return $this->db_game['cached_pending_bets'];
		else {
			$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
			$info = $this->blockchain->app->run_query("SELECT SUM(p.destroy_amount) as destroy_amount, SUM(p.".$this->db_game['payout_weight']."s_destroyed) as inflation_score FROM transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND gio.is_resolved=0 AND gio.option_id IS NOT NULL;")->fetch();
			$pending_bets = $info['destroy_amount'] + round($info['inflation_score']*$coins_per_vote);
			$this->blockchain->app->run_query("UPDATE games SET cached_pending_bets='".$pending_bets."' WHERE game_id='".$this->db_game['game_id']."';");
			$this->db_game['cached_pending_bets'] = $pending_bets;
			return $pending_bets;
		}
	}
	
	public function user_pending_bets(&$user_game) {
		$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
		$info = $this->blockchain->app->run_query("SELECT SUM(p.destroy_amount) as destroy_amount, SUM(:payout_weight_field) as inflation_score, SUM(:ref_votes_field) as ref_votes FROM transaction_game_ios gio JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.game_id=:game_id AND gio.is_resolved=0 AND k.account_id=:account_id;", [
			'payout_weight_field' => "p.".$this->db_game['payout_weight']."s_destroyed",
			'ref_votes_field' => "p.ref_".$this->db_game['payout_weight']."s",
			'game_id' => $this->db_game['game_id'],
			'account_id' => $user_game['account_id']
		])->fetch();
		return $info['destroy_amount'] + round(($info['inflation_score']+$info['ref_votes'])*$coins_per_vote);
	}
	
	public function vote_supply(&$last_block_id, &$current_round, &$coins_per_vote, $use_cache) {
		if ($use_cache && (string)$this->db_game['cached_vote_supply'] != "") $vote_supply = $this->db_game['cached_vote_supply'];
		else {
			$info = $this->blockchain->app->run_query("SELECT *, SUM(gio.colored_amount*(:ref_block_id-io.create_block_id)) AS coin_blocks, SUM(gio.colored_amount*(:current_round-gio.create_round_id)) AS coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id=:game_id AND io.spend_status='unspent';", [
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
	
	public function get_game_peer_by_id($game_peer_id) {
		return $this->blockchain->app->run_query("SELECT * FROM peers p JOIN game_peers gp ON p.peer_id=gp.peer_id WHERE gp.game_peer_id=:game_peer_id;", [
			'game_peer_id' => $game_peer_id
		])->fetch();
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
	
	public function get_game_peer_by_server_name($server_name) {
		$game_peer = false;
		$peer = $this->blockchain->app->get_peer_by_server_name($server_name, true);
		
		if ($peer) {
			$game_peer_r = $this->blockchain->app->run_query("SELECT * FROM game_peers WHERE game_id=:game_id AND peer_id=:peer_id;", [
				'game_id' => $this->db_game['game_id'],
				'peer_id' => $peer['peer_id']
			]);
			
			if ($game_peer_r->rowCount() == 0) {
				$this->blockchain->app->run_query("INSERT INTO game_peers SET game_id=:game_id, peer_id=:peer_id;", [
					'game_id' => $this->db_game['game_id'],
					'peer_id' => $peer['peer_id']
				]);
				$game_peer = $this->get_game_peer_by_id($this->blockchain->app->last_insert_id());
			}
			else $game_peer = $game_peer_r->fetch();
		}
		
		return $game_peer;
	}
	
	public function set_cached_fields() {
		$this->coins_in_existence(false, false);
		$this->pending_bets(false);
		
		$last_block_id = $this->blockchain->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		$coins_per_vote = $this->blockchain->app->coins_per_vote($this->db_game);
		$this->vote_supply($last_block_id, $current_round, $coins_per_vote, false);
		
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
	
	public function display_buyins_by_user_game($user_game_id) {
		$html = "";
		$invoices = $this->blockchain->app->run_query("SELECT * FROM currency_invoices i JOIN addresses a ON i.address_id=a.address_id JOIN currencies c ON i.pay_currency_id=c.currency_id JOIN blockchains b ON  c.blockchain_id=b.blockchain_id WHERE i.invoice_type IN ('sale_buyin','join_buyin','buyin') AND i.user_game_id=:user_game_id ORDER BY i.invoice_id DESC;", ['user_game_id'=>$user_game_id]);
		$num_invoices = $invoices->rowCount();
		
		while ($invoice = $invoices->fetch()) {
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-3">'.$this->blockchain->app->format_bignum($invoice['confirmed_amount_paid']).' '.$invoice['coin_name_plural'].' paid</div>';
			
			$invoice_ios = $this->blockchain->app->run_query("SELECT * FROM currency_invoice_ios WHERE invoice_id=:invoice_id;", ['invoice_id'=>$invoice['invoice_id']])->fetchAll();
			$html .= '<div class="col-sm-3">';
			if (count($invoice_ios) == 0) {
				if ($invoice['confirmed_amount_paid'] == 0) $html .= 'Awaiting&nbsp;Payment';
				else $html .= ucwords($invoice['status']);
			}
			else {
				foreach ($invoice_ios as $invoice_io) {
					$io = $this->blockchain->app->fetch_io_by_hash_out_index($this->blockchain->db_blockchain['blockchain_id'], $invoice_io['tx_hash'], $invoice_io['out_index']);
					$game_amount = $this->game_amount_by_io($io['io_id']);
					$html .= '<a href="/explorer/games/'.$this->db_game['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['game_out_index'].'/">'.$this->blockchain->app->format_bignum($game_amount/pow(10, $this->db_game['decimal_places']))." ".$this->db_game['coin_name_plural']."</a><br/>\n";
				}
			}
			$html .= '</div>';
			
			$html .= '<div class="col-sm-6">';
			if (time() > $invoice['expire_time'] - 3600*2) $html .= '<font class="redtext">Expired</font> &nbsp; ';
			$html .= '<a href="/explorer/blockchains/'.$invoice['url_identifier'].'/addresses/'.$invoice['address'].'/">'.$invoice['address'].'</a>';
			$html .= '</div>';
			$html .= "</div>\n";
		}
		
		return [$num_invoices, $html];
	}
	
	public function display_sellouts_by_user_game($user_game_id) {
		$html = "";
		$invoices = $this->blockchain->app->run_query("SELECT * FROM currency_invoices i JOIN addresses a ON i.address_id=a.address_id JOIN currencies c ON i.pay_currency_id=c.currency_id JOIN blockchains b ON  c.blockchain_id=b.blockchain_id WHERE i.invoice_type='sellout' AND i.user_game_id=:user_game_id ORDER BY i.invoice_id DESC;", ['user_game_id'=>$user_game_id]);
		$num_invoices = $invoices->rowCount();
		
		while ($invoice = $invoices->fetch()) {
			$html .= '<div class="row">';
			if ($invoice['confirmed_amount_paid'] == 0) $display_amount_sold = $invoice['buyin_amount'];
			else $display_amount_sold = $invoice['confirmed_amount_paid'];
			$html .= '<div class="col-sm-3">'.$this->blockchain->app->format_bignum($display_amount_sold).' '.$this->db_game['coin_name_plural'].' sold</div>';
			
			$invoice_ios = $this->blockchain->app->run_query("SELECT * FROM currency_invoice_ios WHERE invoice_id='".$invoice['invoice_id']."';")->fetchAll();
			$html .= '<div class="col-sm-3">';
			if (count($invoice_ios) == 0) {
				if ($invoice['confirmed_amount_paid'] == 0) $html .= 'Pending';
				else $html .= ucwords($invoice['status']);
			}
			else {
				foreach ($invoice_ios as $invoice_io) {
					$io = $this->blockchain->app->fetch_io_by_hash_out_index($invoice['blockchain_id'], $invoice_io['tx_hash'], $invoice_io['out_index']);
					$html .= '<a href="/explorer/blockchains/'.$invoice['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['out_index'].'/">'.$this->blockchain->app->format_bignum($io['amount']/pow(10, $invoice['decimal_places']))." ".$invoice['coin_name_plural']."</a><br/>\n";
				}
			}
			$html .= '</div>';
			
			$html .= '<div class="col-sm-6">';
			if (time() > $invoice['expire_time'] - 3600*2) $html .= '<font class="redtext">Expired</font> &nbsp; ';
			$html .= '<a href="/explorer/games/'.$this->db_game['url_identifier'].'/addresses/'.$invoice['address'].'/">'.$invoice['address'].'</a>';
			$html .= '</div>';
			$html .= "</div>\n";
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
}
?>
