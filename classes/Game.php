<?php
class Game {
	public $db_game;
	public $app;
	public $current_events;
	
	public function __construct(&$app, $game_id) {
		$this->app = $app;
		$this->game_id = $game_id;
		$this->update_db_game();
		$this->load_current_events();
	}
	
	public function update_db_game() {
		$q = "SELECT * FROM games WHERE game_id='".$this->game_id."';";
		$r = $this->app->run_query($q);
		$this->db_game = $r->fetch() or die("Error, could not load game #".$this->game_id);
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
	
	public function create_transaction($option_ids, $amounts, $from_user_id, $to_user_id, $block_id, $type, $io_ids, $address_ids, $remainder_address_id, $transaction_fee) {
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
		
		if ($amount_ok && (count($option_ids) == count($amounts) || ($option_ids === false && count($amounts) == count($address_ids)))) {
			// For real games, don't insert a tx record, it will come in via walletnotify
			if ($this->db_game['game_type'] != "real") {
				$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', fee_amount='".$transaction_fee."', has_all_inputs=1, has_all_outputs=1";
				if ($this->db_game['game_type'] == "simulation") $q .= ", tx_hash='".$this->app->random_string(64)."'";
				$q .= ", transaction_desc='".$type."', amount=".$amount;
				if ($from_user_id) $q .= ", from_user_id='".$from_user_id."'";
				if ($to_user_id) $q .= ", to_user_id='".$to_user_id."'";
				if ($type == "bet") {
					$qq = "SELECT bet_round_id FROM addresses WHERE address_id='".$address_ids[0]."';";
					$rr = $this->app->run_query($qq);
					$bet_round_id = $rr->fetch(PDO::FETCH_NUM);
					$bet_round_id = $bet_round_id[0];
					$q .= "bet_round_id='".$bet_round_id."', ";
				}
				if ($block_id !== false) $q .= ", block_id='".$block_id."', round_id='".$this->block_to_round($block_id)."'";
				$q .= ", time_created='".time()."';";
				$r = $this->app->run_query($q);
				$transaction_id = $this->app->last_insert_id();
			}
			
			$input_sum = 0;
			$overshoot_amount = 0;
			$overshoot_return_addr_id = $remainder_address_id;
			
			if ($type == "giveaway" || $type == "votebase" || $type == "coinbase") {}
			else {
				$q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status='unspent' AND io.user_id='".$from_user_id."' AND io.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($this->last_block_id()-$this->db_game['maturity'])." OR io.instantly_mature=1)";
				if ($io_ids) $q .= " AND io.io_id IN (".implode(",", $io_ids).")";
				$q .= " ORDER BY io.amount ASC;";
				$r = $this->app->run_query($q);
				
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				$ref_block_id = $this->last_block_id()+1;
				$ref_round_id = $this->block_to_round($ref_block_id);
				$ref_cbd = 0;
				$ref_crd = 0;
				
				while ($transaction_input = $r->fetch()) {
					if ($input_sum < $amount) {
						if ($this->db_game['game_type'] != "real") {
							$qq = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_transaction_id='".$transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$transaction_id."', ','))";
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
				
				$qq = "UPDATE transactions SET ref_block_id='".$ref_block_id."', ref_coin_blocks_destroyed='".$ref_cbd."', ref_round_id='".$ref_round_id."', ref_coin_rounds_destroyed='".$ref_crd."' WHERE transaction_id='".$transaction_id."';";
				$rr = $this->app->run_query($qq);
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
						$q = "SELECT * FROM addresses a LEFT JOIN options o ON a.option_id=o.option_id WHERE a.address_id='".$address_id."';";
						$r = $this->app->run_query($q);
						$address = $r->fetch();
						
						if ($this->db_game['game_type'] != "real") {
							$q = "INSERT INTO transaction_ios SET spend_status='";
							if ($instantly_mature == 1) $q .= "unspent";
							else $q .= "unconfirmed";
							$q .= "', out_index='".$out_index."', ";
							if (!empty($address['user_id'])) $q .= "user_id='".$address['user_id']."', ";
							$q .= "address_id='".$address_id."', ";
							if ($address['option_id'] > 0) {
								$q .= "option_id='".$address['option_id']."', event_id='".$address['event_id']."', ";
								if ($block_id !== false) {
									$event = new Event($this, false, $address['event_id']);
									$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_id);
								}
							}
							else $effectiveness_factor = 0;
							if ($block_id !== false) {
								if ($input_sum == 0) $output_cbd = 0;
								else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								
								if ($input_sum == 0) $output_crd = 0;
								else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								
								$q .= "coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', ";
								
								if ($this->db_game['payout_weight'] == "coin") $votes = floor($amounts[$out_index]/$input_sum);
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
					$coin_rpc = new jsonRPCClient('http://'.$this->db_game['rpc_username'].':'.$this->db_game['rpc_password'].'@127.0.0.1:'.$this->db_game['rpc_port'].'/');
					try {
						$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
						$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction);
						$decoded_transaction = $coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
						$tx_hash = $decoded_transaction['txid'];
						$verified_tx_hash = $coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
						
						$this->walletnotify($coin_rpc, $tx_hash, FALSE);
						$this->walletnotify($coin_rpc, $verified_tx_hash, FALSE);
						$this->update_option_votes();
						
						$db_transaction = $this->app->run_query("SELECT * FROM transactions WHERE tx_hash=".$this->app->quote_escape($tx_hash).";")->fetch();
						
						return $db_transaction['transaction_id'];
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
	
	public function update_option_votes() {
		$last_block_id = $this->last_block_id();
		$round_id = $this->block_to_round($last_block_id+1);
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$effectiveness_factor = $this->current_events[$i]->block_id_to_effectiveness_factor($last_block_id+1);
			
			$q = "UPDATE options SET coin_score=0, unconfirmed_coin_score=0, coin_block_score=0, unconfirmed_coin_block_score=0, coin_round_score=0, unconfirmed_coin_round_score=0, votes=0, unconfirmed_votes=0 WHERE event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$r = $this->app->run_query($q);
			
			$q = "UPDATE options op INNER JOIN (
				SELECT option_id, SUM(amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd, SUM(coin_rounds_destroyed) sum_crd, SUM(votes) sum_votes FROM transaction_ios 
				WHERE game_id='".$this->db_game['game_id']."' AND create_round_id=".$round_id." AND amount > 0
				GROUP BY option_id
			) i ON op.option_id=i.option_id SET op.coin_score=i.sum_amount, op.coin_block_score=i.sum_cbd, op.coin_round_score=i.sum_crd, op.votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$r = $this->app->run_query($q);
			
			if ($this->db_game['payout_weight'] == "coin") {
				$q = "UPDATE options op INNER JOIN (
					SELECT option_id, SUM(amount) sum_amount, SUM(amount)*".$effectiveness_factor." sum_votes FROM transaction_ios 
					WHERE game_id='".$this->db_game['game_id']."' AND create_block_id IS NULL AND amount > 0
					GROUP BY option_id
				) i ON op.option_id=i.option_id SET op.unconfirmed_coin_score=i.sum_amount, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->app->run_query($q);
			}
			else if ($this->db_game['payout_weight'] == "coin_block") {
				$q = "UPDATE options op INNER JOIN (
					SELECT io.option_id, SUM((t.ref_coin_blocks_destroyed+(".($last_block_id+1)."-t.ref_block_id)*t.amount)*io.amount/t.amount) sum_cbd, SUM((t.ref_coin_blocks_destroyed+(".($last_block_id+1)."-t.ref_block_id)*t.amount)*io.amount/t.amount)*".$effectiveness_factor." sum_votes FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id
					WHERE t.game_id='".$this->db_game['game_id']."' AND io.create_block_id IS NULL AND io.amount > 0 AND t.block_id IS NULL
					GROUP BY io.option_id
				) i ON op.option_id=i.option_id SET op.unconfirmed_coin_block_score=i.sum_cbd, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->app->run_query($q);
			}
			else {
				$q = "UPDATE options op INNER JOIN (
					SELECT io.option_id, SUM((t.ref_coin_rounds_destroyed+(".$round_id."-t.ref_round_id)*t.amount)*io.amount/t.amount) sum_crd, SUM((t.ref_coin_rounds_destroyed+(".$round_id."-t.ref_round_id)*t.amount)*io.amount/t.amount)*".$effectiveness_factor." sum_votes FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id
					WHERE t.game_id='".$this->db_game['game_id']."' AND io.create_block_id IS NULL AND io.amount > 0 AND t.block_id IS NULL
					GROUP BY io.option_id
				) i ON op.option_id=i.option_id SET op.unconfirmed_coin_round_score=i.sum_crd, op.unconfirmed_votes=i.sum_votes WHERE op.event_id='".$this->current_events[$i]->db_event['event_id']."';";
				$r = $this->app->run_query($q);
			}
		}
	}
	
	public function new_block() {
		// This public function only runs for games with game_type='simulation'
		$log_text = "";
		$last_block_id = $this->last_block_id();
		
		$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_id='".($last_block_id+1)."', block_hash='".$this->app->random_string(64)."', time_created='".time()."', locally_saved=1;";
		$r = $this->app->run_query($q);
		$last_block_id = $this->app->last_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
		$r = $this->app->run_query($q);
		$block = $r->fetch();
		$last_block_id = $block['block_id'];
		$mining_block_id = $last_block_id+1;
		
		$justmined_round = $this->block_to_round($last_block_id);
		
		$log_text .= "Created block $last_block_id<br/>\n";
		
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
				
				$qq = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN options op ON a.option_id=op.option_id WHERE io.create_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->app->run_query($qq);
				
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
					$rrr = $this->app->run_query($qqq);
				}
				
				$qq = "UPDATE transactions t JOIN transaction_ios o ON t.transaction_id=o.create_transaction_id JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id SET t.block_id='".$last_block_id."', t.round_id='".$justmined_round."', o.spend_status='unspent', o.create_block_id='".$last_block_id."', o.create_round_id='".$justmined_round."', i.spend_status='spent', i.spend_block_id='".$last_block_id."', i.spend_round_id='".$justmined_round."' WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->app->run_query($qq);
				
				$fee_sum += $fee_amount;
			}
		}
		
		$mined_address = $this->create_or_fetch_address("Ex".$this->app->random_string(32), true, false, false, true);
		$mined_transaction_id = $this->create_transaction(array(false), array($this->app->pow_reward_in_round($this->db_game, $justmined_round)+$fee_sum), false, false, $last_block_id, "coinbase", false, array($mined_address['address_id']), false, 0);
		
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
				$last_block_id = $this->last_block_id();
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
		$r = $this->app->run_query($q);
		$this->db_game['game_status'] = "completed";
		
		if ($this->db_game['game_winning_rule'] == "event_points") {
			$entity_score_info = $this->entity_score_info();
			
			if (!empty($entity_score_info['winning_entity_id'])) {
				$coins_in_existence = $this->coins_in_existence(false);
				$payout_amount = floor(((float)$coins_in_existence)*$this->db_game['game_winning_inflation']);
				if ($payout_amount > 0) {
					$game_votes_q = "SELECT SUM(io.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."';";
					$game_votes_r = $this->app->run_query($game_votes_q);
					$game_votes_total = $game_votes_r->fetch()['SUM(io.votes)'];
					
					$winner_votes_q = "SELECT SUM(io.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
					$winner_votes_r = $this->app->run_query($winner_votes_q);
					$winner_votes_total = $winner_votes_r->fetch()['SUM(io.votes)'];
					
					echo "payout ".$this->app->format_bignum($payout_amount/pow(10,8))." coins to ".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['entity_name']." (".$this->app->format_bignum($winner_votes_total/pow(10,8))." total votes)<br/>\n";
					
					$payout_io_q = "SELECT * FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
					$amounts = array();
					$address_ids = array();
					$payout_io_r = $this->app->run_query($payout_io_q);
					
					while ($payout_io = $payout_io_r->fetch()) {
						$payout_frac = round(pow(10,8)*$payout_io['votes']/$winner_votes_total)/pow(10,8);
						$payout_io_amount = floor($payout_frac*$payout_amount);
						
						if ($payout_io_amount > 0) {
							$vout = count($amounts);
							$amounts[$vout] = $payout_io_amount;
							$address_ids[$vout] = $payout_io['address_id'];
							echo "pay ".$this->app->format_bignum($payout_io_amount/pow(10,8))." to ".$payout_io['address']."<br/>\n";
						}
					}
					$last_block_id = $this->last_block_id();
					$transaction_id = $this->create_transaction(false, $amounts, false, false, false, "votebase", false, $address_ids, false, 0);
					$q = "UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id SET t.block_id='".$last_block_id."', t.round_id='".$this->block_to_round($last_block_id)."', io.spend_status='unspent', io.create_block_id='".$last_block_id."', io.create_round_id='".$this->block_to_round($last_block_id)."' WHERE t.transaction_id='".$transaction_id."';";
					$r = $this->app->run_query($q);
					$this->refresh_coins_in_existence();

					$q = "UPDATE games SET game_winning_transaction_id='".$transaction_id."', winning_entity_id='".$entity_score_info['winning_entity_id']."' WHERE game_id='".$this->db_game['game_id']."';";
					$r = $this->app->run_query($q);
				}
			}
		}
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
			$q .= " AND (s.voting_strategy='by_rank' OR s.voting_strategy='by_entity' OR s.voting_strategy='api' OR s.voting_strategy='by_plan')";
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
								
								$qq = "SELECT * FROM options WHERE option_id='".$recommendation->option_id."' AND game_id='".$this->db_game['game_id']."';";
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
								
								$transaction_id = $this->create_transaction($vote_option_ids, $vote_amounts, $strategy_user->db_user['user_id'], $strategy_user->db_user['user_id'], false, 'transaction', $input_io_ids, false, false, false);
								
								if ($transaction_id) $log_text .= "Added transaction $transaction_id<br/>\n";
								else $log_text .= "Failed to add transaction.<br/>\n";
							}
						}
					}
					else {
						$pct_free = 100*$mature_balance/$user_coin_value;
						
						if ($pct_free >= $db_user['aggregate_threshold']) {
							$option_pct_sum = 0;
							$skipped_pct_points = 0;
							$skipped_options = "";
							$num_options_skipped = 0;
							$strategy_entity_points = false;

							$qq = "SELECT * FROM user_strategy_entities WHERE strategy_id='".$db_user['strategy_id']."';";
							$rr = $this->app->run_query($qq);
							while ($strategy_entity = $rr->fetch()) {
								$strategy_entity_points[$strategy_entity['entity_id']] = intval($strategy_entity['pct_points']);
							}
							
							$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$this->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
							$rr = $this->app->run_query($qq);
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
										$rr = $this->app->run_query($qq);
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
			$this->update_option_votes();
		}
		return $log_text;
	}
	
	public function delete_reset_game($delete_or_reset) {
		$q = "DELETE FROM transactions WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM blocks WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE eo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE eoo.* FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		$invite_user_ids = array();
		if ($delete_or_reset == "reset") {
			$q = "SELECT * FROM game_invitations WHERE game_id='".$this->db_game['game_id']."' AND used_user_id > 0;";
			$r = $this->app->run_query($q);
			while ($invitation = $r->fetch()) {
				$invite_user_ids[count($invite_user_ids)] = $invitation['used_user_id'];
			}
		}
		
		$q = "DELETE FROM game_invitations WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		
		if ($this->db_game['game_type'] == "simulation") {
			$q = "DELETE FROM addresses WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
		
		if ($delete_or_reset == "reset") {
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
				$invite_event = false;
				$this->app->try_apply_invite_key($invite_user_ids[$i], $invitation['invitation_key'], $invite_event);
			}
			
			$q = "SELECT * FROM game_giveaways WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
			while ($giveaway = $r->fetch()) {
				$replacement_giveaway = $this->new_game_giveaway($giveaway['user_id'], $giveaway['type'], $giveaway['amount']);
				$this->app->run_query("DELETE FROM game_giveaways WHERE giveaway_id='".$giveaway['giveaway_id']."';");
			}
		}
		else {
			$q = "DELETE g.*, ug.* FROM games g, user_games ug WHERE g.game_id=".$this->db_game['game_id']." AND ug.game_id=g.game_id;";
			$r = $this->app->run_query($q);
			
			$q = "DELETE s.*, sra.* FROM user_strategies s LEFT JOIN strategy_round_allocations sra ON s.strategy_id=sra.strategy_id WHERE s.game_id='".$this->db_game['game_id']."';";
			$r = $this->app->run_query($q);
		}
		$this->update_option_votes();
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
			$qq = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id LEFT JOIN options gvo ON a.option_id=gvo.option_id WHERE i.spend_transaction_id='".$transaction['transaction_id']."' ORDER BY i.amount DESC;";
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
		$qq = "SELECT i.*, gvo.*, a.*, p.amount AS payout_amount FROM transaction_ios i LEFT JOIN transaction_ios p ON i.payout_io_id=p.io_id, addresses a LEFT JOIN options gvo ON a.option_id=gvo.option_id WHERE i.create_transaction_id='".$transaction['transaction_id']."' AND i.address_id=a.address_id ORDER BY i.out_index ASC;";
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
	
	public function rounds_complete_html($max_round_id, $limit) {
		$html = "";
		
		$show_initial = false;
		$last_block_id = $this->last_block_id();
		$current_round = $this->block_to_round($last_block_id+1);
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$current_score_q = "SELECT SUM(votes) FROM options WHERE event_id='".$this->current_events[$i]->db_event['event_id']."';";
			$current_score_r = $this->app->run_query($current_score_q);
			$current_score = $current_score_r->fetch(PDO::FETCH_NUM);
			$current_score = $current_score[0];
			if ($current_score > 0) {} else $current_score = 0;
			
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/'.$this->db_game['url_identifier'].'/events/'.$this->current_events[$i]->db_event['event_id'].'">'.$this->current_events[$i]->db_event['event_name'].'</a></div>';
			$html .= '<div class="col-sm-5">Not yet decided';
			$html .= '</div>';
			$html .= '<div class="col-sm-3">'.$this->app->format_bignum($current_score/pow(10,8)).' votes cast</div>';
			$html .= '</div>'."\n";
			
			if ($current_round == 1) $show_initial = true;
		}
		
		$q = "SELECT eo.*, e.*, real_winner.name AS real_winner_name, derived_winner.name AS derived_winner_name FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id LEFT JOIN options real_winner ON eo.winning_option_id=real_winner.option_id LEFT JOIN options derived_winner ON eo.derived_winning_option_id=derived_winner.option_id WHERE e.game_id='".$this->db_game['game_id']."' AND eo.round_id <= ".$max_round_id." GROUP BY e.event_id ORDER BY eo.event_id DESC, eo.round_id DESC LIMIT ".$limit.";";
		$r = $this->app->run_query($q);
		
		$last_round_shown = 0;
		while ($event_outcome = $r->fetch()) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/'.$this->db_game['url_identifier'].'/events/'.$event_outcome['event_id'].'">'.$event_outcome['event_name'].'</a></div>';
			$html .= '<div class="col-sm-5">';
			if ($event_outcome['winning_option_id'] > 0) {
				$html .= $event_outcome['real_winner_name']." wins with ".$this->app->format_bignum($event_outcome['winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				if ($event_outcome['derived_winning_option_id'] != $event_outcome['winning_option_id']) {
					$html .= ". Should have been ".$event_outcome['derived_winner_name']." with ".$this->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['derived_winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				}
			}
			else {
				if ($event_outcome['derived_winning_option_id'] > 0) {
					$html .= $event_outcome['derived_winner_name']." wins with ".$this->app->format_bignum($event_outcome['derived_winning_votes']/pow(10,8))." votes (".round(100*$event_outcome['derived_winning_votes']/$event_outcome['sum_votes'], 2)."%)";
				}
				else $html .= "No winner";
			}
			$html .= "</div>";
			$html .= '<div class="col-sm-3">'.$this->app->format_bignum($event_outcome['sum_votes']/pow(10,8)).' votes cast</div>';
			$html .= "</div>\n";
			$last_round_shown = $event_outcome['round_id'];
			if ($event_outcome['round_id'] == 1) $show_initial = true;
		}
		
		if ($show_initial) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/'.$this->db_game['url_identifier'].'/events/0">'.$this->db_game['name'].'</a></div>';
			$html .= '<div class="col-sm-8">Initial Distribution</div>';
			$html .= '</div>';
		}
		
		$returnvals[0] = $last_round_shown;
		$returnvals[1] = $html;
		
		return $returnvals;
	}

	public function addr_text_to_option_id($addr_text) {
		$option_id = false;
		for ($len=1; $len<=$this->db_game['max_voting_chars']; $len++) {
			$rel_text = strtolower(substr($addr_text, 1, $len));
			$q = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND op.voting_character='".$rel_text."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$option = $r->fetch();
				return $option['option_id'];
			}
		}
		return false;
	}
	
	public function add_round_from_rpc($round_id) {
		/*$rankings = $this->round_voting_stats_all($round_id);
		
		$sum_votes = $rankings[0];
		$max_winning_votes = $rankings[1];
		$option_id_to_rank = $rankings[3];
		$rankings = $rankings[2];
		
		$derived_winning_option_id = FALSE;
		$derived_winning_votes = 0;
		for ($rank=0; $rank<$this->db_event['num_voting_options']; $rank++) {
			if ($rankings[$rank]['votes'] > $max_winning_votes) {}
			else if (!$derived_winning_option_id && $rankings[$rank]['votes'] > 0) {
				$derived_winning_option_id = $rankings[$rank]['option_id'];
				$derived_winning_votes = $rankings[$rank]['votes'];
				$rank = $this->db_event['num_voting_options'];
			}
		}
		
		$winning_option_id = false;
		$q = "SELECT * FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id JOIN addresses a ON a.address_id=i.address_id WHERE t.event_id='".$this->db_event['event_id']."' AND t.block_id='".$round_id*$this->db_event['round_length']."' AND t.transaction_desc='votebase' AND i.out_index=1;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) {
			$votebase_transaction = $r->fetch();
			$winning_option_id = $votebase_transaction['option_id'];
		}
		
		$q = "SELECT * FROM event_outcomes WHERE event_id='".$this->db_event['event_id']."' AND round_id='".$round_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$existing_round = $r->fetch();
			$update_insert = "update";
		}
		else $update_insert = "insert";
		
		if ($update_insert == "update") $q = "UPDATE event_outcomes SET ";
		else $q = "INSERT INTO event_outcomes SET event_id='".$this->db_event['event_id']."', round_id='".$round_id."', ";
		$q .= "payout_block_id='".($round_id*$this->db_event['round_length'])."'";
		
		if ($derived_winning_option_id) $q .= ", derived_winning_option_id='".$derived_winning_option_id."', derived_winning_votes='".$derived_winning_votes."'";
		
		if ($winning_option_id) $q .= ", winning_option_id='".$winning_option_id."'";
		$option_votes = $this->option_votes_in_round($winning_option_id, $round_id);
		$q .= ", winning_votes='".$option_votes['sum']."'";
		
		$q .= ", sum_votes='".$sum_votes."', time_created='".time()."'";
		if ($update_insert == "update") $q .= " WHERE outcome_id='".$existing_round['outcome_id']."'";
		$q .= ";";
		$r = $this->app->run_query($q);
		$outcome_id = $this->app->last_insert_id();
		
		$this->app->run_query("DELETE FROM event_outcome_options WHERE round_id='".$round_id."' AND event_id='".$this->db_event['event_id']."';");
		
		for ($i=0; $i<count($rankings); $i++) {
			$qq = "INSERT INTO event_outcome_options SET outcome_id='".$outcome_id."', round_id='".$round_id."', event_id='".$this->db_event['event_id']."', option_id='".$rankings[$i]['option_id']."', rank='".($i+1)."', coin_score='".$rankings[$i]['coin_score']."', coin_block_score='".$rankings[$i]['coin_block_score']."', coin_round_score='".$rankings[$i]['coin_round_score']."', votes='".$rankings[$i]['votes']."';";
			$rr = $this->app->run_query($qq);
		}
		*/
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
			$transaction_id = $this->create_transaction($option_ids, $amounts, false, false, 0, 'giveaway', false, $addr_ids, false, 0);
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
		$q = "INSERT INTO game_invitations SET game_id='".$this->db_game['game_id']."'";
		if ($inviter_id > 0) $q .= ", inviter_id=".$inviter_id;
		$q .= ", invitation_key='".strtolower($this->app->random_string(32))."', time_created='".time()."'";
		if ($user_id) $q .= ", used_user_id='".$user_id."'";
		$q .= ";";
		$r = $this->app->run_query($q);
		$invitation_id = $this->app->last_insert_id();
		
		if ($this->db_game['giveaway_status'] == "invite_free") {
			$giveaway = $this->new_game_giveaway($user_id, 'initial_purchase', false);
			$q = "UPDATE game_invitations SET giveaway_id='".$giveaway['giveaway_id']."' WHERE invitation_id='".$invitation_id."';";
			$r = $this->app->run_query($q);
		}
		
		$q = "SELECT * FROM game_invitations WHERE invitation_id='".$invitation_id."';";
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
				$r = $this->app->run_query($q);
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
		$r = $this->app->run_query($q);
		$num_players = $r->fetch(PDO::FETCH_NUM);
		return intval($num_players[0]);
	}
	
	public function start_game() {
		$qq = "UPDATE games SET initial_coins='".$this->coins_in_existence(false)."', game_status='running', start_time='".time()."', start_datetime=NOW() WHERE game_id='".$this->db_game['game_id']."';";
		$rr = $this->app->run_query($qq);
		
		$qq = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND u.notification_email LIKE '%@%';";
		$rr = $this->app->run_query($qq);
		while ($player = $rr->fetch()) {
			$subject = $GLOBALS['coin_brand_name']." game \"".$this->db_game['name']."\" has started.";
			$message = $this->db_game['name']." has started. If haven't already entered your votes, please log in now and start playing.<br/>\n";
			$message .= $this->app->game_info_table($this->db_game);
			$email_id = $this->app->mail_async($player['notification_email'], $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
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
		$coins_in_existence = $this->coins_in_existence(false);
		if ($coins_in_existence > 0) $html .= $this->app->format_bignum(100*$account_value/$coins_in_existence)."%";
		else $html .= "0%";
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$this->db_game['invite_currency']."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$payout_currency = $r->fetch();
			$coins_in_existence = $this->coins_in_existence(false);
			if ($coins_in_existence > 0) $payout_currency_value = $this->pot_value()*$account_value/$coins_in_existence;
			else $payout_currency_value = 0;
			
			$innate_currency_value = 0;
			if ($this->db_game['currency_id'] > 0 && $this->db_game['invite_currency'] == $this->app->get_site_constant("reference_currency_id")) {
				$currency_price = $this->app->latest_currency_price($this->db_game['currency_id']);
				$innate_currency_value = ($account_value/pow(10,8))*$currency_price['price'];
			}
			$html .= "&nbsp;=&nbsp;<a href=\"/".$this->db_game['url_identifier']."/?action=show_escrow\">".$payout_currency['symbol'].$this->app->format_bignum($innate_currency_value+$payout_currency_value)."</a>";
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
			$subject .= ". Join now & get ".$this->app->format_bignum($this->db_game['giveaway_amount']/pow(10,8))." ".$this->db_game['coin_name_plural']." for free.";
		}
		
		$message = "<p>";
		if ($this->db_game['short_description'] != "") {
			$message .= "<p>".$this->db_game['short_description']."</p>";
		}
		else {
			if ($this->db_game['inflation'] == "exponential") {}
			else if ($this->db_game['inflation'] == "linear") $message .= $this->db_game['name']." is a cryptocurrency which generates ".$coins_per_hour." ".$this->db_game['coin_name_plural']." per hour. ";
			else $message .= $this->db_game['name']." is a cryptocurrency with ".($this->db_game['exponential_inflation_rate']*100)."% inflation every ".$this->app->format_seconds($seconds_per_round).". ";
			$message .= $miner_pct."% is given to miners for securing the network and the remaining ".(100-$miner_pct)."% is given to players for casting winning votes. ";
			if ($this->db_game['final_round'] > 0) {
				$game_total_seconds = $seconds_per_round*$this->db_game['final_round'];
				$message .= "Once this game starts, it will last for ".$this->app->format_seconds($game_total_seconds)." (".$this->db_game['final_round']." rounds). ";
				$message .= "At the end, all ".$invite_currency['short_name']."s that have been paid in will be divided up and given out to all players in proportion to players' final balances.";
			}
			$message .= "Team up with other players and cast your votes strategically to win coins and destroy your competitors. ";
		}
		$message .= "</p>";
		
		$table = str_replace('<div class="row"><div class="col-sm-5">', '<tr><td>', $this->app->game_info_table($this->db_game));
		$table = str_replace('</div><div class="col-sm-7">', '</td><td>', $table);
		$table = str_replace('</div></div>', '</td></tr>', $table);
		$message .= '<table>'.$table.'</table>';
		$message .= "<p>To start playing, accept your invitation by following <a href=\"".$GLOBALS['base_url']."/wallet/".$this->db_game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
		$message .= "<p>This message was sent to you by ".$GLOBALS['site_name']."</p>";
		
		$email_id = $this->app->mail_async($to_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
		
		$q = "UPDATE game_invitations SET sent_email_id='".$email_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
		$r = $this->app->run_query($q);
		
		return $email_id;
	}
	
	public function entity_score_info($user) {
		$return_obj = false;
		
		if ($user) {
			$qq = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."';";
			$rr = $this->app->run_query($qq);
			$user_entity_votes_total = $rr->fetch();
			$return_obj['user_entity_votes_total'] = $user_entity_votes_total['SUM(io.votes)'];

			$qq = "SELECT SUM(io.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."';";
			$rr = $this->app->run_query($qq);
			$return_obj['entity_votes_total'] = $rr->fetch()['SUM(io.votes)'];
		}
		
		$return_rows = false;
		$q = "SELECT * FROM events ev JOIN options o ON ev.event_id=o.event_id JOIN entities en ON o.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
		$r = $this->app->run_query($q);
		
		while ($entity = $r->fetch()) {
			$qq = "SELECT COUNT(*), SUM(en.".$this->db_game['game_winning_field'].") points FROM event_outcomes eo JOIN options op ON eo.winning_option_id=op.option_id JOIN events ev ON eo.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND op.entity_id='".$entity['entity_id']."';";
			$rr = $this->app->run_query($qq);
			$info = $rr->fetch();
			
			$return_rows[$entity['entity_id']]['points'] = (int) $info['points'];
			$return_rows[$entity['entity_id']]['entity_name'] = $entity['entity_name'];
			
			$entity_my_pct = false;
			if ($user) {
				$qq = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id WHERE io.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$rr = $this->app->run_query($qq);
				$user_entity_votes = $rr->fetch();
				
				$return_rows[$entity['entity_id']]['my_votes'] = $user_entity_votes['SUM(io.votes)'];
				if ($return_obj['user_entity_votes_total'] > 0) $my_pct = 100*$user_entity_votes['SUM(io.votes)']/$return_obj['user_entity_votes_total'];
				else $my_pct = 0;
				$return_rows[$entity['entity_id']]['my_pct'] = $my_pct;
				
				$entity_votes_q = "SELECT SUM(io.votes), COUNT(*) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN entities e ON o.entity_id=e.entity_id WHERE io.game_id='".$this->db_game['game_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$entity_votes_r = $this->app->run_query($entity_votes_q);
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
		if ($this->db_game['game_status'] == "editable") $html .= "The game creator hasn't yet published this game; it's parameters can still be changed.";
		else if ($this->db_game['game_status'] == "published") {
			if ($this->db_game['start_condition'] == "players_joined") {
				$num_players = $this->paid_players_in_game();
				$players_needed = ($this->db_game['start_condition_players']-$num_players);
				if ($players_needed > 0) {
					$html .= $num_players."/".$this->db_game['start_condition_players']." players have already joined, waiting for ".$players_needed." more players.";
				}
			}
			else $html .= "This game starts in ".$this->app->format_seconds(strtotime($this->db_game['start_datetime'])-time())." at ".$this->db_game['start_datetime'];
		}
		else if ($this->db_game['game_status'] == "completed") $html .= "This game is over.";
		
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
				$r = $this->app->run_query($q);
				$game_winning_amount = $r->fetch()['SUM(amount)'];
				$html .= "You won <font class=\"greentext\">".$this->app->format_bignum($game_winning_amount/pow(10,8))."</font> ".$this->db_game['coin_name_plural']." in the end-of-game payout.<br/>\n";
			}
			
			foreach ($entity_score_info['entities'] as $entity_id => $entity_info) {
				$html .= "<div class=\"row\"><div class=\"col-sm-3\">".$entity_info['entity_name']."</div><div class=\"col-sm-3\">".$entity_info['points']." electoral votes</div>";
				if ($user) {
					$coins_in_existence = $this->coins_in_existence(false);
					$add_coins = floor($coins_in_existence*$this->db_game['game_winning_inflation']);
					$new_coins_in_existence = $coins_in_existence + $add_coins;
					$account_value = $user->account_coin_value($this);
					$account_pct = $account_value/$coins_in_existence;
					$payout_amount = floor($add_coins*($entity_info['my_votes']/$entity_info['entity_votes']));
					$new_account_value = $account_value+$payout_amount;
					$new_account_pct = $new_account_value/$new_coins_in_existence;
					if ($account_pct > 0) $change_frac = $new_account_pct/$account_pct-1;
					else $change_frac = 0;
					$html .= "<div class=\"col-sm-3\">".$this->app->format_bignum($entity_info['my_pct'])."% of my votes</div>";
					$html .= "<div class=\"col-sm-3";
					if ($change_frac >= 0) $html .= " greentext";
					else $html .= " redtext";
					$html .= "\">";
					if ($change_frac >= 0) {
						$html .= "+".$this->app->format_bignum(100*$change_frac)."%";
					}
					else {
						$html .= "-".$this->app->format_bignum((-1)*100*$change_frac)."%";
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
		$coins_per_block = $this->app->format_bignum($this->db_game['pow_reward']/pow(10,8));
		
		$post_buyin_supply = $this->db_game['giveaway_amount']+$this->coins_in_existence(false);
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
			$html .= "This game started ".$this->app->format_seconds(time()-$this->db_game['start_time'])." ago; ".$this->app->format_bignum($this->coins_in_existence(false)/pow(10,8))." ".$this->db_game['coin_name_plural']."  are already in circulation. ";
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
		else if ($this->db_game['inflation'] == "fixed_exponential") $html .= "This currency grows by ".(100*$this->db_game['exponential_inflation_rate'])."% per round. ".(100 - 100*$this->db_game['exponential_inflation_minershare'])."% is given to voters and ".(100*$this->db_game['exponential_inflation_minershare'])."% is given to miners every ".$this->app->format_seconds($seconds_per_round).". ";
		else {} // exponential
		
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
		
		$q = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$this->db_game['game_id']."' AND ug.payment_required=0 ORDER BY ug.account_value DESC, u.username ASC;";
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
			$qq = "UPDATE user_games SET account_value='".($temp_user->account_coin_value($this)/pow(10,8))."' WHERE user_game_id='".$temp_user_game['user_game_id']."';";
			$this->app->run_query($qq);
		}
		
		return $html;
	}
	
	public function scramble_plan_allocations($strategy, $weight_map, $from_round, $to_round) {
		if (!$weight_map) $weight_map[0] = 1;
		
		$q = "DELETE FROM strategy_round_allocations WHERE strategy_id='".$strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
		$r = $this->app->run_query($q);
		
		for ($round_id=$from_round; $round_id<=$to_round; $round_id++) {
			$block_id = ($round_id-1)*$this->db_game['round_length']+1;
			$events = $this->events_by_block($block_id);
			$option_list = array();
			for ($e=0; $e<count($events); $e++) {
				$qq = "SELECT * FROM options WHERE event_id='".$events[$e]->db_event['event_id']."' ORDER BY option_id ASC;";
				$rr = $this->app->run_query($qq);
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
					$rr = $this->app->run_query($qq);
					$used_option_ids[$option_list[$option_index]['option_id']] = true;
				}
			}
		}
	}
	
	public function coind_add_block(&$coin_rpc, $block_hash, $block_height, $headers_only) {
		$start_time = microtime(true);
		$html = "";
		
		$db_block = false;
		$q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id='".$block_height."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$db_block = $r->fetch();
		}
		else {
			$this->app->run_query("INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_id='".$block_height."', time_created='".time()."', effectiveness_factor='".$this->block_id_to_effectiveness_factor($block_height)."', locally_saved=0;");
			$internal_block_id = $this->app->last_insert_id();
			$db_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';")->fetch();
		}
		
		if ($db_block['block_hash'] == "") {
			$this->app->run_query("UPDATE blocks SET block_hash='".$block_hash."' WHERE internal_block_id='".$db_block['internal_block_id']."';");
			$html .= $block_height." ";
		}
		
		if ($db_block['locally_saved'] == 0 && !$headers_only) {
			try {
				if ($headers_only) {
					$lastblock_rpc = $coin_rpc->getblockheader($block_hash);
				}
				else {
					$lastblock_rpc = $coin_rpc->getblock($block_hash);
				}
			}
			catch (Exception $e) {
				var_dump($e);
				die("RPC failed to get block $block_hash");
			}
			
			$block_within_round = $this->block_id_to_round_index($block_height);
			
			echo $block_height." ";
			
			$coins_created = 0;
			
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				
				$db_transaction = $this->add_transaction($coin_rpc, $tx_hash, $block_height, true);
				if ($db_transaction['transaction_desc'] != "transaction") $coins_created += $db_transaction['amount'];
			}
			
			$this->app->run_query("UPDATE blocks SET locally_saved=1 WHERE internal_block_id='".$db_block['internal_block_id']."';");
			$this->app->run_query("UPDATE games SET coins_in_existence=coins_in_existence+".$coins_created.", coins_in_existence_block=".$block_height." WHERE game_id='".$this->db_game['game_id']."';");
			
			echo "Took ".(microtime(true)-$start_time)." sec to add block #".$block_height."<br/>\n";
			if ($block_height%$this->db_game['round_length'] == 0) $this->add_round_from_rpc($block_height/$this->db_game['round_length']);
		}
		
		return $html;
	}
	
	public function walletnotify(&$coin_rpc, $tx_hash, $skip_set_site_constant) {
		$start_time = microtime(true);
		if (!$skip_set_site_constant) $this->app->set_site_constant('walletnotify', $tx_hash);
		
		$require_inputs = true;
		if ($this->db_game['payout_weight'] == "coin") $require_inputs = false;
		$this->add_transaction($coin_rpc, $tx_hash, false, $require_inputs);
	}
	
	public function add_transaction(&$coin_rpc, $tx_hash, $block_height, $require_inputs) {
		$q = "SELECT * FROM transactions WHERE tx_hash='".$tx_hash."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$unconfirmed_tx = $r->fetch();
			if ($unconfirmed_tx['game_id'] == $this->db_game['game_id']) {
				$q = "DELETE t.*, io.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$r = $this->app->run_query($q);
			}
		}
		
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
			
			$outputs = $transaction_rpc["vout"];
			$inputs = $transaction_rpc["vin"];
			
			if (count($inputs) == 1 && !empty($inputs[0]['coinbase'])) {
				$transaction_type = "coinbase";
				if (count($outputs) > 1) $transaction_type = "votebase";
			}
			else $transaction_type = "transaction";
			
			$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."'";
			if ($block_height) $q .= ", block_id='".$block_height."', round_id='".$this->block_to_round($block_height)."', effectiveness_factor='".$this->block_id_to_effectiveness_factor($block_height)."'";
			$q .= ", time_created='".time()."';";
			$r = $this->app->run_query($q);
			$db_transaction_id = $this->app->last_insert_id();
			echo ". ";
			
			$spend_io_ids = array();
			$input_sum = 0;
			$output_sum = 0;
			$coin_blocks_destroyed = 0;
			$coin_rounds_destroyed = 0;
			
			if ($transaction_type == "transaction" && $require_inputs) {
				for ($j=0; $j<count($inputs); $j++) {
					$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.game_id='".$this->db_game['game_id']."' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
					$r = $this->app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$spend_io = $r->fetch();
					}
					else {
						$new_tx = $this->add_transaction($coin_rpc, $inputs[$j]["txid"], false, false);
						$r = $this->app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$spend_io = $r->fetch();
						}
						else {
							$error_message = "Failed to create inputs for tx #".$db_transaction_id.", looked for tx_hash=".$inputs[$j]['txid'].", vout=".$inputs[$j]['vout'];
							$this->app->log($error_message);
							die($error_message);
						}
					}
					$spend_io_ids[$j] = $spend_io['io_id'];
					
					$input_sum += (int) $spend_io['amount'];
					
					if ($block_height) {
						$this_io_cbd = ($block_height - $spend_io['block_id'])*$spend_io['amount'];
						$this_io_crd = ($this->block_to_round($block_height) - $spend_io['create_round_id'])*$spend_io['amount'];
						
						$coin_blocks_destroyed += $this_io_cbd;
						$coin_rounds_destroyed += $this_io_crd;
					
						$r = $this->app->run_query("UPDATE transaction_ios SET coin_blocks_created='".$this_io_cbd."', coin_rounds_created='".$this_io_crd."' WHERE io_id='".$spend_io['io_id']."';");
					}
				}
			}
			
			for ($j=0; $j<count($outputs); $j++) {
				$address_text = $outputs[$j]["scriptPubKey"]["addresses"][0];
				
				$output_address = $this->create_or_fetch_address($address_text, true, $coin_rpc, false, true);
				
				$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', out_index='".$j."'";
				if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
				$q .= ", address_id='".$output_address['address_id']."'";
				if ($output_address['option_id'] > 0) $q .= ", option_id=".$output_address['option_id'];
				$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."'";
				if ($block_height) $q .= ", create_block_id='".$block_height."', create_round_id='".$this->block_to_round($block_height)."'";
				$q .= ";";
				$r = $this->app->run_query($q);
				
				$output_sum += $outputs[$j]["value"]*pow(10,8);
				
				if ($input_sum > 0) $output_cbd = floor($coin_blocks_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
				else $output_cbd = 0;
				if ($input_sum > 0) $output_crd = floor($coin_rounds_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
				else $output_crd = 0;
				
				if ($this->db_game['payout_weight'] == "coin") $votes = (int) $outputs[$j]["value"]*pow(10,8);
				else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
				else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
				else $votes = 0;
				
				$votes = floor($votes*$this->block_id_to_effectiveness_factor($block_height));
				if ($votes != 0 || $output_cbd != 0 || $output_crd =! 0) {
					$q = "UPDATE transaction_ios SET coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', votes='".$votes."' WHERE io_id='".$db_output['io_id']."';";
					$r = $this->app->run_query($q);
				}
			}
			
			if (count($spend_io_ids) > 0) {
				$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_status='spent', spend_transaction_id='".$db_transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$db_transaction_id."', ',')), spend_block_id='".$block_height."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
				$r = $this->app->run_query($q);
			}
			
			$fee_amount = ($input_sum-$output_sum);
			if ($transaction_type != "transaction" || !$require_inputs) $fee_amount = 0;
			
			$q = "UPDATE transactions SET amount='".$output_sum."', has_all_outputs=1, fee_amount='".$fee_amount."'";
			if ($require_inputs || $transaction_type != "transaction") $q .= ", has_all_inputs=1";
			$q .= " WHERE transaction_id='".$db_transaction_id."';";
			$r = $this->app->run_query($q);
			
			$db_transaction = $this->app->run_query("SELECT * FROM transactions WHERE transaction_id='".$db_transaction_id."';")->fetch();
			return $db_transaction;
		}
		catch (Exception $e) {
			var_dump($e);
			$this->app->log($this->db_game['name'].": Failed to fetch transaction ".$tx_hash);
		}
	}
	
	public function sync_coind(&$coin_rpc) {
		$html = "";
		
		$last_block_id = $this->last_block_id();

		$startblock_q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id='".$last_block_id."';";
		$startblock_r = $this->app->run_query($startblock_q);
		
		if ($startblock_r->rowCount() == 0) {
			if ($last_block_id == 0) {
				$this->add_genesis_block($coin_rpc);
				$startblock_r = $this->app->run_query($startblock_q);
			}
			else {
				die("sync_coind failed, block $last_block_id is missing.\n");
			}
		}
		
		if ($startblock_r->rowCount() == 1) {
			$last_block = $startblock_r->fetch();
			if ($last_block['block_hash'] == "") {
				$last_block_hash = $coin_rpc->getblockhash((int) $last_block['block_id']);
				$this->coind_add_block($coin_rpc, $last_block_hash, $last_block['block_id'], TRUE);
				$this->update_option_votes();
				$last_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$last_block['internal_block_id']."';")->fetch();
			}
			
			echo "Resolving potential fork on block #".$last_block['block_id']."<br/>\n";
			$this->resolve_potential_fork_on_block($coin_rpc, $last_block);

			echo "Loading new blocks...\n";
			$this->load_new_blocks($coin_rpc);
			
			echo "Loading unconfirmed transactions...\n";
			$this->load_unconfirmed_transactions($coin_rpc);
			
			echo "Updating option votes...\n";
			$this->update_option_votes();
			
			echo "Done syncing!\n";
		}
		
		return $html;
	}
	
	public function load_new_blocks(&$coin_rpc) {
		$last_block_id = $this->last_block_id();
		$last_block = $this->app->run_query("SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id='".$last_block_id."';")->fetch();
		$block_height = $last_block['block_id'];
		$rpc_block = $coin_rpc->getblock($last_block['block_hash']);
		$keep_looping = true;
		do {
			$block_height++;
			if (empty($rpc_block['nextblockhash'])) {
				$keep_looping = false;
			}
			else {
				echo "Add block #$block_height (".$rpc_block['nextblockhash'].")\n";
				$rpc_block = $coin_rpc->getblock($rpc_block['nextblockhash']);
				$this->coind_add_block($coin_rpc, $rpc_block['hash'], $block_height, FALSE);
			}
		}
		while ($keep_looping);
	}
	
	public function load_all_block_headers(&$coin_rpc, $required_blocks_only) {
		$html = "";
		
		// Load headers for blocks with NULL block hash
		$keep_looping = true;
		do {
			$q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_hash IS NULL";
			if ($required_blocks_only && $this->db_game['game_starting_block'] > 0) $q .= " AND block_id >= ".$this->db_game['game_starting_block'];
			$q .= " ORDER BY block_id DESC LIMIT 1;";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$unknown_block = $r->fetch();
				
				$unknown_block_hash = $coin_rpc->getblockhash((int) $unknown_block['block_id']);
				$this->coind_add_block($coin_rpc, $unknown_block_hash, $unknown_block['block_id'], TRUE);
				
				$html .= $unknown_block['block_id']." ";
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
		
		return $html;
	}
	
	public function load_all_blocks(&$coin_rpc, $required_blocks_only) {
		// Fully load blocks where block headers were already loaded into the db
		$keep_looping = true;
		do {
			$q = "SELECT * FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND locally_saved=0";
			if ($required_blocks_only && $this->db_game['game_starting_block'] > 0) $q .= " AND block_id >= ".$this->db_game['game_starting_block'];
			$q .= " ORDER BY block_id ASC LIMIT 1;";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$unknown_block = $r->fetch();
				
				if ($unknown_block['block_hash'] == "") {
					$unknown_block_hash = $coin_rpc->getblockhash((int)$unknown_block['block_id']);
					$this->coind_add_block($coin_rpc, $unknown_block_hash, $unknown_block['block_id'], TRUE);
					$unknown_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$unknown_block['internal_block_id']."';")->fetch();
				}
				
				echo 'Download full block #'.$unknown_block['block_id']." (".$unknown_block['block_hash'].")<br/>\n";
				echo $this->coind_add_block($coin_rpc, $unknown_block['block_hash'], $unknown_block['block_id'], FALSE);
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
	}
	
	public function resolve_potential_fork_on_block(&$coin_rpc, &$db_block) {
		$rpc_block = $coin_rpc->getblock($db_block['block_hash']);
		
		if ($rpc_block['confirmations'] < 0) {
			$this->app->log("Detected a chain fork at block #".$db_block['block_id']);
			
			$delete_block_height = $db_block['block_id'];
			$rpc_delete_block = $rpc_block;
			$keep_looping = true;
			do {
				$rpc_prev_block = $coin_rpc->getblock($rpc_delete_block['previousblockhash']);
				if ($rpc_prev_block['confirmations'] < 0) {
					$rpc_delete_block = $rpc_prev_block;
					$delete_block_height--;
				}
				else $keep_looping = false;
			}
			while ($keep_looping);
			
			$this->app->log("Deleting blocks #".$delete_block_height." and above.");
			
			$this->delete_blocks_from_height($delete_block_height);
		}
	}
	
	public function load_unconfirmed_transactions(&$coin_rpc) {
		$unconfirmed_txs = $coin_rpc->getrawmempool();
		echo "Looping through ".count($unconfirmed_txs)." unconfirmed transactions.<br/>\n";
		for ($i=0; $i<count($unconfirmed_txs); $i++) {
			$this->walletnotify($coin_rpc, $unconfirmed_txs[$i], TRUE);
			if ($i%100 == 0) echo "$i ";
		}
		$this->app->set_site_constant('walletnotify', $unconfirmed_txs[count($unconfirmed_txs)-1]);
	}
	
	public function insert_initial_blocks(&$coin_rpc) {
		$r = $this->app->run_query("SELECT MAX(block_id) FROM blocks WHERE game_id='".$this->db_game['game_id']."';");
		$db_block_height = $r->fetch();
		$db_block_height = $db_block_height['MAX(block_id)'];
		
		$getinfo = $coin_rpc->getinfo();
		
		echo "Inserting blocks ".($db_block_height+1)." to ".$getinfo['blocks']."<br/>\n";
		
		$start_insert = "INSERT INTO blocks (game_id, block_id, effectiveness_factor, time_created) VALUES ";
		$modulo = 0;
		$q = $start_insert;
		for ($block_i=$db_block_height+1; $block_i<$getinfo['blocks']; $block_i++) {
			if ($modulo == 1000) {
				$q = substr($q, 0, strlen($q)-2).";";
				$this->app->run_query($q);
				$modulo = 0;
				$q = $start_insert;
				echo ". ";
			}
			else $modulo++;
		
			$q .= "('".$this->db_game['game_id']."', '".$block_i."', '".$this->block_id_to_effectiveness_factor($block_i)."', '".time()."'), ";
		}
		if ($modulo > 0) {
			$q = substr($q, 0, strlen($q)-2).";";
			$this->app->run_query($q);
			echo ". ";
		}
	}
	
	function delete_blocks_from_height($block_height) {
		echo "deleting from block #".$block_height." and up.<br/>\n";
		$this->app->run_query("DELETE FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND block_id IS NULL;");
		$this->app->run_query("DELETE FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."' AND create_block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM transaction_ios WHERE game_id='".$this->db_game['game_id']."' AND create_block_id IS NULL;");
		$this->app->run_query("UPDATE transaction_ios SET spend_round_id=NULL, coin_blocks_created=0, coin_rounds_created=0, votes=0, spend_transaction_id=NULL, spend_count=NULL, spend_status='unspent', payout_io_id=NULL WHERE game_id='".$this->db_game['game_id']."' AND spend_block_id >= ".$block_height.";");
		
		$this->app->run_query("DELETE FROM blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id >= ".$block_height.";");
		
		$round_id = $this->block_to_round($block_height);
		$this->app->run_query("DELETE eo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND round_id >= ".$round_id.";");
		$this->app->run_query("DELETE eoo.* FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND round_id >= ".$round_id.";");
		
		$this->app->run_query("UPDATE strategy_round_allocations sra JOIN user_strategies us ON us.strategy_id=sra.strategy_id SET sra.applied=0 WHERE us.game_id='".$this->db_game['game_id']."' AND sra.round_id >= ".$round_id.";");
		
		$this->update_option_votes();
		$coins_in_existence = $this->coins_in_existence(false);
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
		
		$q = "INSERT INTO transactions SET game_id='".$this->db_game['game_id']."', amount='".$this->db_game['pow_reward']."', transaction_desc='coinbase', tx_hash='".$tx_hash."', block_id='0', time_created='".time()."', has_all_inputs=1, has_all_outputs=1;";
		$this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		$q = "INSERT INTO transaction_ios SET spend_status='unspent', instantly_mature=0, game_id='".$this->db_game['game_id']."', user_id=NULL, address_id='".$output_address['address_id']."'";
		$q .= ", create_transaction_id='".$transaction_id."', amount='".$this->db_game['pow_reward']."', create_block_id='0';";
		$r = $this->app->run_query($q);
		
		$q = "INSERT INTO blocks SET game_id='".$this->db_game['game_id']."', block_hash='".$genesis_hash."', block_id='0', time_created='".time()."', locally_saved=1;";
		$r = $this->app->run_query($q);
		
		$html .= "Added the genesis transaction!<br/>\n";
		
		$returnvals['log_text'] = $html;
		$returnvals['genesis_hash'] = $genesis_hash;
		$returnvals['nextblockhash'] = $rpc_block->json_obj['nextblockhash'];
		return $returnvals;
	}

	public function refresh_coins_in_existence() {
		$last_block_id = $this->last_block_id();
		$q = "UPDATE games SET coins_in_existence_block=0 WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->app->run_query($q);
		$coi = $this->coins_in_existence($last_block_id);
	}
	
	public function coins_in_existence($block_id) {
		$last_block_id = $this->last_block_id();
		
		if ($last_block_id == 0 || ($last_block_id != $this->db_game['coins_in_existence_block'] || ($block_id !== false && $last_block_id != $block_id))) {
			$q = "SELECT SUM(amount) FROM transactions WHERE block_id IS NOT NULL AND game_id='".$this->db_game['game_id']."' AND transaction_desc IN ('giveaway','votebase','coinbase')";
			if ($block_id !== false) $q .= " AND block_id <= ".$block_id;
			$q .= ";";
			$r = $this->app->run_query($q);
			$coins = $r->fetch(PDO::FETCH_NUM);
			$coins = $coins[0];
			if ($coins > 0) {} else $coins = 0;
			if (!$block_id || $block_id == $last_block_id) {
				$q = "UPDATE games SET coins_in_existence='".$coins."', coins_in_existence_block='".$last_block_id."' WHERE game_id='".$this->db_game['game_id']."';";
				$this->app->run_query($q);
			}
			return $coins;
		}
		else {
			return $this->db_game['coins_in_existence'];
		}
	}
	
	public function fetch_user_strategy(&$user_game) {
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$user_game['strategy_id']."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$user_strategy = $r->fetch();
		}
		else {
			$q = "SELECT * FROM user_strategies WHERE user_id='".$user_game['user_id']."' AND game_id='".$user_game['game_id']."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$user_strategy = $r->fetch();
				$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE user_game_id='".$user_game['user_game_id']."';";
				$r = $this->app->run_query($q);
			}
			else {
				$q = "DELETE FROM user_games WHERE user_game_id='".$user_game['user_game_id']."';";
				$r = $this->app->run_query($q);
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
			$db_winning_option = $this->app->run_query("SELECT * FROM options WHERE option_id='".$winning_option."';")->fetch();
		}
		
		$q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.event_id='".$this->db_event['event_id']."' AND ug.notification_preference='email' AND u.notification_email LIKE '%@%';";
		$r = $this->app->run_query($q);
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
			
			$delivery_id = $this->app->mail_async($user_event['notification_email'], $GLOBALS['site_name'], "noreply@".$GLOBALS['site_domain'], $subject, $message, "", "");
			echo "sent one to ".$user_event['notification_email']." (".$delivery_id.")<br/>\n";
		}*/
	}
	
	public function load_current_events() {
		$this->current_events = array();
		$mining_block_id = $this->last_block_id()+1;
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND ev.event_starting_block<=".$mining_block_id." AND ev.event_final_block>=".$mining_block_id." ORDER BY ev.event_id ASC;";
		$r = $this->app->run_query($q);
		while ($db_event = $r->fetch()) {
			$this->current_events[count($this->current_events)] = new Event($this, $db_event, false);
		}
	}
	
	public function events_by_block($block_id) {
		$events = array();
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND ev.event_starting_block<=".$block_id." AND ev.event_final_block>=".$block_id." ORDER BY ev.event_id ASC;";
		$r = $this->app->run_query($q);
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
		$last_block_id = $this->last_block_id();
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
		
		for ($i=0; $i<count($this->current_events); $i++) {
			$event = $this->current_events[$i];
			$round_stats = $event->round_voting_stats_all($current_round);
			$sum_votes = $round_stats[0];
			$option_id2rank = $round_stats[3];
			$js .= '
			games['.$game_index.'].events['.$i.'] = new Event(games['.$game_index.'], '.$i.', '.$event->db_event['event_id'].', '.$event->db_event['num_voting_options'].', "'.$event->db_event['vote_effectiveness_function'].'");'."\n";
			
			$option_q = "SELECT * FROM options WHERE event_id='".$event->db_event['event_id']."' ORDER BY option_id ASC;";
			$option_r = $this->app->run_query($option_q);
			while ($option = $option_r->fetch()) {
				$js .= 'event_html += "<div class=\'modal fade\' id=\'game'.$game_index.'_event'.$i.'_vote_confirm_'.$option['option_id'].'\'></div>";';
			}
			
			$option_r = $this->app->run_query($option_q);
			
			$j=0;
			while ($option = $option_r->fetch()) {
				$js .= "games[".$game_index."].events[".$i."].options.push(new option(games[".$game_index."].events[".$i."], ".$j.", ".$option['option_id'].", '".$option['name']."', 0));\n";
				/*if ($user) {
					$votingaddr_id = $user->user_address_id($this->db_game['game_id'], $option['option_id']);
					if ($votingaddr_id !== false) {
						$js .= "votingAddrOptions.push(".$option['option_id'].");\n";
					}
				}*/
				$j++;
			}
			$js .= '
			games['.$game_index.'].events['.$i.'].option_selected(0);
			console.log("adding game, event '.$i.' into DOM...");'."\n";
			if ($i == 0) $js .= 'event_html += "<div class=\'row\'>";';
			$js .= 'event_html += "<div class=\'col-sm-6\'>";';
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
		games['.$game_index.'].setVotingAddresses();
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
		}
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
			
			$input_buttons_html .= 'id="select_utxo_'.$utxo['io_id'].'" class="btn btn-default select_utxo';
			if ($this->db_game['logo_image_id'] > 0) $input_buttons_html .= ' select_utxo_image';
			$input_buttons_html .= '" onclick="add_utxo_to_vote(\''.$utxo['io_id'].'\', '.$utxo['amount'].', '.$utxo['create_block_id'].');">';
			$input_buttons_html .= '</div>'."\n";
			
			$js .= "mature_ios.push(new mature_io(mature_ios.length, ".$utxo['io_id'].", ".$utxo['amount'].", ".$utxo['create_block_id']."));\n";
		}
		$js .= "refresh_mature_io_btns();\n";
		
		$html .= '<div id="select_input_buttons_msg"></div>'."\n";
		$html .= $input_buttons_html;
		$html .= '<script type="text/javascript">'.$js."</script>\n";
		
		return $html;
	}
	
	public function load_all_event_points_js($game_index, $user_strategy) {
		$js = "";
		$q = "SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id='".$this->db_game['game_id']."' ORDER BY e.event_id ASC;";
		$r = $this->app->run_query($q);
		$i=0;
		while ($db_event = $r->fetch()) {
			$option_q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_id ASC;";
			$option_r = $this->app->run_query($option_q);
			$j=0;
			while ($option = $option_r->fetch()) {
				$qq = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND option_id='".$option['option_id']."';";
				$rr = $this->app->run_query($qq);
				if ($rr->rowCount() > 0) {
					$sra = $rr->fetch();
					$points = $sra['points'];
				}
				else $points = 0;
				
				$js .= "games[".$game_index."].all_events[".$i."].options[".$j."].points = ".$points.";\n";
				$j++;
			}
			$i++;
		}
		return $js;
	}
	
	public function logo_image_url() {
		if ($this->db_game['logo_image_id'] > 0) {
			$db_image = $this->app->run_query("SELECT * FROM images WHERE image_id='".$this->db_game['logo_image_id']."';")->fetch();
			return $this->app->image_url($db_image);
		}
		else return "";
	}
	
	public function vote_effectiveness_function() {
		return $this->current_events[0]->db_event['vote_effectiveness_function'];
	}
}
?>
