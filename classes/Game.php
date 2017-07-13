<?php
class Game {
	public $db_game;
	public $blockchain;
	public $current_events;
	public $genesis_hash;
	
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
	
	public function create_transaction($option_ids, $amounts, $user_game, $block_id, $type, $io_ids, $address_ids, $remainder_address_id, $transaction_fee) {
		if (!$type || $type == "") $type = "transaction";
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			$amount += $amounts[$i];
		}
		
		$instantly_mature = 0;
		
		if ($user_game) {
			$from_user = new User($this->blockchain->app, $user_game['user_id']);
			$account_value = $from_user->account_coin_value($this, $user_game);
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
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", $io_ids).");";
			$r = $this->blockchain->app->run_query($q);
			$utxo_balance = $r->fetch(PDO::FETCH_NUM);
			$utxo_balance = $utxo_balance[0];
		}
		
		$raw_txin = array();
		$raw_txout = array();
		$affected_input_ids = array();
		$created_input_ids = array();
		
		if ($type == "votebase" || $type == "coinbase") $amount_ok = true;
		else if ($utxo_balance == $amount || (!$io_ids && $amount <= $mature_balance)) $amount_ok = true;
		else $amount_ok = false;
		
		if ($amount_ok && (count($option_ids) == count($amounts) || ($option_ids === false && count($amounts) == count($address_ids)))) {
			// For rpc games, don't insert a tx record, it will come in via walletnotify
			if ($this->blockchain->db_blockchain['p2p_mode'] != "rpc") {
				$new_tx_hash = $this->blockchain->app->random_string(64);
				$q = "INSERT INTO transactions SET blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."', fee_amount='".$transaction_fee."', has_all_inputs=1, has_all_outputs=1, num_inputs='".count($io_ids)."', num_outputs='".count($amounts)."'";
				$q .= ", tx_hash='".$new_tx_hash."'";
				$q .= ", transaction_desc='".$type."', amount=".$amount;
				if ($block_id !== false) $q .= ", block_id='".$block_id."', round_id='".$this->block_to_round($block_id)."'";
				$q .= ", time_created='".time()."';";
				$r = $this->blockchain->app->run_query($q);
				$transaction_id = $this->blockchain->app->last_insert_id();
			}
			
			$input_sum = 0;
			$overshoot_amount = 0;
			$overshoot_return_addr_id = $remainder_address_id;
			
			if ($type == "votebase" || $type == "coinbase") {}
			else {
				$q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status IN ('unspent','unconfirmed') AND io.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."'";
				if ($this->db_game['maturity'] > 0) $q .= " AND io.create_block_id <= ".($this->blockchain->last_block_id()-$this->db_game['maturity']);
				if ($io_ids) $q .= " AND io.io_id IN (".implode(",", $io_ids).")";
				else $q .= " AND io.io_id IN (".$this->mature_io_ids_csv($user_game).")";
				$q .= " ORDER BY io.amount ASC;";
				$r = $this->blockchain->app->run_query($q);
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				$ref_block_id = $this->blockchain->last_block_id()+1;
				$ref_cbd = 0;
				
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
				
				$overshoot_amount = $input_sum - $amount;
			}
			
			$output_error = false;
			$out_index = 0;
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					if ($address_ids) {
						if (count($address_ids) == count($amounts)) $address_id = $address_ids[$out_index];
						else $address_id = $address_ids[0];
					}
					else $address_id = $from_user->user_address_id($this, false, $option_ids[$out_index], $user_game['account_id']);
					
					if ($address_id) {
						$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
						$r = $this->blockchain->app->run_query($q);
						$address = $r->fetch();
						
						if ($this->blockchain->db_blockchain['p2p_mode'] == "none") {
							$q = "INSERT INTO transaction_ios SET blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."', spend_status='";
							if ($instantly_mature == 1) $q .= "unspent";
							else $q .= "unconfirmed";
							$q .= "', out_index='".$out_index."', ";
							if (!empty($address['user_id'])) $q .= "user_id='".$address['user_id']."', ";
							$q .= "address_id='".$address_id."', ";
							$q .= "option_index='".$address['option_index']."', ";
							
							if ($block_id !== false) {
								if ($input_sum == 0) $output_cbd = 0;
								else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								
								if ($input_sum == 0) $output_crd = 0;
								else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								
								$q .= "coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', ";
							}
							if ($block_id !== false) {
								$q .= "create_block_id='".$block_id."', create_round_id='".$this->block_to_round($block_id)."', ";
							}
							$q .= "create_transaction_id='".$transaction_id."', amount='".$amounts[$out_index]."';";
							
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
				else {
					$successful = false;
					$coin_rpc = false;
					$this->blockchain->add_transaction($coin_rpc, $new_tx_hash, $block_id, true, $successful, 0, false, false);
					return $transaction_id;
				}
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
		// This function only runs for games with p2p_mode='none'
		$log_text = "";
		$last_block_id = $this->blockchain->last_block_id();
		
		$q = "INSERT INTO blocks SET blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."', block_id='".($last_block_id+1)."', block_hash='".$this->blockchain->app->random_string(64)."', time_created='".time()."', time_loaded='".time()."', locally_saved=1;";
		$r = $this->blockchain->app->run_query($q);
		$internal_block_id = $this->blockchain->app->last_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';";
		$r = $this->blockchain->app->run_query($q);
		$block = $r->fetch();
		$created_block_id = $block['block_id'];
		$mining_block_id = $created_block_id+1;
		
		$justmined_round = $this->block_to_round($created_block_id);
		
		$log_text .= "Created block $created_block_id<br/>\n";
		
		// Include all unconfirmed TXs in the just-mined block
		$q = "SELECT * FROM transactions WHERE transaction_desc='transaction' AND blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id IS NULL;";
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
				
				while ($input_utxo = $rr->fetch()) {
					$coin_blocks_created = ($created_block_id - $input_utxo['create_block_id'])*$input_utxo['amount'];
					$total_coin_blocks_created += $coin_blocks_created;
				}
				
				$voted_coins_out = $this->blockchain->app->transaction_voted_coins_out($unconfirmed_tx['transaction_id']);
				
				if ($voted_coins_out > 0) {
					$cbd_per_coin_out = floor(pow(10,8)*$total_coin_blocks_created/$voted_coins_out)/pow(10,8);
				}
				else $cbd_per_coin_out = 0;
				
				$qq = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				
				while ($output_utxo = $rr->fetch()) {
					$coin_blocks_destroyed = floor($cbd_per_coin_out*$output_utxo['amount']);
					
					$qqq = "UPDATE transaction_ios SET coin_blocks_destroyed='".$coin_blocks_destroyed."', create_block_id='".$created_block_id."' WHERE io_id='".$output_utxo['io_id']."';";
					$rrr = $this->blockchain->app->run_query($qqq);
				}
				
				$qq = "UPDATE transactions t JOIN transaction_ios o ON t.transaction_id=o.create_transaction_id JOIN transaction_ios i ON t.transaction_id=i.spend_transaction_id SET t.block_id='".$created_block_id."', o.spend_status='unspent', o.create_block_id='".$created_block_id."', i.spend_status='spent', i.spend_block_id='".$created_block_id."' WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				
				$fee_sum += $fee_amount;
			}
		}
		
		$coin_rpc = false;
		$ref_account = false;
		$mined_address_str = $this->blockchain->app->random_string(34);
		$mined_address = $this->blockchain->create_or_fetch_address($mined_address_str, false, false, false, false, true);
		
		$mined_transaction_id = $this->blockchain->create_transaction('coinbase', array($this->blockchain->db_blockchain['initial_pow_reward']), $created_block_id, false, array($mined_address['address_id']), 0);
		
		$this->add_block($created_block_id);
		
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
					$game_votes_q = "SELECT SUM(gio.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id='".$this->db_game['game_id']."';";
					$game_votes_r = $this->blockchain->app->run_query($game_votes_q);
					$game_votes_total = $game_votes_r->fetch()['SUM(io.votes)'];
					
					$winner_votes_q = "SELECT SUM(gio.votes) FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
					$winner_votes_r = $this->blockchain->app->run_query($winner_votes_q);
					$winner_votes_total = $winner_votes_r->fetch()['SUM(io.votes)'];
					
					echo "payout ".$this->blockchain->app->format_bignum($payout_amount/pow(10,8))." coins to ".$entity_score_info['entities'][$entity_score_info['winning_entity_id']]['entity_name']." (".$this->blockchain->app->format_bignum($winner_votes_total/pow(10,8))." total votes)<br/>\n";
					
					$payout_io_q = "SELECT * FROM options o JOIN addresses a ON o.option_id=a.option_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id='".$this->db_game['game_id']."' AND e.entity_id='".$entity_score_info['winning_entity_id']."';";
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
					$transaction_id = $this->create_transaction(false, $amounts, false, false, "votebase", false, $address_ids, false, 0);
					$q = "UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET t.block_id='".$last_block_id."', io.spend_status='unspent', io.create_block_id='".$last_block_id."', gio.create_round_id='".$this->block_to_round($last_block_id)."' WHERE t.transaction_id='".$transaction_id."';";
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
				
				$user_balance = $this->blockchain->user_balance($db_user);
				$mature_balance = $this->blockchain->user_mature_balance($db_user);
				$free_balance = $mature_balance;
				
				$available_votes = $strategy_user->user_current_votes($this, $last_block_id, $current_round_id, $db_user);
				
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
								
								$transaction_id = $this->create_transaction($vote_option_ids, $vote_amounts, $db_user, false, 'transaction', $input_io_ids, false, false, false);
								
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
							}
							
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
								
								$transaction_id = $this->create_transaction($option_ids, $amounts, $db_user, false, 'transaction', false, false, false, $db_user['transaction_fee']);
								
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
												
												$log_text .= "Vote ".$by_entity_pct_points."% (".($coin_amount/pow(10,8))." coins) for ".$entity['entity_name']."<br/>\n";
												
												$option_ids[count($option_ids)] = $entity['option_id'];
												$amounts[count($amounts)] = $coin_amount;
												$amount_sum += $coin_amount;
											}
										}
									}
									if ($amount_sum < ($free_balance-$db_user['transaction_fee'])) $amounts[count($amounts)-1] += ($free_balance-$db_user['transaction_fee']) - $amount_sum;
									$transaction_id = $this->create_transaction($option_ids, $amounts, $db_user, false, 'transaction', false, false, false, $db_user['transaction_fee']);
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
									
									$transaction_id = $this->create_transaction($option_ids, $amounts, $db_user, false, 'transaction', false, false, false, $db_user['transaction_fee']);
									
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
	
	public function delete_from_block($block_height) {
		$q = "DELETE FROM game_blocks WHERE game_id='".$this->db_game['game_id']."' AND block_id >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE FROM game_sellouts WHERE game_id='".$this->db_game['game_id']."' AND in_block_id >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE gio.* FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.create_block_id >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND ob.block_height >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE eo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_final_block >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE eoo.* FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_final_block >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE e.*, o.* FROM events e LEFT JOIN options o ON e.event_id=o.event_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_final_block >= ".$block_height.";";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "UPDATE games SET events_until_block=NULL, coins_in_existence=0, coins_in_existence_block=NULL WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
	}
	
	public function delete_reset_game($delete_or_reset) {
		$q = "DELETE FROM game_blocks WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE FROM game_sellouts WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE FROM transaction_game_ios WHERE game_id='".$this->db_game['game_id']."';";
		$r = $this->blockchain->app->run_query($q);
		
		$q = "DELETE ob.* FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id JOIN events e ON o.event_id=e.event_id WHERE e.game_id='".$this->db_game['game_id']."';";
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
			
			$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			while ($user_game = $r->fetch()) {
				$temp_user = new User($this->blockchain->app, $user_game['user_id']);
				$temp_user->generate_user_addresses($this, $user_game);
			}
			
			for ($i=0; $i<count($invite_user_ids); $i++) {
				$invitation = false;
				$this->generate_invitation($this->db_game['creator_id'], $invitation, $invite_user_ids[$i]);
				$invite_event = false;
				$this->blockchain->app->try_apply_invite_key($invite_user_ids[$i], $invitation['invitation_key'], $invite_event);
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
	
	public function event_outcomes_html($from_event_index, $to_event_index) {
		$html = "";
		
		$show_initial = false;
		
		$q = "SELECT eo.*, e.*, winner.name AS winner_name FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id LEFT JOIN options winner ON eo.winning_option_id=winner.option_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_index <= ".$to_event_index." AND e.event_index >= ".$from_event_index." ORDER BY e.event_index DESC;";
		$r = $this->blockchain->app->run_query($q);
		
		$last_round_shown = 0;
		while ($event_outcome = $r->fetch()) {
			$html .= '<div class="row bordered_row">';
			$html .= '<div class="col-sm-4"><a href="/explorer/games/'.$this->db_game['url_identifier'].'/events/'.($event_outcome['event_index']+1).'">'.$event_outcome['event_name'].'</a></div>';
			$html .= '<div class="col-sm-5">';
			
			if ($event_outcome['winning_option_id'] > 0) {
				if (!empty($event_outcome['option_block_rule'])) {
					$qq = "SELECT * FROM event_outcome_options WHERE outcome_id='".$event_outcome['outcome_id']."' ORDER BY option_id ASC;";
					$rr = $this->blockchain->app->run_query($qq);
					$score_label = "";
					while ($outcome_option = $rr->fetch()) {
						if (empty($score_label)) $score_label = $outcome_option['option_block_score']."-";
						else $score_label .= $outcome_option['option_block_score'];
					}
					$html .= " ".$score_label;
				}
				else {
					$html .= " (".$this->blockchain->app->format_bignum($event_outcome['winning_votes']/pow(10,8))." votes";
					if ($event_outcome['sum_votes'] > 0) $html .= ", ".round(100*$event_outcome['winning_votes']/$event_outcome['sum_votes'], 2)."%";
					$html .= ")";
				}
				$html .= " &nbsp;&nbsp; ".$event_outcome['winner_name'];
			}
			else $html .= "No winner";
			
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
	
	public function generate_invitation($inviter_id, &$invitation, $user_id) {
		$q = "INSERT INTO game_invitations SET game_id='".$this->db_game['game_id']."'";
		if ($inviter_id > 0) $q .= ", inviter_id=".$inviter_id;
		$q .= ", invitation_key='".strtolower($this->blockchain->app->random_string(32))."', time_created='".time()."'";
		if ($user_id) $q .= ", used_user_id='".$user_id."'";
		$q .= ";";
		$r = $this->blockchain->app->run_query($q);
		$invitation_id = $this->blockchain->app->last_insert_id();
		
		$q = "SELECT * FROM game_invitations WHERE invitation_id='".$invitation_id."';";
		$r = $this->blockchain->app->run_query($q);
		$invitation = $r->fetch();
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
		$to_block_id = $to_round*$this->db_game['round_length']+1;
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
			$html .= '<div class="plan_row"><b>Round #'.$this->round_to_display_round($round)."</b><br/>\n";
			for ($event_i=0; $event_i<count($events); $event_i++) {
				$js .= "temp_plan_round.event_ids.push(".$events[$event_i]->db_event['event_id'].");\n";
				$q = "SELECT * FROM options WHERE event_id='".$events[$event_i]->db_event['event_id']."' ORDER BY option_id ASC;";
				$r = $this->blockchain->app->run_query($q);
				$option_index = 0;
				$html .= '<div class="planned_votes_event">'.$events[$event_i]->db_event['event_name'].'<br/>';
				while ($game_option = $r->fetch()) {
					$html .= '<div class="plan_option" id="plan_option_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" onclick="plan_option_clicked('.$round.', '.$events[$event_i]->db_event['event_id'].', '.$game_option['option_id'].');">';
					$html .= '<div class="plan_option_label" id="plan_option_label_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'">'.$game_option['name']."</div>";
					$html .= '<div class="plan_option_amount" id="plan_option_amount_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'"></div>';
					$html .= '<input type="hidden" id="plan_option_input_'.$round.'_'.$events[$event_i]->db_event['event_id'].'_'.$game_option['option_id'].'" name="poi_'.$round.'_'.$game_option['option_id'].'" value="" />';
					$html .= '</div>';
					$option_index++;
				}
				$html .= "</div>\n";
			}
			$js .= "plan_rounds.push(temp_plan_round);\n";
			$html .= "</div>\n";
			$round_i++;
		}
		$html .= '<script type="text/javascript">'.$js."\n".$this->load_all_event_points_js(0, $user_strategy, $from_round, $to_round)."\nset_plan_rightclicks();\nset_plan_round_sums();\nrender_plan_rounds();\n</script>\n";
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
		if (!empty($this->db_game['game_starting_block']) && !empty($this->db_game['escrow_address']) && ($this->blockchain->db_blockchain['p2p_mode'] == "none" || $transaction['block_id'] >= $this->db_game['game_starting_block'])) {
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
				
				if ($transaction['tx_hash'] == $this->db_game['genesis_tx_hash']) {
					$colored_coins_generated = $this->db_game['genesis_amount'];
				}
				else {
					$escrow_value = $this->escrow_value($transaction['block_id']-1);
					$coins_in_existence = $this->coins_in_existence($transaction['block_id']-1);
					
					$exchange_rate = $coins_in_existence/$escrow_value;
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
	
	public function escrow_value($block_id) {
		if (!$block_id) $block_id = $this->blockchain->last_block_id();
		
		$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
		
		$value = $this->blockchain->address_balance_at_block($escrow_address, $block_id);
		
		$q = "SELECT SUM(amount_out) FROM game_sellouts WHERE game_id='".$this->db_game['game_id']."' AND (out_block_id IS NULL OR out_block_id > ".$block_id.");";
		$r = $this->blockchain->app->run_query($q);
		$liabilities = $r->fetch();
		$liabilities = (int)$liabilities['SUM(amount_out)'];
		
		$value = $value - $liabilities;
		
		return $value;
	}
	
	public function account_value_html($account_value) {
		$html = '<font class="greentext">'.$this->blockchain->app->format_bignum($account_value/pow(10,8), 2).'</font> '.$this->db_game['coin_name_plural'];
		$html .= ' <font style="font-size: 12px;">(';
		$coins_in_existence = $this->coins_in_existence(false);
		if ($coins_in_existence > 0) $html .= $this->blockchain->app->format_bignum(100*$account_value/$coins_in_existence)."%";
		else $html .= "0%";
		
		$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
		$escrow_value = $this->escrow_value(false);
		if ($coins_in_existence > 0) {
			$innate_currency_value = floor(($account_value/$coins_in_existence)*$escrow_value);
		}
		else $innate_currency_value = 0;
		
		if ($innate_currency_value > 0 && $this->db_game['buyin_policy'] != "none") {
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
			$qq = "SELECT SUM(gio.votes), COUNT(*) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN options o ON gio.option_id=o.option_id JOIN entities e ON o.entity_id=e.entity_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$user_entity_votes_total = $rr->fetch();
			$return_obj['user_entity_votes_total'] = $user_entity_votes_total['SUM(gio.votes)'];

			$qq = "SELECT SUM(gio.votes) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON gio.io_id=io.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id='".$this->db_game['game_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
			$return_obj['entity_votes_total'] = $rr->fetch()['SUM(gio.votes)'];
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
				$qq = "SELECT SUM(gio.votes), COUNT(*) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id='".$this->db_game['game_id']."' AND a.user_id='".$user->db_user['user_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$rr = $this->blockchain->app->run_query($qq);
				$user_entity_votes = $rr->fetch();
				
				$return_rows[$entity['entity_id']]['my_votes'] = $user_entity_votes['SUM(gio.votes)'];
				if ($return_obj['user_entity_votes_total'] > 0) $my_pct = 100*$user_entity_votes['SUM(gio.votes)']/$return_obj['user_entity_votes_total'];
				else $my_pct = 0;
				$return_rows[$entity['entity_id']]['my_pct'] = $my_pct;
				
				$entity_votes_q = "SELECT SUM(gio.votes), COUNT(*) FROM options o JOIN transaction_game_ios gio ON o.option_id=gio.option_id JOIN transaction_ios io ON io.io_id=gio.io_id JOIN entities e ON o.entity_id=e.entity_id WHERE gio.game_id='".$this->db_game['game_id']."' AND o.entity_id='".$entity['entity_id']."';";
				$entity_votes_r = $this->blockchain->app->run_query($entity_votes_q);
				$return_rows[$entity['entity_id']]['entity_votes'] = $entity_votes_r->fetch()['SUM(gio.votes)'];
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
	
	public function game_status_explanation(&$user, &$user_game) {
		$last_block_id = $this->blockchain->last_block_id();
		
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
		
		$nextblock_effectiveness = $this->current_events[0]->block_id_to_effectiveness_factor($last_block_id+1);
		$html .= "<p>Votes are ".round(100*$nextblock_effectiveness)."% effective right now.</p>\n";
		
		if ($this->db_game['p2p_mode'] == "rpc") {
			$total_blocks = $last_block_id;
			
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
				$q = "SELECT MAX(block_id), SUM(load_time) FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND locally_saved=1 AND block_id>=".$this->db_game['game_starting_block'].";";
				$r = $this->blockchain->app->run_query($q)->fetch();
				$sum_load_time = (float) $r['SUM(load_time)'];
				$loading_block_id = (int) $r['MAX(block_id)']+1;
				$loading_block = $this->blockchain->app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND block_id='".$loading_block_id."';")->fetch();
				if ($loading_block) {
					list($loading_transactions, $loading_block_sum) = $this->blockchain->block_stats($loading_block);
					$block_fraction = $loading_transactions/$loading_block['num_transactions'];
				}
			}
			else $sum_load_time = 0;
			
			$headers_pct_complete = 100*($total_game_blocks-$missingheader_blocks)/$total_game_blocks;
			$blocks_pct_complete = 100*($total_game_blocks-($missing_blocks-$block_fraction))/$total_game_blocks;
			$total_load_time = $sum_load_time*(100/$blocks_pct_complete);
			$est_time_remaining = ((100-$blocks_pct_complete)/100)*$total_load_time;
			
			if ($blocks_pct_complete != 100) $html .= "<br/>Loading blocks... ".round($blocks_pct_complete, 2)."% complete (".$this->blockchain->app->format_seconds($est_time_remaining)." left). ";
			if ($loading_block) {
				$html .= "Loaded ".$loading_transactions."/".$loading_block['num_transactions']." in block <a href=\"/explorer/games/".$this->db_game['url_identifier']."/blocks/".$loading_block_id."\">#".$loading_block_id."</a>. ";
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
				$q = "SELECT SUM(amount) FROM addresses a JOIN address_keys k ON a.address_id=k.address_id JOIN transaction_ios io ON a.address_id=io.address_id WHERE k.account_id='".$user_game['account_id']."' AND io.create_transaction_id='".$this->db_game['game_winning_transaction_id']."';";
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
					$account_value = $user->account_coin_value($this, $user_game);
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
		$blocks_per_hour = 3600/$this->db_game['seconds_per_block'];
		$round_reward = ($this->db_game['pos_reward']+$this->db_game['pow_reward']*$this->db_game['round_length'])/pow(10,8);
		$rounds_per_hour = 3600/($this->db_game['seconds_per_block']*$this->db_game['round_length']);
		$coins_per_hour = $round_reward*$rounds_per_hour;
		$seconds_per_round = $this->db_game['seconds_per_block']*$this->db_game['round_length'];
		$coins_per_block = $this->blockchain->app->format_bignum($this->db_game['pow_reward']/pow(10,8));
		
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
			$account_coin_value = $temp_user->account_coin_value($this, $temp_user_game);
			$networth_disp = $this->blockchain->app->format_bignum($account_coin_value/pow(10,8));
			
			$html .= '<div class="row">';
			$html .= '<div class="col-sm-4"><a href="" onclick="openChatWindow('.$temp_user_game['user_id'].'); return false;">'.$temp_user_game['username'].'</a></div>';
			
			$html .= '<div class="col-sm-4">'.$networth_disp.' ';
			if ($networth_disp == '1') $html .= $this->db_game['coin_name'];
			else $html .= $this->db_game['coin_name_plural'];
			$html .= '</div>';
			
			$html .= '</div>';
			$qq = "UPDATE user_games SET account_value='".($account_coin_value/pow(10,8))."' WHERE user_game_id='".$temp_user_game['user_game_id']."';";
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
	
	public function last_block_id() {
		$q = "SELECT * FROM game_blocks WHERE game_id='".$this->db_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$game_block = $r->fetch();
			return (int) $game_block['block_id'];
		}
		else return 0;
	}
	
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
		$log_text = "function disabled";
		/*for ($i=0; $i<count($this->current_events); $i++) {
			$log_text .= $this->current_events[$i]->set_outcome_from_db($last_block_id, $add_payout_transaction);
		}*/
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
	
	public function events_by_payout_block($block_id) {
		$events = array();
		$q = "SELECT * FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN entities en ON et.entity_id=en.entity_id WHERE ev.game_id='".$this->db_game['game_id']."' AND ev.event_payout_block=".$block_id." ORDER BY ev.event_index ASC;";
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
		if ($user) {
			$user_id = $user->db_user['user_id'];
			$user_game = $user->ensure_user_in_game($this);
		}
		
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
			
			if ($event->db_event['display_mode'] == "default") {
				$holder_class = "game_event_box";
			}
			else {
				$holder_class = "game_event_slim";
			}
			
			$js .= '
			games['.$game_index.'].events['.$i.'] = new Event(games['.$game_index.'], '.$i.', '.$event->db_event['event_id'].', '.$event->db_event['num_voting_options'].', "'.$event->db_event['vote_effectiveness_function'].'", "'.$event->db_event['option_block_rule'].'");'."\n";
			
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
					$votingaddr_id = $user->user_address_id($this, $option['option_index'], false, $user_game['account_id']);
					if ($votingaddr_id !== false) $has_votingaddr = "true";
				}
				$js .= "games[".$game_index."].events[".$i."].options.push(new option(games[".$game_index."].events[".$i."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", '".str_replace("'", "", $option['name'])."', 0, $has_votingaddr));\n";
				$j++;
			}
			$js .= '
			games['.$game_index.'].events['.$i.'].option_selected(0);
			games['.$game_index.'].events['.$i.'].refresh_time_estimate();
			console.log("adding game, event '.$i.' into DOM...");'."\n";
			
			if ($i == 0) $js .= 'event_html += "<div class=\'row\'>";';
			$js .= 'event_html += "<div class=\'col-sm-'.$event_bootstrap_cols.'\'>";';
			$js .= 'event_html += "<div id=\'game'.$game_index.'_event'.$i.'\' class=\''.$holder_class.'\'><div id=\'game'.$game_index.'_event'.$i.'_current_round_table\'></div><div id=\'game'.$game_index.'_event'.$i.'_my_current_votes\'>Loading...</div></div>";'."\n";
			$js .= 'event_html += "</div>";';
			if ($i%2 == 1 || $i == count($this->current_events)-1) {
				$js .= 'event_html += "</div>';
				if ($i < count($this->current_events)-1) $js .= '<div class=\'row\'>';
				$js .= '";'."\n";
			}
		}
		$js .= '$("#game'.$game_index.'_events").html(event_html);'."\n";
		$js .= '
		$(document).ready(function() {
			render_tx_fee();
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
	
	public function mature_io_ids_csv($user_game) {
		$ids_csv = "";
		$last_block_id = $this->blockchain->last_block_id();
		$io_q = "SELECT gio.game_io_id FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND k.account_id='".$user_game['account_id']."' AND gio.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR gio.instantly_mature = 1)";
		if ($this->db_game['payout_weight'] == "coin_round") {
			$io_q .= " AND gio.create_round_id < ".$this->block_to_round($last_block_id+1);
		}
		$io_q .= " ORDER BY io.io_id ASC, gio.game_io_id ASC;";
		$io_r = $this->blockchain->app->run_query($io_q);
		while ($io = $io_r->fetch(PDO::FETCH_NUM)) {
			$ids_csv .= $io[0].",";
		}
		if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, strlen($ids_csv)-1);
		return $ids_csv;
	}
	
	public function last_voting_transaction_id() {
		$q = "SELECT transaction_id FROM transactions WHERE game_id='".$this->db_game['game_id']."' AND option_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
		$r = $this->game->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		if ($r[0] > 0) {} else $r[0] = 0;
		return $r[0];
	}
	
	public function select_input_buttons($user_game) {
		$js = "mature_ios.length = 0;\n";
		$html = "<p>Click on the coins below to compose your voting transaction.</p>\n";
		$input_buttons_html = "";
		
		$last_block_id = $this->blockchain->last_block_id();
		
		$output_q = "SELECT io.*, gio.* FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND k.account_id='".$user_game['account_id']."' AND gio.game_id='".$this->db_game['game_id']."' AND (io.create_block_id <= ".($last_block_id-$this->db_game['maturity'])." OR gio.instantly_mature=1)";
		if ($this->db_game['payout_weight'] == "coin_round") $output_q .= " AND gio.create_round_id < ".$this->block_to_round($last_block_id+1);
		$output_q .= " ORDER BY io.io_id ASC, gio.game_io_id ASC;";
		$output_r = $this->blockchain->app->run_query($output_q);
		
		$utxos = array();
		$prev_utxo = false;
		$mature_io_index = 0;
		while ($utxo = $output_r->fetch()) {
			if (intval($utxo['create_block_id']) > 0) {} else $utxo['create_block_id'] = 0;
			
			$utxos[count($utxos)] = $utxo;
			$input_buttons_html .= '<div ';
			
			$input_buttons_html .= 'id="select_utxo_'.$utxo['game_io_id'].'" ';
			$input_buttons_html .= 'class="btn btn-primary btn-sm select_utxo';
			if (!empty($prev_utxo) && $utxo['io_id'] == $prev_utxo['io_id']) $input_buttons_html .= ' connected_utxo';
			if ($this->db_game['logo_image_id'] > 0) $input_buttons_html .= ' select_utxo_image';
			$input_buttons_html .= '" onclick="add_utxo_to_vote('.$mature_io_index.', true);">';
			$input_buttons_html .= '</div>'."\n";
			
			$js .= "mature_ios.push(new mature_io(mature_ios.length, ".$utxo['game_io_id'].", ".$utxo['colored_amount'].", ".$utxo['create_block_id'].", ".$utxo['io_id']."));\n";
			$prev_utxo = $utxo;
			$mature_io_index++;
		}
		$js .= "refresh_mature_io_btns();\n";
		
		$html .= '<div id="select_input_buttons_msg"></div>'."\n";
		$html .= $input_buttons_html;
		$html .= '<script type="text/javascript">'.$js."</script>\n";
		
		return $html;
	}
	
	public function load_all_event_points_js($game_index, $user_strategy, $from_round_id, $to_round_id) {
		$js = "";
		$from_block_id = ($from_round_id-1)*$this->db_game['round_length']+1;
		$to_block_id = ($to_round_id-1)*$this->db_game['round_length']+1;
		$q = "SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id='".$this->db_game['game_id']."' AND e.event_starting_block >= ".$from_block_id." AND e.event_starting_block <= ".$to_block_id." ORDER BY e.event_id ASC;";
		$r = $this->blockchain->app->run_query($q);
		$i=0;
		while ($db_event = $r->fetch()) {
			$js .= "if (typeof games[".$game_index."].all_events[".$db_event['event_index']."] == 'undefined') {";
			$js .= "games[".$game_index."].all_events[".$db_event['event_index']."] = new Event(games[".$game_index."], ".$db_event['event_index'].", ".$db_event['event_id'].", ".$db_event['num_voting_options'].', "'.$db_event['vote_effectiveness_function'].'", "'.$db_event['option_block_rule'].'");';
			$js .= "}\n";
			
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
				
				$has_votingaddr = "false";
				
				$js .= "if (typeof games[".$game_index."].all_events[".$db_event['event_index']."].options[".$j."] == 'undefined') {";
				$js .= "games[".$game_index."].all_events[".$db_event['event_index']."].options[".$j."] = new option(games[".$game_index."].all_events[".$db_event['event_index']."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", '".str_replace("'", "", $option['name'])."', 0, $has_votingaddr);\n";
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
	
	public function all_pairs_points_to_index($num_options) {
		$points_to_index = array();
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
	
	public function ensure_events_until_block($block_id) {
		$round_id = $this->block_to_round($block_id);
		$ensured_round = $this->block_to_round((int)$this->db_game['events_until_block']);
		
		if ($round_id > $ensured_round) {
			if ($this->db_game['event_rule'] == "game_definition") {
				if (!empty($this->db_game['module'])) {
					eval('$module = new '.$this->db_game['module'].'GameDefinition($this->blockchain->app);');
					
					$game_starting_round = $this->block_to_round($this->db_game['game_starting_block']);
					
					$q = "SELECT * FROM game_defined_events WHERE game_id='".$this->db_game['game_id']."' ORDER BY event_index DESC LIMIT 1;";
					$r = $this->blockchain->app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$db_last_gde = $r->fetch();
						
						$init_event_index = $db_last_gde['event_index'];
						$from_round = $this->block_to_round($db_last_gde['event_starting_block'])+2-$game_starting_round;
					}
					else {
						$init_event_index = -1;
						$from_round = 1;
					}
					$event_verbatim_vars = $this->blockchain->app->event_verbatim_vars();
					
					$to_round = $round_id - $game_starting_round;
					if (!empty($this->db_game['final_round'])) $to_round = $this->db_game['final_round'];
					$gdes_to_add = $module->events_between_rounds($from_round, $to_round+1, $this->db_game['round_length'], $this->db_game['game_starting_block']);
					$msg = "Adding ".count($gdes_to_add)." events for rounds (".$from_round." : ".($to_round+1).")";
					$this->blockchain->app->log_message($msg);
					
					$i = 0;
					for ($event_index=$init_event_index+1; $event_index<$init_event_index+1+count($gdes_to_add); $event_index++) {
						$this->blockchain->app->check_set_gde($this, $event_index, $gdes_to_add[$i], $event_verbatim_vars);
						$i++;
					}
				}
				
				$q = "SELECT * FROM game_defined_events WHERE game_id='".$this->db_game['game_id']."' AND event_starting_block <= ".$block_id." ORDER BY event_index ASC;";
				$r = $this->blockchain->app->run_query($q);
				
				while ($game_defined_event = $r->fetch()) {
					$qq = "SELECT *  FROM events WHERE game_id='".$this->db_game['game_id']."' AND event_index='".$game_defined_event['event_index']."';";
					$rr = $this->blockchain->app->run_query($qq);
					
					if ($rr->rowCount() == 0) {
						$events = $this->events_by_block($game_defined_event['event_starting_block']);
						$option_offset = 0;
						for ($i=0; $i<count($events); $i++) {
							if ($events[$i]->db_event['event_index'] < $game_defined_event['event_index']) {
								$qqq = "SELECT COUNT(*) FROM options WHERE event_id='".$events[$i]->db_event['event_id']."';";
								$rrr = $this->blockchain->app->run_query($qqq);
								$rrr = $rrr->fetch();
								$offset_amount = $rrr['COUNT(*)'];
								$option_offset += $offset_amount;
							}
						}
						
						$gdo_q = "SELECT * FROM game_defined_options WHERE game_id='".$this->db_game['game_id']."' AND event_index='".$game_defined_event['event_index']."' ORDER BY option_index ASC;";
						$gdo_r = $this->blockchain->app->run_query($gdo_q);
						
						$etype_url_id = $this->blockchain->app->normalize_username($game_defined_event['event_name']);
						
						$qq = "SELECT * FROM event_types WHERE game_id='".$this->db_game['game_id']."' AND url_identifier=".$this->blockchain->app->quote_escape($etype_url_id).";";
						$rr = $this->blockchain->app->run_query($qq);
						
						if ($rr->rowCount() == 0) {
							$qq = "INSERT INTO event_types SET url_identifier=".$this->blockchain->app->quote_escape($etype_url_id).", name=".$this->blockchain->app->quote_escape($game_defined_event['event_name']).", event_winning_rule='game_definition', vote_effectiveness_function='".$this->db_game['default_vote_effectiveness_function']."', max_voting_fraction=".$this->db_game['default_max_voting_fraction'].", num_voting_options='".$gdo_r->rowCount()."', default_option_max_width=".$this->db_game['default_option_max_width'].";";
							$rr = $this->blockchain->app->run_query($qq);
							$event_type_id = $this->blockchain->app->last_insert_id();
							
							$qq = "SELECT * FROM event_types WHERE event_type_id='".$event_type_id."';";
							$rr = $this->blockchain->app->run_query($qq);
							$event_type = $rr->fetch();
						}
						else $event_type = $rr->fetch();
						
						$qq = "INSERT INTO events SET game_id='".$this->db_game['game_id']."', event_type_id='".$event_type['event_type_id']."', event_index='".$game_defined_event['event_index']."', next_event_index='".$game_defined_event['next_event_index']."', event_starting_block='".$game_defined_event['event_starting_block']."', event_final_block='".$game_defined_event['event_final_block']."', event_payout_block='".$game_defined_event['event_payout_block']."', event_name=".$this->blockchain->app->quote_escape($game_defined_event['event_name']).", option_name=".$this->blockchain->app->quote_escape($game_defined_event['option_name']).", option_name_plural=".$this->blockchain->app->quote_escape($game_defined_event['option_name_plural']).", option_max_width=".$event_type['default_option_max_width'];
						if (!empty($game_defined_event['option_block_rule'])) $qq .= ", option_block_rule='".$game_defined_event['option_block_rule']."'";
						$qq .= ";";
						$rr = $this->blockchain->app->run_query($qq);
						$event_id = $this->blockchain->app->last_insert_id();
						
						$option_i = 0;
						while ($game_defined_option = $gdo_r->fetch()) {
							$vote_identifier = $this->blockchain->app->option_index_to_vote_identifier($option_i + $option_offset);
							$qqq = "INSERT INTO options SET event_id='".$event_id."', name=".$this->blockchain->app->quote_escape($game_defined_option['name']).", vote_identifier=".$this->blockchain->app->quote_escape($vote_identifier).", option_index='".($option_i + $option_offset)."'";
							
							if (!empty($game_defined_option['entity_id'])) {
								$qqq .= ", entity_id='".$game_defined_option['entity_id']."'";
								
								$entity = $this->blockchain->app->run_query("SELECT * FROM entities WHERE entity_id='".$game_defined_option['entity_id']."';")->fetch();
								if (!empty($entity['default_image_id'])) $qqq .= ", image_id='".$entity['default_image_id']."'";
							}
							$qqq .= ";";
							$rrr = $this->blockchain->app->run_query($qqq);
							$option_i++;
						}
					}
				}
			}
			else if ($this->db_game['event_rule'] == "entity_type_option_group" || $this->db_game['event_rule'] == "single_event_series" || $this->db_game['event_rule'] == "all_pairs") {
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
				
				if ($this->db_game['event_rule'] == "all_pairs") {
					$all_pairs_points_to_index = $this->all_pairs_points_to_index(count($db_option_entities));
				}
				
				$event_i = 0;
				$round_option_i = 0;
				
				if ($ensured_round > 0) $start_round = $ensured_round+1;
				else $start_round = $this->block_to_round($this->db_game['game_starting_block']);
				
				if ($this->db_game['event_rule'] == "entity_type_option_group") {
					$q = "SELECT COUNT(*) FROM entities WHERE entity_type_id='".$entity_type['entity_type_id']."' ORDER BY entity_id ASC;";
					$r = $this->blockchain->app->run_query($q);
					$num_event_types = $r->fetch();
					$num_event_types = (int) $num_event_types['COUNT(*)'];
					
					for ($i=$start_round; $i<=$round_id; $i++) {
						$round_first_event_i = $this->db_game['events_per_round']*($i-$this->block_to_round($this->db_game['game_starting_block']));
						$offset = $round_first_event_i%$num_event_types;
						$q = "SELECT * FROM entities WHERE entity_type_id='".$entity_type['entity_type_id']."' ORDER BY entity_id ASC LIMIT ".$this->db_game['events_per_round'];
						if ($offset > 0) $q .= " OFFSET ".$offset;
						$q .= ";";
						$r = $this->blockchain->app->run_query($q);
						
						for ($j=0; $j<$this->db_game['events_per_round']; $j++) {
							$event_i = $round_first_event_i+$j;
							$event_entity = $r->fetch();
							$event_type = $this->add_event_type($db_option_entities, $event_entity, $event_i);
							$this->add_event_by_event_type($event_type, $db_option_entities, $option_group, $round_option_i, $event_i, $event_type['name'], $event_entity);
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
					}
				}
			}
			
			$q = "UPDATE games SET events_until_block='".$block_id."' WHERE game_id='".$this->db_game['game_id']."';";
			$r = $this->blockchain->app->run_query($q);
			$this->db_game['events_until_block'] = $block_id;
		}
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
		$qq = "SELECT * FROM event_types WHERE game_id='".$this->db_game['game_id']."' AND url_identifier=".$this->blockchain->app->quote_escape($event_type_identifier).";";
		$rr = $this->blockchain->app->run_query($qq);
		if ($rr->rowCount() > 0) {
			$event_type = $rr->fetch();
		}
		else {
			$qq = "INSERT INTO event_types SET game_id='".$this->db_game['game_id']."', event_winning_rule='max_below_cap', option_group_id='".$this->db_game['option_group_id']."'";
			if ($head_to_head) $qq .= ", primary_entity_id='".$db_option_entities[0]['entity_id']."', secondary_entity_id='".$db_option_entities[1]['entity_id']."'";
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
			$qq = "INSERT INTO events SET game_id='".$this->db_game['game_id']."', event_index='".$event_i."', event_type_id='".$event_type['event_type_id']."', event_starting_block='".$event_starting_block."', event_final_block='".$event_final_block."', event_payout_block='".($this->db_game['default_payout_block_delay']+$event_final_block)."', event_name=".$this->blockchain->app->quote_escape($event_name).", option_name=".$this->blockchain->app->quote_escape($option_group['option_name']).", option_name_plural=".$this->blockchain->app->quote_escape($option_group['option_name_plural']).", option_max_width='".$this->db_game['default_option_max_width']."';";
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
		$q = "SELECT * FROM game_blocks WHERE locally_saved=1 AND game_id='".$this->db_game['game_id']."' ORDER BY block_id DESC LIMIT 1;";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$last_game_block = $r->fetch();
			$load_block_height = $last_game_block['block_id']+1;
		}
		else {
			$last_game_block = false;
			$load_block_height = $this->db_game['game_starting_block'];
		}
		
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
			
			if ($this->db_game['buyin_policy'] != "none") {
				$buyin_q = "SELECT * FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.create_block_id='".$block_height."' AND io.address_id='".$escrow_address['address_id']."' GROUP BY t.transaction_id;";
				$buyin_r = $this->blockchain->app->run_query($buyin_q);
				
				while ($buyin_tx = $buyin_r->fetch()) {
					// Check if buy-in transaction has already been created
					$qq = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.create_transaction_id='".$buyin_tx['transaction_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					if ($rr->rowCount() == 0) {
						if ($this->db_game['sellout_policy'] == "off") {
							$this->process_buyin_transaction($buyin_tx);
						}
						else {
							// Check if any colored coins are being deposited to the escrow address
							// If so, this is a sell-out rather than buy-in tx, so skip the buy-in
							$qq = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.spend_transaction_id='".$buyin_tx['transaction_id']."';";
							$rr = $this->blockchain->app->run_query($qq);
							if ($rr->rowCount() == 0) {
								$this->process_buyin_transaction($buyin_tx);
							}
						}
					}
				}
			}
			
			$keep_looping = true;
			
			do {
				$q = "SELECT * FROM transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=".$this->db_game['game_id']." AND t.block_id='".$block_height."' AND t.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND gio.spend_round_id IS NULL GROUP BY t.transaction_id ORDER BY t.transaction_id ASC;";
				$r = $this->blockchain->app->run_query($q);
				
				if ($r->rowCount() > 0) {
					while ($db_transaction = $r->fetch()) {
						$round_spent = $this->block_to_round($block_height);
						$input_sum = 0;
						$input_colored_sum = 0;
						$crd_sum = 0;
						$cbd_sum = 0;
						
						$qq = "SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id='".$db_transaction['transaction_id']."' AND gio.game_id='".$this->db_game['game_id']."';";
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
						
						$qq = "DELETE gio.* FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.create_transaction_id='".$db_transaction['transaction_id']."';";
						$rr = $this->blockchain->app->run_query($qq);
						
						$qq = "SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$db_transaction['transaction_id']."'";
						if ($this->db_game['sellout_policy'] == "on") $qq .= " AND a.address_id != '".$escrow_address['address_id']."'";
						$qq .= ";";
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
									
									if ($this->db_game['payout_weight'] == "coin_block") $votes = floor($effectiveness_factor*$cbd);
									else if ($this->db_game['payout_weight'] == "coin_round") $votes = floor($effectiveness_factor*$crd);
									else $votes = floor($effectiveness_factor*$colored_amount);
									$qqq .= ", votes='".$votes."'";
								}
							}
							
							$qqq .= ";";
							$rrr = $this->blockchain->app->run_query($qqq);
						}
					}
				}
				else $keep_looping = false;
			}
			while ($keep_looping);
			
			if ($this->db_game['buyin_policy'] != "none") $this->process_sellouts_in_block($block_height);
			
			$q = "UPDATE game_blocks SET locally_saved=1 WHERE game_block_id='".$game_block['game_block_id']."';";
			$r = $this->blockchain->app->run_query($q);
			
			$events = $this->events_by_block($block_height);
			
			for ($i=0; $i<count($events); $i++) {
				$events[$i]->process_option_blocks($game_block, count($events), $events[0]->db_event['event_index']);
				
				/*if ($block_height == $events[$i]->db_event['event_starting_block'] || $block_height == $events[$i]->db_event['event_final_block']) {
					$events[$i]->set_outcome_from_db($block_height, false);
				}*/
			}
			
			$this->ensure_events_until_block($this->blockchain->last_block_id()+1);
			
			$payout_events = $this->events_by_payout_block($block_height);
			
			if (count($payout_events) > 0) {
				eval('$module = new '.$this->db_game['module'].'GameDefinition($this->blockchain->app);');
				
				for ($i=0; $i<count($payout_events); $i++) {
					if (!empty($this->db_game['module'])) {
						try {
							$coin_rpc = new jsonRPCClient('http://'.$this->blockchain->db_blockchain['rpc_username'].':'.$this->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->blockchain->db_blockchain['rpc_port'].'/');
						}
						catch (Exception $e) {
							echo "Error, failed to load RPC connection for ".$this->blockchain->db_blockchain['blockchain_name'].".<br/>\n";
						}
						
						$module->set_event_outcome($this, $coin_rpc, $payout_events[$i]->db_event);
					}
					
					$payout_events[$i]->set_outcome_from_db($block_height, true);
					
					if (!empty($this->db_game['module']) && method_exists($module, "event_index_to_next_event_index")) {
						$event_index = $module->event_index_to_next_event_index($payout_events[$i]->db_event['event_index']);
						$this->set_event_labels_by_gde($event_index);
					}
				}
			}
		}
	}
	
	public function set_event_labels_by_gde($event_index) {
		$q = "SELECT * FROM game_defined_events WHERE game_id='".$this->db_game['game_id']."' AND event_index='".$event_index."';";
		$r = $this->blockchain->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$gde = $r->fetch();
			
			$q = "SELECT * FROM events WHERE game_id='".$this->db_game['game_id']."' AND event_index='".$event_index."';";
			$r = $this->blockchain->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$db_event = $r->fetch();
				
				$q = "UPDATE events SET event_name=".$this->blockchain->app->quote_escape($gde['event_name'])." WHERE event_id='".$db_event['event_id']."';";
				$this->blockchain->app->log_message($q);
				$r = $this->blockchain->app->run_query($q);
				
				$q = "SELECT * FROM game_defined_options gdo JOIN entities e ON gdo.entity_id=e.entity_id WHERE gdo.game_id='".$this->db_game['game_id']."' AND gdo.event_index='".$gde['event_index']."' ORDER BY gdo.option_index ASC;";
				$r = $this->blockchain->app->run_query($q);
				$option_offset = 0;
				
				while ($gdo = $r->fetch()) {
					$qq = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_index ASC LIMIT 1";
					if ($option_offset > 0) $qq .= " OFFSET ".$option_offset;
					$qq .= ";";
					$rr = $this->blockchain->app->run_query($qq);
					
					if ($rr->rowCount() > 0) {
						$db_option = $rr->fetch();
						
						$db_entity = false;
						$db_entity_q = "SELECT * FROM entities WHERE entity_id='".$gdo['entity_id']."';";
						$db_entity_r = $this->blockchain->app->run_query($db_entity_q);
						if ($db_entity_r->rowCount() > 0) $db_entity = $db_entity_r->fetch();
						
						$qq = "UPDATE options SET entity_id='".$gdo['entity_id']."'";
						if ($db_entity && !empty($db_entity['default_image_id'])) $qq .= ", image_id='".$db_entity['default_image_id']."'";
						$qq .= ", name=".$this->blockchain->app->quote_escape($gdo['name'])." WHERE option_id='".$db_option['option_id']."';";
						$rr = $this->blockchain->app->run_query($qq);
						$this->blockchain->app->log_message($qq);
					}
					$option_offset++;
				}
			}
		}
	}
	
	public function add_round_from_rpc($round_id) {
		$block_id = ($round_id-1)*$this->db_game['round_length']+1;
		$events = $this->events_by_block($block_id);
		
		for ($i=0; $i<count($events); $i++) {
			$winning_option_id = false;
			$q = "SELECT * FROM transactions t JOIN transaction_ios i ON i.create_transaction_id=t.transaction_id WHERE t.votebase_event_id='".$events[$i]->db_event['event_id']."' AND t.block_id='".$round_id*$this->db_game['round_length']."' AND t.transaction_desc='votebase' AND i.out_index=1;";
			$r = $this->blockchain->app->run_query($q);
			if ($r->rowCount() == 1) {
				$votebase_transaction = $r->fetch();
				$winning_option_id = $votebase_transaction['option_id'];
			}
			
			$round_voting_stats_all = false;
			list($derived_winning_option_id, $derived_winning_votes) = $events[$i]->determine_winning_option($this->block_to_round($events[$i]->db_event['event_final_block']), $round_voting_stats_all);
			
			$sum_votes = $round_voting_stats_all[0];
			$max_winning_votes = $round_voting_stats_all[1];
			$option_id_to_rank = $round_voting_stats_all[3];
			$rankings = $round_voting_stats_all[2];
			
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
			else $q .= ", derived_winning_option_id=NULL, derived_winning_votes=0";
			
			if ($winning_option_id) $q .= ", winning_option_id='".$winning_option_id."'";
			else $q .= ", winning_option_id=NULL";
			$q .= ", winning_votes='".$winning_votes."'";
			
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
		
		if ($transaction['transaction_desc'] == "votebase") {
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
		$qq = "SELECT gio.*, io.create_block_id, io.spend_block_id, io.io_id, io.spend_status, a.*, op.name AS option_name FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id LEFT JOIN options op ON gio.option_id=op.option_id WHERE gio.game_id='".$this->db_game['game_id']."' AND io.create_transaction_id='".$transaction['transaction_id']."' AND gio.is_coinbase=0 ORDER BY io.out_index ASC;";
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
				if ($transaction['block_id'] > 0) {
					$expected_votes = $output[$this->db_game['payout_weight'].'s_destroyed'];
					$effective_votes = floor($expected_votes*$output['effectiveness_factor']);
				}
				else {
					$ref_block_id = $this->blockchain->last_block_id()+1;
					$temp_event = new Event($this, false, $output['event_id']);
					if ($this->db_game['payout_weight'] == "coin") $expected_votes = $output['colored_amount'];
					else if ($this->db_game['payout_weight'] == "coin_block") $expected_votes = $output['ref_coin_blocks']+($ref_block_id-$output['ref_block_id'])*$output['colored_amount'];
					else $expected_votes = $output['ref_coin_rounds']+($this->block_to_round($ref_block_id)-$output['ref_round_id'])*$output['colored_amount'];
					
					$effectiveness_factor = $temp_event->block_id_to_effectiveness_factor($ref_block_id);
					$effective_votes = floor($expected_votes*$effectiveness_factor);
				}
				$html .= $this->blockchain->app->format_bignum($effective_votes/pow(10,8));
				$html .= " votes (".$this->blockchain->app->format_bignum($expected_votes/pow(10,8)).") for ".$output['option_name'];
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
		return $this->blockchain->explorer_block_list($from_block_id, $to_block_id, $this, false);
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
		return $balance['SUM(gio.colored_amount)'];
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
		if ($this->db_game['sellout_policy'] == "on") {
			$escrow_address = $this->blockchain->create_or_fetch_address($this->db_game['escrow_address'], true, false, false, false, false);
			$escrow_balance = $this->blockchain->address_balance_at_block($escrow_address, $block_id);
			$coins_in_existence = $this->coins_in_existence($block_id);
			
			// Identify sellout transactions paid into escrow & create records in game_sellouts table
			$q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE io.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND t.block_id = ".$block_id." AND io.address_id='".$escrow_address['address_id']."' GROUP BY t.transaction_id;";
			$r = $this->blockchain->app->run_query($q);
			
			while ($transaction = $r->fetch()) {
				$qq = "SELECT * FROM game_sellouts WHERE game_id='".$this->db_game['game_id']."' AND in_tx_hash=".$this->blockchain->app->quote_escape($transaction['tx_hash']).";";
				$rr = $this->blockchain->app->run_query($qq);
				
				if ($rr->rowCount() == 0) {
					$qq = "SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.spend_transaction_id='".$transaction['transaction_id']."' AND gio.game_id='".$this->db_game['game_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					$stats_in = $rr->fetch();
					
					$qq = "SELECT COUNT(*), SUM(gio.colored_amount) FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.create_transaction_id='".$transaction['transaction_id']."' AND gio.game_id='".$this->db_game['game_id']."';";
					$rr = $this->blockchain->app->run_query($qq);
					$stats_out = $rr->fetch();
					
					if ($stats_in['COUNT(*)'] > 0) {
						$exchange_rate = round($coins_in_existence/$escrow_balance*pow(10,6))/pow(10,6);
						$coloredcoins_destroyed = $stats_in['SUM(gio.colored_amount)'] - $stats_out['SUM(gio.colored_amount)'];
						
						$value_destroyed_coins = floor($coloredcoins_destroyed/$exchange_rate);
						
						$qq = "SELECT SUM(amount) FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."' AND address_id='".$escrow_address['address_id']."';";
						$rr = $this->blockchain->app->run_query($qq);
						$coins_into_escrow = $rr->fetch();
						$coins_into_escrow = $coins_into_escrow['SUM(amount)'];
						
						if ($this->blockchain->db_blockchain['url_identifier'] == "bitcoin") $fee_amount = 0.0005;
						else $fee_amount = 0.001;
						$fee_amount = $fee_amount*pow(10,8);
						
						$refund_amount = ($coins_into_escrow+$value_destroyed_coins) - $fee_amount;
						
						$qq = "SELECT SUM(amount) FROM transaction_ios WHERE spend_transaction_id='".$transaction['transaction_id']."';";
						$rr = $this->blockchain->app->run_query($qq);
						$in_io_sum = $rr->fetch();
						$in_io_sum = (int)$in_io_sum['SUM(amount)'];
						
						$qq = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$transaction['transaction_id']."' ORDER BY out_index ASC;";
						$rr = $this->blockchain->app->run_query($qq);
						$num_in_ios = $rr->rowCount();
						$in_io_i=0;
						$refund_sum = 0;
						$out_amounts = array();
						while ($in_io = $rr->fetch()) {
							$refund_amount = floor($refund_amount*$in_io['amount']/$in_io_sum);
							if ($in_io_i == $num_in_ios-1) $refund_amount = $refund_amount - $refund_sum;
							array_push($out_amounts, $refund_amount);
							$refund_sum += $refund_amount;
							$in_io_i++;
						}
						
						$qq = "INSERT INTO game_sellouts SET game_id='".$this->db_game['game_id']."', in_block_id='".$block_id."', in_tx_hash=".$this->blockchain->app->quote_escape($transaction['tx_hash']).", color_amount_in='".$coloredcoins_destroyed."', exchange_rate='".$exchange_rate."', amount_in='".$coins_into_escrow."', amount_out='".($coins_into_escrow+$value_destroyed_coins)."', out_amounts='".implode(",", $out_amounts)."', fee_amount='".$fee_amount."';";
						$rr = $this->blockchain->app->run_query($qq);
					}
				}
			}
			
			// Identify refund transactions made out of escrow and update out_tx_hash in game_sellouts
			$q = "SELECT * FROM game_sellouts WHERE game_id='".$this->db_game['game_id']."' AND out_tx_hash IS NULL;";
			$r = $this->blockchain->app->run_query($q);
			
			while ($pending_sellout = $r->fetch()) {
				$qq = "SELECT * FROM transactions WHERE blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."' AND tx_hash=".$this->blockchain->app->quote_escape($pending_sellout['in_tx_hash']).";";
				$rr = $this->blockchain->app->run_query($qq);
				
				if ($rr->rowCount() == 1) {
					$in_transaction = $rr->fetch();
					
					$matching_tx_id = false;
					$matching_tx_error = false;
					
					$expected_amounts = explode(",", $pending_sellout['out_amounts']);
					$expected_addr_ids = array();
					
					$qq = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$in_transaction['transaction_id']."' ORDER BY out_index ASC;";
					$rr = $this->blockchain->app->run_query($qq);
					
					for ($i=0; $i<count($expected_amounts); $i++) {
						$in_io = $rr->fetch();
						
						$qqq = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE io.amount='".$expected_amounts[$i]."' AND io.address_id='".$in_io['address_id']."';";
						$rrr = $this->blockchain->app->run_query($qqq);
						
						if ($rrr->rowCount() == 1) {
							$matching_tx = $rrr->fetch();
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
						$qq = "UPDATE game_sellouts SET out_tx_hash=".$this->blockchain->app->quote_escape($matching_tx['tx_hash'])." WHERE sellout_id='".$pending_sellout['sellout_id']."';";
						$rr = $this->blockchain->app->run_query($qq);
					}
				}
			}
			
			$qq = "UPDATE game_sellouts s JOIN transactions t ON s.out_tx_hash=t.tx_hash SET s.out_block_id=t.block_id WHERE s.game_id='".$this->db_game['game_id']."' AND t.blockchain_id='".$this->blockchain->db_blockchain['blockchain_id']."';";
			$rr = $this->blockchain->app->run_query($qq);
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
	
	public function check_set_game_definition() {
		$game_definition = $this->blockchain->app->fetch_game_definition($this);
		$definition_hash = $this->blockchain->app->game_definition_hash($this);
		
		$this->blockchain->app->check_set_game_definition($definition_hash, $game_definition);
	}
	
	public function trace_io_to_unspent_io_in_block(&$transaction_io, $block_id) {
		$current_io = $transaction_io;
		$keep_looping = true;
		do {
			if ($current_io['spend_transaction_id'] > 0) {
				$db_transaction_r = $this->blockchain->app->run_query("SELECT * FROM transactions WHERE transaction_id='".$current_io['spend_transaction_id']."';");
				if ($db_transaction_r->rowCount() > 0) {
					$db_transaction = $db_transaction_r->fetch();
					
					if (!empty($db_transaction['block_id']) && $db_transaction['block_id'] <= $block_id) {
						$next_io_q = "SELECT * FROM transaction_ios WHERE create_transaction_id='".$db_transaction['transaction_id']."' AND out_index=0;";
						$next_io_r = $this->blockchain->app->run_query($next_io_q);
						
						if ($next_io_r->rowCount() > 0) {
							$current_io = $next_io_r->fetch();
						}
						else $keep_looping = false;
					}
					else $keep_looping = false;
				}
				else $keep_looping = false;
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
		
		return $current_io['io_id'];
	}
}
?>
