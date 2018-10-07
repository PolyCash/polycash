<?php
class Blockchain {
	public $db_blockchain;
	public $app;
	
	public function __construct(&$app, $blockchain_id) {
		$this->app = $app;
		$r = $this->app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';");
		if ($r->rowCount() == 1) $this->db_blockchain = $r->fetch();
		else {
			throw new Exception("Failed to load blockchain #".$blockchain_id);
		}
		if (empty($this->db_blockchain['first_required_block'])) {
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				if (!empty($this->db_blockchain['rpc_username']) && !empty($this->db_blockchain['rpc_password'])) {
					try {
						$coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->db_blockchain['rpc_port'].'/');
						$this->set_first_required_block($coin_rpc);
					}
					catch (Exception $e) {}
				}
			}
			else {
				$coin_rpc = false;
				$this->set_first_required_block($coin_rpc);
			}
		}
		if (!empty($this->db_blockchain['authoritative_issuer_id'])) {
			$this->authoritative_issuer = $this->app->run_query("SELECT * FROM card_issuers WHERE issuer_id='".$this->db_blockchain['authoritative_issuer_id']."';")->fetch();
		}
	}
	
	public function associated_games($filter_statuses) {
		$associated_games = array();
		$q = "SELECT * FROM games WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."'";
		if (!empty($filter_statuses)) $q .= " AND game_status IN ('".implode("','", $filter_statuses)."')";
		$q .= ";";
		$r = $this->app->run_query($q);
		while ($db_game = $r->fetch()) {
			array_push($associated_games, new Game($this, $db_game['game_id']));
		}
		return $associated_games;
	}
	
	public function fetch_block_by_id($block_id) {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) return $r->fetch();
		else return false;
	}
	
	public function last_block_id() {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' ORDER BY block_id DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$block = $r->fetch();
			return $block['block_id'];
		}
		else return false;
	}
	
	public function last_complete_block_id() {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."'";
		if (!empty($this->db_blockchain['first_required_block'])) $q .= " AND block_id >= ".$this->db_blockchain['first_required_block'];
		$q .= " AND locally_saved=0 ORDER BY block_id ASC LIMIT 1;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$block = $r->fetch();
			return $block['block_id']-1;
		}
		else {
			$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."'";
			if (!empty($this->db_blockchain['first_required_block'])) $q .= " AND block_id >= ".$this->db_blockchain['first_required_block'];
			$q .= " AND locally_saved=1 ORDER BY block_id DESC LIMIT 1;";
			$r = $this->app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$block = $r->fetch();
				return $block['block_id'];
			}
			else return 0;
		}
	}
	
	public function most_recently_loaded_block() {
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND locally_saved=1 AND time_loaded IS NOT NULL ORDER BY time_loaded DESC LIMIT 1;";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_block = $r->fetch();
			return $db_block;
		}
		else return false;
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
	
	public function web_api_add_block(&$db_block, &$api_block, $headers_only, $print_debug) {
		$start_time = microtime(true);
		$html = "";
		
		if (empty($api_block)) {
			$api_response = $this->web_api_fetch_block($db_block['block_id']);
			$api_block = get_object_vars($api_response['blocks'][0]);
		}
		
		if (!empty($api_block['block_hash'])) {
			if (empty($db_block['internal_block_id'])) {
				$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_hash=".$this->app->quote_escape($api_block['block_hash']).", block_id='".$db_block['block_id']."', time_created='".time()."', locally_saved=0;";
				$r = $this->app->run_query($q);
				$internal_block_id = $this->app->last_insert_id();
				
				$q = "SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';";
				$r = $this->app->run_query($q);
				$db_block = $r->fetch();
			}
			
			if (empty($db_block['block_hash'])) {
				$q = "UPDATE blocks SET block_hash='".$api_block['block_hash']."' WHERE internal_block_id='".$db_block['internal_block_id']."';";
				$this->app->run_query($q);
			}
			
			if ($db_block['locally_saved'] == 0 && !$headers_only) {
				if ($db_block['num_transactions'] == "") $this->app->run_query("UPDATE blocks SET time_mined='".$api_block['time_mined']."', num_transactions=".count($api_block['transactions'])." WHERE internal_block_id=".$db_block['internal_block_id'].";");
				
				$coins_created = 0;
				
				$tx_error = false;
				
				for ($i=0; $i<count($api_block['transactions']); $i++) {
					$tx = get_object_vars($api_block['transactions'][$i]);
					$tx_hash = $tx['tx_hash'];
					
					$q = "SELECT * FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND tx_hash=".$this->app->quote_escape($tx_hash).";";
					$r = $this->app->run_query($q);
					
					if ($r->rowCount() == 0) {
						$transaction_id = $this->add_transaction_from_web_api($db_block['block_id'], $tx);
					}
					
					$coin_rpc = false;
					$successful = true;
					$db_transaction = $this->add_transaction($coin_rpc, $tx_hash, $db_block['block_id'], true, $successful, $i, false, $print_debug);
					
					if ($db_transaction['transaction_desc'] != "transaction") $coins_created += $db_transaction['amount'];
				}
				
				if (!$tx_error) {
					$this->app->run_query("UPDATE blocks SET locally_saved=1, time_loaded='".time()."' WHERE internal_block_id='".$db_block['internal_block_id']."';");
				}
				$this->app->run_query("UPDATE blocks SET load_time=load_time+".(microtime(true)-$start_time)." WHERE internal_block_id='".$db_block['internal_block_id']."';");
				
				$html .= "Took ".(microtime(true)-$start_time)." sec to add block #".$db_block['block_id']."<br/>\n";
			}
		}
		
		return $html;
	}
	
	public function coind_add_block(&$coin_rpc, $block_hash, $block_height, $headers_only, $print_debug) {
		$start_time = microtime(true);
		$html = "";
		
		$db_block = false;
		$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_height."' ORDER BY internal_block_id ASC;";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$db_block = $r->fetch();
		}
		else {
			$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_hash=".$this->app->quote_escape($block_hash).", block_id='".$block_height."', time_created='".time()."', locally_saved=0;";
			$this->app->run_query($q);
			$internal_block_id = $this->app->last_insert_id();
			$db_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';")->fetch();
		}
		
		if (empty($db_block['block_hash'])) {
			$q = "UPDATE blocks SET block_hash='".$block_hash."' WHERE internal_block_id='".$db_block['internal_block_id']."';";
			$this->app->run_query($q);
			$html .= $block_height." ";
		}
		
		if ($coin_rpc && $db_block['locally_saved'] == 0 && !$headers_only) {
			try {
				$lastblock_rpc = $coin_rpc->getblock($block_hash);
			}
			catch (Exception $e) {
				var_dump($e);
				die("RPC failed to get block $block_hash");
			}
			
			if ($db_block['num_transactions'] == "") $this->app->run_query("UPDATE blocks SET time_mined='".$lastblock_rpc['time']."', num_transactions=".count($lastblock_rpc['tx'])." WHERE internal_block_id=".$db_block['internal_block_id'].";");
			
			$html .= $block_height." ";
			
			$coins_created = 0;
			
			$start_time = microtime(true);
			$tx_error = false;
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				$html .= $i."/".count($lastblock_rpc['tx'])." ".$tx_hash." ";
				$successful = true;
				$db_transaction = $this->add_transaction($coin_rpc, $tx_hash, $block_height, true, $successful, $i, false, $print_debug);
				if (!$successful) {
					$tx_error = true;
					$i = count($lastblock_rpc['tx']);
					$html .= "failed to add tx ".$tx_hash."<br/>\n";
				}
				$html .= "\n";
				if ($db_transaction['transaction_desc'] != "transaction") $coins_created += $db_transaction['amount'];
			}
			
			$q = "UPDATE blocks SET ";
			if (!$tx_error) {
				$q .= "locally_saved=1, time_loaded='".time()."', ";
			}
			$q .= "load_time=load_time+".(microtime(true)-$start_time)." WHERE internal_block_id='".$db_block['internal_block_id']."';";
			$this->app->run_query($q);
			
			$this->try_start_games($block_height);
			$html .= "Took ".(microtime(true)-$start_time)." sec to add block #".$block_height."<br/>\n";
		}
		
		return $html;
	}
	
	public function try_start_games($block_height) {
		$q = "SELECT * FROM games WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND game_starting_block<='".$block_height."' AND game_status='published';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_start_game = $r->fetch();
			$start_game = new Game($this, $db_start_game['game_id']);
			$start_game->start_game();
		}
	}
	
	public function add_transaction(&$coin_rpc, $tx_hash, $block_height, $require_inputs, &$successful, $position_in_block, $only_vout, $show_debug) {
		$successful = true;
		$start_time = microtime(true);
		$benchmark_time = $start_time;
		
		$add_transaction = true;
		
		$q = "SELECT * FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND tx_hash='".$tx_hash."';";
		$r = $this->app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$unconfirmed_tx = $r->fetch();
			
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				$q = "DELETE t.*, io.*, gio.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';";
				$r = $this->app->run_query($q);
			}
			else $db_transaction_id = $unconfirmed_tx['transaction_id'];
			
			$benchmark_time = microtime(true);
		}
		else if ($this->db_blockchain['p2p_mode'] != "rpc") $add_transaction = false;
		
		if ($add_transaction) {
			try {
				$benchmark_time = microtime(true);
				
				$spend_io_ids = array();
				$input_sum = 0;
				$coin_blocks_destroyed = 0;
				$coin_rounds_destroyed = 0;
				
				if ($this->db_blockchain['p2p_mode'] != "rpc") {
					$transaction_type = $unconfirmed_tx['transaction_desc'];
					
					if ($block_height !== false && $unconfirmed_tx['block_id'] !== "") {
						$q = "UPDATE transactions SET position_in_block='".$position_in_block."', block_id='".$block_height."' WHERE transaction_id='".$unconfirmed_tx['transaction_id']."';";
						$r = $this->app->run_query($q);
					}
					
					$q = "SELECT * FROM transaction_ios WHERE spend_transaction_id='".$db_transaction_id."';";
					$r = $this->app->run_query($q);
					
					while ($spend_io = $r->fetch()) {
						$spend_io_ids[count($spend_io_ids)] = $spend_io['io_id'];
						$input_sum += $spend_io['amount'];
						
						if ($block_height !== false) {
							$this_io_cbd = ($block_height - $spend_io['create_block_id'])*$spend_io['amount'];
							$coin_blocks_destroyed += $this_io_cbd;
							$this->app->run_query("UPDATE transaction_ios SET spend_block_id='".$block_height."', coin_blocks_created='".$this_io_cbd."' WHERE io_id='".$spend_io['io_id']."';");
						}
					}
				}
				else {
					if ($block_height !== false) {
						$raw_transaction = $coin_rpc->getrawtransaction($tx_hash);
						$transaction_rpc = $coin_rpc->decoderawtransaction($raw_transaction);
					}
					else {
						$transaction_rpc = $coin_rpc->getrawtransaction($tx_hash, 1);
						if (!empty($transaction_rpc['blockhash'])) {
							if ($this->db_blockchain['supports_getblockheader'] == 1) {
								$rpc_block = $coin_rpc->getblockheader($transaction_rpc['blockhash']);
							}
							else {
								$rpc_block = $coin_rpc->getblock($transaction_rpc['blockhash']);
							}
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
					
					$q = "INSERT INTO transactions SET blockchain_id='".$this->db_blockchain['blockchain_id']."', transaction_desc='".$transaction_type."', tx_hash='".$tx_hash."', num_inputs='".count($inputs)."', num_outputs='".count($outputs)."'";
					if ($position_in_block !== false) $q .= ", position_in_block='".$position_in_block."'";
					if ($block_height !== false) $q .= ", block_id='".$block_height."'";
					$q .= ", time_created='".time()."';";
					$r = $this->app->run_query($q);
					$db_transaction_id = $this->app->last_insert_id();
					
					$benchmark_time = microtime(true);
					
					if ($transaction_type == "transaction" && $require_inputs) {
						for ($j=0; $j<count($inputs); $j++) {
							$q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND t.tx_hash='".$inputs[$j]["txid"]."' AND i.out_index='".$inputs[$j]["vout"]."';";
							$r = $this->app->run_query($q);
							
							if ($r->rowCount() > 0) {
								$spend_io = $r->fetch();
							}
							else {
								$child_successful = true;
								if ($show_debug) echo "\n -> $j ";
								$new_tx = $this->add_transaction($coin_rpc, $inputs[$j]["txid"], false, false, $child_successful, false, $inputs[$j]["vout"], $show_debug);
								$r = $this->app->run_query($q);
								
								if ($r->rowCount() > 0) {
									$spend_io = $r->fetch();
								}
								else {
									$successful = false;
									$error_message = "Failed to create inputs for tx #".$db_transaction_id.", created tx #".$new_tx['transaction_id']." then looked for tx_hash=".$inputs[$j]['txid'].", vout=".$inputs[$j]['vout'];
									$this->app->log_message($error_message);
									if ($show_debug) echo $error_message."\n";
								}
							}
							if ($successful) {
								$spend_io_ids[$j] = $spend_io['io_id'];
								
								$input_sum += (int) $spend_io['amount'];
								
								if ($block_height !== false) {
									$this_io_cbd = ($block_height - $spend_io['block_id'])*$spend_io['amount'];
									$coin_blocks_destroyed += $this_io_cbd;
									$r = $this->app->run_query("UPDATE transaction_ios SET coin_blocks_created='".$this_io_cbd."' WHERE io_id='".$spend_io['io_id']."';");
								}
							}
						}
					}
				}
				$benchmark_time = microtime(true);
				
				if ($this->db_blockchain['p2p_mode'] != "rpc" || $successful) {
					$output_io_ids = array();
					$output_io_indices = array();
					$output_io_address_ids = array();
					$output_is_destroy = array();
					$output_sum = 0;
					$output_destroy_sum = 0;
					$last_nondestroy_output_index = false;
					
					if ($this->db_blockchain['p2p_mode'] != "rpc") {
						$outputs = array();
						
						if ($block_height !== false) {
							$q = "UPDATE transaction_ios SET spend_status='unspent', create_block_id='".$block_height."' WHERE create_transaction_id='".$unconfirmed_tx['transaction_id']."';";
							$r = $this->app->run_query($q);
						}
						
						$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$unconfirmed_tx['transaction_id']."' ORDER BY io.out_index ASC;";
						$r = $this->app->run_query($q);
						
						$j=0;
						while ($out_io = $r->fetch()) {
							$outputs[$j] = array("value"=>$out_io['amount']/pow(10,$this->db_blockchain['decimal_places']));
							
							$output_address = $this->create_or_fetch_address($out_io['address'], true, false, false, true, false, false);
							$output_io_indices[$j] = $output_address['option_index'];
							
							$output_io_ids[$j] = $out_io['io_id'];
							$output_io_address_ids[$j] = $output_address['address_id'];
							$output_is_destroy[$j] = $out_io['is_destroy_address'];
							
							$output_sum += $out_io['amount'];
							if ($out_io['is_destroy_address'] == 1) {
								$output_destroy_sum += $out_io['amount'];
							}
							else $last_nondestroy_output_index = $j;
							$j++;
						}
					}
					else {
						$from_vout = 0;
						$to_vout = count($outputs)-1;
						if ($only_vout) {
							$from_vout = $only_vout;
							$to_vout = $only_vout;
						}
						
						for ($j=$from_vout; $j<=$to_vout; $j++) {
							$option_id = false;
							$event = false;
							
							if (!empty($outputs[$j]["scriptPubKey"]) && (!empty($outputs[$j]["scriptPubKey"]["addresses"]) || !empty($outputs[$j]["scriptPubKey"]["hex"]))) {
								$script_type = $outputs[$j]["scriptPubKey"]["type"];
								
								if (!empty($outputs[$j]["scriptPubKey"]["addresses"])) $address_text = $outputs[$j]["scriptPubKey"]["addresses"][0];
								else $address_text = $outputs[$j]["scriptPubKey"]["hex"];
								if (strlen($address_text) > 50) $address_text = substr($address_text, 0, 50);
								
								$output_address = $this->create_or_fetch_address($address_text, true, $coin_rpc, false, true, false, false);
								
								$q = "INSERT INTO transaction_ios SET spend_status='";
								if ($block_height !== false) $q .= "unspent";
								else $q .= "unconfirmed";
								$q .= "', blockchain_id='".$this->db_blockchain['blockchain_id']."', script_type='".$script_type."', out_index='".$j."'";
								if ($output_address['user_id'] > 0) $q .= ", user_id='".$output_address['user_id']."'";
								$q .= ", address_id='".$output_address['address_id']."'";
								
								if ($output_address['option_index'] != "") {
									$q .= ", option_index=".$output_address['option_index'];
									$output_io_indices[$j] = $output_address['option_index'];
								}
								else $output_io_indices[$j] = false;
								
								$q .= ", create_transaction_id='".$db_transaction_id."', amount='".($outputs[$j]["value"]*pow(10,$this->db_blockchain['decimal_places']))."'";
								if ($block_height !== false) $q .= ", create_block_id='".$block_height."'";
								$q .= ";";
								$r = $this->app->run_query($q);
								$io_id = $this->app->last_insert_id();
								
								$output_io_ids[$j] = $io_id;
								$output_io_address_ids[$j] = $output_address['address_id'];
								$output_is_destroy[$j] = $output_address['is_destroy_address'];
								
								$output_sum += $outputs[$j]["value"]*pow(10,$this->db_blockchain['decimal_places']);
								if ($output_address['is_destroy_address'] == 1) {
									$output_destroy_sum += $outputs[$j]["value"]*pow(10,$this->db_blockchain['decimal_places']);
								}
								else $last_nondestroy_output_index = $j;
							}
						}
					}
					
					if (count($spend_io_ids) > 0 && $block_height === false) {
						$ref_block_id = $this->last_block_id()+1;
						
						$q = "SELECT g.game_id, SUM(gio.colored_amount) AS colored_amount_sum, SUM(gio.colored_amount*(".$ref_block_id."-io.create_block_id)) AS ref_coin_block_sum FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN games g ON gio.game_id=g.game_id WHERE io.io_id IN (".implode(",", $spend_io_ids).") GROUP BY gio.game_id ORDER BY g.game_id ASC;";
						$r = $this->app->run_query($q);
						
						while ($db_color_game = $r->fetch()) {
							$color_game = new Game($this, $db_color_game['game_id']);
							$escrow_address = $this->create_or_fetch_address($color_game->db_game['escrow_address'], true, false, false, false, false, false);
							$color_amount = $db_color_game['colored_amount_sum'];
							$coin_blocks = $db_color_game['ref_coin_block_sum'];
							$color_amount_sum = 0;
							$coin_block_sum = 0;
							
							$ref_round_id = $color_game->block_to_round($ref_block_id);
							
							$qq = "SELECT SUM(colored_amount*(".$ref_round_id."-create_round_id)) AS ref_coin_round_sum FROM transaction_game_ios WHERE io_id IN (".implode(",", $spend_io_ids).");";
							$rr = $this->app->run_query($qq);
							$coin_rounds = $rr->fetch();
							$coin_rounds = $coin_rounds['ref_coin_round_sum'];
							
							$coin_round_sum = 0;
							$ref_round_id = $color_game->block_to_round($ref_block_id);
							
							$destroy_color_amount = ceil($color_amount*$output_destroy_sum/$output_sum);
							$nondestroy_color_amount = $color_amount-$destroy_color_amount;
							
							$destroy_sum = 0;
							
							$insert_q = "INSERT INTO transaction_game_ios (game_id, is_coinbase, io_id, colored_amount, destroy_amount, ref_block_id, ref_coin_blocks, ref_round_id, ref_coin_rounds, option_id, event_id, effectiveness_factor, effective_destroy_amount, is_resolved) VALUES ";
							
							for ($j=0; $j<count($outputs); $j++) {
								$payout_insert_q = "";
								
								$this_color_amount = floor($color_amount*($outputs[$j]["value"]*pow(10,$color_game->db_game['decimal_places']))/$output_sum);
								if ($j == count($outputs)-1) $this_color_amount = $color_amount - $color_amount_sum;
								
								if ($output_is_destroy[$j] == 1) $this_destroy_amount = 0;
								else {
									if ($j == $last_nondestroy_output_index) $this_destroy_amount = $destroy_color_amount-$destroy_sum;
									else $this_destroy_amount = floor($destroy_color_amount*$this_color_amount/$nondestroy_color_amount);
								}
								$destroy_sum += $this_destroy_amount;
								
								$this_coin_blocks = floor($coin_blocks*($outputs[$j]["value"]*pow(10,$color_game->db_game['decimal_places']))/$output_sum);
								if ($j == count($outputs)-1) $this_coin_blocks = $coin_blocks - $coin_block_sum;
								
								$this_coin_rounds = floor($coin_rounds*($outputs[$j]["value"]*pow(10,$color_game->db_game['decimal_places']))/$output_sum);
								if ($j == count($outputs)-1) $this_coin_rounds = $coin_rounds - $coin_round_sum;
									
								if ($output_is_destroy[$j] == 0) {
									$insert_q .= "('".$color_game->db_game['game_id']."', '0', '".$output_io_ids[$j]."', '".$this_color_amount."', '".$this_destroy_amount."', '".$ref_block_id."', '".$this_coin_blocks."', '".$ref_round_id."', '".$this_coin_rounds."', ";
									
									if ($output_io_indices[$j] !== false) {
										$option_id = $color_game->option_index_to_option_id_in_block($output_io_indices[$j], $ref_block_id);
										if ($option_id) {
											$db_event = $this->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id='".$option_id."';")->fetch();
											$event = new Event($color_game, $db_event, false);
											$effectiveness_factor = $event->block_id_to_effectiveness_factor($this->last_block_id()+1);
											
											$effective_destroy_amount = floor($this_destroy_amount*$effectiveness_factor);
											
											$insert_q .= "'".$option_id."', '".$db_event['event_id']."', '".$effectiveness_factor."', '".$effective_destroy_amount."', 0";
											
											$is_resolved = 0;
											if ($this_destroy_amount == 0) $is_resolved=1;
											$payout_insert_q = "('".$color_game->db_game['game_id']."', 1, '".$output_io_ids[$j]."', 0, 0, null, 0, null, 0, '".$option_id."', '".$db_event['event_id']."', null, 0, ".$is_resolved."), ";
										}
										else $insert_q .= "null, null, null, 0, 1";
									}
									else $insert_q .= "null, null, null, 0, 1";
									
									$insert_q .= "), ";
									if ($payout_insert_q != "") $insert_q .= $payout_insert_q;
								}
								$color_amount_sum += $this_color_amount;
								$coin_block_sum += $this_coin_blocks;
								$coin_round_sum += $this_coin_rounds;
							}
							
							$insert_q = substr($insert_q, 0, strlen($insert_q)-2).";";
							
							$this->app->dbh->beginTransaction();
							$this->app->run_query($insert_q);
							$coinbase_q1 = "UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.parent_io_id=gio.game_io_id-1 WHERE io.create_transaction_id='".$db_transaction_id."' AND gio.game_id='".$color_game->db_game['game_id']."' AND gio.is_coinbase=1;";
							$this->app->run_query($coinbase_q1);
							$coinbase_q2 = "UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.payout_io_id=gio.game_io_id+1 WHERE gio.event_id IS NOT NULL AND io.create_transaction_id='".$db_transaction_id."' AND gio.game_id='".$color_game->db_game['game_id']."' AND gio.is_coinbase=0;";
							$this->app->run_query($coinbase_q2);
							$this->app->dbh->commit();
						}
					}
					
					$benchmark_time = microtime(true);
					
					if (count($spend_io_ids) > 0) {
						$q = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_status='spent', spend_transaction_id='".$db_transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$db_transaction_id."', ','))";
						if ($block_height !== false) $q .= ", spend_block_id='".$block_height."'";
						$q .= " WHERE io_id IN (".implode(",", $spend_io_ids).");";
						$r = $this->app->run_query($q);
					}
					
					$fee_amount = ($input_sum-$output_sum);
					if ($transaction_type == "coinbase" || !$require_inputs) $fee_amount = 0;
					
					$q = "UPDATE transactions SET load_time=load_time+".(microtime(true)-$start_time);
					if (!$only_vout) $q .= ", has_all_outputs=1";
					if ($require_inputs) $q .= ", has_all_inputs=1";
					$q .= ", amount='".$output_sum."', fee_amount='".$fee_amount."'";
					$q .= " WHERE transaction_id='".$db_transaction_id."';";
					$r = $this->app->run_query($q);
					
					if ($show_debug) echo ". ";
					
					$db_transaction = $this->app->run_query("SELECT * FROM transactions WHERE transaction_id='".$db_transaction_id."';")->fetch();
					return $db_transaction;
				}
				else {
					if ($show_debug) echo ". ";
					return false;
				}
			}
			catch (Exception $e) {
				$successful = false;
				var_dump($e);
				$this->app->log_message($this->db_blockchain['blockchain_name'].": Failed to fetch transaction ".$tx_hash);
				return false;
			}
		}
	}
	
	public function walletnotify(&$coin_rpc, $tx_hash, $skip_set_site_constant) {
		$start_time = microtime(true);
		if (!$skip_set_site_constant) $this->app->set_site_constant('walletnotify', $tx_hash);
		
		$require_inputs = true;
		$successful = true;
		$this->add_transaction($coin_rpc, $tx_hash, false, $require_inputs, $successful, false, false, false);
	}
	
	public function sync_coind(&$coin_rpc, $print_debug) {
		$html = "";
		$txt = "Running Blockchain->sync_coind() for ".$this->db_blockchain['blockchain_name']."\n";
		if ($print_debug) echo $txt;
		else $html .= $txt;
		
		$last_block_id = $this->last_complete_block_id();
		$startblock_q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$last_block_id."';";
		$startblock_r = $this->app->run_query($startblock_q);
		
		if ($startblock_r->rowCount() == 0) {
			if ($last_block_id == 0) {
				$this->add_genesis_block($coin_rpc);
				$startblock_r = $this->app->run_query($startblock_q);
			}
			else {
				$txt = "sync_coind failed, block $last_block_id is missing.\n";
				if ($print_debug) echo $txt;
				else $html .= $txt;
				$this->app->log_then_die($txt);
			}
		}
		
		if ($startblock_r->rowCount() == 1) {
			$last_block = $startblock_r->fetch();
			
			if ($this->db_blockchain['p2p_mode'] == "rpc" && $last_block['block_hash'] == "") {
				$last_block_hash = $coin_rpc->getblockhash((int) $last_block['block_id']);
				$this->coind_add_block($coin_rpc, $last_block_hash, $last_block['block_id'], TRUE, $print_debug);
				$last_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$last_block['internal_block_id']."';")->fetch();
			}
			
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				$txt = "Resolving potential fork on block #".$last_block['block_id']."\n";
				if ($print_debug) echo $txt;
				else $html .= $txt;
				$this->resolve_potential_fork_on_block($coin_rpc, $last_block);
			}
			
			$txt = "Loading new blocks...\n";
			if ($print_debug) echo $txt;
			else $html .= $txt;
			$txt = $this->load_new_blocks($coin_rpc, $print_debug);
			if ($print_debug) echo $txt;
			else $html .= $txt;
			
			$txt = "Loading all blocks...\n";
			if ($print_debug) echo $txt;
			else $html .= $txt;
			$txt = $this->load_all_blocks($coin_rpc, TRUE, $print_debug);
			if ($print_debug) echo $txt;
			else $html .= $txt;
			
			if ($this->db_blockchain['p2p_mode'] == "rpc" && !empty($GLOBALS['load_unconfirmed_transactions'])) {
				$txt = "Loading unconfirmed transactions...\n";
				if ($print_debug) echo $txt;
				else $html .= $txt;
				$txt = $this->load_unconfirmed_transactions($coin_rpc, 30);
				if ($print_debug) echo $txt;
				else $html .= $txt;
			}
			
			$txt = "Done syncing ".$this->db_blockchain['blockchain_name']."\n";
			if ($print_debug) echo $txt;
			else $html .= $txt;
		}
		
		return $html;
	}
	
	public function load_new_blocks(&$coin_rpc, $print_debug) {
		$last_block_id = $this->last_block_id();
		$last_block = $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$last_block_id."';")->fetch();
		$block_height = $last_block['block_id'];
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			if (!empty($last_block['block_hash'])) {
				$rpc_block = $coin_rpc->getblock($last_block['block_hash']);
				$keep_looping = true;
				do {
					$block_height++;
					if (empty($rpc_block['nextblockhash'])) {
						$keep_looping = false;
					}
					else {
						$rpc_block = $coin_rpc->getblock($rpc_block['nextblockhash']);
						$txt = $this->coind_add_block($coin_rpc, $rpc_block['hash'], $block_height, true, $print_debug);
					}
				}
				while ($keep_looping);
			}
		}
		else {
			$info = $this->web_api_fetch_blockchain();
			$last_block_id = $this->last_block_id();
			
			if ($last_block_id < $info['last_block_id']) {
				$start_q = "INSERT INTO blocks (blockchain_id, block_id, time_created) VALUES ";
				$modulo = 0;
				$q = $start_q;
				for ($block_id = $last_block_id+1; $block_id <= $info['last_block_id']; $block_id++) {
					if ($modulo == 100) {
						$q = substr($q, 0, strlen($q)-2).";";
						$this->app->run_query($q);
						$modulo = 0;
						$q = $start_q;
					}
					else $modulo++;
					
					$q .= "('".$this->db_blockchain['blockchain_id']."', '".$block_id."', '".time()."'), ";
				}
				if ($modulo > 0) {
					$q = substr($q, 0, strlen($q)-2).";";
					$this->app->run_query($q);
				}
			}
		}
	}
	
	public function load_all_block_headers(&$coin_rpc, $required_blocks_only, $max_execution_time, $print_debug) {
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
				$this->coind_add_block($coin_rpc, $unknown_block_hash, $unknown_block['block_id'], TRUE, $print_debug);
				
				$html .= $unknown_block['block_id']." ";
				if ((microtime(true)-$start_time) >= $max_execution_time) $keep_looping = false;
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
		
		return $html;
	}
	
	public function more_web_api_blocks() {
		if ($this->db_blockchain['first_required_block']) {
			$q = "SELECT MIN(b.block_id), MAX(b.block_id) FROM (SELECT block_id FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND locally_saved=0 AND block_id >= ".$this->db_blockchain['first_required_block']." ORDER BY block_id ASC, internal_block_id ASC LIMIT 100) b;";
			$r = $this->app->run_query($q);
			$info = $r->fetch();
			
			$ref_api_blocks_r = $this->web_api_fetch_blocks($info['MIN(b.block_id)'], $info['MAX(b.block_id)']);
			return $ref_api_blocks_r['blocks'];
		}
		else return array();
	}
	
	public function load_all_blocks(&$coin_rpc, $required_blocks_only, $print_debug) {
		if ($required_blocks_only && empty($this->db_blockchain['first_required_block'])) {}
		else {
			$keep_looping = true;
			$loop_i = 0;
			$load_at_once = 100;
			
			if ($this->db_blockchain['p2p_mode'] == "web_api") {
				$ref_api_blocks = $this->more_web_api_blocks();
			}
			else $ref_api_blocks = array();
			
			do {
				$q = "SELECT * FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND locally_saved=0";
				$q .= " AND block_id >= ".$this->db_blockchain['first_required_block'];
				$q .= " ORDER BY block_id ASC LIMIT ".$load_at_once.";";
				$loadblocks_r = $this->app->run_query($q);
				
				if ($loadblocks_r->rowCount() < $load_at_once) $keep_looping = false;
				
				while ($unknown_block = $loadblocks_r->fetch()) {
					if ($this->db_blockchain['p2p_mode'] == "rpc") {
						if (empty($unknown_block['block_hash'])) {
							$unknown_block_hash = $coin_rpc->getblockhash((int)$unknown_block['block_id']);
							$this->app->run_query("UPDATE blocks SET block_hash=".$this->app->quote_escape($unknown_block_hash)." WHERE internal_block_id='".$unknown_block['internal_block_id']."';");
							$this->coind_add_block($coin_rpc, $unknown_block_hash, $unknown_block['block_id'], true, $print_debug);
							$unknown_block = $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id='".$unknown_block['internal_block_id']."';")->fetch();
						}
						$this->coind_add_block($coin_rpc, $unknown_block['block_hash'], $unknown_block['block_id'], false, $print_debug);
					}
					else {
						if ($loop_i >= count($ref_api_blocks)) {
							$loop_i = 0;
							$ref_api_blocks = $this->more_web_api_blocks();
						}
						
						if (empty($ref_api_blocks[$loop_i])) $keep_looping = false;
						else {
							$ref_api_blocks[$loop_i] = get_object_vars($ref_api_blocks[$loop_i]);
							$this->web_api_add_block($unknown_block, $ref_api_blocks[$loop_i], false, $print_debug);
						}
					}
					$loop_i++;
				}
			}
			while ($keep_looping);
		}
	}
	
	public function resolve_potential_fork_on_block(&$coin_rpc, &$db_block) {
		$rpc_block = $coin_rpc->getblock($db_block['block_hash']);
		
		if ($rpc_block['confirmations'] < 0) {
			$this->app->log_message("Detected a chain fork at block #".$db_block['block_id']);
			
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
			
			$this->app->log_message("Deleting blocks #".$delete_block_height." and above.");
			
			$this->delete_blocks_from_height($delete_block_height);
		}
	}
	
	public function load_unconfirmed_transactions(&$coin_rpc, $max_execution_time) {
		$start_time = microtime(true);
		$unconfirmed_txs = $coin_rpc->getrawmempool();
		
		for ($i=0; $i<count($unconfirmed_txs); $i++) {
			$this->walletnotify($coin_rpc, $unconfirmed_txs[$i], TRUE);
			if ($max_execution_time && (microtime(true)-$start_time) > $max_execution_time) $i=count($unconfirmed_txs);
		}
		$this->app->set_site_constant('walletnotify', $unconfirmed_txs[count($unconfirmed_txs)-1]);
	}
	
	public function insert_initial_blocks(&$coin_rpc) {
		$r = $this->app->run_query("SELECT MAX(block_id) FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';");
		$db_block_height = $r->fetch();
		$db_block_height = $db_block_height['MAX(block_id)'];
		if ((string)$db_block_height == "") $db_block_height = 0;
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$info = $coin_rpc->getblockchaininfo();
			$block_height = (int) $info['headers'];
		}
		else if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$info = $this->web_api_fetch_blockchain();
			$block_height = (int) $info['last_block_id'];
		}
		else $block_height = 1;
		
		$html = "Inserting blocks ".($db_block_height+1)." to ".$block_height."<br/>\n";
		
		$start_insert = "INSERT INTO blocks (blockchain_id, block_id, time_created) VALUES ";
		$modulo = 0;
		$q = $start_insert;
		for ($block_i=$db_block_height+1; $block_i<$block_height; $block_i++) {
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
		$this->app->run_query("DELETE s.* FROM game_sellouts s JOIN games g ON s.game_id=g.game_id WHERE g.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND s.in_block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id IS NULL;");
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.create_block_id >= ".$block_height.";");
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.create_block_id IS NULL;");
		$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET gio.spend_round_id=NULL, io.coin_blocks_created=0, gio.coin_rounds_created=0, gio.votes=0, io.spend_transaction_id=NULL, io.spend_count=NULL, io.spend_status='unspent', gio.payout_io_id=NULL WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.spend_block_id >= ".$block_height.";");
		$this->app->run_query("DELETE FROM blocks WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id >= ".$block_height.";");
	}
	
	public function add_genesis_block(&$input) {
		$html = "";
		
		if ($this->db_blockchain['p2p_mode'] == "none") {
			$game = &$input;
			$genesis_block_hash = $this->app->random_hex_string(64);
			$nextblock_hash = "";
			$genesis_tx_hash = $this->app->random_hex_string(64);
		}
		else if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$web_api_block = $this->web_api_fetch_block(0);
			$first_block = get_object_vars($web_api_block['blocks'][0]);
			$first_transaction = get_object_vars($first_block['transactions'][0]);
			$genesis_block_hash = $first_block['block_hash'];
			$genesis_tx_hash = $first_transaction['tx_hash'];
			$nextblock_hash = "";
		}
		else {
			$coin_rpc = &$input;
			$genesis_block_hash = $coin_rpc->getblockhash(0);
			$rpc_block = new block($coin_rpc->getblock($genesis_block_hash), 0, $genesis_block_hash);
			$genesis_tx_hash = $rpc_block->json_obj['tx'][0];
			
			$nextblock_hash = $rpc_block->json_obj['nextblockhash'];
		}
		
		$this->app->run_query("DELETE t.*, io.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.tx_hash=".$this->app->quote_escape($genesis_tx_hash)." AND t.blockchain_id='".$this->db_blockchain['blockchain_id']."';");
		
		if (!empty($this->db_blockchain['genesis_address'])) {
			$genesis_address = $this->db_blockchain['genesis_address'];
		}
		else {
			if ($this->db_blockchain['p2p_mode'] == "web_api") {
				$first_output = get_object_vars($first_transaction['outputs'][0]);
				$genesis_address = $first_output['address'];
			}
			else {
				$genesis_address = $this->app->random_string(34);
			}
			
			$q = "UPDATE blockchains SET genesis_address=".$this->app->quote_escape($genesis_address)." WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
			$r = $this->app->run_query($q);
			
			$this->db_blockchain['genesis_address'] = $genesis_address;
		}
		
		$force_is_mine = false;
		if ($this->db_blockchain['p2p_mode'] == "none") $force_is_mine = true;
		
		$output_address = $this->create_or_fetch_address($genesis_address, true, false, false, false, $force_is_mine, false);
		$html .= "genesis hash: ".$genesis_block_hash."<br/>\n";
		
		$q = "INSERT INTO transactions SET blockchain_id='".$this->db_blockchain['blockchain_id']."', amount='".$this->db_blockchain['initial_pow_reward']."', transaction_desc='coinbase', tx_hash='".$genesis_tx_hash."', block_id='0', position_in_block=0, time_created='".time()."', num_inputs=0, num_outputs=1, has_all_inputs=1, has_all_outputs=1;";
		$this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		$q = "INSERT INTO transaction_ios SET spend_status='unspent', blockchain_id='".$this->db_blockchain['blockchain_id']."', user_id=NULL, address_id='".$output_address['address_id']."'";
		$q .= ", create_transaction_id='".$transaction_id."', amount='".$this->db_blockchain['initial_pow_reward']."', create_block_id='0';";
		$r = $this->app->run_query($q);
		$genesis_io_id = $this->app->last_insert_id();
		
		$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_hash='".$genesis_block_hash."', block_id='0', time_created='".time()."', time_loaded='".time()."', time_mined='".time()."', num_transactions=1, locally_saved=1;";
		$r = $this->app->run_query($q);
		
		$html .= "Added the genesis transaction!<br/>\n";
		$this->app->log_message($html);
		
		$returnvals['log_text'] = $html;
		$returnvals['genesis_hash'] = $genesis_tx_hash;
		$returnvals['nextblockhash'] = $nextblock_hash;
		return $returnvals;
	}
	
	public function unset_first_required_block() {
		$q = "UPDATE blockchains SET first_required_block=NULL WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			try {
				$coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->db_blockchain['rpc_port'].'/');
				$this->set_first_required_block($coin_rpc);
			}
			catch (Exception $e) {}
		}
		else {
			$coin_rpc = false;
			$this->set_first_required_block($coin_rpc);
		}
	}
	
	public function set_first_required_block(&$coin_rpc) {
		$first_required_block = false;
		if ($coin_rpc) {
			$info = $coin_rpc->getblockchaininfo();
			$first_required_block = (int) $info['headers'];
		}
		else if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$info = $this->web_api_fetch_blockchain();
			$first_required_block = (int) $info['last_block_id'];
		}
		
		$q = "SELECT MIN(game_starting_block) FROM games WHERE game_status IN ('published', 'running') AND blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		$min_starting_block = (int) $r->fetch()['MIN(game_starting_block)'];
		if ($min_starting_block > 0 && (!$first_required_block || $min_starting_block < $first_required_block)) $first_required_block = $min_starting_block;
		
		if ($first_required_block) $this->db_blockchain['first_required_block'] = $first_required_block;
		else {
			$this->db_blockchain['first_required_block'] = false;
			$first_required_block = "NULL";
		}
		
		$q = "UPDATE blockchains SET first_required_block=".$first_required_block." WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$this->app->run_query($q);
	}
	
	public function sync_initial($from_block_id) {
		$html = "";
		$start_time = microtime(true);
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->db_blockchain['rpc_port'].'/');
		}
		else $coin_rpc = false;
		
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
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					$temp_block = $coin_rpc->getblock($db_prev_block['block_hash']);
					$current_hash = $temp_block['nextblockhash'];
				}
				$this->delete_blocks_from_height($block_height+1);
			}
			else die("Error, that block was not found (".$r->rowCount().").");
		}
		else {
			if ($this->db_blockchain['p2p_mode'] == "none") {
				$this->app->run_query("UPDATE blockchains SET genesis_address=NULL WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';");
				$this->db_blockchain['genesis_address'] = "";
			}
			
			$this->reset_blockchain();
			
			if ($this->db_blockchain['p2p_mode'] == "none") {
				$returnvals = $this->add_genesis_block($coin_rpc);
				$current_hash = $returnvals['nextblockhash'];
			}
		}
		
		$html .= $this->insert_initial_blocks($coin_rpc);
		$last_block_id = $this->last_block_id();
		if ($this->db_blockchain['p2p_mode'] == "rpc") $this->set_block_hash_by_height($coin_rpc, $last_block_id);
		
		$html .= "<br/>Finished inserting blocks at ".(microtime(true) - $start_time)." sec<br/>\n";
		
		return $html;
	}
	
	public function reset_blockchain() {
		$associated_games = $this->associated_games(false);
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
	
	public function render_transaction($transaction, $selected_address_id, $selected_io_id) {
		$html = "";
		$html .= '<div class="row bordered_row"><div class="col-md-12">';
		
		if ($transaction['block_id'] != "") {
			if ($transaction['position_in_block'] == "") $html .= "Confirmed";
			else $html .= "#".(int)$transaction['position_in_block'];
			$html .= " in block <a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>, ";
		}
		$html .= (int)$transaction['num_inputs']." inputs, ".(int)$transaction['num_outputs']." outputs, ".$this->app->format_bignum($transaction['amount']/pow(10,$this->db_blockchain['decimal_places']))." coins";
		
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase" && $transaction['transaction_desc'] != "votebase") {
			$fee_disp = $this->app->format_bignum($transaction_fee/pow(10,$this->db_blockchain['decimal_places']));
			$html .= ", ".$fee_disp;
			$html .= " tx fee";
		}
		if ($transaction['block_id'] == "") $html .= ", not yet confirmed";
		$html .= '. <br/><a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a>';
		
		$html .= '</div><div class="col-md-6">';
		
		if ($transaction['transaction_desc'] == "votebase") {
			$payout_disp = round($transaction['amount']/pow(10,$this->db_blockchain['decimal_places']), 2);
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
				$amount_disp = $this->app->format_bignum($input['amount']/pow(10,$this->db_blockchain['decimal_places']));
				$html .= '<p><a class="display_address" style="';
				if ($input['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
				$html .= '" href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$input['address'].'">'.$input['address'].'</a>';
				
				$html .= "<br/>\n";
				if ($input['io_id'] == $selected_io_id) $html .= "<b>";
				else $html .= "<a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/utxo/".$input['io_id']."\">";
				$html .= $amount_disp." ";
				if ($amount_disp == '1') $html .= $this->db_blockchain['coin_name'];
				else $html .= $this->db_blockchain['coin_name_plural'];
				if ($input['io_id'] == $selected_io_id) $html .= "</b>";
				else $html .= "</a>";
				$html .= " &nbsp; ".ucwords($input['spend_status']);
				$html .= "</p>\n";
				
				$input_sum += $input['amount'];
			}
		}
		$html .= '</div><div class="col-md-6">';
		$qq = "SELECT i.*, a.* FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.create_transaction_id='".$transaction['transaction_id']."' ORDER BY i.out_index ASC;";
		$rr = $this->app->run_query($qq);
		$output_sum = 0;
		while ($output = $rr->fetch()) {
			$html .= '<p><a class="display_address" style="';
			if ($output['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= '" href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$output['address'].'">'.$output['address']."</a><br/>\n";
			
			if ($output['io_id'] == $selected_io_id) $html .= "<b>";
			else $html .= "<a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/utxo/".$output['io_id']."\">";
			$amount_disp = $this->app->format_bignum($output['amount']/pow(10,$this->db_blockchain['decimal_places']));
			$html .= $amount_disp." ";
			if ($amount_disp == '1') $html .= $this->db_blockchain['coin_name'];
			else $html .= $this->db_blockchain['coin_name_plural'];
			if ($output['io_id'] == $selected_io_id) $html .= "</b>";
			else $html .= "</a>";
			$html .= " &nbsp; ".ucwords($output['spend_status']);
			$html .= "</p>\n";
			
			$output_sum += $output['amount'];
		}
		$html .= '</div></div>'."\n";
		
		return $html;
	}
	
	public function explorer_block_list($from_block_id, $to_block_id, &$game, $complete_blocks_only) {
		$html = "";
		
		$q = "SELECT * FROM blocks b";
		if ($game) $q .= " JOIN game_blocks gb ON b.internal_block_id=gb.internal_block_id";
		$q .= " WHERE b.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND b.block_id >= ".$from_block_id." AND b.block_id <= ".$to_block_id;
		if ($game) $q .= " AND gb.game_id=".$game->db_game['game_id'];
		if ($complete_blocks_only) $q .= " AND b.locally_saved=1";
		$q .= " ORDER BY b.block_id DESC;";
		$r = $this->app->run_query($q);
		
		while ($block = $r->fetch()) {
			if ($game) {
				list($num_trans, $block_sum) = $game->block_stats($block);
				$block_sum_disp = $block_sum/pow(10,$game->db_game['decimal_places']);
			}
			else {
				list($num_trans, $block_sum) = $this->block_stats($block);
				$block_sum_disp = $block_sum/pow(10,$this->db_blockchain['decimal_places']);
			}
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
			$html .= "<div class=\"col-sm-2\" style=\"text-align: right;\">".$this->app->format_bignum($block_sum_disp)."&nbsp;";
			if ($game) $html .= $game->db_game['coin_name_plural'];
			else $html .= $this->db_blockchain['coin_name_plural'];
			$html .= "</div>\n";
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function block_stats($block) {
		$q = "SELECT COUNT(*), SUM(amount) FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block['block_id']."' AND (amount > 0 OR num_inputs = 0);";
		$r = $this->app->run_query($q);
		$r = $r->fetch(PDO::FETCH_NUM);
		return array($r[0], $r[1]);
	}
	
	public function set_block_hash_by_height(&$coin_rpc, $block_height) {
		try {
			$block_hash = $coin_rpc->getblockhash((int) $block_height);
			$q = "UPDATE blocks SET block_hash=".$this->app->quote_escape($block_hash)." WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id='".$block_height."';";
			$r = $this->app->run_query($q);
		}
		catch (Exception $e) {}
	}
	
	public function address_balance_at_block($db_address, $block_id) {
		if ($block_id) {
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND address_id='".$db_address['address_id']."' AND create_block_id <= ".$block_id." AND (spend_status IN ('unspent','unconfirmed') OR spend_block_id>".$block_id.");";
		}
		else {
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND address_id='".$db_address['address_id']."' AND spend_status IN ('unspent','unconfirmed');";
		}
		$r = $this->app->run_query($q);
		$balance = $r->fetch();
		return $balance['SUM(amount)'];
	}
	
	public function create_or_fetch_address($address, $check_existing, $rpc, $delete_optionless, $claimable, $force_is_mine, $account_id) {
		if ($check_existing) {
			$q = "SELECT * FROM addresses WHERE address=".$this->app->quote_escape($address).";";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				return $r->fetch();
			}
		}
		$vote_identifier = $this->app->addr_text_to_vote_identifier($address);
		$option_index = $this->app->vote_identifier_to_option_index($vote_identifier);
		
		if ($option_index !== false || !$delete_optionless) {
			if ($rpc || $force_is_mine) {
				if ($force_is_mine) $is_mine=1;
				else {
					$validate_address = $rpc->validateaddress($address);
					
					if (!empty($validate_address['ismine'])) $is_mine = 1;
					else $is_mine = 0;
				}
			}
			else $is_mine=0;
			
			if ($option_index == 0) $is_destroy_address=1;
			else $is_destroy_address=0;
			
			$q = "INSERT INTO addresses SET primary_blockchain_id='".$this->db_blockchain['blockchain_id']."', address=".$this->app->quote_escape($address).", time_created='".time()."', is_mine=".$is_mine.", vote_identifier=".$this->app->quote_escape($vote_identifier).", option_index='".$option_index."', is_destroy_address=".$is_destroy_address.";";
			$r = $this->app->run_query($q);
			$output_address_id = $this->app->last_insert_id();
			
			if ($is_mine == 1) {
				if ($this->db_blockchain['p2p_mode'] == "rpc") $save_method = "wallet.dat";
				else $save_method = "fake";
				
				$q = "INSERT INTO address_keys SET address_id='".$output_address_id."', account_id=NULL, save_method='".$save_method."', pub_key=".$this->app->quote_escape($address).";";
				$r = $this->app->run_query($q);
			}
			
			$q = "SELECT * FROM addresses WHERE address_id='".$output_address_id."';";
			$r = $this->app->run_query($q);
			
			return $r->fetch();
		}
		else return false;
	}
	
	public function account_balance($account_id) {
		$q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.spend_status='unspent' AND k.account_id='".$account_id."' AND io.create_block_id IS NOT NULL;";
		$r = $this->app->run_query($q);
		$coins = $r->fetch(PDO::FETCH_NUM);
		$coins = $coins[0];
		if ($coins > 0) return $coins;
		else return 0;
	}
	
	public function user_balance(&$user_game) {
		return $this->account_balance($user_game['account_id']);
	}

	public function user_immature_balance(&$user_game) {
		$q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND k.account_id='".$user_game['account_id']."' AND (io.create_block_id > ".$this->last_block_id()." OR io.create_block_id IS NULL);";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}

	public function user_mature_balance(&$user_game) {
		$q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND k.account_id='".$user_game['account_id']."' AND io.create_block_id <= ".$this->last_block_id().";";
		$r = $this->app->run_query($q);
		$sum = $r->fetch(PDO::FETCH_NUM);
		$sum = $sum[0];
		if ($sum > 0) return $sum;
		else return 0;
	}
	
	public function set_blockchain_creator(&$user) {
		$q = "UPDATE blockchains SET creator_id='".$user->db_user['user_id']."' WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
		$r = $this->app->run_query($q);
		$this->db_blockchain['creator_id'] = $user->db_user['user_id'];
	}
	
	public function create_transaction($type, $amounts, $block_id, $io_ids, $address_ids, $transaction_fee, &$error_message) {
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			$amount += $amounts[$i];
		}
		$utxo_balance = 0;
		
		if ($type != "coinbase") {
			$q = "SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", $io_ids).");";
			$r = $this->app->run_query($q);
			$utxo_balance = $r->fetch(PDO::FETCH_NUM);
			$utxo_balance = $utxo_balance[0];
		}
		
		$raw_txin = array();
		$raw_txout = array();
		$affected_input_ids = array();
		$created_input_ids = array();
		
		if (($type == "coinbase" || $utxo_balance == $amount) && count($amounts) == count($address_ids)) {
			$num_inputs = 0;
			if ($io_ids) $num_inputs = count($io_ids);
			
			if ($this->db_blockchain['p2p_mode'] != "rpc") {
				$tx_hash = $this->app->random_hex_string(32);
				$q = "INSERT INTO transactions SET blockchain_id='".$this->db_blockchain['blockchain_id']."', fee_amount='".$transaction_fee."', has_all_inputs=1, has_all_outputs=1, num_inputs='".$num_inputs."', num_outputs='".count($amounts)."'";
				$q .= ", tx_hash='".$tx_hash."'";
				$q .= ", transaction_desc='".$type."', amount=".$amount;
				if ($block_id !== false) $q .= ", block_id='".$block_id."'";
				$q .= ", time_created='".time()."';";
				$r = $this->app->run_query($q);
				$transaction_id = $this->app->last_insert_id();
			}
			
			$input_sum = 0;
			$coin_blocks_destroyed = 0;
			
			if ($type == "coinbase") {}
			else {
				$q = "SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status IN ('unspent','unconfirmed') AND io.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND io.io_id IN (".implode(",", $io_ids).") ORDER BY io.amount ASC;";
				$r = $this->app->run_query($q);
				
				$ref_block_id = $this->last_block_id()+1;
				$ref_cbd = 0;
				
				while ($transaction_input = $r->fetch()) {
					if ($input_sum < $amount) {
						$qq = "UPDATE transaction_ios SET spend_count=spend_count+1, spend_transaction_id='".$transaction_id."', spend_transaction_ids=CONCAT(spend_transaction_ids, CONCAT('".$transaction_id."', ','))";
						if ($block_id !== false) $qq .= ", spend_status='spent', spend_block_id='".$block_id."'";
						else $qq .= ", spend_status='unconfirmed'";
						$qq .= " WHERE io_id='".$transaction_input['io_id']."';";
						$rr = $this->app->run_query($qq);
						
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
			}
			
			$output_error = false;
			$out_index = 0;
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					$address_id = $address_ids[$out_index];
					
					if ($address_id) {
						$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
						$r = $this->app->run_query($q);
						$address = $r->fetch();
						
						if ($this->db_blockchain['p2p_mode'] != "rpc") {
							$spend_status = "unconfirmed";
							if ($type == "coinbase") $spend_status = "unspent";
							
							$q = "INSERT INTO transaction_ios SET blockchain_id='".$this->db_blockchain['blockchain_id']."', spend_status='".$spend_status."', out_index='".$out_index."', script_type='pubkeyhash', ";
							if (!empty($address['user_id'])) $q .= "user_id='".$address['user_id']."', ";
							$q .= "address_id='".$address_id."', ";
							$q .= "option_index='".$address['option_index']."', ";
							
							if ($block_id !== false) {
								if ($input_sum == 0) $output_cbd = 0;
								else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
								
								if ($input_sum == 0) $output_crd = 0;
								else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
								
								$q .= "coin_blocks_destroyed='".$output_cbd."', ";
							}
							if ($block_id !== false) {
								$q .= "create_block_id='".$block_id."', ";
							}
							$q .= "create_transaction_id='".$transaction_id."', amount='".$amounts[$out_index]."';";
							
							$r = $this->app->run_query($q);
							$created_input_ids[count($created_input_ids)] = $this->app->last_insert_id();
						}
						else if ($this->db_blockchain['p2p_mode'] == "rpc") {
							$raw_txout[$address['address']] = $amounts[$out_index]/pow(10,$this->db_blockchain['decimal_places']);
						}
					}
					else $output_error = true;
				}
			}
			
			if ($output_error) {
				$error_message = "There was an error creating the outputs.";
				//$this->blockchain->app->cancel_transaction($transaction_id, $affected_input_ids, false);
				return false;
			}
			else {
				$successful = false;
				$coin_rpc = false;
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					$coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@127.0.0.1:'.$this->db_blockchain['rpc_port'].'/');
					
					try {
						$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
						$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction);
						$decoded_transaction = $coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
						$tx_hash = $decoded_transaction['txid'];
						$verified_tx_hash = $coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
						
						$this->walletnotify($coin_rpc, $verified_tx_hash, FALSE);
						
						$db_transaction = $this->app->run_query("SELECT * FROM transactions WHERE tx_hash=".$this->app->quote_escape($tx_hash).";")->fetch();
						
						$error_message = "Success!";
						return $db_transaction['transaction_id'];
					}
					catch (Exception $e) {
						$error_message = "There was an error with one of the RPC calls";
						return false;
					}
				}
				$this->add_transaction($coin_rpc, $tx_hash, $block_id, true, $successful, false, false, false);
				
				if ($this->db_blockchain['p2p_mode'] == "web_api") {
					$this->web_api_push_transaction($transaction_id);
				}
				
				$error_message = "Finished adding the transaction";
				return $transaction_id;
			}
		}
		else {
			$error_message = "Invalid balance or inputs.";
			return false;
		}
	}
	
	public function new_block(&$log_text) {
		// This function only runs for private blockchains
		$last_block_id = (int) $this->last_block_id();
		$coin_rpc = false;
		
		$q = "INSERT INTO blocks SET blockchain_id='".$this->db_blockchain['blockchain_id']."', block_id='".($last_block_id+1)."', block_hash='".$this->app->random_hex_string(64)."', time_created='".time()."', time_loaded='".time()."', time_mined='".time()."', locally_saved=1;";
		$r = $this->app->run_query($q);
		$internal_block_id = $this->app->last_insert_id();
		
		$q = "SELECT * FROM blocks WHERE internal_block_id='".$internal_block_id."';";
		$r = $this->app->run_query($q);
		$block = $r->fetch();
		$created_block_id = $block['block_id'];
		$mining_block_id = $created_block_id+1;
		
		$log_text .= "Created block $created_block_id<br/>\n";
		
		$num_transactions = 0;
		
		$ref_account = false;
		$mined_address_str = $this->app->random_string(34);
		$mined_address = $this->create_or_fetch_address($mined_address_str, false, false, false, false, true, false);
		
		$mined_error = false;
		$mined_transaction_id = $this->create_transaction('coinbase', array($this->db_blockchain['initial_pow_reward']), $created_block_id, false, array($mined_address['address_id']), 0, $mined_error);
		$num_transactions++;
		$r = $this->app->run_query("UPDATE transactions SET position_in_block='0' WHERE transaction_id='".$mined_transaction_id."';");
		
		// Include all unconfirmed TXs in the just-mined block
		$q = "SELECT * FROM transactions WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."' AND block_id IS NULL;";
		$r = $this->app->run_query($q);
		$fee_sum = 0;
		$tx_error = false;
		
		while ($unconfirmed_tx = $r->fetch()) {
			$coins_in = $this->app->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->app->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if (($coins_in > 0 && $coins_in >= $coins_out) || $unconfirmed_tx['transaction_desc'] == "coinbase") {
				$fee_amount = $coins_in - $coins_out;
				
				$successful = true;
				$db_transaction = $this->add_transaction($coin_rpc, $unconfirmed_tx['tx_hash'], $created_block_id, true, $successful, $num_transactions, false, false);
				if (!$successful) {
					$tx_error = true;
					$log_text .= "failed to add tx ".$unconfirmed_tx['tx_hash']."<br/>\n";
				}
				
				$fee_sum += $fee_amount;
				$num_transactions++;
			}
			else $tx_error = true;
		}
		
		$q = "UPDATE blocks SET num_transactions=".$num_transactions." WHERE internal_block_id='".$internal_block_id."';";
		$r = $this->app->run_query($q);
		
		$associated_games = $this->associated_games(array('running'));
		
		for ($i=0; $i<count($associated_games); $i++) {
			$associated_games[$i]->add_block($created_block_id);
		}
		
		return $created_block_id;
	}
	
	public function set_last_hash_time($time) {
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$q = "UPDATE blockchains SET last_hash_time='".$time."' WHERE blockchain_id='".$this->db_blockchain['blockchain_id']."';";
			$r = $this->app->run_query($q);
		}
	}
	
	public function games_by_transaction($db_transaction) {
		$q = "SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE (io.create_transaction_id=".$db_transaction['transaction_id']." OR io.spend_transaction_id=".$db_transaction['transaction_id'].") GROUP BY g.game_id ORDER BY g.game_id ASC;";
		$r = $this->app->run_query($q);
		
		$db_games = array();
		
		while ($db_game = $r->fetch()) {
			array_push($db_games, $db_game);
		}
		return $db_games;
	}
	
	public function games_by_io($io_id) {
		$q = "SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id WHERE gio.io_id='".$io_id."' GROUP BY g.game_id ORDER BY g.game_id ASC;";
		$r = $this->app->run_query($q);
		
		$db_games = array();
		
		while ($db_game = $r->fetch()) {
			array_push($db_games, $db_game);
		}
		return $db_games;
	}
	
	public function games_by_address($db_address) {
		$q = "SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.address_id=".$db_address['address_id']." AND io.blockchain_id='".$this->db_blockchain['blockchain_id']."' GROUP BY g.game_id ORDER BY g.game_id ASC;";
		$r = $this->app->run_query($q);
		
		$db_games = array();
		
		while ($db_game = $r->fetch()) {
			array_push($db_games, $db_game);
		}
		return $db_games;
	}
	
	public function web_api_fetch_block($block_height) {
		$remote_url = $this->authoritative_issuer['base_url']."/api/block/".$this->db_blockchain['url_identifier']."/".$block_height;
		$remote_response_raw = file_get_contents($remote_url);
		return get_object_vars(json_decode($remote_response_raw));
	}
	
	public function web_api_fetch_blocks($from_block_height, $to_block_height) {
		$remote_url = $this->authoritative_issuer['base_url']."/api/blocks/".$this->db_blockchain['url_identifier']."/".$from_block_height.":".$to_block_height;
		$remote_response_raw = file_get_contents($remote_url);
		return get_object_vars(json_decode($remote_response_raw));
	}
	
	public function web_api_fetch_blockchain() {
		$remote_url = $this->authoritative_issuer['base_url']."/api/blockchain/".$this->db_blockchain['url_identifier']."/";
		$remote_response_raw = file_get_contents($remote_url);
		return get_object_vars(json_decode($remote_response_raw));
	}
	
	public function web_api_push_transaction($transaction_id) {
		$tx = $this->app->run_query("SELECT transaction_id, block_id, transaction_desc, tx_hash, amount, fee_amount, time_created, position_in_block, num_inputs, num_outputs FROM transactions WHERE transaction_id='".$transaction_id."';")->fetch(PDO::FETCH_ASSOC);
		
		if ($tx) {
			list($inputs, $outputs) = $this->app->web_api_transaction_ios($tx['transaction_id']);
			
			unset($tx['transaction_id']);
			$tx['inputs'] = $inputs;
			$tx['outputs'] = $outputs;
			$data_txt = json_encode($tx, JSON_PRETTY_PRINT);
			$data['data'] = $data_txt;
			
			$url = $this->authoritative_issuer['base_url']."/api/transactions/".$this->db_blockchain['url_identifier']."/post/";
			
			$remote_response = $this->app->curl_post_request($url, $data, false);
		}
	}
	
	public function add_transaction_from_web_api($block_height, &$tx) {
		$q = "INSERT INTO transactions SET time_created='".time()."', blockchain_id='".$this->db_blockchain['blockchain_id']."'";
		if ($block_height !== false) $q .= ", block_id='".$block_height."', position_in_block='".((int)$tx['position_in_block'])."'";
		$q .= ", transaction_desc=".$this->app->quote_escape($tx['transaction_desc']).", tx_hash=".$this->app->quote_escape($tx['tx_hash']).", amount=".$this->app->quote_escape($tx['amount']).", fee_amount=".$this->app->quote_escape($tx['fee_amount']).", num_inputs=".((int)$tx['num_inputs']).", num_outputs=".((int)$tx['num_outputs']).";";
		$r = $this->app->run_query($q);
		$transaction_id = $this->app->last_insert_id();
		
		for ($j=0; $j<count($tx['inputs']); $j++) {
			$tx_input = get_object_vars($tx['inputs'][$j]);
			$q = "UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id SET io.spend_transaction_id='".$transaction_id."' WHERE t.blockchain_id='".$this->db_blockchain['blockchain_id']."' AND t.tx_hash=".$this->app->quote_escape($tx_input['tx_hash'])." AND io.out_index=".$this->app->quote_escape($tx_input['out_index']).";";
			$r = $this->app->run_query($q);
		}
		
		for ($j=0; $j<count($tx['outputs']); $j++) {
			$tx_output = get_object_vars($tx['outputs'][$j]);
			$db_address = $this->create_or_fetch_address($tx_output['address'], true, false, false, false, false, false);
			
			$q = "INSERT INTO transaction_ios SET blockchain_id='".$this->db_blockchain['blockchain_id']."', out_index='".$j."', address_id='".$db_address['address_id']."', option_index=".$this->app->quote_escape($tx_output['option_index']).", create_block_id='".$block_height."', create_transaction_id='".$transaction_id."', amount=".$this->app->quote_escape($tx_output['amount']);
			if ($block_height !== false) ", spend_status='unspent'";
			$q .= ";";
			$r = $this->app->run_query($q);
		}
		
		return $transaction_id;
	}
}
?>