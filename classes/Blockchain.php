<?php
class Blockchain {
	public $db_blockchain;
	public $app;
	
	public function __construct(&$app, $blockchain_id) {
		$this->app = $app;
		$r = $this->app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';");
		if ($r->rowCount() == 1) $this->db_blockchain = $r->fetch();
		else die("Failed to load blockchain #".$blockchain_id);
	}
	
	public function associated_games() {
		$associated_games = array();
		$r = $this->app->run_query("SELECT * FROM games WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';");
		while ($db_game = $r->fetch()) {
			array_push($associated_games, new Game($this, $db_game['game_id']));
		}
		return $associated_games;
	}
	
	public function last_block_id() {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' ORDER BY block_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$block = $r->fetch();
			return $block['block_id'];
		}
		else return 0;
	}
	
	public function last_transaction_id() {
		$q = "SELECT transaction_id FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' ORDER BY transaction_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		if ($r[0] > 0) {} else $r[0] = 0;
		return $r[0];
	}
	
	public function new_nonuser_address() {
		$ref_account = false;
		$address_key = $this->app->new_address_key(false, $ref_account);
		return $db_address['address_id'];
	}
	
	public function currency_id() {
		$q = "SELECT * FROM currencies WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) return $r->fetch()['currency_id'];
		else return false;
	}
	
	public function coind_add_block(&$coin_rpc, $block_hash, $block_height, $headers_only) {
		$start_time = microtime(true);
		$html = "";
		
		$db_block = false;
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_height."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$db_block = $r->fetch();
		}
		else {
			$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_id='".$block_height."', time_created='".time()."', locally_saved=0";
			//$q .= ", effectiveness_factor='".$this->block_id_to_effectiveness_factor($block_height)."'";
			$q .= ";";
			$this->app->run_query($q);
			$internal_block_id = $this->app->last_insert_id();
			$db_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';")->fetch();
		}
		
		if ($db_block['block_hash'] == "") {
			$this->app->run_query("UPDATE blocks SET block_hash='".$block_hash."' WHERE internal_block_id='".$db_block['internal_block_id']."';");
			$html .= $block_height." ";
		}
		
		if ($db_block['locally_saved'] == 0 && !$headers_only) {
			try {
				$lastblock_rpc = $coin_rpc->getblock($block_hash);
			}
			catch (Exception $e) {
				var_dump($e);
				die("RPC failed to get block $block_hash");
			}
			
			if ($db_block['num_transactions'] == "") $this->app->run_query("UPDATE blocks SET num_transactions=".count($lastblock_rpc['tx'])." WHERE internal_block_id=".$db_block['internal_block_id'].";");
			
			echo $block_height." ";
			
			$coins_created = 0;
			
			$start_time = microtime(true);
			$tx_error = false;
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				echo $i."/".count($lastblock_rpc['tx'])." ".$tx_hash." ";
				$successful = true;
				$db_transaction = $this->add_transaction($coin_rpc, $tx_hash, $block_height, true, $successful, $i, false);
				if (!$successful) $tx_error = true;
				echo "\n";
				if ($db_transaction['transaction_desc'] != "transaction") $coins_created += $db_transaction['amount'];
			}
			
			if (!$tx_error) {
				$this->app->run_query("UPDATE blocks SET locally_saved=1 WHERE internal_block_id='".$db_block['internal_block_id']."';");
			}
			$this->app->run_query("UPDATE blocks SET load_time=load_time+".(microtime(true)-$start_time)." WHERE internal_block_id='".$db_block['internal_block_id']."';");
			
			$q = "SELECT * FROM games WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND game_starting_block<='".$block_height."' AND game_status='published';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$db_start_game = $r->fetch();
				$start_game = new Game($this, $db_start_game['game_id']);
				$start_game->start_game();
			}
			echo "Took ".(microtime(true)-$start_time)." sec to add block #".$block_height."<br/>\n";
		}
		
		return $html;
	}
	
	public function add_transaction(&$coin_rpc, $tx_hash, $block_height, $require_inputs, &$successful, $position_in_block, $only_vout) {
		$successful = true;
		$start_time = microtime(true);
		$benchmark_time = $start_time;
		
		if ($only_vout) {
			$error_message = "Downloading vout #".$only_vout." in ".$tx_hash;
			echo $error_message."\n";
		}
		$q = "SELECT * FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND tx_hash='".$tx_hash."';";
		$r = $this->app->run_query($q);
		
		$add_transaction = true;
		if ($r->rowCount() > 0) {
			$unconfirmed_tx = $r->fetch();
			if ($unconfirmed_tx['block_id'] > 0 && $unconfirmed_tx['has_all_outputs'] == 1 && (!$require_inputs || $unconfirmed_tx['has_all_inputs'] == 1)) $add_transaction = false;
			else {
				if ($unconfirmed_tx['blockchain_id'] == $this->db_blockchain['blockchain_id']) {
					$q = "DELETE t.*, io.*, gio.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
					$r = $this->app->run_query($q);
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
						try {
							$rpc_block = $coin_rpc->getblockheader($transaction_rpc['blockhash']);
						}
						catch (Exception $e) {
							$rpc_block = $coin_rpc->getblock($transaction_rpc['blockhash']);
						}
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
				
				$q = "INSERT INTO transactions SET blockchain_id='".$this->db_blockchain['blockchain_id']."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', num_inputs='".count($inputs)."', num_outputs='".count($outputs)."'";
				if ($position_in_block !== false) $q .= ", position_in_block='".$position_in_block."'";
				if ($block_height) {
					if ($transaction_type == "votebase") {
						$vote_identifier = $this->app->addr_text_to_vote_identifier($outputs[1]["scriptPubKey"]["addresses"][0]);
						$option_index = $this->app->vote_identifier_to_option_index($vote_identifier);
						//$option_id = $this->option_index_to_option_id_in_block($option_index, $block_height);
						//$votebase_option = $this->app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';")->fetch();
						//if (!empty($votebase_option['event_id'])) $q .= ", votebase_event_id='".$votebase_option['event_id']."'";
					}
					$q .= ", block_id='".$block_height."'";
					//$q .= ", effectiveness_factor='".$this->block_id_to_effectiveness_factor($block_height)."'";
				}
				$q .= ", time_created='".time()."';";
				$r = $this->app->run_query($q);
				$db_transaction_id = $this->app->last_insert_id();
				
				echo "insert.".(microtime(true)-$benchmark_time)." ";
				$benchmark_time = microtime(true);
				
				$spend_io_ids = array();
				$input_sum = 0;
				$output_sum = 0;
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				if ($transaction_type == "transaction" && $require_inputs) {
					for ($j=0; $j<count($inputs); $j++) {
						$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
						$r = $this->app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$spend_io = $r->fetch();
						}
						else {
							$child_successful = true;
							echo "\n -> $j ";
							$new_tx = $this->add_transaction($coin_rpc, $inputs[$j]["txid"], false, false, $child_successful, false, $inputs[$j]["vout"]);
							$r = $this->app->run_query($q);
							
							if ($r->rowCount() > 0) {
								$spend_io = $r->fetch();
							}
							else {
								$successful = false;
								$error_message = "Failed to create inputs for tx #".$db_transaction_id.", created tx #".$new_tx['transaction_id']." then looked for tx_hash=".$inputs[$j]['txid'].", vout=".$inputs[$j]['vout'];
								$this->app->log($error_message);
								echo $error_message."\n";
							}
						}
						if ($successful) {
							$spend_io_ids[$j] = $spend_io['io_id'];
							
							$input_sum += (int) $spend_io['amount'];
							
							if ($block_height) {
								$this_io_cbd = ($block_height - $spend_io['block_id'])*$spend_io['amount'];
								//$this_io_crd = ($this->block_to_round($block_height) - $spend_io['create_round_id'])*$spend_io['amount'];
								
								$coin_blocks_destroyed += $this_io_cbd;
								//$coin_rounds_destroyed += $this_io_crd;
								
								$r = $this->app->run_query("UPDATE transaction_ios SET coin_blocks_created='".$this_io_cbd."' WHERE io_id='".$spend_io['io_id']."';");
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
						
						$output_address = $this->app->create_or_fetch_address($address_text, true, $coin_rpc, false, true, false);
						
						$q = "INSERT INTO transaction_ios SET spend_status='unspent', blockchain_id='".$this->db_blockchain['blockchain_id']."', out_index='".$j."'";
						if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
						$q .= ", address_id='".$output_address['address_id']."'";
						if ($output_address['option_index'] != "") {
							$q .= ", option_index=".$output_address['option_index'];
							/*if ($block_height) {
								$option_id = $this->option_index_to_option_id_in_block($output_address['option_index'], $block_height);
								if ($option_id) {
									$db_event = $this->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id='".$option_id."';")->fetch();
									$event = new Event($this, $db_event, false);
									$effectiveness_factor = $event->block_id_to_effectiveness_factor($block_height);
									$q .= ", option_id='".$option_id."', event_id='".$db_event['event_id']."', effectiveness_factor='".$effectiveness_factor."'";
								}
							}*/
						}
						$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,8))."'";
						if ($block_height) $q .= ", create_block_id='".$block_height."'";
						$q .= ";";
						$r = $this->app->run_query($q);
						$io_id = $this->app->last_insert_id();
						
						$output_sum += $outputs[$j]["value"]*pow(10,8);
						
						if ($input_sum > 0) $output_cbd = floor($coin_blocks_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
						else $output_cbd = 0;
						//if ($input_sum > 0) $output_crd = floor($coin_rounds_destroyed*($outputs[$j]["value"]*pow(10,8)/$input_sum));
						//else $output_crd = 0;
						
						//if ($this->db_game['payout_weight'] == "coin") $votes = (int) $outputs[$j]["value"]*pow(10,8);
						//else if ($this->db_game['payout_weight'] == "coin_block") $votes = $output_cbd;
						//else if ($this->db_game['payout_weight'] == "coin_round") $votes = $output_crd;
						//else $votes = 0;
						
						/*if ($event) {
							$votes = floor($votes*$event->block_id_to_effectiveness_factor($block_height));
							if ($votes != 0 || $output_cbd != 0 || $output_crd =! 0) {
								$q = "UPDATE transaction_ios SET coin_blocks_destroyed='".$output_cbd."', coin_rounds_destroyed='".$output_crd."', votes='".$votes."' WHERE io_id='".$io_id."';";
								$r = $this->app->run_query($q);
							}
						}*/
					}
					echo "outputs.".(microtime(true)-$benchmark_time)." ";
					$benchmark_time = microtime(true);
					
					if (count($spend_io_ids) > 0) {
						$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_status='spent', spend_transaction_id='".$db_transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$db_transaction_id."', ',')), spend_block_id='".$block_height."' WHERE io_id IN (".implode(",", $spend_io_ids).");";
						$r = $this->app->run_query($q);
					}
					
					$fee_amount = ($input_sum-$output_sum);
					if ($transaction_type != "transaction" || !$require_inputs) $fee_amount = 0;
					
					$q = "UPDATE transactions SET load_time=load_time+".(microtime(true)-$start_time);
					if (!$only_vout) $q .= ", has_all_outputs=1";
					if ($require_inputs || $transaction_type != "transaction") $q .= ", has_all_inputs=1, amount='".$output_sum."', fee_amount='".$fee_amount."'";
					$q .= " WHERE transaction_id='".$db_transaction_id."';";
					$r = $this->app->run_query($q);
					echo "done.".(microtime(true)-$benchmark_time);
					
					$db_transaction = $this->app->run_query("SELECT * FROM transactions WHERE transaction_id='".$db_transaction_id."';")->fetch();
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
				$this->app->log($this->db_blockchain['blockchain_name'].": Failed to fetch transaction ".$tx_hash);
				return false;
			}
		}
	}
	
	public function walletnotify(&$coin_rpc, $tx_hash, $skip_set_site_constant) {
		$start_time = microtime(true);
		if (!$skip_set_site_constant) $this->app->set_site_constant('walletnotify', $tx_hash);
		
		$require_inputs = true;
		$successful = true;
		$this->add_transaction($coin_rpc, $tx_hash, false, $require_inputs, $successful, false, false);
	}
	
	public function sync_coind(&$coin_rpc) {
		$html = "";
		echo "Running Blockchain->sync_coind() for ".$this->db_blockchain['blockchain_name']."\n";
		$last_block_id = $this->last_block_id();
		
		$startblock_q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$last_block_id."';";
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
		$last_block = $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$last_block_id."';")->fetch();
		$block_height = $last_block['block_id'];
		
		if ($last_block['block_hash'] != "") {
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
					$this->coind_add_block($coin_rpc, $rpc_block['hash'], $block_height, true);
					
					$associated_games = $this->associated_games();
					for ($i=0; $i<count($associated_games); $i++) {
						$associated_games[$i]->ensure_events_until_block($block_height+1);
					}
				}
			}
			while ($keep_looping);
		}
	}
	
	public function load_all_block_headers(&$coin_rpc, $required_blocks_only, $max_execution_time) {
		$start_time = microtime(true);
		$html = "";
		
		// Load headers for blocks with NULL block hash
		$keep_looping = true;
		do {
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_hash IS NULL";
			if ($required_blocks_only && $this->db_blockchain['first_required_block'] > 0) $q .= " AND block_id >= ".$this->db_blockchain['first_required_block'];
			$q .= " ORDER BY block_id DESC LIMIT 1;";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$unknown_block = $r->fetch();
				
				$unknown_block_hash = $coin_rpc->getblockhash((int) $unknown_block['block_id']);
				$this->coind_add_block($coin_rpc, $unknown_block_hash, $unknown_block['block_id'], TRUE);
				
				$html .= $unknown_block['block_id']." ";
				if ((microtime(true)-$start_time) >= $max_execution_time) $keep_looping = false;
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
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND locally_saved=0";
			if ($required_blocks_only && $this->db_blockchain['first_required_block'] > 0) $q .= " AND block_id >= ".$this->db_blockchain['first_required_block'];
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
	
	public function load_unconfirmed_transactions(&$coin_rpc, $max_execution_time) {
		$start_time = microtime(true);
		$unconfirmed_txs = $coin_rpc->getrawmempool();
		echo "Looping through ".count($unconfirmed_txs)." unconfirmed transactions.<br/>\n";
		for ($i=0; $i<count($unconfirmed_txs); $i++) {
			$this->walletnotify($coin_rpc, $unconfirmed_txs[$i], TRUE);
			if ($i%100 == 0) echo "$i ";
			if ($max_execution_time && (microtime(true)-$start_time) > $max_execution_time) $i=count($unconfirmed_txs);
		}
		$this->app->set_site_constant('walletnotify', $unconfirmed_txs[count($unconfirmed_txs)-1]);
	}
	
	public function insert_initial_blocks(&$coin_rpc) {
		$r = $this->app->run_query("SELECT MAX(block_id) FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';");
		$db_block_height = $r->fetch();
		$db_block_height = $db_block_height['MAX(block_id)'];
		
		$getinfo = $coin_rpc->getinfo();
		
		$html = "Inserting blocks ".($db_block_height+1)." to ".$getinfo['blocks']."<br/>\n";
		
		$start_insert = "INSERT INTO blocks (blockchain_id, block_id, time_created) VALUES ";
		$modulo = 0;
		$q = $start_insert;
		for ($block_i=$db_block_height+1; $block_i<$getinfo['blocks']; $block_i++) {
			if ($modulo == 1000) {
				$q = substr($q, 0, strlen($q)-2).";";
				$this->app->run_query($q);
				$modulo = 0;
				$q = $start_insert;
				$html .= ". ";
			}
			else $modulo++;
		
			$q .= "('".$this->db_blockchain['blockchain_id']."', '".$block_i."', '".time()."'), ";
		}
		if ($modulo > 0) {
			$q = substr($q, 0, strlen($q)-2).";";
			$this->app->run_query($q);
			$html .= ". ";
		}
		return $html;
	}
	
	public function delete_blocks_from_height($block_height) {
		echo "deleting from block #".$block_height." and up.<br/>\n";
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id IS NULL;");
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.create_block_id >= ".$block_height.";");
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.create_block_id IS NULL;");
		$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET gio.spend_round_id=NULL, io.coin_blocks_created=0, gio.coin_rounds_created=0, gio.votes=0, io.spend_transaction_id=NULL, io.spend_count=NULL, io.spend_status='unspent', gio.payout_game_io_id=NULL WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.spend_block_id >= ".$block_height.";");
		
		$this->app->run_query("DELETE FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id >= ".$block_height.";");
		
		$round_id = $this->block_to_round($block_height);
		$this->app->run_query("DELETE eo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id JOIN games g ON eo.game_id=g.game_id WHERE g.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND eo.round_id >= ".$round_id.";");
		$this->app->run_query("DELETE eoo.* FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id JOIN games g ON eo.game_id=g.game_id WHERE g.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND eoo.round_id >= ".$round_id.";");
		
		$this->app->run_query("UPDATE strategy_round_allocations sra JOIN user_strategies us ON us.strategy_id=sra.strategy_id JOIN games g ON us.game_id=g.game_id SET sra.applied=0 WHERE g.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND sra.round_id >= ".$round_id.";");
		
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
		
		$output_address = $this->app->create_or_fetch_address("genesis_address", true, false, false, false, false);
		
		$this->app->run_query("DELETE t.*, io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.tx_hash='".$tx_hash."' AND t.blockchain_id='".$this->db_blockchain['blockchain_id']."';");
		
		$q = "INSERT INTO transactions SET blockchain_id='".$this->db_blockchain['blockchain_id']."', amount='".$this->db_blockchain['initial_pow_reward']."', transaction_desc='coinbase', tx_hash='".$tx_hash."', block_id='0', time_created='".time()."', has_all_inputs=1, has_all_outputs=1;";
		$this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		$q = "INSERT INTO transaction_ios SET spend_status='unspent', blockchain_id='".$this->db_blockchain['blockchain_id']."', user_id=NULL, address_id='".$output_address['address_id']."'";
		$q .= ", create_transaction_id='".$transaction_id."', amount='".$this->db_blockchain['initial_pow_reward']."', create_block_id='0';";
		$r = $this->app->run_query($q);
		
		$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_hash='".$genesis_hash."', block_id='0', time_created='".time()."', locally_saved=1;";
		$r = $this->app->run_query($q);
		
		$html .= "Added the genesis transaction!<br/>\n";
		
		$returnvals['log_text'] = $html;
		$returnvals['genesis_hash'] = $genesis_hash;
		$returnvals['nextblockhash'] = $rpc_block->json_obj['nextblockhash'];
		return $returnvals;
	}
	
	public function set_first_required_block(&$coin_rpc) {
		if ($this->db_blockchain['first_required_block'] == "") {
			$first_required_block = false;
			if ($coin_rpc) {
				$info = $coin_rpc->getinfo();
				$first_required_block = (int) $info['blocks'];
			}
			
			$q = "SELECT MIN(game_starting_block) FROM games WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
			$r = $this->app->run_query($q);
			$min_starting_block = (int) $r->fetch()['MIN(game_starting_block)'];
			
			if ($min_starting_block > 0 && (!$first_required_block || $min_starting_block < $first_required_block)) $first_required_block = $min_starting_block;
			
			$q = "UPDATE blockchains SET first_required_block='".$first_required_block."' WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
			$this->app->run_query($q);
			
			$q = "UPDATE games SET game_starting_block='".$first_required_block."' WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
			$this->app->run_query($q);
			
			$this->db_blockchain['first_required_block'] = $first_required_block;
		}
	}
	
	public function sync_initial($from_block_id) {
		$html = "";
		$start_time = microtime(true);
		$coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->db_blockchain['rpc_port'].'/');
		
		$this->set_first_required_block($coin_rpc);
		
		$blocks = array();
		$transactions = array();
		$block_height = 0;
		
		$keep_looping = true;
		
		$new_transaction_count = 0;
		
		if (!empty($from_block_id)) {
			if (!empty($from_block_id)) {
				$block_height = $from_block_id-1;
			}
			
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_height."';";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$db_prev_block = $r->fetch();
				$temp_block = $coin_rpc->getblock($db_prev_block['block_hash']);
				$current_hash = $temp_block['nextblockhash'];
				$this->delete_blocks_from_height($block_height+1);
			}
			else die("Error, that block was not found (".$r->rowCount().").");
		}
		else {
			$this->reset_blockchain();
			
			$returnvals = $this->add_genesis_block($coin_rpc);
			$current_hash = $returnvals['nextblockhash'];
		}
		
		$html .= $this->insert_initial_blocks($coin_rpc);
		$last_block_id = $this->last_block_id();
		$this->set_block_hash_by_height($coin_rpc, $last_block_id);
		
		$html .= "<br/>Finished inserting blocks at ".(microtime(true) - $start_time)." sec<br/>\n";
		
		return $html;
	}
	
	public function reset_blockchain() {
		$associated_games = $this->associated_games();
		for ($i=0; $i<count($associated_games); $i++) {
			$associated_games[$i]->delete_reset_game('reset');
		}
		
		$q = "DELETE FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM transaction_ios WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		
		$q = "DELETE FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
	}
	
	public function block_next_prev_links($block, $explore_mode) {
		$html = "";
		$prev_link_target = false;
		if ($explore_mode == "unconfirmed") $prev_link_target = "blocks/".$this->last_block_id();
		else if ($block['block_id'] > 1) $prev_link_target = "blocks/".($block['block_id']-1);
		if ($prev_link_target) $html .= '<a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/'.$prev_link_target.'" style="margin-right: 30px;">&larr; Previous Block</a>';
		
		$next_link_target = false;
		if ($explore_mode == "unconfirmed") {}
		else if ($block['block_id'] == $this->last_block_id()) $next_link_target = "transactions/unconfirmed";
		else if ($block['block_id'] < $this->last_block_id()) $next_link_target = "blocks/".($block['block_id']+1);
		if ($next_link_target) $html .= '<a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/'.$next_link_target.'">Next Block &rarr;</a>';
		
		return $html;
	}
	
	public function render_transaction($transaction, $selected_address_id) {
		$html = "";
		$html .= '<div class="row bordered_row"><div class="col-md-12">';
		
		if (!empty($transaction['block_id'])) {
			if ($transaction['position_in_block'] == "") $html .= "Confirmed";
			else $html .= "#".(int)$transaction['position_in_block'];
			$html .= " in block <a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>, ";
		}
		$html .= (int)$transaction['num_inputs']." inputs, ".(int)$transaction['num_outputs']." outputs, ".$this->app->format_bignum($transaction['amount']/pow(10,8))." coins";
		
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase" && $transaction['transaction_desc'] != "votebase") {
			$fee_disp = $this->app->format_bignum($transaction_fee/pow(10,8));
			$html .= ", ".$fee_disp;
			$html .= " tx fee";
		}
		if (empty($transaction['block_id'])) $html .= ", not yet confirmed";
		$html .= '. <br/><a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a>';
		
		$html .= '</div><div class="col-md-6">';
		
		if ($transaction['transaction_desc'] == "giveaway") {
			$q = "SELECT * FROM game_giveaways WHERE transaction_id='".$transaction['transaction_id']."';";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$giveaway = $r->fetch();
				$html .= $this->app->format_bignum($giveaway['amount']/pow(10,8))." coins were given to a player for joining.";
			}
		}
		else if ($transaction['transaction_desc'] == "votebase") {
			$payout_disp = round($transaction['amount']/pow(10,8), 2);
			$html .= "Voting Payout&nbsp;&nbsp;".$payout_disp." ";
			if ($payout_disp == '1') $html .= "coin";
			else $html .= "coins";
		}
		else if ($transaction['transaction_desc'] == "coinbase") {
			$html .= "Miner found a block.";
		}
		else {
			$qq = "SELECT * FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_transaction_id='".$transaction['transaction_id']."' ORDER BY i.amount DESC;";
			$rr = $this->app->run_query($qq);
			$input_sum = 0;
			while ($input = $rr->fetch()) {
				$amount_disp = $this->app->format_bignum($input['amount']/pow(10,8));
				$html .= '<a class="display_address" style="';
				if ($input['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
				$html .= '" href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$input['address'].'">'.$input['address'].'</a>';
				$html .= "<br/>\n";
				$html .= $amount_disp." ";
				if ($amount_disp == '1') $html .= $this->db_blockchain['coin_name'];
				else $html .= $this->db_blockchain['coin_name_plural'];
				
				$html .= "<br/>\n";
				$input_sum += $input['amount'];
			}
		}
		$html .= '</div><div class="col-md-6">';
		$qq = "SELECT i.*, a.* FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.create_transaction_id='".$transaction['transaction_id']."' ORDER BY i.out_index ASC;";
		$rr = $this->app->run_query($qq);
		$output_sum = 0;
		while ($output = $rr->fetch()) {
			$html .= '<a class="display_address" style="';
			if ($output['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= '" href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$output['address'].'">'.$output['address']."</a><br/>\n";
			
			$amount_disp = $this->app->format_bignum($output['amount']/pow(10,8));
			$html .= $amount_disp." ";
			if ($amount_disp == '1') $html .= $this->db_blockchain['coin_name'];
			else $html .= $this->db_blockchain['coin_name_plural'];
			
			$html .= "<br/>\n";
			$output_sum += $output['amount'];
		}
		$html .= '</div></div>'."\n";
		
		return $html;
	}
	
	public function explorer_block_list($from_block_id, $to_block_id, &$game) {
		$html = "";
		$q = "SELECT * FROM blocks b";
		if ($game) $q .= " JOIN game_blocks gb ON b.internal_block_id=gb.internal_block_id";
		$q .= " WHERE b.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND b.block_id >= ".$from_block_id." AND b.block_id <= ".$to_block_id;
		if ($game) $q .= " AND gb.game_id=".$game->db_game['game_id'];
		$q .= " ORDER BY b.block_id DESC;";
		$r = $this->app->run_query($q);
		while ($block = $r->fetch()) {
			if ($game) list($num_trans, $block_sum) = $game->block_stats($block);
			else list($num_trans, $block_sum) = $this->block_stats($block);
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-3\">";
			$html .= "<a href=\"/explorer/";
			if ($game) $html .= "games/".$game->db_game['url_identifier'];
			else $html .= "blockchains/".$this->db_blockchain['url_identifier'];
			$html .= "/blocks/".$block['block_id']."\">Block #".$block['block_id']."</a>";
			if ($block['locally_saved'] == 0 && $block['block_id'] >= $this->db_blockchain['first_required_block']) $html .= "&nbsp;(Pending)";
			$html .= "</div>";
			$html .= "<div class=\"col-sm-2";
			$block_loading = false;
			if ($block['num_transactions'] > 0 && $block['num_transactions'] != $num_trans) {
				$block_loading = true;
				$html .= " redtext";
			}
			$html .= "\" style=\"text-align: right;\">".number_format($num_trans);
			if ($block_loading) $html .= "/".number_format($block['num_transactions']);
			$html .= "&nbsp;transactions</div>\n";
			$html .= "<div class=\"col-sm-2\" style=\"text-align: right;\">".$this->app->format_bignum($block_sum/pow(10,8))."&nbsp;";
			if ($game) $html .= $game->db_game['coin_name_plural'];
			else $html .= $this->db_blockchain['coin_name_plural'];
			$html .= "</div>\n";
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function block_stats($block) {
		$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block['block_id']."' AND amount > 0;";
		$r = $this->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		return array($r[0], $r[1]);
	}
	
	public function set_block_hash_by_height(&$coin_rpc, $block_height) {
		$block_hash = $coin_rpc->getblockhash((int) $block_height);
		$q = "UPDATE blocks SET block_hash=".$this->app->quote_escape($block_hash)." WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_height."';";
		$r = $this->app->run_query($q);
	}
	
	public function address_balance_at_block($db_address, $block_id) {
		$q = "SELECT SUM(amount) FROM transaction_ios WHERE address_id='".$db_address['address_id']."' AND create_block_id <= ".$block_id." AND ((spend_block_id IS NULL AND spend_status='unspent') OR spend_block_id>".$block_id.");";
		$r = $this->app->run_query($q);
		$balance = $r->fetch();
		return (int)$balance['SUM(amount)'];
	}
}
?>