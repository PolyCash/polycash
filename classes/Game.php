<?php
class Game {
	public $db_game;
	public $blockchain;
	public $current_events;
	
	public function __construct(&$blockchain, $game_id) {
		$this->blockchain = $blockchain;
		$this->game_id = $game_id;
		$this->update_db_game();
		$this->load_current_events();
	}
	
	public function update_db_game() {
		$q = "SELECT g.*, b.p2p_mode, b.coin_name AS base_coin_name, b.coin_name_plural AS base_coin_name_plural FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE g.game_id='".$this->game_id."';";
		$r = $this->blockchain->app->run_query($q);
		$this->db_game = $r->fetch() or die("Error, could not load game #".$this->game_id);
	}
	
	public function block_to_round($mining_block_id) {
		return ceil($mining_block_id/$this->db_game['round_length']);
	}
	
	public function create_transaction($option_ids, $amounts, $from_user_id, $to_user_id, $block_id, $type, $io_ids, $address_ids, $remainder_address_id, $transaction_fee) {
		if (!$type || $type == "") $type = "transaction";
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			$amount += $amounts[$i];
		}
		
		if ($type == "giveaway") $instantly_mature = 1;
		else $instantly_mature = 0;
		
		if ($from_user_id) {
			$from_user = new User($this->blockchain->app, $from_user_id);
			$account_value = $from_user->account_coin_value($this);
			$immature_balance = $from_user->immature_balance($this);
			$mature_balance = $from_user->mature_balance($this);
		}
		else {
			$from_user = false;
			$account_value = 0;
			$immature_balance = 0;
			$mature_balance = 0;
		}
		
		if ($to_user_id) $to_user = new User($this->blockchain->app, $to_user_id);
		
		$utxo_balance = false;
		if ($io_ids) {
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", $io_ids).");";
			$r = $this->blockchain->app->run_query($q);
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
		
		if ($amount_ok && (count($option_ids) == count($amounts) || ($option_ids === false && count($amounts) == count($address_ids)))) {
			// For rpc games, don't insert a tx record, it will come in via walletnotify
			if ($this->blockchain->db_blockchain['p2p_mode'] != "rpc") {
				$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', fee_amount='".$transaction_fee."', has_all_inputs=1, has_all_outputs=1";
				if ($this->blockchain->db_blockchain['p2p_mode'] == "none") $q .= ", tx_hash='".$this->blockchain->app->random_string(64)."'";
				$q .= ", transaction_desc='".$type."', amount=".$amount;
				if ($from_user_id) $q .= ", from_user_id='".$from_user_id."'";
				if ($to_user_id) $q .= ", to_user_id='".$to_user_id."'";
				if ($type == "bet") {
					$qq = "SELECT bet_round_id FROM addresses WHERE address_id='".$address_ids[0]."';";
					$rr = $this->blockchain->app->run_query($qq);
					$bet_round_id = $rr->fetch(PDO::FETCH_NUM);
					$bet_round_id = $bet_round_id[0];
					$q .= "bet_round_id='".$bet_round_id."', ";
				}
				if ($block_id !== false) $q .= ", block_id='".$block_id."', round_id='".$this->block_to_round($block_id)."'";
				$q .= ", time_created='".time()."';";
				$r = $this->blockchain->app->run_query($q);
				$transaction_id = $this->blockchain->app->last_insert_id();
			}
			
			$input_sum = 0;
			$overshoot_amount = 0;
			$overshoot_return_addr_id = $remainder_address_id;
			
			if ($type == "giveaway" || $type == "votebase" || $type == "coinbase") {}
			else {
				$q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status='unspent' AND io.user_id='".$from_user_id."' AND io.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND io.create_block_id <= ".($this->blockchain->last_block_id()-$this->db_game['maturity']);
				if ($io_ids) $q .= " AND io.io_id IN (".implode(",", $io_ids).")";
				$q .= " ORDER BY io.amount ASC;";
				$r = $this->blockchain->app->run_query($q);
				
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				$ref_block_id = $this->blockchain->last_block_id()+1;
				$ref_round_id = $this->block_to_round($ref_block_id);
				$ref_cbd = 0;
				$ref_crd = 0;
				
				while ($transaction_input = $r->fetch()) {
					if ($input_sum < $amount) {
						if ($this->blockchain->db_blockchain['p2p_mode'] == "none") {
							$qq = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_transaction_id='".$transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$transaction_id."', ','))";
							if ($block_id !== false) $qq .= ", spend_status='spent', spend_block_id='".$block_id."', spend_round_id='".$this->block_to_round($block_id)."'";
							$qq .= " WHERE io_id='".$transaction_input['io_id']."';";
							$rr = $this->blockchain->app->run_query($qq);
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
				
				//$qq = "UPDATE transactions SET ref_block_id='".$ref_block_id."', ref_coin_blocks_destroyed='".$ref_cbd."' WHERE transaction_id='".$transaction_id."';";
				//$rr = $this->blockchain->app->run_query($qq);
			}
			
			$output_error = false;
			$out_index = 0;
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					if ($address_ids) {
						if (count($address_ids) == count($amounts)) $address_id = $address_ids[$out_index];
						else $address_id = $address_ids[0];
					}
					else $address_id = $to_user->user_address_id($this, false, $option_ids[$out_index]);
					
					if ($address_id) {
						$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
						$r = $this->blockchain->app->run_query($q);
						$address = $r->fetch();
						
						if ($this->blockchain->db_blockchain['p2p_mode'] == "none") {
							$q = "INSERT INTO transaction_ios SET spend_status='";
							if ($instantly_mature == 1) $q .= "unspent";
							else $q .= "unconfirmed";
							$q .= "', out_index='".$out_index."', ";
							if (!empty($address['user_id'])) $q .= "user_id='".$address['user_id']."', ";
							$q .= "address_id='".$address_id."', ";
							if ($address['option_index'] != "") {
								$option_id = $this->option_index_to_current_option_id($address['option_index']);
								if ($option_id) {
									$db_option = $this->blockchain->app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';")->fetch();
									$q .= "option_index='".$address['option_index']."', option_id='".$option_id."', event_id='".$db_option['event_id']."', ";
									if ($block_id !== false) {
										$event = new Event($this, false, $db_option['event_id']);
										$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_id);
									}
								}
								else $effectiveness_factor = 0;
							}
							else $effectiveness_factor = 0;
							if ($block_id !== false) {
								if ($input_sum == 0) $output_cbd = 0;
								else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								
								if ($input_sum == 0) $output_crd = 0;
								else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								
								$q .= "coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', ";
								
								if ($this->db_game['payout_weight'] == "coin") {
									if ($input_sum > 0) $votes = floor($amounts[$out_index]/$input_sum);
									else $votes = 0;
								}
								else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
								else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
								else $votes = 0;
								
								$votes = floor($votes*$effectiveness_factor);
								
								$q .= "votes='".$votes."', ";
							}
							$q .= "instantly_mature='".$instantly_mature."', game_id='".$this->db_game['game_id']."', ";
							if ($block_id !== false) {
								$q .= "create_block_id='".$block_id."', create_round_id='".$this->block_to_round($block_id)."', ";
							}
							$q .= "create_transaction_id='".$transaction_id."', colored_amount='".$amounts[$out_index]."', amount='".$amounts[$out_index]."';";
							
							$r = $this->blockchain->app->run_query($q);
							$created_input_ids[count($created_input_ids)] = $this->blockchain->app->last_insert_id();
						}
						
						$raw_txout[$address['address']] = $amounts[$out_index]/pow(10,8);
					}
					else $output_error = true;
				}
			}
			
			if ($output_error) {
				$this->blockchain->app->cancel_transaction($transaction_id, $affected_input_ids, false);
				return false;
			}
			else {
				if ($overshoot_amount > 0) {
					$out_index++;
					
					$q = "SELECT * FROM addresses WHERE address_id='".$overshoot_return_addr_id."';";
					$r = $this->blockchain->app->run_query($q);
					$overshoot_address = $r->fetch();
					
					if ($this->blockchain->db_blockchain['p2p_mode'] == "none") {
						$q = "INSERT INTO transaction_ios SET out_index='".$out_index."', spend_status='unconfirmed', game_id='".$this->db_game['game_id']."', ";
						if ($block_id !== false) {
							$overshoot_cbd = floor($coin_blocks_destroyed*($overshoot_amount/$input_sum));
							$overshoot_crd = floor($coin_rounds_destroyed*($overshoot_amount/$input_sum));
							$q .= "coin_blocks_destroyed='".$overshoot_cbd."', coin_rounds_destroyed='".$overshoot_crd."', ";
						}
						$q .= "user_id='".$from_user_id."', address_id='".$overshoot_return_addr_id."', ";
						if ($overshoot_address['option_index'] > 0) {
							$option_id = $this->option_index_to_current_option_id($overshoot_address['option_index']);
							$db_option = $this->blockchain->app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';")->fetch();
							$q .= "option_index='".$overshoot_address['option_index']."', option_id='".$option_id."', event_id='".$db_option['event_id']."', ";
							if ($block_id !== false) {
								$event = new Event($this, false, $db_option['event_id']);
								$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_id);
								$q .= "effectiveness_factor='".$effectiveness_factor."', ";
							}
						}
						$q .= "create_transaction_id='".$transaction_id."', ";
						if ($block_id !== false) {
							$q .= "create_block_id='".$block_id."', create_round_id='".$this->block_to_round($block_id)."', ";
						}
						
						$q .= "colored_amount='".$overshoot_amount."', amount='".$overshoot_amount."';";
						$r = $this->blockchain->app->run_query($q);
						$created_input_ids[count($created_input_ids)] = $this->blockchain->app->last_insert_id();
					}
					
					$raw_txout[$overshoot_address['address']] = $overshoot_amount/pow(10,8);
				}
				
				$rpc_error = false;
				
				if ($this->blockchain->db_blockchain['p2p_mode'] == "rpc") {
					$coin_rpc = new jsonRPCClient('http://'.$this->blockchain->db_blockchain['rpc_username'].':'.$this->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->blockchain->db_blockchain['rpc_port'].'/');
					try {
						$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
						$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction);
						$decoded_transaction = $coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
						$tx_hash = $decoded_transaction['txid'];
						$verified_tx_hash = $coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
						
						$this->blockchain->walletnotify($coin_rpc, $verified_tx_hash, FALSE);
						$this->update_option_votes();
						
						$db_transaction = $this->blockchain->app->run_query("SELECT * FROM transactions WHERE tx_hash=".$this->blockchain->app->quote_escape($tx_hash).";")->fetch();
						
						return $db_transaction['transaction_id'];
					}
					catch (Exception $e) {
						echo "raw_transaction:".$raw_transaction."<br/>\n";
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
	
	public function update_option_votes() {
		$last_block_id = $this->blockchain->last_block_id();
		$round_id = $this->block_to_round($last_block_id+1);
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$effectiveness_factor = $this->current_events[$i]->block_id_to_effectiveness_factor($last_block_id+1);
			
			$q = "UPDATE options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, votes=0, unconfirmed_votes=0 WHERE event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			$q = "UPDATE options op INNER JOIN (
				SELECT option_id, SUM(colored_amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes FROM transaction_game_ios 
				WHERE game_id='".$this->db_game['game_id']."' AND create_round_id=".$round_id." AND colored_amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.coin_score=i.sum_amount, op.coin_block_score=i.sum_cbd, op.coin_round_score=i.sum_crd, op.votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			if ($this->db_game['payout_weight'] == "coin") {
				$q = "UPDATE options op INNER JOIN (
					SELECT option_index, SUM(amount) sum_amount, SUM(amount)*".$effectiveness_factor." sum_votes FROM transaction_ios 
					WHERE game_id='".$this->db_game['game_id']."' AND create_block_id IS NULL AND amount > 0
					GROUP BY option_index
				) i ON op.option_index=i.option_index SET op.unconfirmed_coin_score=i.sum_amount, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->blockchain->app->run_query($q);
			}
			else if ($this->db_game['payout_weight'] == "coin_block") {
				$q = "UPDATE options op INNER JOIN (
					SELECT option_id, SUM(ref_coin_blocks+(".($last_block_id+1)."-ref_block_id)*colored_amount) sum_cbd, SUM(ref_coin_blocks+(".($last_block_id+1)."-ref_block_id)*colored_amount)*".$effectiveness_factor." sum_votes FROM transaction_game_ios 
					WHERE game_id='".$this->db_game['game_id']."' AND create_round_id IS NULL AND colored_amount > 0 
					GROUP BY option_id
				) i ON op.option_id=i.option_id SET op.unconfirmed_coin_block_score=i.sum_cbd, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->blockchain->app->run_query($q);
			}
			else {
				$q = "UPDATE options op INNER JOIN (
					SELECT option_id, SUM(ref_coin_rounds+(".$round_id."-ref_round_id)*colored_amount) sum_crd, SUM(ref_coin_rounds+(".$round_id."-ref_round_id)*colored_amount)*".$effectiveness_factor." sum_votes FROM transaction_game_ios
					WHERE game_id='".$this->db_game['game_id']."' AND create_round_id IS NULL AND colored_amount > 0 
					GROUP BY option_id
				) i ON op.option_id=i.option_id SET op.unconfirmed_coin_round_score=i.sum_crd, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->blockchain->app->run_query($q);
			}
		}
	}
	
	public function new_block() {
		// This public function only runs for games with p2p_mode='none'
		$log_text = "";
		$last_block_id = $this->blockchain->last_block_id();
		
		$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_id='".($last_block_id+1)."', block_hash='".$this->blockchain->app->random_string(64)."', time_created='".time()."', locally_saved=1;";
		$r = $this->blockchain->app->run_query($q);
		$last_block_id = $this->blockchain->app->last_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
		$r = $this->blockchain->app->run_query($q);
		$block = $r->fetch();
		$last_block_id = $block['block_id'];
		$mining_block_id = $last_block_id+1;
		
		$justmined_round = $this->block_to_round($last_block_id);
		
		$log_text .= "Created block $last_block_id<br/>\n";
		
		// Include all unconfirmed TXs in the just-mined block
		$q = "SELECT * FROM transactions WHERE transaction_desc='transaction' AND game_id='".$this->db_game['game_id']."' AND block_id IS NULL;";
		$r = $this->blockchain->app->run_query($q);
		$fee_sum = 0;
		
		while ($unconfirmed_tx = $r->fetch()) {
			$coins_in = $this->blockchain->app->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->blockchain->app->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if ($coins_in > 0 && $coins_in >= $coins_out) {
				$fee_amount = $coins_in - $coins_out;
				
				$qq = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				
				$total_coin_blocks_created = 0;
				$total_coin_rounds_created = 0;
				
				while ($input_utxo = $rr->fetch()) {
					$coin_blocks_created = ($last_block_id - $input_utxo['create_block_id'])*$input_utxo['amount'];
					$coin_rounds_created = ($justmined_round - $input_utxo['create_round_id'])*$input_utxo['amount'];
					$qqq = "UPDATE transaction_ios SET coin_blocks_created='".$coin_blocks_created."', coin_rounds_created='".$coin_rounds_created."' WHERE io_id='".$input_utxo['io_id']."';";
					$rrr = $this->blockchain->app->run_query($qqq);
					$total_coin_blocks_created += $coin_blocks_created;
					$total_coin_rounds_created += $coin_rounds_created;
				}
				
				$voted_coins_out = $this->blockchain->app->transaction_voted_coins_out($unconfirmed_tx['transaction_id']);
				
				$cbd_per_coin_out = floor(pow(10,8)*$total_coin_blocks_created/$voted_coins_out)/pow(10,8);
				$crd_per_coin_out = floor(pow(10,8)*$total_coin_rounds_created/$voted_coins_out)/pow(10,8);
				
				$qq = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN options op ON a.option_index=op.option_index WHERE io.create_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				
				while ($output_utxo = $rr->fetch()) {
					$temp_event = new Event($this, false, $output_utxo['event_id']);
					
					$coin_blocks_destroyed = floor($cbd_per_coin_out*$output_utxo['amount']);
					$coin_rounds_destroyed = floor($crd_per_coin_out*$output_utxo['amount']);
					
					if ($this->db_game['payout_weight'] == "coin") $votes = $output_utxo['amount'];
					else if ($this->db_game['payout_weight'] == "coin_block") $votes = $coin_blocks_destroyed;
					else if ($this->db_game['payout_weight'] == "coin_round") $votes = $coin_rounds_destroyed;
					else $votes = 0;
					
					$effectiveness_factor = $temp_event->block_id_to_effectiveness_factor($last_block_id);
					$votes = floor($votes*$effectiveness_factor);
					
					$qqq = "UPDATE transaction_ios SET effectiveness_factor='".$effectiveness_factor."', coin_blocks_destroyed='".$coin_blocks_destroyed."', coin_rounds_destroyed='".$coin_rounds_destroyed."', votes='".$votes."' WHERE io_id='".$output_utxo['io_id']."';";
					$rrr = $this->blockchain->app->run_query($qqq);
				}
				
				$qq = "UPDATE transactions t JOIN transaction_ios o ON t.transaction_id=o.create_transaction_id JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id SET t.block_id='".$last_block_id."', t.round_id='".$justmined_round."', o.spend_status='unspent', o.create_block_id='".$last_block_id."', o.create_round_id='".$justmined_round."', i.spend_status='spent', i.spend_block_id='".$last_block_id."', i.spend_round_id='".$justmined_round."' WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				
				$fee_sum += $fee_amount;
			}
		}
		
		$ref_account = false;
		$mined_address = $this->blockchain->app->new_address_key(false, $ref_account);
		$mined_transaction_id = $this->create_transaction(array(false), array($this->blockchain->app->pow_reward_in_round($this->db_game, $justmined_round)+$fee_sum), false, false, $last_block_id, "coinbase", false, array($mined_address['address_id']), false, 0);
		
		// Run payouts
		if ($last_block_id%$this->db_game['round_length'] == 0) {
			$log_text .= "<br/>Running payout on voting round #".$justmined_round.", it's now round ".($justmined_round+1)."<br/>\n";
			$log_text .= $this->add_round_from_db($justmined_round, $last_block_id, true);
		}
		
		$this->update_option_votes();
		$this->check_set_game_over();
		
		return $log_text;
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
	
	public function set_game_over() {		
		$q = "UPDATE games SET game_status='completed', completion_datetime=NOW() WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		$this->db_game['game_status'] = "completed";
		
		if ($this->db_game['game_winning_rule'] == "event_points") {
			$entity_score_info = $this->entity_score_info();
			
			if (!empty($entity_score_info['winning_entity_id'])) {
				$coins_in_existence = $this->coins_in_existence(false);
				$payout_amount = floor(((float)$coins_in_existence)*$this->db_game['game_winning_inflation']);
				if ($payout_amount > 0) {
					$game_votes_q = "SELECT SUM(io.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."';";
					$game_votes_r = $this->blockchain->app->run_query($game_votes_q);
					$game_votes_total = $game_votes_r->fetch()['SUM(io.votes)'];
					
					$winner_votes_q = "SELECT SUM(io.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
					$winner_votes_r = $this->blockchain->app->run_query($winner_votes_q);
					$winner_votes_total = $winner_votes_r->fetch()['SUM(io.votes)'];
					
					echo "payout ".$this->blockchain->app->format_bignum($payout_amount/pow(10,8))." coins to ".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['entity_name']." (".$this->blockchain->app->format_bignum($winner_votes_total/pow(10,8))." total votes)<br/>\n";
					
					$payout_io_q = "SELECT * FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
					$amounts = array();
					$address_ids = array();
					$payout_io_r = $this->blockchain->app->run_query($payout_io_q);
					
					while ($payout_io = $payout_io_r->fetch()) {
						$payout_frac = round(pow(10,8)*$payout_io['votes']/$winner_votes_total)/pow(10,8);
						$payout_io_amount = floor($payout_frac*$payout_amount);
						
						if ($payout_io_amount > 0) {
							$vout = count($amounts);
							$amounts[$vout] = $payout_io_amount;
							$address_ids[$vout] = $payout_io['address_id'];
							echo "pay ".$this->blockchain->app->format_bignum($payout_io_amount/pow(10,8))." to ".$payout_io['address']."<br/>\n";
						}
					}
					$last_block_id = $this->blockchain->last_block_id();
					$transaction_id = $this->create_transaction(false, $amounts, false, false, false, "votebase", false, $address_ids, false, 0);
					$q = "UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id SET t.block_id='".$last_block_id."', t.round_id='".$this->block_to_round($last_block_id)."', io.spend_status='unspent', io.create_block_id='".$last_block_id."', io.create_round_id='".$this->block_to_round($last_block_id)."' WHERE t.transaction_id='".$transaction_id."';";
					$r = $this->blockchain->app->run_query($q);
					$this->refresh_coins_in_existence();

					$q = "UPDATE games SET game_winning_transaction_id='".$transaction_id."', winning_entity_id='".$entity_score_info['winning_entity_id']."' WHERE game_id='".$this->db_game['game_id']."';";
					$r = $this->blockchain->app->run_query($q);
				}
			}
		}
	}
	
	public function apply_user_strategies() {
		$log_text = "";
		$last_block_id = $this->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		
		$current_round_id = $this->block_to_round($mining_block_id);
		$block_of_round = $this->block_id_to_round_index($mining_block_id);
		
		echo 'applying user strategies, block of round = '.$block_of_round.', round length: '.$this->db_game['round_length']."<br/>\n";
		if ($block_of_round != $this->db_game['round_length']) {
			$q = "SELECT * FROM users u JOIN user_games g ON u.user_id=g.user_id JOIN user_strategies s ON g.strategy_id=s.strategy_id";
			$q .= " JOIN user_strategy_blocks usb ON s.strategy_id=usb.strategy_id";
			$q .= " WHERE g.game_id='".$this->db_game['game_id']."' AND usb.block_within_round='".$block_of_round."'";
			$q .= " AND (s.voting_strategy='by_rank' OR s.voting_strategy='by_entity' OR s.voting_strategy='api' OR s.voting_strategy='by_plan')";
			$q .= " ORDER BY RAND();";
			$r = $this->blockchain->app->run_query($q);
			
			$log_text .= "Applying user strategies for block #".$mining_block_id." of ".$this->db_game['name']." looping through ".$r->rowCount()." users.<br/>\n";
			while ($db_user = $r->fetch()) {
				$strategy_user = new User($this->blockchain->app, $db_user['user_id']);
				
				$user_balance = $this->blockchain->user_balance($strategy_user);
				$mature_balance = $this->blockchain->user_mature_balance($strategy_user);
				$free_balance = $mature_balance;
				
				$available_votes = $strategy_user->user_current_votes($this, $last_block_id, $current_round_id);
				
				$log_text .= $strategy_user->db_user['username'].": ".$this->blockchain->app->format_bignum($free_balance/pow(10,8))." coins (".$free_balance.") ".$db_user['voting_strategy']."<br/>\n";
				
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
												$utxo_r = $this->blockchain->app->run_query($utxo_q);
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
							
							$log_text .= $strategy_user->db_user['username']." has ".$free_balance/pow(10,8)." coins available, hitting url: ".$strategy_user->db_user['api_url']."<br/>\n";
							
							foreach ($api_obj->recommendations as $recommendation) {
								if ($recommendation->recommended_amount && $recommendation->recommended_amount > 0 && friendly_intval($recommendation->recommended_amount) == $recommendation->recommended_amount) $amount_sum += $recommendation->recommended_amount;
								else $amount_error = true;
								
								$qq = "SELECT * FROM options WHERE option_id='".$recommendation->option_id."' AND game_id='".$this->db_game['game_id']."';";
								$rr = $this->blockchain->app->run_query($qq);
								if ($rr->rowCount() == 1) {}
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
								$vote_option_ids = array();
								$vote_amounts = array();
								
								foreach ($api_obj->recommendations as $recommendation) {
									if ($api_obj->recommendation_unit == "coin") $vote_amount = $recommendation->recommended_amount;
									else $vote_amount = floor($free_balance*$recommendation->recommended_amount/100);
									
									$vote_option_id = $recommendation->option_id;
									
									$vote_option_ids[count($vote_option_ids)] = $vote_option_id;
									$vote_amounts[count($vote_amounts)] = $vote_amount;
									
									$log_text .= "Vote ".$vote_amount." for ".$vote_option_id."<br/>\n";
								}
								
								$transaction_id = $this->create_transaction($vote_option_ids, $vote_amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', $input_io_ids, false, false, false);
								
								if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
								else $log_text .= "Failed to add transaction.<br/>\n";
							}
						}
					}
					else {
						$pct_free = 100*$free_balance/$user_balance;
						
						if ($pct_free >= $db_user['aggregate_threshold']) {
							$entity_pct_sum = 0;
							$skipped_pct_points = 0;
							$skipped_options = "";
							$num_options_skipped = 0;
							$strategy_entity_points = false;

							$qq = "SELECT * FROM user_strategy_entities WHERE strategy_id='".$db_user['strategy_id']."';";
							$rr = $this->blockchain->app->run_query($qq);
							while ($strategy_entity = $rr->fetch()) {
								$strategy_entity_points[$strategy_entity['entity_id']] = intval($strategy_entity['pct_points']);
							}
							
							$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$this->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
							$rr = $this->blockchain->app->run_query($qq);
							while ($entity = $rr->fetch()) {
								if ($db_user['voting_strategy'] == "by_entity") {
									$by_entity_pct_points = 0;
									if (empty($strategy_entity_points[$entity['entity_id']])) $by_entity_pct_points = 0;
									else $by_entity_pct_points = $strategy_entity_points[$entity['entity_id']];
									$entity_pct_sum += $by_entity_pct_points;
								}
								
								/*if ($sum_votes > 0) {
									$pct_of_votes = 100*$ranked_stats[$option_id2rank[$voting_option['option_id']]]['votes']/$sum_votes;
									if ($pct_of_votes >= $db_user['min_votesum_pct'] && $pct_of_votes <= $db_user['max_votesum_pct']) {}
									else {
										$skipped_options[$voting_option['option_id']] = TRUE;
										if ($db_user == "by_option") $skipped_pct_points += $by_option_pct_points;
										else if (in_array($option_id2rank[$voting_option['option_id']], $by_rank_ranks)) $num_options_skipped++;
									}
								}*/
							}

							/*$round_stats = $this->round_voting_stats_all($current_round_id);
							$sum_votes = $round_stats[0];
							$ranked_stats = $round_stats[2];
							$option_id2rank = $round_stats[3];
							
							if ($db_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $db_user['by_rank_ranks']);
							*/
							if ($db_user['voting_strategy'] == "by_rank") {
								/*$divide_into = count($by_rank_ranks)-$num_options_skipped;
								
								$coins_each = floor(($free_balance-$db_user['transaction_fee'])/$divide_into);
								$remainder_coins = ($free_balance-$db_user['transaction_fee']) - count($by_rank_ranks)*$coins_each;
								
								$log_text .= "Dividing by rank among ".$divide_into." options for ".$strategy_user->db_user['username']."<br/>\n";
								
								$option_ids = array();
								$amounts = array();
								
								$qq = "SELECT * FROM options op JOIN events ev ON op.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
								$rr = $this->blockchain->app->run_query($qq);
								
								while ($voting_option = $rr->fetch()) {
									$rank = $option_id2rank[$voting_option['option_id']]+1;
									if (in_array($rank, $by_rank_ranks) && empty($skipped_options[$ranked_stats[$rank-1]['option_id']])) {
										$log_text .= "Vote ".round($coins_each/pow(10,8), 3)." coins for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>\n";
										
										$option_ids[count($option_ids)] = $ranked_stats[$rank-1]['option_id'];
										$amounts[count($amounts)] = $coins_each;
									}
								}
								if ($remainder_coins > 0) $amounts[count($amounts)-1] += $remainder_coins;
								
								$transaction_id = $this->create_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
								
								if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
								else $log_text .= "Failed to add transaction.<br/>\n";*/
							}
							else if ($db_user['voting_strategy'] == "by_entity") {
								$log_text .= "Dividing by entity for ".$strategy_user->db_user['username']." (".(($free_balance-$db_user['transaction_fee'])/pow(10,8))." coins)<br/>\n";
								
								$mult_factor = 1;
								if ($skipped_pct_points > 0) {
									$mult_factor = floor(pow(10,6)*$entity_pct_sum/($entity_pct_sum-$skipped_pct_points))/pow(10,6);
								}
								
								if ($entity_pct_sum == 100) {
									$option_ids = array();
									$amounts = array();
									$amount_sum = 0;
									
									for ($i=0; $i<count($this->current_events); $i++) {
										$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_id='".$this->current_events[$i]->db_event['event_id']."' GROUP BY en.entity_id ORDER BY en.entity_id;";
										$rr = $this->blockchain->app->run_query($qq);
										while ($entity = $rr->fetch()) {
											$by_entity_pct_points = 0;
											if (!empty($strategy_entity_points[$entity['entity_id']])) $by_entity_pct_points = $strategy_entity_points[$entity['entity_id']];
											if (empty($skipped_entities[$entity['entity_id']]) && $by_entity_pct_points > 0) {
												$effective_frac = floor((1/count($this->current_events))*pow(10,4)*$by_entity_pct_points*$mult_factor)/pow(10,6);
												$coin_amount = floor($effective_frac*($free_balance-$db_user['transaction_fee']));
												
												$log_text .= "Vote ".$by_entity_pct_points."% (".round($coin_amount/pow(10,8), 3)." coins) for ".$entity['entity_name']."<br/>\n";
												
												$option_ids[count($option_ids)] = $entity['option_id'];
												$amounts[count($amounts)] = $coin_amount;
												$amount_sum += $coin_amount;
											}
										}
									}
									if ($amount_sum < ($free_balance-$db_user['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$db_user['transaction_fee']) - $amount_sum;
									$transaction_id = $this->create_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
									if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
									else $log_text .= "Failed to add transaction.<br/>\n";
								}
							}
							else { // by_plan
								$log_text .= "Dividing by plan for ".$strategy_user->db_user['username']."<br/>\n";
								
								$qq = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$db_user['strategy_id']."' AND round_id='".$current_round_id."' AND applied=0;";
								$rr = $this->blockchain->app->run_query($qq);
								
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
										$amount = floor(($free_balance-$db_user['transaction_fee'])*$allocations[$i]['points']/$point_sum);
										$amounts[$i] = $amount;
										$amount_sum += $amount;
									}
									if ($amount_sum < ($free_balance-$db_user['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$db_user['transaction_fee']) - $amount_sum;
									
									$transaction_id = $this->create_transaction($option_ids, $amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', false, false, false, $db_user['transaction_fee']);
									
									if ($transaction_id) {
										$log_text .= "Added transaction $transaction_id<br/>\n";
										
										for ($i=0; $i<count($allocations); $i++) {
											$qq = "UPDATE strategy_round_allocations SET applied=1 WHERE allocation_id='".$allocations[$i]['allocation_id']."';";
											$rr = $this->blockchain->app->run_query($qq);
										}
									}
									else $log_text .= "Failed to add transaction.<br/>\n";
								}
							}
						}
					}
				}
			}
			$this->update_option_votes();
		}
		return $log_text;
	}
	
	public function delete_reset_game($delete_or_reset) {
		$q = "DELETE FROM game_blocks WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "SELECT * FROM game_blocks WHERE locally_saved=1 AND game_id='".$this->db_game['game_id']."' ORDER BY game_block_id DESC LIMIT 1;";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE FROM transaction_game_ios WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE eo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE eoo.* FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$invite_user_ids = array();
		if ($delete_or_reset == "reset") {
			$q = "SELECT * FROM game_invitations WHERE game_id='".$this->db_game['game_id']."' AND used_user_id > 0;";
			$r = $this->blockchain->app->run_query($q);
			while ($invitation = $r->fetch()) {
				$invite_user_ids[count($invite_user_ids)] = $invitation['used_user_id'];
			}
		}
		
		$q = "DELETE FROM game_invitations WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		if ($delete_or_reset == "reset") {
			$q = "UPDATE games SET game_status='published', events_until_block=NULL, coins_in_existence=0, coins_in_existence_block=NULL WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			$giveaway_block_id = $this->blockchain->last_block_id();
			if (!$giveaway_block_id) $giveaway_block_id = 0;
			
			$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			while ($user_game = $r->fetch()) {
				$temp_user = new User($this->blockchain->app, $user_game['user_id']);
				$temp_user->generate_user_addresses($this);
			}
			
			for ($i=0; $i<count($invite_user_ids); $i++) {
				$invitation = false;
				$this->generate_invitation($this->db_game['creator_id'], $invitation, $invite_user_ids[$i]);
				$invite_event = false;
				$this->blockchain->app->try_apply_invite_key($invite_user_ids[$i], $invitation['invitation_key'], $invite_event);
			}
			
			$q = "SELECT * FROM game_giveaways WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			while ($giveaway = $r->fetch()) {
				$replacement_giveaway = $this->new_game_giveaway($giveaway['user_id'], $giveaway['type'], $giveaway['amount']);
				$this->blockchain->app->run_query("DELETE FROM game_giveaways WHERE giveaway_id='".$giveaway['giveaway_id']."';");
			}
		}
		else {
			$q = "DELETE g.*, ug.* FROM games g, user_games ug WHERE g.game_id=".$this->db_game['game_id']." AND ug.game_id=g.game_id;";
			$r = $this->blockchain->app->run_query($q);
			
			$q = "DELETE s.*, sra.* FROM user_strategies s LEFT JOIN strategy_round_allocations sra ON s.strategy_id=sra.strategy_id WHERE s.game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
		}
		$this->update_option_votes();
	}
	
	public function rounds_complete_html($max_round_id, $limit) {
		$html = "";
		
		$show_initial = false;
		$last_block_id = $this->blockchain->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$current_score_q = "SELECT SUM(votes) FROM options WHERE event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$current_score_r = $this->blockchain->app->run_query($current_score_q);
			$current_score = $current_score_r->fetch(PDO::FETCH_NUM);
			$current_score = $current_score[0];
			if ($current_score > 0) {} else $current_score = 0;
			
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/games/'.$this->db_game['url_identifier'].'/events/'.($this->current_events[$i]->db_event['event_index']+1).'">'.$this->current_events[$i]->db_event['event_name'].'</a></div>';
			$html .= '<div class="col-sm-5">Not yet decided';
			$html .= '</div>';
			$html .= '<div class="col-sm-3">'.$this->blockchain->app->format_bignum($current_score/pow(10,8)).' votes cast</div>';
			$html .= '</div>'."\n";
			
			if ($current_round == 1) $show_initial = true;
		}
		
		$q = "SELECT eo.*, e.*, real_winner.name AS real_winner_name, derived_winner.name AS derived_winner_name FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id LEFT JOIN options real_winner ON eo.winning_option_id=real_winner.option_id LEFT JOIN options derived_winner ON eo.derived_winning_option_id=derived_winner.option_id WHERE e.game_id='".$this->db_game['game_id']."' AND eo.round_id <= ".$max_round_id." GROUP BY e.event_id ORDER BY eo.event_id DESC, eo.round_id DESC LIMIT ".$limit.";";
		$r = $this->blockchain->app->run_query($q);
		
		$last_round_shown = 0;
		while ($event_outcome = $r->fetch()) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/games/'.$this->db_game['url_identifier'].'/events/'.($event_outcome['event_index']+1).'">'.$event_outcome['event_name'].'</a></div>';
			$html .= '<div class="col-sm-5">';
			if ($event_outcome['winning_option_id'] > 0) {
				$html .= $event_outcome['real_winner_name']." wins with ".$this->blockchain->app->format_bignum($event_outcome['winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				if ($event_outcome['derived_winning_option_id'] != $event_outcome['winning_option_id']) {
					$html .= ". Should have been ".$event_outcome['derived_winner_name']." with ".$this->blockchain->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['derived_winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				}
			}
			else {
				if ($event_outcome['derived_winning_option_id'] > 0) {
					$html .= $event_outcome['derived_winner_name']." wins with ".$this->blockchain->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['derived_winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				}
				else $html .= "No winner";
			}
			$html .= "</div>";
			$html .= '<div class="col-sm-3">'.$this->blockchain->app->format_bignum($event_outcome['sum_votes']/pow(10,8)).' votes cast</div>';
			$html .= "</div>\n";
			$last_round_shown = $event_outcome['round_id'];
			if ($event_outcome['round_id'] == 1) $show_initial = true;
		}
		
		if ($show_initial) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/games/'.$this->db_game['url_identifier'].'/events/0">'.$this->db_game['name'].'</a></div>';
			$html .= '<div class="col-sm-8">Initial Distribution</div>';
			$html .= '</div>';
		}
		
		$returnvals[0] = $last_round_shown;
		$returnvals[1] = $html;
		
		return $returnvals;
	}
	
	public function addr_text_to_option_id($addr_text) {
		$vote_identifier = $this->blockchain->app->addr_text_to_vote_identifier($addr_text);
		if (!empty($vote_identifier)) {
			$q = "SELECT * FROM options o JOIN events e ON o.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND o.vote_identifier=".$this->blockchain->app->quote_escape($vote_identifier).";";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() == 1) return $r->fetch()['option_id'];
			else return false;
		}
		else return false;
	}
	
	public function option_index_range() {
		$range_row = $this->blockchain->app->run_query("SELECT MAX(o.option_index), MIN(o.option_index) FROM options o JOIN events e ON o.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';")->fetch();
		$min = (int) $range_row['MIN(o.option_index)'];
		$max = (int) $range_row['MAX(o.option_index)'];
		return array($min, $max);
	}
	
	public function option_index_to_current_option_id($option_index) {
		return $this->option_index_to_option_id_in_block($option_index, $this->blockchain->last_block_id()+1);
	}
	
	public function option_index_to_option_id_in_block($option_index, $block_id) {
		$events = $this->events_by_block($block_id);
		$sum_options = 0;
		for ($i=0; $i<count($events); $i++) {
			$thisevent_options = (int) $this->blockchain->app->run_query("SELECT COUNT(*) FROM options WHERE event_id='".$events[$i]->db_event['event_id']."';")->fetch()['COUNT(*)'];
			if ($option_index >= $sum_options && $option_index < $sum_options+$thisevent_options) {
				$event_option_offset = $option_index-$sum_options;
				$first_option_q = "SELECT * FROM options WHERE event_id='".$events[$i]->db_event['event_id']."' ORDER BY option_id ASC LIMIT 1;";
				$first_option = $this->blockchain->app->run_query($first_option_q)->fetch();
				return $first_option['option_id']+$event_option_offset;
			}
			$sum_options += $thisevent_options;
		}
		return false;
	}
	
	public function new_game_giveaway(&$invoice, $target_amount, &$currency_address) {
		/*$type = $invoice['invoice_type'];
		if ($type != "buyin") {
			$type = "join_buyin";
		}
		
		$transaction_id = false;
		if ($target_amount > 0) {
			$addr_id = $this->new_nonuser_address();
			if ($addr_id) {
				$num_utxos = 5;
				$first_denom = 4;
				$actual_amount = 0;
				
				$addr_ids = array();
				$amounts = array();
				$option_ids = array();
				
				$frac_sum = 0;
				for ($i=0; $i<$num_utxos; $i++) {
					$frac_sum += 1/($first_denom+$i);
				}
				for ($i=0; $i<$num_utxos; $i++) {
					$amounts[$i] = floor($target_amount*(1/($first_denom+$i))/$frac_sum);
					$addr_ids[$i] = $addr_id;
					$option_ids[$i] = false;
					$actual_amount += $amounts[$i];
				}
				
				$transaction_id = $this->create_transaction($option_ids, $amounts, false, false, 0, 'giveaway', false, $addr_ids, false, 0);
			}
		}
		
		if ($transaction_id) {
			$q = "INSERT INTO game_giveaways SET type='".$type."', user_game_id='".$invoice['user_game_id']."', amount='".$actual_amount."'";
			if ($transaction_id > 0) $q .= ", transaction_id='".$transaction_id."'";
			if ($user_id) $q .= ", status='claimed'";
			$q .= ";";
			$r = $this->blockchain->app->run_query($q);
			$giveaway_id = $this->blockchain->app->last_insert_id();
			
			$q = "SELECT * FROM game_giveaways WHERE giveaway_id='".$giveaway_id."';";
			$r = $this->blockchain->app->run_query($q);
			
			return $r->fetch();
		}
		else return false;*/
		return false;
	}
	
	public function generate_invitation($inviter_id, &$invitation, $user_id) {
		$q = "INSERT INTO game_invitations SET game_id='".$this->db_game['game_id']."'";
		if ($inviter_id > 0) $q .= ", inviter_id=".$inviter_id;
		$q .= ", invitation_key='".strtolower($this->blockchain->app->random_string(32))."', time_created='".time()."'";
		if ($user_id) $q .= ", used_user_id='".$user_id."'";
		$q .= ";";
		$r = $this->blockchain->app->run_query($q);
		$invitation_id = $this->blockchain->app->last_insert_id();
		
		if ($this->db_game['giveaway_status'] == "invite_free") {
			$giveaway = $this->new_game_giveaway($user_id, 'initial_purchase', false);
			$q = "UPDATE game_invitations SET giveaway_id='".$giveaway['giveaway_id']."' WHERE invitation_id='".$invitation_id."';";
			$r = $this->blockchain->app->run_query($q);
		}
		
		$q = "SELECT * FROM game_invitations WHERE invitation_id='".$invitation_id."';";
		$r = $this->blockchain->app->run_query($q);
		$invitation = $r->fetch();
	}
	
	public function check_giveaway_available($user, &$giveaway) {
		if ($this->db_game['p2p_mode'] == "none") {
			$q = "SELECT * FROM game_giveaways g JOIN transactions t ON g.transaction_id=t.transaction_id WHERE g.status='claimed' AND g.game_id='".$this->db_game['game_id']."' AND g.user_id='".$user->db_user['user_id']."';";
			$r = $this->blockchain->app->run_query($q);

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
			$r = $this->blockchain->app->run_query($q);
			
			$q = "UPDATE game_giveaways SET status='redeemed' WHERE giveaway_id='".$giveaway['giveaway_id']."';";
			$r = $this->blockchain->app->run_query($q);

			return true;
		}
		else return false;
	}

	public function get_user_strategy($user_id, &$user_strategy) {
		$q = "SELECT * FROM user_strategies s JOIN user_games g ON s.strategy_id=g.strategy_id WHERE s.user_id='".$user_id."' AND g.game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		if ($r->rowCount() == 1) {
			$user_strategy = $r->fetch();
			return true;
		}
		else {
			$user_strategy = false;
			return false;
		}
	}
	
	public function plan_options_html($from_round, $to_round, $user_strategy) {
		$html = "
		<script type='text/javascript'>
		var plan_option_max_points = 5;
		var plan_option_increment = 1;
		var plan_rounds = new Array();
		var round_id2plan_round_id = {};
		console.log('Running plan_options_html');
		</script>\n";
		$js = "";
		$round_i = 0;
		for ($round=$from_round; $round<=$to_round; $round++) {
			$js .= "var temp_plan_round = new plan_round(".$round.");\n";
			$js .= "round_id2plan_round_id[".$round."] = ".$round_i.";\n";
			$block_id = ($round-1)*$this->db_game['round_length']+1;
			$events = $this->events_by_block($block_id);
			$html .= '<div class="plan_row">#'.$round.": ";
			for ($event_i=0; $event_i<count($events); $event_i++) {
				$js .= "temp_plan_round.event_ids.push(".$events[$event_i]->db_event['event_id'].");\n";
				$q = "SELECT * FROM options WHERE event_id='".$events[$event_i]->db_event['event_id']."' ORDER BY option_id ASC;";
				$r = $this->blockchain->app->run_query($q);
				$option_index = 0;
				while ($game_option = $r->fetch()) {
					$html .= '<div class="plan_option" id="plan_option_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" onclick="plan_option_clicked('.$round.', '.$events[$event_i]->db_event['event_id'].', '.$game_option['option_id'].');">';
					$html .= '<div class="plan_option_label" id="plan_option_label_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'">'.$game_option['name']."</div>";
					$html .= '<div class="plan_option_amount" id="plan_option_amount_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'"></div>';
					$html .= '<input type="hidden" id="plan_option_input_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" name="poi_'.$round.'_'.$game_option['option_id'].'" value="" />';
					$html .= '</div>';
					$option_index++;
				}
			}
			$js .= "plan_rounds.push(temp_plan_round);\n";
			$html .= "</div>\n";
			$round_i++;
		}
		$html .= '<script type="text/javascript">'.$js."\n".$this->load_all_event_points_js(0, $user_strategy)."\nset_plan_rightclicks();\nset_plan_round_sums();\nrender_plan_rounds();\n</script>\n";
		return $html;
	}
	
	public function paid_players_in_game() {
		$q = "SELECT COUNT(*) FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND ug.payment_required=0;";
		$r = $this->blockchain->app->run_query($q);
		$num_players = $r->fetch(PDO::FETCH_NUM);
		return intval($num_players[0]);
	}
	
	public function start_game() {
		if ($this->db_game['genesis_tx_hash'] != "") {
			$qq = "SELECT * FROM transactions WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND tx_hash=".$this->blockchain->app->quote_escape($this->db_game['genesis_tx_hash']).";";
			$rr = $this->blockchain->app->run_query($qq);
			
			if ($rr->rowCount() == 1) {
				$genesis_transaction = $rr->fetch();
				
				$this->process_buyin_transaction($genesis_transaction);
			}
		}
		
		$qq = "UPDATE games SET initial_coins='".$this->coins_in_existence(false)."', game_status='running', start_time='".time()."', start_datetime=NOW() WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->blockchain->app->run_query($qq);
		
		$qq = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND u.notification_email LIKE '%@%';";
		$rr = $this->blockchain->app->run_query($qq);
		while ($player = $rr->fetch()) {
			$subject = $GLOBALS['coin_brand_name']." game \"".$this->db_game['name']."\" has started.";
			$message = $this->db_game['name']." has started. If haven't already entered your votes, please log in now and start playing.<br/>\n";
			$message .= $this->blockchain->app->game_info_table($this->db_game);
			$email_id = $this->blockchain->app->mail_async($player['notification_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
		}
	}
	
	public function process_buyin_transaction($transaction) {
		if (!empty($this->db_game['game_starting_block']) && !empty($this->db_game['escrow_address']) && $transaction['block_id'] >= $this->db_game['game_starting_block']) {
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
			
			$qq = "SELECT * FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND address_id='".$escrow_address['address_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			
			if ($rr->rowCount() > 0) {
				$qq = "SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND address_id = '".$escrow_address['address_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				$escrowed_coins = (int) $rr->fetch()['SUM(amount)'];
				
				$qq = "SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND address_id != '".$escrow_address['address_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				$non_escrowed_coins = (int) $rr->fetch()['SUM(amount)'];
				
				if ($transaction['tx_hash'] == $this->db_game['genesis_tx_hash']) $colored_coins_generated = $this->db_game['genesis_amount'];
				else {
					$escrow_balance = $this->blockchain->address_balance_at_block($escrow_address, $transaction['block_id']-1);
					$coins_in_existence = $this->coins_in_existence($transaction['block_id']-1);
					
					$exchange_rate = $coins_in_existence/$escrow_balance;
					$colored_coins_generated = floor($exchange_rate*$escrowed_coins);
				}
				
				$sum_colored_coins = 0;
				
				$qq = "SELECT * FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND address_id != '".$escrow_address['address_id']."' ORDER BY out_index ASC;";
				$rr = $this->blockchain->app->run_query($qq);
				
				$create_round_id = $this->block_to_round($transaction['block_id']);
				
				while ($non_escrow_io = $rr->fetch()) {
					$colored_coins = floor($colored_coins_generated*$non_escrow_io['amount']/$non_escrowed_coins);
					$sum_colored_coins += $colored_coins;
					
					$qqq = "INSERT INTO transaction_game_ios SET io_id='".$non_escrow_io['io_id']."', game_id='".$this->db_game['game_id']."', is_coinbase=0, colored_amount='".$colored_coins."', create_round_id='".$create_round_id."', coin_blocks_destroyed=0, coin_rounds_destroyed=0;";
					$rrr = $this->blockchain->app->run_query($qqq);
				}
			}
		}
	}
	
	public function pot_value() {
		$value = $this->paid_players_in_game()*$this->db_game['invite_cost'];
		$qq = "SELECT SUM(settle_amount) FROM game_buyins WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->blockchain->app->run_query($qq);
		$amt = $rr->fetch(PDO::FETCH_NUM);
		$value += $amt[0];
		return $value;
	}
	
	public function account_value_html($account_value) {
		$html = '<font class="greentext">'.$this->blockchain->app->format_bignum($account_value/pow(10,8), 2).'</font> '.$this->db_game['coin_name_plural'];
		$html .= ' <font style="font-size: 12px;">(';
		$coins_in_existence = $this->coins_in_existence(false);
		if ($coins_in_existence > 0) $html .= $this->blockchain->app->format_bignum(100*$account_value/$coins_in_existence)."%";
		else $html .= "0%";
		
		$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
		$escrow_coins = $this->blockchain->address_balance_at_block($escrow_address, $this->blockchain->last_block_id());
		
		$innate_currency_value = floor(($account_value/$coins_in_existence)*$escrow_coins);
		if ($innate_currency_value > 0) {
			$html .= "&nbsp;=&nbsp;".$this->blockchain->app->format_bignum($innate_currency_value/pow(10,8))." ".$this->blockchain->db_blockchain['coin_name_plural'];
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
			$r = $this->blockchain->app->run_query($q);
			$invite_currency = $r->fetch();
		}
		
		$subject = "You've been invited to join ".$this->db_game['name'];
		if ($this->db_game['giveaway_status'] == "invite_pay" || $this->db_game['giveaway_status'] == "public_pay") {
			$subject .= ". Join by paying ".$this->blockchain->app->format_bignum($this->db_game['invite_cost'])." ".$invite_currency['short_name']."s for ".$this->blockchain->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural'].".";
		}
		else {
			$subject .= ". Join now & get ".$this->blockchain->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." for free.";
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
		
		$table = str_replace('<div class="row"><div class="col-sm-5">', '<tr><td>', $this->blockchain->app->game_info_table($this->db_game));
		$table = str_replace('</div><div class="col-sm-7">', '</td><td>', $table);
		$table = str_replace('</div></div>', '</td></tr>', $table);
		$message .= '<table>'.$table.'</table>';
		$message .= "<p>To start playing, accept your invitation by following <a href=\"".$GLOBALS['base_url']."/wallet/".$this->db_game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
		$message .= "<p>This message was sent to you by ".$GLOBALS['site_name']."</p>";
		
		$email_id = $this->blockchain->app->mail_async($to_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
		
		$q = "UPDATE game_invitations SET sent_email_id='".$email_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		return $email_id;
	}
	
	public function entity_score_info($user) {
		$return_obj = false;
		
		if ($user) {
			$qq = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN  transaction_ios io ON o.option_id=io.option_id JOIN entities e ON o.entity_id=e.entity_id JOIN addresses a ON io.address_id=a.address_id WHERE io.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$user_entity_votes_total = $rr->fetch();
			$return_obj['user_entity_votes_total'] = $user_entity_votes_total['SUM(io.votes)'];

			$qq = "SELECT SUM(io.votes) FROM options o JOIN transaction_ios io ON o.option_id=io.option_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$return_obj['entity_votes_total'] = $rr->fetch()['SUM(io.votes)'];
		}
		
		$return_rows = false;
		$q = "SELECT * FROM events ev JOIN options o ON ev.event_id=o.event_id JOIN entities en ON o.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		
		while ($entity = $r->fetch()) {
			$qq = "SELECT COUNT(*), SUM(en.".$this->db_game['game_winning_field'].") points FROM event_outcomes eo JOIN options op ON eo.winning_option_id=op.option_id JOIN events ev ON eo.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND op.entity_id='".$entity['entity_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$info = $rr->fetch();
			
			$return_rows[$entity['entity_id']]['points'] = (int) $info['points'];
			$return_rows[$entity['entity_id']]['entity_name'] = $entity['entity_name'];
			
			$entity_my_pct = false;
			if ($user) {
				$qq = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN transaction_ios io ON o.option_id=io.option_id JOIN addresses a ON io.address_id=a.address_id WHERE io.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				$user_entity_votes = $rr->fetch();
				
				$return_rows[$entity['entity_id']]['my_votes'] = $user_entity_votes['SUM(io.votes)'];
				if ($return_obj['user_entity_votes_total'] > 0) $my_pct = 100*$user_entity_votes['SUM(io.votes)']/$return_obj['user_entity_votes_total'];
				else $my_pct = 0;
				$return_rows[$entity['entity_id']]['my_pct'] = $my_pct;
				
				$entity_votes_q = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN transaction_ios io ON o.option_id=io.option_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$entity_votes_r = $this->blockchain->app->run_query($entity_votes_q);
				$return_rows[$entity['entity_id']]['entity_votes'] = $entity_votes_r->fetch()['SUM(io.votes)'];
			}
		}
		$returnvals['entities'] = $return_rows;

		if ($this->db_game['game_winning_rule'] == "event_points") {
			$max_points = 0;
			$winning_entity_ids = array();
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
	
	public function game_status_explanation($user) {
		$html = "";
		if ($this->db_game['game_status'] == "editable") $html .= "The game creator hasn't yet published this game; it's parameters can still be changed. ";
		else if ($this->db_game['game_status'] == "published") {
			if ($this->db_game['start_condition'] == "players_joined") {
				$num_players = $this->paid_players_in_game();
				$players_needed = ($this->db_game['start_condition_players']-$num_players);
				if ($players_needed > 0) {
					$html .= $num_players."/".$this->db_game['start_condition_players']." players have already joined, waiting for ".$players_needed." more players. ";
				}
			}
			else if ($this->db_game['start_condition'] == "fixed_block") {
				$html .= "This game starts in ".($this->db_game['game_starting_block']-$this->blockchain->last_block_id())." blocks. ";
			}
			else $html .= "This game starts in ".$this->blockchain->app->format_seconds(strtotime($this->db_game['start_datetime'])-time())." at ".$this->db_game['start_datetime'];
		}
		else if ($this->db_game['game_status'] == "completed") $html .= "This game is over. ";
		
		if ($this->db_game['p2p_mode'] == "rpc") {
			$total_blocks = $this->blockchain->last_block_id();
			
			$total_game_blocks = $total_blocks-$this->db_game['game_starting_block']+1;
			
			$q = "SELECT COUNT(*) FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id >= ".$this->db_game['game_starting_block']." AND block_hash IS NULL;";
			$missingheader_blocks = $this->blockchain->app->run_query($q)->fetch()['COUNT(*)'];
			
			$q = "SELECT COUNT(*) FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id >= ".$this->db_game['game_starting_block']." AND locally_saved=0;";
			$missing_blocks = $this->blockchain->app->run_query($q)->fetch()['COUNT(*)'];
			
			$q = "SELECT COUNT(*) FROM game_blocks WHERE game_id='".$this->db_game['game_id']."' AND locally_saved=1;";
			$loaded_game_blocks = $this->blockchain->app->run_query($q)->fetch()['COUNT(*)'];
			$missing_game_blocks = $total_game_blocks - $loaded_game_blocks;
			
			$loading_block = false;
			
			$block_fraction = 0;
			if ($missing_blocks > 0) {
				$q = "SELECT MAX(block_id) FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND locally_saved=1;";
				$loading_block_id = $this->blockchain->app->run_query($q)->fetch()['MAX(block_id)']+1;
				$loading_block = $this->blockchain->app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id='".$loading_block_id."';")->fetch();
				if ($loading_block) {
					list($loading_transactions, $loading_block_sum) = $this->blockchain->block_stats($loading_block);
					$block_fraction = $loading_transactions/$loading_block['num_transactions'];
				}
			}
			$headers_pct_complete = 100*($total_game_blocks-$missingheader_blocks)/$total_game_blocks;
			$blocks_pct_complete = 100*($total_game_blocks-($missing_blocks-$block_fraction))/$total_game_blocks;
			if ($blocks_pct_complete != 100) $html .= "<br/>Loading blocks... ".round($blocks_pct_complete, 2)."% complete. ";
			if ($loading_block) {
				$html .= "Loaded ".$loading_transactions."/".$loading_block['num_transactions']." in block <a href=\"/explorer/".$this->db_game['url_identifier']."/blocks/".$loading_block_id."\">#".$loading_block_id."</a>. ";
			}
			
			$game_blocks_pct_complete = 100*($total_game_blocks-$missing_game_blocks)/$total_game_blocks;
			if ($game_blocks_pct_complete != 100) $html .= "<br/>Loading game... ".round($game_blocks_pct_complete, 2)."% complete. ";
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
				$q = "SELECT SUM(amount) FROM addresses a JOIN transaction_ios io ON a.address_id=io.address_id WHERE a.user_id='".$user->db_user['user_id']."' AND io.create_transaction_id='".$this->db_game['game_winning_transaction_id']."';";
				$r = $this->blockchain->app->run_query($q);
				$game_winning_amount = $r->fetch()['SUM(amount)'];
				$html .= "You won <font class=\"greentext\">".$this->blockchain->app->format_bignum($game_winning_amount/pow(10,8))."</font> ".$this->db_game['coin_name_plural']." in the end-of-game payout.<br/>\n";
			}
			
			foreach ($entity_score_info['entities'] as $entity_id => $entity_info) {
				$html .= "<div class=\"row\"><div class=\"col-sm-3\">".$entity_info['entity_name']."</div><div class=\"col-sm-3\">".$entity_info['points']." electoral votes</div>";
				if ($user) {
					$coins_in_existence = $this->coins_in_existence(false);
					$add_coins = floor($coins_in_existence*$this->db_game['game_winning_inflation']);
					$new_coins_in_existence = $coins_in_existence + $add_coins;
					$account_value = $user->account_coin_value($this);
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
				$html .= "</div>\n";
			}
			$html .= "<br/>\n";
		}
		return $html;
	}
	
	public function game_description() {
		$html = "";
		$blocks_per_hour = 3600/$this->db_game['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,8);
		$rounds_per_hour = 3600/($this->db_game['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->db_game['seconds_per_block']*$this->db_game['round_length'];
		$coins_per_block = $this->blockchain->app->format_bignum($this->db_game['pow_reward']/pow(10,8));
		
		$post_buyin_supply = $this->db_game['giveaway_amount']+$this->coins_in_existence(false);
		if ($post_buyin_supply > 0) $receive_pct = (100*$this->db_game['giveaway_amount']/$post_buyin_supply);
		else $receive_pct = 100;
		
		if ($this->db_game['giveaway_status'] == "invite_pay" || $this->db_game['giveaway_status'] == "public_pay") {
			$invite_disp = $this->blockchain->app->format_bignum($this->db_game['invite_cost']);
			$html .= "To join this game, buy ".$this->blockchain->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." (".round($receive_pct, 2)."% of the coins) for ".$invite_disp." ".$this->db_game['currency_short_name'];
			if ($invite_disp != '1') $html .= "s";
			$html .= ". ";
		}
		else {
			if ($this->db_game['giveaway_amount'] > 0) {
				$coin_disp = $this->blockchain->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8));
				$html .= "Join this game and get ".$coin_disp." ";
				if ($coin_disp == "1") $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
				$html .= " (".round($receive_pct, 2)."% of the coins) for free. ";
			}
		}

		if ($this->db_game['game_status'] == "running") {
			$html .= "This game started ".$this->blockchain->app->format_seconds(time()-$this->db_game['start_time'])." ago; ".$this->blockchain->app->format_bignum($this->coins_in_existence(false)/pow(10,8))." ".$this->db_game['coin_name_plural']."  are already in circulation. ";
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
			$html .= ". In each round, ".$this->blockchain->app->format_bignum($this->db_game['pos_reward']/pow(10,8))." ".$this->db_game['coin_name_plural']." are given to voters and ".$this->blockchain->app->format_bignum($this->db_game['pow_reward']*$this->db_game['round_length']/pow(10,8))." ".$this->db_game['coin_name_plural']." are given to miners";
			$html .= " (".$coins_per_block." coin";
			if ($coins_per_block != 1) $html .= "s";
			$html .= " per block). ";
		}
		else if ($this->db_game['inflation'] == "fixed_exponential") $html .= "This currency grows by ".(100*$this->db_game['exponential_inflation_rate'])."% per round. ".(100 - 100*$this->db_game['exponential_inflation_minershare'])."% is given to voters and ".(100*$this->db_game['exponential_inflation_minershare'])."% is given to miners every ".$this->blockchain->app->format_seconds($seconds_per_round).". ";
		else {} // exponential
		
		$html .= "Each round consists of ".$this->db_game['round_length'].", ".str_replace(" ", "-", rtrim($this->blockchain->app->format_seconds($this->db_game['seconds_per_block']), 's'))." blocks. ";
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
		
		$q = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND ug.payment_required=0 ORDER BY ug.account_value DESC, u.username ASC;";
		$r = $this->blockchain->app->run_query($q);
		$html .= "<h3>".$r->rowCount()." players</h3>\n";
		
		while ($temp_user_game = $r->fetch()) {
			$temp_user = new User($this->blockchain->app, $temp_user_game['user_id']);
			$networth_disp = $this->blockchain->app->format_bignum($temp_user->account_coin_value($this)/pow(10,8));
			
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-4"><a href="" onclick="openChatWindow('.$temp_user_game['user_id'].'); return false;">'.$temp_user_game['username'].'</a></div>';
			
			$html .= '<div class="col-sm-4">'.$networth_disp.' ';
			if ($networth_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			$html .= '</div>';
			
			$html .= '</div>';
			$qq = "UPDATE user_games SET account_value='".($temp_user->account_coin_value($this)/pow(10,8))."' WHERE user_game_id='".$temp_user_game['user_game_id']."';";
			$this->blockchain->app->run_query($qq);
		}
		
		return $html;
	}
	
	public function scramble_plan_allocations($strategy, $weight_map, $from_round, $to_round) {
		if (!$weight_map) $weight_map[0] = 1;
		
		$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
		$r = $this->blockchain->app->run_query($q);
		
		for ($round_id=$from_round; $round_id<=$to_round; $round_id++) {
			$block_id = ($round_id-1)*$this->db_game['round_length']+1;
			$events = $this->events_by_block($block_id);
			$option_list = array();
			for ($e=0; $e<count($events); $e++) {
				$qq = "SELECT * FROM options WHERE event_id='".$events[$e]->db_event['event_id']."' ORDER BY option_id ASC;";
				$rr = $this->blockchain->app->run_query($qq);
				while ($option = $rr->fetch()) {
					$option_list[count($option_list)] = $option;
				}
			}
			$used_option_ids = false;
			for ($i=0; $i<count($weight_map); $i++) {
				$option_index = rand(0, count($option_list)-1);
				if (empty($used_option_ids[$option_list[$option_index]['option_id']])) {
					$points = round($weight_map[$i]*rand(1, 5));
					
					$qq = "INSERT INTO strategy_round_allocations SET strategy_id='".$strategy['strategy_id']."', round_id='".$round_id."', option_id='".$option_list[$option_index]['option_id']."', points='".$points."';";
					$rr = $this->blockchain->app->run_query($qq);
					$used_option_ids[$option_list[$option_index]['option_id']] = true;
				}
			}
		}
	}
	/*
	public function add_transaction(&$coin_rpc, $tx_hash, $block_height, $require_inputs, &$successful, $position_in_block, $only_vout) {
		$successful = true;
		$start_time = microtime(true);
		$benchmark_time = $start_time;
		
		if ($only_vout) {
			$error_message = "Downloading vout #".$only_vout." in ".$tx_hash;
			echo $error_message."\n";
		}
		$q = "SELECT * FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND tx_hash='".$tx_hash."';";
		$r = $this->blockchain->app->run_query($q);
		
		$add_transaction = true;
		if ($r->rowCount() > 0) {
			$unconfirmed_tx = $r->fetch();
			if ($unconfirmed_tx['block_id'] > 0 && $unconfirmed_tx['has_all_outputs'] == 1 && (!$require_inputs || $unconfirmed_tx['has_all_inputs'] == 1)) $add_transaction = false;
			else {
				if ($unconfirmed_tx['game_id'] == $this->db_game['game_id']) {
					$q = "DELETE t.*, io.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
					$r = $this->blockchain->app->run_query($q);
					echo "del.".(microtime(true)-$benchmark_time)." ";
					$benchmark_time = microtime(true);
				}
			}
		}
		
		if ($add_transaction) {
			try {
				if ($block_height) {
					$raw_transaction = $coin_rpc->getrawtransaction($tx_hash);
					$transaction_rpc = $coin_rpc->decoderawtransaction($raw_transaction);
				}
				else {
					$transaction_rpc = $coin_rpc->getrawtransaction($tx_hash, 1);
					if (!empty($transaction_rpc['blockhash'])) {
						$rpc_block = $coin_rpc->getblockheader($transaction_rpc['blockhash']);
						$block_height = $rpc_block['height'];
					}
				}
				echo "get.".(microtime(true)-$benchmark_time)." ";
				$benchmark_time = microtime(true);
				
				$outputs = $transaction_rpc["vout"];
				$inputs = $transaction_rpc["vin"];
				
				if (count($inputs) == 1 && !empty($inputs[0]['coinbase'])) {
					$transaction_type = "coinbase";
					if (count($outputs) > 1) $transaction_type = "votebase";
				}
				else $transaction_type = "transaction";
				
				$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', num_inputs='".count($inputs)."', num_outputs='".count($outputs)."'";
				if ($position_in_block !== false) $q .= ", position_in_block='".$position_in_block."'";
				if ($block_height) {
					if ($transaction_type == "votebase") {
						$vote_identifier = $this->blockchain->app->addr_text_to_vote_identifier($outputs[1]["scriptPubKey"]["addresses"][0]);
						$option_index = $this->vote_identifier_to_option_index($vote_identifier);
						$option_id = $this->option_index_to_option_id_in_block($option_index, $block_height);
						$votebase_option = $this->blockchain->app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';")->fetch();
						if (!empty($votebase_option['event_id'])) $q .= ", votebase_event_id='".$votebase_option['event_id']."'";
					}
					$q .= ", block_id='".$block_height."', round_id='".$this->block_to_round($block_height)."'";
					//$q .= ", effectiveness_factor='".$this->block_id_to_effectiveness_factor($block_height)."'";
				}
				$q .= ", time_created='".time()."';";
				$r = $this->blockchain->app->run_query($q);
				$db_transaction_id = $this->blockchain->app->last_insert_id();
				
				echo "insert.".(microtime(true)-$benchmark_time)." ";
				$benchmark_time = microtime(true);
				
				$spend_io_ids = array();
				$input_sum = 0;
				$output_sum = 0;
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				if ($transaction_type == "transaction" && $require_inputs) {
					for ($j=0; $j<count($inputs); $j++) {
						$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.game_id='".$this->db_game['game_id']."' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
						$r = $this->blockchain->app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$spend_io = $r->fetch();
						}
						else {
							$child_successful = true;
							echo "\n -> $j ";
							$new_tx = $this->add_transaction($coin_rpc, $inputs[$j]["txid"], false, false, $child_successful, false, $inputs[$j]["vout"]);
							$r = $this->blockchain->app->run_query($q);
							
							if ($r->rowCount() > 0) {
								$spend_io = $r->fetch();
							}
							else {
								$successful = false;
								$error_message = "Failed to create inputs for tx #".$db_transaction_id.", created tx #".$new_tx['transaction_id']." then looked for tx_hash=".$inputs[$j]['txid'].", vout=".$inputs[$j]['vout'];
								$this->blockchain->app->log($error_message);
								echo $error_message."\n";
							}
						}
						if ($successful) {
							$spend_io_ids[$j] = $spend_io['io_id'];
							
							$input_sum += (int) $spend_io['amount'];
							
							if ($block_height) {
								$this_io_cbd = ($block_height - $spend_io['block_id'])*$spend_io['amount'];
								$this_io_crd = ($this->block_to_round($block_height) - $spend_io['create_round_id'])*$spend_io['amount'];
								
								$coin_blocks_destroyed += $this_io_cbd;
								$coin_rounds_destroyed += $this_io_crd;
							
								$r = $this->blockchain->app->run_query("UPDATE transaction_ios SET coin_blocks_created='".$this_io_cbd."', coin_rounds_created='".$this_io_crd."' WHERE io_id='".$spend_io['io_id']."';");
							}
						}
					}
				}
				echo "inputs.".(microtime(true)-$benchmark_time)." ";
				$benchmark_time = microtime(true);
				
				if ($successful) {
					$from_vout = 0;
					$to_vout = count($outputs)-1;
					if ($only_vout) {
						$from_vout = $only_vout;
						$to_vout = $only_vout;
					}
					for ($j=$from_vout; $j<=$to_vout; $j++) {
						$option_id = false;
						$event = false;
						$address_text = $outputs[$j]["scriptPubKey"]["addresses"][0];
						
						$output_address = $this->blockchain->create_or_fetch_address($address_text, true, $coin_rpc, false, true, false);
						
						$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', out_index='".$j."'";
						if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
						$q .= ", address_id='".$output_address['address_id']."'";
						if ($output_address['option_index'] != "") {
							$q .= ", option_index=".$output_address['option_index'];
							if ($block_height) {
								$option_id = $this->option_index_to_option_id_in_block($output_address['option_index'], $block_height);
								if ($option_id) {
									$db_event = $this->blockchain->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id='".$option_id."';")->fetch();
									$event = new Event($this, $db_event, false);
									$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_height);
									$q .= ", option_id='".$option_id."', event_id='".$db_event['event_id']."', effectiveness_factor='".$effectiveness_factor."'";
								}
							}
						}
						$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."'";
						if ($block_height) $q .= ", create_block_id='".$block_height."', create_round_id='".$this->block_to_round($block_height)."'";
						$q .= ";";
						$r = $this->blockchain->app->run_query($q);
						$io_id = $this->blockchain->app->last_insert_id();
						
						$output_sum += $outputs[$j]["value"]*pow(10,8);
						
						if ($input_sum > 0) $output_cbd = floor($coin_blocks_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
						else $output_cbd = 0;
						if ($input_sum > 0) $output_crd = floor($coin_rounds_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
						else $output_crd = 0;
						
						if ($this->db_game['payout_weight'] == "coin") $votes = (int) $outputs[$j]["value"]*pow(10,8);
						else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
						else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
						else $votes = 0;
						
						if ($event) {
							$votes = floor($votes*$event->block_id_to_effectiveness_factor($block_height));
							if ($votes != 0 || $output_cbd != 0 || $output_crd =! 0) {
								$q = "UPDATE transaction_ios SET coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', votes='".$votes."' WHERE io_id='".$io_id."';";
								$r = $this->blockchain->app->run_query($q);
							}
						}
					}
					echo "outputs.".(microtime(true)-$benchmark_time)." ";
					$benchmark_time = microtime(true);
					
					if (count($spend_io_ids) > 0) {
						$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_status='spent', spend_transaction_id='".$db_transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$db_transaction_id."', ',')), spend_block_id='".$block_height."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
						$r = $this->blockchain->app->run_query($q);
					}
					
					$fee_amount = ($input_sum-$output_sum);
					if ($transaction_type != "transaction" || !$require_inputs) $fee_amount = 0;
					
					$q = "UPDATE transactions SET load_time=load_time+".(microtime(true)-$start_time);
					if (!$only_vout) $q .= ", has_all_outputs=1";
					if ($require_inputs || $transaction_type != "transaction") $q .= ", has_all_inputs=1, amount='".$output_sum."', fee_amount='".$fee_amount."'";
					$q .= " WHERE transaction_id='".$db_transaction_id."';";
					$r = $this->blockchain->app->run_query($q);
					echo "done.".(microtime(true)-$benchmark_time);
					
					$db_transaction = $this->blockchain->app->run_query("SELECT * FROM transactions WHERE transaction_id='".$db_transaction_id."';")->fetch();
					return $db_transaction;
				}
				else {
					echo "done.".(microtime(true)-$benchmark_time);
					return false;
				}
			}
			catch (Exception $e) {
				$successful = false;
				var_dump($e);
				$this->blockchain->app->log($this->db_game['name'].": Failed to fetch transaction ".$tx_hash);
				return false;
			}
		}
	}*/
	
	public function refresh_coins_in_existence() {
		$last_block_id = $this->blockchain->last_block_id();
		$q = "UPDATE games SET coins_in_existence_block=0 WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		$coi = $this->coins_in_existence($last_block_id);
	}
	
	public function coins_in_existence($block_id) {
		$last_block_id = $this->blockchain->last_block_id();
		
		if ($last_block_id == 0 || ($last_block_id != $this->db_game['coins_in_existence_block'] || ($block_id !== false && $last_block_id != $block_id))) {
			$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id WHERE gio.game_id='".$this->db_game['game_id']."'";
			if ($block_id !== false) $q .= " AND io.create_block_id <= ".$block_id." AND ((io.spend_block_id IS NULL AND io.spend_status='unspent') OR io.spend_block_id>".$block_id.")";
			else $q .= " AND io.spend_status='unspent'";
			$q .= ";";
			$r = $this->blockchain->app->run_query($q);
			$coins = $r->fetch(PDO::FETCH_NUM);
			$coins = $coins[0];
			if ($coins > 0) {} else $coins = 0;
			if (!$block_id || $block_id == $last_block_id) {
				$q = "UPDATE games SET coins_in_existence='".$coins."', coins_in_existence_block='".$last_block_id."' WHERE game_id='".$this->db_game['game_id']."';";
				$this->blockchain->app->run_query($q);
			}
			return $coins;
		}
		else {
			return $this->db_game['coins_in_existence'];
		}
		
		return 0;
	}
	
	public function fetch_user_strategy(&$user_game) {
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$user_game['strategy_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$user_strategy = $r->fetch();
		}
		else {
			$q = "SELECT * FROM user_strategies WHERE user_id='".$user_game['user_id']."' AND game_id='".$user_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$user_strategy = $r->fetch();
				$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE user_game_id='".$user_game['user_game_id']."';";
				$r = $this->blockchain->app->run_query($q);
			}
			else {
				$q = "DELETE FROM user_games WHERE user_game_id='".$user_game['user_game_id']."';";
				$r = $this->blockchain->app->run_query($q);
				die("No strategy!");
			}
		}
		return $user_strategy;
	}
	
	public function add_round_from_db($round_id, $last_block_id, $add_payout_transaction) {
		$log_text = "";
		for ($i=0; $i<count($this->current_events); $i++) {
			$log_text .= $this->current_events[$i]->set_outcome_from_db($round_id, $last_block_id, $add_payout_transaction);
		}
		return $log_text;
	}
	
	public function send_round_notifications($round_id, &$round_voting_stats) {
		/*if (empty($round_voting_stats)) $round_voting_stats = $this->round_voting_stats_all($round_id);
		
		$sum_votes = $round_voting_stats[0];
		$max_sum_votes = $round_voting_stats[1];
		$option_id2rank = $round_voting_stats[3];
		$round_voting_stats = $round_voting_stats[2];
		
		$winning_option = FALSE;
		$winning_votesum = 0;
		$winning_votes = 0;
		for ($rank=0; $rank<$this->db_event['num_voting_options']; $rank++) {
			$option_id = $round_voting_stats[$rank]['option_id'];
			$option_rank2db_id[$rank] = $option_id;
			$option_votes = $this->option_votes_in_round($option_id, $round_id);
			
			if ($option_votes['sum'] > $max_sum_votes) {}
			else if (!$winning_option && $option_votes['sum'] > 0) {
				$winning_option = $option_id;
				$winning_votesum = $option_votes['sum'];
				$winning_votes = $option_votes['sum'];
			}
		}
		$db_winning_option = false;
		if ($winning_option) {
			$db_winning_option = $this->blockchain->app->run_query("SELECT * FROM options WHERE option_id='".$winning_option."';")->fetch();
		}
		
		$q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.event_id='".$this->db_event['event_id']."' AND ug.notification_preference='email' AND u.notification_email LIKE '%@%';";
		$r = $this->blockchain->app->run_query($q);
		echo "Sending notifications to ".$r->rowCount()." players.<br/>\n";
		while ($user_event = $r->fetch()) {
			$returnvals = $this->my_votes_in_round($round_id, $user_event['user_id'], false);
			$my_votes = $returnvals[0];
			$coins_voted = $returnvals[1];
			
			$subject = $this->db_event['name'].": ";
			if ($winning_option) $subject .= $db_winning_option['name']." wins round #".$round_id;
			else $subject .= "No winner in round #".$round_id;
			
			$my_votes = false;
			$message = "<p>".$this->user_winnings_in_round_description($user_event['user_id'], $round_id, $round_status, $winning_option, $winning_votes, $db_winning_option['name'], $my_votes)."</p>";
			
			if ($this->db_event['final_round'] > 0 && $round_id >= $this->db_event['final_round']) {}
			else $message .= "<p>Round #".($round_id+1)." has just started. <a href=\"".$GLOBALS['base_url']."/wallet/".$this->db_event['url_identifier']."\">Log in</a> now and vote to make sure you don't miss out.</p>";
			
			$message .= "<p>To stop receiving these notifications, please <a href=\"".$GLOBALS['base_url']."/wallet/".$this->db_event['url_identifier']."\">log in</a>, then click \"Strategy\" and then edit your notification settings.</p>";
			
			$delivery_id = $this->blockchain->app->mail_async($user_event['notification_email'], $GLOBALS['site_name'], "noreply@".$GLOBALS['site_domain'], $subject, $message, "", "");
			echo "sent one to ".$user_event['notification_email']." (".$delivery_id.")<br/>\n";
		}*/
	}
	
	public function load_current_events() {
		$this->current_events = array();
		$mining_block_id = $this->blockchain->last_block_id()+1;
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND ev.event_starting_block<=".$mining_block_id." AND ev.event_final_block>=".$mining_block_id." ORDER BY ev.event_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		while ($db_event = $r->fetch()) {
			$this->current_events[count($this->current_events)] = new Event($this, $db_event, false);
		}
	}
	
	public function events_by_block($block_id) {
		$events = array();
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND ev.event_starting_block<=".$block_id." AND ev.event_final_block>=".$block_id." ORDER BY ev.event_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		while ($db_event = $r->fetch()) {
			$events[count($events)] = new Event($this, $db_event, false);
		}
		return $events;
	}
	
	public function event_ids() {
		$event_ids = "";
		for ($i=0; $i<count($this->current_events); $i++) {
			$event_ids .= $this->current_events[$i]->db_event['event_id'].",";
		}
		$event_ids = substr($event_ids, 0, strlen($event_ids)-1);
		return $event_ids;
	}
	
	public function new_event_js($game_index, $user) {
		$last_block_id = $this->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		$current_round = $this->block_to_round($mining_block_id);
		
		$user_id = false;
		if ($user) $user_id = $user->db_user['user_id'];
		
		$js = "console.log('loading new events!');\n";
		$js .= "for (var i=0; i<games[".$game_index."].events.length; i++) {\n";
		$js .= "\tgames[".$game_index."].events[i].deleted = true;\n";
		$js .= "\t$('#game".$game_index."_event'+i).remove();\n";
		$js .= "}\n";
		$js .= "games[".$game_index."].events.length = 0;\n";
		$js .= "games[".$game_index."].events = new Array();\n";
		$js .= "var event_html = '';\n";
		
		$event_bootstrap_cols = 6;
		if (count($this->current_events) == 1) $event_bootstrap_cols = 12;
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$event = $this->current_events[$i];
			$round_stats = $event->round_voting_stats_all($current_round);
			$sum_votes = $round_stats[0];
			$option_id2rank = $round_stats[3];
			$js .= '
			games['.$game_index.'].events['.$i.'] = new Event(games['.$game_index.'], '.$i.', '.$event->db_event['event_id'].', '.$event->db_event['num_voting_options'].', "'.$event->db_event['vote_effectiveness_function'].'");'."\n";
			
			$option_q = "SELECT * FROM options WHERE event_id='".$event->db_event['event_id']."' ORDER BY option_id ASC;";
			$option_r = $this->blockchain->app->run_query($option_q);
			while ($option = $option_r->fetch()) {
				$js .= 'event_html += "<div class=\'modal fade\' id=\'game'.$game_index.'_event'.$i.'_vote_confirm_'.$option['option_id'].'\'></div>";';
			}
			
			$option_r = $this->blockchain->app->run_query($option_q);
			
			$j=0;
			while ($option = $option_r->fetch()) {
				$has_votingaddr = "false";
				if ($user) {
					$votingaddr_id = $user->user_address_id($this, $option['option_index'], false);
					if ($votingaddr_id !== false) $has_votingaddr = "true";
				}
				$js .= "games[".$game_index."].events[".$i."].options.push(new option(games[".$game_index."].events[".$i."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", '".$option['name']."', 0, $has_votingaddr));\n";
				$j++;
			}
			$js .= '
			games['.$game_index.'].events['.$i.'].option_selected(0);
			console.log("adding game, event '.$i.' into DOM...");'."\n";
			if ($i == 0) $js .= 'event_html += "<div class=\'row\'>";';
			$js .= 'event_html += "<div class=\'col-sm-'.$event_bootstrap_cols.'\'>";';
			$js .= 'event_html += "<div id=\'game'.$game_index.'_event'.$i.'\' class=\'game_event_box\'><div id=\'game'.$game_index.'_event'.$i.'_current_round_table\'></div><div id=\'game'.$game_index.'_event'.$i.'_my_current_votes\'></div></div>";'."\n";
			$js .= 'event_html += "</div>";';
			if ($i%2 == 1 || $i == count($this->current_events)-1) {
				$js .= 'event_html += "</div>';
				if ($i < count($this->current_events)-1) $js .= '<div class=\'row\'>';
				$js .= '";'."\n";
			}
		}
		$js .= '$("#game'.$game_index.'_events").html(event_html);'."\n";
		$js .= 'for (var i=0; i<games['.$game_index.'].events.length; i++) {'."\n";
		$js .= 'games['.$game_index.'].events[i].event_loop_event();'."\n";
		$js .= "}\n";
		$js .= '
		$(document).ready(function() {
			render_tx_fee();
			//load_plan_option_games();
			notification_pref_changed();
			alias_pref_changed();
			reload_compose_vote();
			set_select_add_output();
			
			$(".datepicker").datepicker();
		});
		$(document).keypress(function (e) {
			if (e.which == 13) {
				var selected_option_db_id = $("#game'.$game_index.'_rank2option_id_"+selected_option_id).val();
				
				if ($("#game'.$game_index.'_vote_amount_"+selected_option_db_id).is(":focus")) {
					games['.$game_index.'].confirm_vote(selected_option_db_id);
				}
			}
		});';
		return $js;
	}
	
	public function block_id_to_round_index($block_id) {
		return (($block_id-1)%$this->db_game['round_length'])+1;
	}
	
	public function mature_io_ids_csv($user_id) {
		if ($user_id > 0) {
			$ids_csv = "";
			$last_block_id = $this->blockchain->last_block_id();
			$io_q = "SELECT io.io_id FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND a.user_id='".$user_id."' AND gio.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR gio.instantly_mature = 1)";
			if ($this->db_game['payout_weight'] == "coin_round") {
				$io_q .= " AND gio.create_round_id < ".$this->block_to_round($last_block_id+1);
			}
			$io_q .= " ORDER BY io.io_id ASC;";
			$io_r = $this->blockchain->app->run_query($io_q);
			while ($io = $io_r->fetch(PDO::FETCH_NUM)) {
				$ids_csv .= $io[0].",";
			}
			if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, strlen($ids_csv)-1);
			return $ids_csv;
		}
		else return "";
	}
	
	public function bet_round_range() {
		$last_block_id = $this->blockchain->last_block_id();
		$mining_block_within_round = $this->block_id_to_round_index($last_block_id+1);
		$current_round = $this->block_to_round($last_block_id+1);
		
		if ($mining_block_within_round <= 5) $start_round_id = $current_round;
		else $start_round_id = $current_round+1;
		$stop_round_id = $start_round_id+99;
		
		return array($start_round_id, $stop_round_id);
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
			$q = "SELECT * FROM options WHERE event_id='".$this->db_game['game_id']."' AND option_id='".$winner."';";
			$r = $this->game->app->run_query($q);
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
			
			$q = "SELECT * FROM addresses WHERE event_id='".$this->db_game['game_id']."' AND address='".$burn_address_text."';";
			$r = $this->game->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$burn_address = $r->fetch();
			}
			else {
				$q = "INSERT INTO addresses SET event_id='".$this->db_game['game_id']."', address='".$burn_address_text."', bet_round_id='".$round_id."'";
				if ($option_id > 0) $q .= ", bet_option_id='".$option_id."'";
				$q .= ";";
				$r = $this->game->app->run_query($q);
				$burn_address_id = $this->game->app->last_insert_id();
				
				$q = "SELECT * FROM addresses WHERE address_id='".$burn_address_id."';";
				$r = $this->game->app->run_query($q);
				$burn_address = $r->fetch();
			}
			return $burn_address;
		}
		else return false;
	}

	public function my_bets($user) {
		$html = "";
		/*$q = "SELECT * FROM transactions WHERE transaction_desc='bet' AND game_id='".$this->db_game['game_id']."' AND from_user_id='".$user->db_user['user_id']."' GROUP BY bet_round_id ORDER BY bet_round_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$last_block_id = $this->blockchain->last_block_id();
			$current_round = $this->block_to_round($last_block_id+1);
			
			$html .= "<h2>You've placed bets on ".$r->rowCount()." round";
			if ($r->rowCount() != 1) $html .= "s";
			$html .= ".</h2>\n";
			$html .= '<div class="bets_table">';
			while ($bet_round = $r->fetch()) {
				$html .= '<div class="row bordered_row bet_row">';
				$disp_html = "";
				$qq = "SELECT a.*, n.*, SUM(i.amount) FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id JOIN addresses a ON i.address_id=a.address_id LEFT JOIN options gvo ON a.bet_option_id=gvo.option_id WHERE t.game_id='".$this->db_game['game_id']."' AND t.from_user_id='".$user['user_id']."' AND t.bet_round_id='".$bet_round['bet_round_id']."' AND a.bet_round_id > 0 GROUP BY a.address_id ORDER BY SUM(i.amount) DESC;";
				$rr = $this->game->app->run_query($qq);
				$coins_bet_for_round = 0;
				while ($option_bet = $rr->fetch()) {
					if ($option_bet['name'] == "") $option_bet['name'] = "No Winner";
					$coins_bet_for_round += $option_bet['SUM(i.amount)'];
					$disp_html .= '<div class="">';
					$disp_html .= '<div class="col-md-5">'.$this->game->app->format_bignum($option_bet['SUM(i.amount)']/pow(10,8))." coins towards ".$option_bet['name'].'</div>';
					$disp_html .= '<div class="col-md-5"><a href="/explorer/'.$this->db_event['url_identifier'].'/addresses/'.$option_bet['address'].'">'.$option_bet['address'].'</a></div>';
					$disp_html .= "</div>\n";
				}
				if ($bet_round['bet_round_id'] >= $current_round) {
					$html .= "You made bets totalling ".$this->game->app->format_bignum($coins_bet_for_round/pow(10,8))." coins on round ".$bet_round['bet_round_id'].".";
				}
				else {
					$qq = "SELECT SUM(i.amount) FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id JOIN addresses a ON i.address_id=a.address_id WHERE t.block_id='".($bet_round['bet_round_id']*$this->db_game['round_length'])."' AND t.transaction_desc='betbase' AND a.user_id='".$user['user_id']."';";
					$rr = $this->game->app->run_query($qq);
					$amount_won = $rr->fetch(PDO::FETCH_NUM);
					$amount_won = $amount_won[0];
					if ($amount_won > 0) {
						$html .= "You bet ".$this->game->app->format_bignum($coins_bet_for_round/pow(10,8))." coins and won ".$this->game->app->format_bignum($amount_won/pow(10,8))." back for a ";
						if ($amount_won-$coins_bet_for_round >= 0) $html .= 'profit of <font class="greentext">+'.$this->game->app->format_bignum(($amount_won-$coins_bet_for_round)/pow(10,8)).'</font> coins.';
						else $html .= 'loss of <font class="redtext">'.$this->game->app->format_bignum(($coins_bet_for_round-$amount_won)/pow(10,8))."</font> coins.";
					}
				}
				$html .= '&nbsp;&nbsp; <a href="" onclick="$(\'#my_bets_details_'.$bet_round['bet_round_id'].'\').toggle(\'fast\'); return false;">Details</a><br/>'."\n";
				$html .= '<div id="my_bets_details_'.$bet_round['bet_round_id'].'" style="display: none;">'.$disp_html."</div>\n";
				$html .= "</div>\n";
			}
			$html .= "</div>\n";
		}*/
		return $html;
	}
	
	public function last_voting_transaction_id() {
		$q = "SELECT transaction_id FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND option_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
		$r = $this->game->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		if ($r[0] > 0) {} else $r[0] = 0;
		return $r[0];
	}
	
	public function select_input_buttons($user_id) {
		$js = "mature_ios.length = 0;\n";
		$html = "<p>Click on the coins below to compose your voting transaction.</p>\n";
		$input_buttons_html = "";
		
		$last_block_id = $this->blockchain->last_block_id();
		
		$output_q = "SELECT io.*, gio.* FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND a.user_id='".$user_id."' AND gio.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR gio.instantly_mature=1)";
		if ($this->db_game['payout_weight'] == "coin_round") $output_q .= " AND gio.create_round_id < ".$this->block_to_round($last_block_id+1);
		$output_q .= " GROUP BY io.io_id ORDER BY io.io_id ASC;";
		$output_r = $this->blockchain->app->run_query($output_q);
		
		$utxos = array();
		
		while ($utxo = $output_r->fetch()) {
			if (intval($utxo['create_block_id']) > 0) {} else $utxo['create_block_id'] = 0;
			
			$utxos[count($utxos)] = $utxo;
			$input_buttons_html .= '<div ';
			
			$input_buttons_html .= 'id="select_utxo_'.$utxo['io_id'].'" class="btn btn-default select_utxo';
			if ($this->db_game['logo_image_id'] > 0) $input_buttons_html .= ' select_utxo_image';
			$input_buttons_html .= '" onclick="add_utxo_to_vote(\''.$utxo['io_id'].'\', '.$utxo['colored_amount'].', '.$utxo['create_block_id'].');">';
			$input_buttons_html .= '</div>'."\n";
			
			$js .= "mature_ios.push(new mature_io(mature_ios.length, ".$utxo['io_id'].", ".$utxo['colored_amount'].", ".$utxo['create_block_id']."));\n";
		}
		$js .= "refresh_mature_io_btns();\n";
		
		$html .= '<div id="select_input_buttons_msg"></div>'."\n";
		$html .= $input_buttons_html;
		$html .= '<script type="text/javascript">'.$js."</script>\n";
		
		return $html;
	}
	
	public function load_all_event_points_js($game_index, $user_strategy) {
		$js = "";
		/*$q = "SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id='".$this->db_game['game_id']."' ORDER BY e.event_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		$i=0;
		while ($db_event = $r->fetch()) {
			$option_q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_id ASC;";
			$option_r = $this->blockchain->app->run_query($option_q);
			$j=0;
			while ($option = $option_r->fetch()) {
				$qq = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND option_id='".$option['option_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				if ($rr->rowCount() > 0) {
					$sra = $rr->fetch();
					$points = $sra['points'];
				}
				else $points = 0;
				
				$js .= "games[".$game_index."].all_events[".$i."].options[".$j."].points = ".$points.";\n";
				$j++;
			}
			$i++;
		}*/
		return $js;
	}
	
	public function logo_image_url() {
		if ($this->db_game['logo_image_id'] > 0) {
			$db_image = $this->blockchain->app->run_query("SELECT * FROM images WHERE image_id='".$this->db_game['logo_image_id']."';")->fetch();
			return $this->blockchain->app->image_url($db_image);
		}
		else return "";
	}
	
	public function vote_effectiveness_function() {
		return $this->db_game['default_vote_effectiveness_function'];
	}
	
	public function generate_voting_addresses(&$coin_rpc, $time_limit_seconds) {
		$option_index_range = $this->option_index_range();
		
		if ($time_limit_seconds) $seconds_per_option = ceil($time_limit_seconds/($option_index_range[1] - $option_index_range[0]));
		
		for ($option_index=$option_index_range[0]; $option_index<=$option_index_range[1]; $option_index++) {
			$loop_start_time = microtime(true);
			
			$qq = "SELECT * FROM addresses WHERE primary_blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND option_index='".$option_index."' AND user_id IS NULL AND is_mine=1;";
			$rr = $this->blockchain->app->run_query($qq);
			$num_addr = $rr->rowCount();
			
			if ($num_addr < $this->db_game['min_unallocated_addresses']) {
				echo "Generate ".($this->db_game['min_unallocated_addresses']-$num_addr)." unallocated #".$option_index." addresses in ".$this->db_game['name'];
				if ($coin_rpc) echo " by RPC";
				else echo " by bitcoin-sci";
				echo "<br/>\n";
				
				if ($coin_rpc) {
					$try_by_sci = false;
					try {
						$new_voting_addr_count = 0;
						do {
							$temp_address = $coin_rpc->getnewaddress();
							$new_addr_db = $this->blockchain->create_or_fetch_address($temp_address, false, $coin_rpc, true, false, false);
							if ($new_addr_db['option_index'] == $option_index) {
								$new_voting_addr_count++;
							}
						}
						while ($new_voting_addr_count < ($this->db_game['min_unallocated_addresses']-$num_addr) && (!$time_limit_seconds || microtime(true) <= $loop_start_time+$seconds_per_option));
					}
					catch (Exception $e) {
						$try_by_sci = true;
					}
				}
				else $try_by_sci = true;
				
				if ($try_by_sci) {
					$new_voting_addr_count = 0;
					do {
						$ref_account = false;
						$db_address = $this->blockchain->app->new_address_key($this->blockchain->currency_id(), $ref_account);
						$new_voting_addr_count++;
					}
					while ($new_voting_addr_count < ($this->db_game['min_unallocated_addresses']-$num_addr) && (!$time_limit_seconds || microtime(true) <= $loop_start_time+$seconds_per_option));
				}
			}
		}
	}
	
	public function ensure_events_until_block($block_id) {
		$round_id = $this->block_to_round($block_id);
		$ensured_round = $this->block_to_round((int)$this->db_game['events_until_block']);
		
		if ($round_id > $ensured_round) {
			if ($this->db_game['event_rule'] == "entity_type_option_group" || $this->db_game['event_rule'] == "single_event_series") {
				if ($this->db_game['event_rule'] == "entity_type_option_group") {
					$q = "SELECT * FROM entity_types WHERE entity_type_id='".$this->db_game['event_entity_type_id']."';";
					$r = $this->blockchain->app->run_query($q);
					
					if ($r->rowCount() == 1) {
						$entity_type = $r->fetch();
					}
					else die("Error: game type ".$this->db_game['game_type_id']." requires an event_entity_type_id.\n");
				}
				
				$q = "SELECT * FROM option_groups WHERE group_id='".$this->db_game['option_group_id']."';";
				$r = $this->blockchain->app->run_query($q);
				$option_group = $r->fetch();
				
				$q = "SELECT * FROM entities e JOIN option_group_memberships mem ON e.entity_id=mem.entity_id WHERE mem.option_group_id='".$this->db_game['option_group_id']."' ORDER BY e.entity_id ASC;";
				$r = $this->blockchain->app->run_query($q);
				$db_option_entities = array();
				while ($option_entity = $r->fetch()) {
					$db_option_entities[count($db_option_entities)] = $option_entity;
				}
				
				$event_i = 0;
				$round_option_i = 0;
				
				if ($this->db_game['event_rule'] == "entity_type_option_group") {
					$q = "SELECT * FROM entities WHERE entity_type_id='".$entity_type['entity_type_id']."' ORDER BY entity_id ASC;";
					$r = $this->blockchain->app->run_query($q);
					
					while ($event_entity = $r->fetch()) {
						$event_type = $this->add_event_type($db_option_entities, $event_entity, $event_i);
						$this->add_event_by_event_type($event_type, $db_option_entities, $option_group, $round_option_i, $event_i, $event_type['name'], $event_entity);
						$event_i++;
					}
				}
				else {
					if ($ensured_round > 0) $start_round = $ensured_round+1;
					else $start_round = $this->block_to_round($this->db_game['game_starting_block']);
					
					$event_type = $this->add_event_type($db_option_entities, false, false);
					echo "looping from round #".($start_round-1)." to ".$round_id."<br/>\n";
					for ($i=$start_round-1; $i<$round_id; $i++) {
						$event_i = $i-$this->block_to_round($this->db_game['game_starting_block']);
						$event_name = $event_type['name']." #".($event_i+1);
						$this->add_event_by_event_type($event_type, $db_option_entities, $option_group, $round_option_i, $event_i, $event_name, false);
						$event_i++;
					}
				}
			}
			
			$q = "UPDATE games SET events_until_block='".$block_id."' WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			$this->db_game['events_until_block'] = $block_id;
		}
	}
	
	public function add_event_type($db_option_entities, $event_entity, $event_i) {
		if ($event_entity) {
			if (count($db_option_entities) == 2 && !empty($db_option_entities[0]['last_name'])) {
				$event_type_name = $db_option_entities[0]['last_name']." vs ".$db_option_entities[1]['last_name']." in ".$event_entity['entity_name'];
				$event_type_identifier = strtolower($db_option_entities[0]['last_name']."-".$db_option_entities[1]['last_name']."-".$event_entity['entity_name']);
			}
			else {
				$event_type_name = $event_entity['entity_name']." ".ucwords($this->db_game['event_type_name']);
				$event_type_identifier = strtolower($event_entity['entity_name']."-".$this->db_game['event_type_name']);
			}
		}
		else {
			$event_type_name = $this->db_game['event_type_name'];
			if (!empty($event_i)) $event_type_name .= " - Round #".($event_i+1);
			$event_type_identifier = str_replace(" ", "-", strtolower($this->db_game['event_type_name']));
			if (!empty($event_i)) $event_type_identifier .= "-round-".($event_i+1);
		}
		$qq = "SELECT * FROM event_types WHERE game_id='".$this->db_game['game_id']."' AND url_identifier=".$this->blockchain->app->quote_escape($event_type_identifier).";";
		$rr = $this->blockchain->app->run_query($qq);
		if ($rr->rowCount() > 0) {
			$event_type = $rr->fetch();
		}
		else {
			$qq = "INSERT INTO event_types SET game_id='".$this->db_game['game_id']."', option_group_id='".$this->db_game['option_group_id']."'";
			if ($event_entity) $qq .= ", entity_id='".$event_entity['entity_id']."'";
			$qq .= ", name='".$event_type_name."', url_identifier=".$this->blockchain->app->quote_escape($event_type_identifier).", num_voting_options='".count($db_option_entities)."', vote_effectiveness_function='".$this->db_game['default_vote_effectiveness_function']."', max_voting_fraction='".$this->db_game['default_max_voting_fraction']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$event_type_id = $this->blockchain->app->last_insert_id();
			
			$qq = "SELECT * FROM event_types WHERE event_type_id='".$event_type_id."';";
			$event_type = $this->blockchain->app->run_query($qq)->fetch();
		}
		return $event_type;
	}
	
	public function add_event_by_event_type(&$event_type, &$db_option_entities, &$option_group, &$round_option_i, &$event_i, $event_name, $event_entity) {
		$skip_blocks = ceil(($this->db_game['game_starting_block']-1)/$this->db_game['round_length'])*$this->db_game['round_length'];
		$starting_round = floor($event_i/$this->db_game['events_per_round'])+1;
		$event_starting_block = $skip_blocks+($starting_round-1)*$this->db_game['round_length']+1;
		$event_final_block = $skip_blocks+$starting_round*$this->db_game['round_length'];
		
		if ($event_i%$this->db_game['events_per_round'] == 0) $round_option_i = 0;
		
		$qq = "SELECT * FROM events WHERE game_id='".$this->db_game['game_id']."' AND event_index='".$event_i."';";
		$rr = $this->blockchain->app->run_query($qq);
		
		if ($rr->rowCount() == 0) {
			$qq = "INSERT INTO events SET game_id='".$this->db_game['game_id']."', event_index='".$event_i."', event_type_id='".$event_type['event_type_id']."', event_starting_block='".$event_starting_block."', event_final_block='".$event_final_block."', event_name=".$this->blockchain->app->quote_escape($event_name).", option_name=".$this->blockchain->app->quote_escape($option_group['option_name']).", option_name_plural=".$this->blockchain->app->quote_escape($option_group['option_name_plural']).", option_max_width='".$this->db_game['default_option_max_width']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$event_id = $this->blockchain->app->last_insert_id();
			
			for ($i=0; $i<count($db_option_entities); $i++) {
				if (!empty($event_entity)) $option_name = $db_option_entities[$i]['last_name']." wins ".$event_entity['entity_name'];
				else $option_name = $db_option_entities[$i]['entity_name'];
				$vote_identifier = $this->blockchain->app->option_index_to_vote_identifier($round_option_i);
				$qq = "INSERT INTO options SET event_id='".$event_id."', entity_id='".$db_option_entities[$i]['entity_id']."', membership_id='".$db_option_entities[$i]['membership_id']."', image_id='".$db_option_entities[$i]['default_image_id']."', name=".$this->blockchain->app->quote_escape($option_name).", vote_identifier=".$this->blockchain->app->quote_escape($vote_identifier).", option_index='".$round_option_i."';";
				$rr = $this->blockchain->app->run_query($qq);
				$round_option_i++;
			}
		}
	}
	
	public function event_next_prev_links($event) {
		$html = "";
		if ($event->db_event['event_index'] > 0) $html .= "<a href=\"/explorer/games/".$this->db_game['url_identifier']."/events/".$event->db_event['event_index']."\" style=\"margin-right: 30px;\">&larr; Previous Event</a>";
		$html .= "<a href=\"/explorer/games/".$this->db_game['url_identifier']."/events/".($event->db_event['event_index']+2)."\">Next Event &rarr;</a>";
		return $html;
	}
	
	public function sync() {
		$q = "SELECT * FROM game_blocks WHERE locally_saved=1 AND game_id='".$this->db_game['game_id']."' ORDER BY game_block_id DESC LIMIT 1;";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$last_game_block = $r->fetch();
			$load_block_height = $last_game_block['block_id']+1;
		}
		else {
			$last_game_block = false;
			$load_block_height = $this->db_game['game_starting_block'];
		}
		
		echo "Looping block #".$load_block_height." to ".$this->blockchain->last_block_id()."\n";
		for ($block_height=$load_block_height; $block_height<=$this->blockchain->last_block_id(); $block_height++) {
			$this->add_block($block_height);
		}
		
		$this->update_option_votes();
	}
	
	public function add_block($block_height) {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block_height."' AND locally_saved=1;";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_block = $r->fetch();
			
			$round_id = $this->block_to_round($block_height);
			
			if ($this->db_game['game_status'] == "published" && $this->db_game['game_starting_block'] == $block_height) $this->start_game();
			
			$q = "SELECT * FROM game_blocks WHERE internal_block_id='".$db_block['internal_block_id']."' AND game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() > 0) {
				$game_block = $r->fetch();
			}
			else {
				$q = "INSERT INTO game_blocks SET internal_block_id='".$db_block['internal_block_id']."', game_id='".$this->db_game['game_id']."', block_id='".$block_height."', locally_saved=0, num_transactions=0;";
				$r = $this->blockchain->app->run_query($q);
				$game_block_id = $this->blockchain->app->last_insert_id();
				
				$q = "SELECT * FROM game_blocks WHERE game_block_id='".$game_block_id."';";
				$game_block = $this->blockchain->app->run_query($q)->fetch();
			}
			
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
			
			$buyin_q = "SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.create_block_id='".$block_height."' AND io.address_id='".$escrow_address['address_id']."' GROUP BY t.transaction_id;";
			$buyin_r = $this->blockchain->app->run_query($buyin_q);
			
			while ($buyin_tx = $buyin_r->fetch()) {
				$qq = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.create_transaction_id='".$buyin_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				if ($rr->rowCount() == 0) {
					$this->process_buyin_transaction($buyin_tx);
				}
			}
			
			$q = "SELECT * FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=".$this->db_game['game_id']." AND t.block_id='".$block_height."' AND t.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
			$r = $this->blockchain->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				echo $r->rowCount()." colored coin ios spent in block #".$block_height."\n";
				
				while ($db_transaction = $r->fetch()) {
					$round_spent = $this->block_to_round($block_height);
					$input_sum = 0;
					$input_colored_sum = 0;
					$crd_sum = 0;
					$cbd_sum = 0;
					
					$qq = "SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id='".$db_transaction['transaction_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					
					while ($input_io = $rr->fetch()) {
						$round_created = $this->block_to_round($input_io['create_block_id']);
						
						$input_sum += $input_io['amount'];
						$input_colored_sum += $input_io['colored_amount'];
						$colored_coin_blocks = $input_io['colored_amount']*($block_height - $input_io['create_block_id']);
						$colored_coin_rounds = $input_io['colored_amount']*($round_id - $input_io['create_round_id']);
						$cbd_sum += $colored_coin_blocks;
						$crd_sum += $colored_coin_rounds;
						
						$qqq = "UPDATE transaction_game_ios SET spend_round_id='".$round_id."', coin_blocks_created='".$colored_coin_blocks."', coin_rounds_created='".$colored_coin_rounds."' WHERE game_io_id='".$input_io['game_io_id']."';";
						$rrr = $this->blockchain->app->run_query($qqq);
					}
					
					$qq = "SELECT * FROM transaction_ios WHERE create_transaction_id='".$db_transaction['transaction_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					
					$output_sum = 0;
					while ($output_io = $rr->fetch()) {
						$output_sum += (int) $output_io['amount'];
					}
					
					$qq = "SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$db_transaction['transaction_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					
					while ($output_io = $rr->fetch()) {
						$colored_amount = floor($input_colored_sum*$output_io['amount']/$output_sum);
						$cbd = floor($cbd_sum*$output_io['amount']/$output_sum);
						$crd = floor($crd_sum*$output_io['amount']/$output_sum);
						
						$qqq = "INSERT INTO transaction_game_ios SET game_id='".$this->db_game['game_id']."', io_id='".$output_io['io_id']."', is_coinbase=0, colored_amount='".$colored_amount."', coin_blocks_destroyed='".$cbd."', coin_rounds_destroyed='".$crd."', create_round_id='".$round_id."'";
						
						if ($output_io['option_index'] != "") {
							$option_id = $this->option_index_to_option_id_in_block($output_io['option_index'], $block_height);
							if ($option_id) {
								$db_event = $this->blockchain->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id='".$option_id."';")->fetch();
								$event = new Event($this, $db_event, false);
								$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_height);
								$qqq .= ", option_id='".$option_id."', event_id='".$db_event['event_id']."', effectiveness_factor='".$effectiveness_factor."'";
								
								if ($this->db_game['payout_weight'] == "coin_block") $votes = $cbd;
								else if ($this->db_game['payout_weight'] == "coin_round") $votes = $crd;
								else $votes = $colored_amount;
								$qqq .= ", votes='".$votes."'";
							}
						}
						
						$qqq .= ";";
						$rrr = $this->blockchain->app->run_query($qqq);
					}
				}
			}
			
			$q = "UPDATE game_blocks SET locally_saved=1 WHERE game_block_id='".$game_block['game_block_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			if ($block_height%$this->db_game['round_length'] == 0) {
				echo "Check outcomes for ".count($this->events_by_block($block_height))." events.\n";
				$events = $this->events_by_block($block_height);
				for ($i=0; $i<count($events); $i++) {
					$events[$i]->set_outcome_from_db($this->block_to_round($block_height), $block_height, true);
				}
			}
			
			$this->ensure_events_until_block($this->blockchain->last_block_id()+1+$this->db_game['round_length']);
		}
	}
	
	public function add_round_from_rpc($round_id) {
		$block_id = ($round_id-1)*$this->db_game['round_length']+1;
		$events = $this->events_by_block($block_id);
		
		for ($i=0; $i<count($events); $i++) {
			$rankings = $events[$i]->round_voting_stats_all($round_id);
			$sum_votes = $rankings[0];
			$max_winning_votes = $rankings[1];
			$option_id_to_rank = $rankings[3];
			$rankings = $rankings[2];
			
			$derived_winning_option_id = FALSE;
			$derived_winning_votes = 0;
			for ($rank=0; $rank<$events[$i]->db_event['num_voting_options']; $rank++) {
				if ($rankings[$rank]['votes'] > $max_winning_votes) {}
				else if (!$derived_winning_option_id && $rankings[$rank]['votes'] > 0) {
					$derived_winning_option_id = $rankings[$rank]['option_id'];
					$derived_winning_votes = $rankings[$rank]['votes'];
					$rank = $events[$i]->db_event['num_voting_options'];
				}
			}
			
			$winning_option_id = false;
			$q = "SELECT * FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id WHERE t.votebase_event_id='".$events[$i]->db_event['event_id']."' AND t.block_id='".$round_id*$this->db_game['round_length']."' AND t.transaction_desc='votebase' AND i.out_index=1;";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() == 1) {
				$votebase_transaction = $r->fetch();
				$winning_option_id = $votebase_transaction['option_id'];
			}
			
			$q = "SELECT * FROM event_outcomes WHERE event_id='".$events[$i]->db_event['event_id']."' AND round_id='".$round_id."';";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() > 0) {
				$existing_round = $r->fetch();
				$update_insert = "update";
			}
			else $update_insert = "insert";
			
			if ($update_insert == "update") $q = "UPDATE event_outcomes SET ";
			else $q = "INSERT INTO event_outcomes SET event_id='".$events[$i]->db_event['event_id']."', round_id='".$round_id."', ";
			$q .= "payout_block_id='".$events[$i]->db_event['event_final_block']."'";
			
			if ($derived_winning_option_id) $q .= ", derived_winning_option_id='".$derived_winning_option_id."', derived_winning_votes='".$derived_winning_votes."'";
			
			if ($winning_option_id) $q .= ", winning_option_id='".$winning_option_id."'";
			$option_votes = $events[$i]->option_votes_in_round($winning_option_id, $round_id);
			$q .= ", winning_votes='".$option_votes['sum']."'";
			
			$q .= ", sum_votes='".$sum_votes."', time_created='".time()."'";
			if ($update_insert == "update") $q .= " WHERE outcome_id='".$existing_round['outcome_id']."'";
			$q .= ";";
			$r = $this->blockchain->app->run_query($q);
			if ($update_insert == "insert") $outcome_id = $this->blockchain->app->last_insert_id();
			else $outcome_id = $existing_round['outcome_id'];
			
			$this->blockchain->app->run_query("DELETE FROM event_outcome_options WHERE round_id='".$round_id."' AND event_id='".$events[$i]->db_event['event_id']."';");
			for ($j=0; $j<count($rankings); $j++) {
				$qq = "INSERT INTO event_outcome_options SET outcome_id='".$outcome_id."', round_id='".$round_id."', event_id='".$events[$i]->db_event['event_id']."', option_id='".$rankings[$j]['option_id']."', rank='".($j+1)."', coin_score='".$rankings[$j]['coin_score']."', coin_block_score='".$rankings[$j]['coin_block_score']."', coin_round_score='".$rankings[$j]['coin_round_score']."', votes='".$rankings[$j]['votes']."';";
				$rr = $this->blockchain->app->run_query($qq);
			}
		}
	}
	
	public function render_transaction($transaction, $selected_address_id) {
		$html = "";
		$html .= '<div class="row bordered_row"><div class="col-md-12">';
		
		if (!empty($transaction['block_id'])) {
			if ($transaction['position_in_block'] == "") $html .= "Confirmed";
			else $html .= "#".(int)$transaction['position_in_block'];
			$html .= " in block <a href=\"/explorer/games/".$this->db_game['url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>, ";
		}
		$html .= (int)$transaction['num_inputs']." inputs, ".(int)$transaction['num_outputs']." outputs";
		
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase" && $transaction['transaction_desc'] != "votebase") {
			$fee_disp = $this->blockchain->app->format_bignum($transaction_fee/pow(10,8));
			$html .= ", ".$fee_disp." ".$this->blockchain->db_blockchain['coin_name'];
			$html .= " tx fee";
		}
		if (empty($transaction['block_id'])) $html .= ", not yet confirmed";
		$html .= '. <br/><a href="/explorer/games/'.$this->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a>';
		
		$html .= '</div><div class="col-md-6">';
		
		if ($transaction['transaction_desc'] == "giveaway") {
			$q = "SELECT * FROM game_giveaways WHERE transaction_id='".$transaction['transaction_id']."';";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() > 0) {
				$giveaway = $r->fetch();
				$html .= $this->blockchain->app->format_bignum($giveaway['amount']/pow(10,8))." coins were given to a player for joining.";
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
			$qq = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.spend_transaction_id='".$transaction['transaction_id']."' ORDER BY io.amount DESC;";
			$rr = $this->blockchain->app->run_query($qq);
			$input_sum = 0;
			while ($input = $rr->fetch()) {
				$amount_disp = $this->blockchain->app->format_bignum($input['colored_amount']/pow(10,8));
				$html .= '<a class="display_address" style="';
				if ($input['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
				$html .= '" href="/explorer/games/'.$this->db_game['url_identifier'].'/addresses/'.$input['address'].'">'.$input['address'].'</a>';
				$html .= "<br/>\n";
				$html .= $amount_disp." ";
				if ($amount_disp == '1') $html .= $this->db_game['coin_name'];
				else $html .= $this->db_game['coin_name_plural'];
				
				$html .= "<br/>\n";
				$input_sum += $input['amount'];
			}
		}
		$html .= '</div><div class="col-md-6">';
		$qq = "SELECT gio.*, io.*, a.*, op.name AS option_name FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id LEFT JOIN options op ON gio.option_id=op.option_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.create_transaction_id='".$transaction['transaction_id']."' AND gio.is_coinbase=0 ORDER BY io.out_index ASC;";
		$rr = $this->blockchain->app->run_query($qq);
		$output_sum = 0;
		while ($output = $rr->fetch()) {
			$html .= '<a class="display_address" style="';
			if ($output['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= '" href="/explorer/games/'.$this->db_game['url_identifier'].'/addresses/'.$output['address'].'">'.$output['address']."</a><br/>\n";
			
			$amount_disp = $this->blockchain->app->format_bignum($output['colored_amount']/pow(10,8));
			$html .= $amount_disp." ";
			if ($amount_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			
			if ($output['option_id'] > 0) {
				$html .= ", ";
				if ($transaction['block_id'] > 0) $expected_votes = $output['votes'];
				else {
					$ref_block_id = $this->blockchain->last_block_id()+1;
					$temp_event = new Event($this, false, $output['event_id']);
					if ($this->db_game['payout_weight'] == "coin") $expected_votes = $output['colored_amount'];
					else if ($this->db_game['payout_weight'] == "coin_block") $expected_votes = $output['ref_coin_blocks']+($ref_block_id-$output['ref_block_id'])*$output['colored_amount'];
					else $expected_votes = $output['ref_coin_rounds']+($this->block_to_round($ref_block_id)-$output['ref_round_id'])*$output['colored_amount'];
					
					$effectiveness_factor = $temp_event->block_id_to_effectiveness_factor($ref_block_id);
					$expected_votes = floor($expected_votes*$effectiveness_factor);
				}
				$html .= $this->blockchain->app->format_bignum($expected_votes/pow(10,8));
				$html .= " votes for ".$output['option_name'];
			}
			if ($output['payout_game_io_id'] > 0) {
				$payout_io = $this->blockchain->app->run_query("SELECT * FROM transaction_game_ios WHERE game_io_id='".$output['payout_game_io_id']."';")->fetch();
				$html .= '&nbsp;&nbsp;<font class="greentext">+'.$this->blockchain->app->format_bignum($payout_io['colored_amount']/pow(10,8)).'</font>';
			}
			$html .= "<br/>\n";
			$output_sum += $output['colored_amount'];
		}
		$html .= '</div></div>'."\n";
		
		return $html;
	}
	
	public function explorer_block_list($from_block_id, $to_block_id) {
		return $this->blockchain->explorer_block_list($from_block_id, $to_block_id, $this);
	}
	
	public function block_stats($block) {
		$q = "SELECT COUNT(*), SUM(gio.colored_amount) FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND t.block_id='".$block['block_id']."' GROUP BY t.transaction_id;";
		$r = $this->blockchain->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		return array($r[0], $r[1]);
	}
	
	public function address_balance_at_block($db_address, $block_id) {
		if ($block_id) {
			$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.address_id='".$db_address['address_id']."' AND io.create_block_id <= ".$block_id." AND ((io.spend_block_id IS NULL AND io.spend_status='unspent') OR io.spend_block_id>".$block_id.");";
		}
		else {
			$q = "SELECT SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.address_id='".$db_address['address_id']."' AND io.spend_block_id IS NULL AND io.spend_status='unspent';";
		}
		$r = $this->blockchain->app->run_query($q);
		$balance = $r->fetch();
		return (int)$balance['SUM(gio.colored_amount)'];
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
}
?>
