<?php
class Blockchain {
	public $db_blockchain;
	public $app;
	public $coin_rpc;
	
	public function __construct(&$app, $blockchain_id) {
		$this->coin_rpc = false;
		$this->app = $app;
		$this->db_blockchain = $this->app->fetch_blockchain_by_id($blockchain_id);
		if (!$this->db_blockchain) throw new Exception("Failed to load blockchain #".$blockchain_id);
		
		if (!empty($this->db_blockchain['authoritative_peer_id'])) {
			$this->authoritative_peer = $this->app->fetch_peer_by_id($this->db_blockchain['authoritative_peer_id']);
		}
	}
	
	public function load_coin_rpc() {
		if ($this->coin_rpc) {}
		else if ($this->db_blockchain['p2p_mode'] == "rpc") {
			if (!empty($this->db_blockchain['rpc_username']) && !empty($this->db_blockchain['rpc_password'])) {
				try {
					$this->coin_rpc = new jsonRPCClient('http://'.$this->db_blockchain['rpc_username'].':'.$this->db_blockchain['rpc_password'].'@'.$this->db_blockchain['rpc_host'].':'.$this->db_blockchain['rpc_port'].'/');
				}
				catch (Exception $e) {}
			}
		}
	}
	
	public function set_last_complete_block($block_id) {
		$this->app->run_query("UPDATE blockchains SET last_complete_block=:block_id WHERE blockchain_id=:blockchain_id;", [
			'block_id' => $block_id,
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->db_blockchain['last_complete_block'] = $block_id;
	}
	
	public function associated_games($filter_statuses) {
		$associated_games = [];
		$associated_games_params = [$this->db_blockchain['blockchain_id']];
		$associated_games_q = "SELECT * FROM games WHERE blockchain_id=?";
		
		if (!empty($filter_statuses)) {
			$associated_games_q .= " AND (";
			foreach ($filter_statuses as $filter_status) {
				$associated_games_q .= "game_status =? OR ";
				array_push($associated_games_params, $filter_status);
			}
			$associated_games_q = substr($associated_games_q, 0, -4).")";
		}
		$associated_games_q .= ";";
		$associated_games_r = $this->app->run_query($associated_games_q, $associated_games_params);
		while ($db_game = $associated_games_r->fetch()) {
			array_push($associated_games, new Game($this, $db_game['game_id']));
		}
		return $associated_games;
	}
	
	public function fetch_block_by_internal_id($internal_block_id) {
		return $this->app->run_query("SELECT * FROM blocks WHERE internal_block_id=:internal_block_id;", [
			'internal_block_id' => $internal_block_id
		])->fetch();
	}
	
	public function fetch_block_by_id($block_id) {
		return $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id=:block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_id
		])->fetch();
	}
	
	public function fetch_block_by_hash($block_hash) {
		return $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_hash=:block_hash;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_hash' => $block_hash
		])->fetch();
	}
	
	public function last_block_id() {
		$block = $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id ORDER BY block_id DESC LIMIT 1;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch();
		
		if ($block) return $block['block_id'];
		else return false;
	}
	
	public function last_complete_block_id() {
		$complete_block_params = [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		];
		$complete_block_q = "SELECT block_id FROM blocks WHERE blockchain_id=:blockchain_id";
		if ($this->db_blockchain['first_required_block'] !== "") {
			$complete_block_q .= " AND block_id >= :first_required_block";
			$complete_block_params['first_required_block'] = $this->db_blockchain['first_required_block'];
		}
		$complete_block_q .= " AND locally_saved=0 ORDER BY block_id ASC LIMIT 1;";
		$block = $this->app->run_query($complete_block_q, $complete_block_params)->fetch(PDO::FETCH_NUM);
		
		if ($block && $block[0] != "") return $block[0]-1;
		else {
			$complete_block_q = "SELECT block_id FROM blocks WHERE blockchain_id=:blockchain_id";
			if ($this->db_blockchain['first_required_block'] !== "") {
				$complete_block_q .= " AND block_id >= :first_required_block";
			}
			$complete_block_q .= " AND locally_saved=1 ORDER BY block_id DESC LIMIT 1;";
			$block = $this->app->run_query($complete_block_q, $complete_block_params)->fetch(PDO::FETCH_NUM);
			
			if ($block && $block[0] != "") return $block[0];
			else return $this->db_blockchain['first_required_block']-1;
		}
	}
	
	public function most_recently_loaded_block() {
		return $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND locally_saved=1 AND time_loaded IS NOT NULL ORDER BY time_loaded DESC LIMIT 1;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch();
	}
	
	public function last_transaction_id() {
		$tx = $this->app->run_query("SELECT transaction_id FROM transactions WHERE blockchain_id=:blockchain_id ORDER BY transaction_id DESC LIMIT 1;", [
			'blockchain_id'=> $this->db_blockchain['blockchain_id']
		])->fetch(PDO::FETCH_NUM);
		if ($tx[0] > 0) return $tx[0];
		else return 0;
	}
	
	public function fetch_transaction_by_hash(&$tx_hash) {
		return $this->app->run_query("SELECT * FROM transactions WHERE blockchain_id=:blockchain_id AND tx_hash=:tx_hash;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'tx_hash' => $tx_hash
		])->fetch();
	}
	
	public function delete_transaction($transaction) {
		if ($transaction['block_id'] == "") {
			$this->app->run_query("UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id LEFT JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET io.spend_status='unspent', io.spend_block_id=NULL, io.spend_transaction_id=NULL, io.in_index=NULL, gio.spend_round_id=NULL WHERE t.transaction_id=:transaction_id;", [
				'transaction_id' => $transaction['transaction_id']
			]);
			
			$this->app->run_query("DELETE t.*, io.*, gio.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id LEFT JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE t.transaction_id=:transaction_id;", [
				'transaction_id' => $transaction['transaction_id']
			]);
			
			return true;
		}
		else return false;
	}
	
	public function currency_id() {
		$currency = $this->app->run_query("SELECT * FROM currencies WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch();
		if ($currency) return $currency['currency_id'];
		else return false;
	}
	
	// Loads a block for blockchains with p2p_mode = web_api/none. (The 2 modes for dev blockchains)
	public function web_api_add_block(&$db_block, &$api_block, $headers_only, $print_debug) {
		$start_time = microtime(true);
		$any_error = false;
		
		if (empty($api_block)) {
			$api_response = $this->web_api_fetch_block($db_block['block_id']);
			$api_block = get_object_vars($api_response['blocks'][0]);
		}
		
		if (!empty($api_block['block_hash'])) {
			$any_error = $this->add_block_fast($api_block['block_hash'], $db_block['block_id'], $api_block, $print_debug);
		}
		else $any_error = true;
		
		$successful = !$any_error;
		
		return $successful;
	}
	
	public function coind_prep_add_block(&$block_hash, &$block_height, $print_debug=false) {
		$db_block = $this->fetch_block_by_id($block_height);
		
		if ($db_block && $db_block['locally_saved'] == 0 && !empty($db_block['num_transactions'])) {
			$message = "Incomplete block found, resetting ".$this->db_blockchain['blockchain_name']." from block ".$block_height;
			$this->app->log_message($message);
			if ($print_debug) $this->app->print_debug($message);
			
			$this->delete_blocks_from_height($block_height);
			$this->load_new_blocks($print_debug);
			$db_block = $this->fetch_block_by_id($block_height);
		}
		
		if (!$db_block) {
			$this->app->run_insert_query("blocks", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'block_hash' => $block_hash,
				'block_id' => $block_height,
				'time_created' => time(),
				'locally_saved' => 0
			]);
			$internal_block_id = $this->app->last_insert_id();
			$db_block = $this->fetch_block_by_internal_id($internal_block_id);
		}
		
		if (empty($db_block['block_hash'])) {
			$this->set_block_hash($db_block['block_id'], $block_hash);
		}
		
		return $db_block;
	}
	
	public function vout_to_address_info(&$vout) {
		$address_text = "";
		$script_type = "";
		$address_error = false;
		
		if (!empty($vout['scriptPubKey']) && (!empty($vout['scriptPubKey']['addresses']) || !empty($vout['scriptPubKey']['hex']))) {
			$script_type = $vout['scriptPubKey']['type'];
			
			if (!empty($vout['scriptPubKey']['addresses'])) $address_text = $vout['scriptPubKey']['addresses'][0];
			else $address_text = $vout['scriptPubKey']['hex'];
			
			if (strlen($address_text) > 50) $address_text = substr($address_text, 0, 50);
		}
		else $address_error = true;
		
		return [$address_text, $script_type, $address_error];
	}
	
	public function add_block_fast($block_hash, $block_height, &$web_api_block, $print_debug) {
		$ref_time = microtime(true);
		
		$any_error = false;
		$this->load_coin_rpc();
		
		$db_block = $this->coind_prep_add_block($block_hash, $block_height, $print_debug);
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$rpc_block = (array)($this->coin_rpc->getblock($block_hash));
			$num_tx_in_block = count($rpc_block['tx']);
			$time_mined = $rpc_block['time'];
		}
		else {
			$num_tx_in_block = $web_api_block['num_transactions'];
			$time_mined = $web_api_block['time_mined'];
		}
		
		if ($print_debug) $this->app->print_debug("Processing ".$num_tx_in_block." transactions in block ".$block_height." in fast mode.");
		
		// Fetch all transactions via RPC, getrawtransaction
		$tx_hash_to_pos = [];
		$tx_pos = 0;
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$rpc_transactions = [];
			foreach ($rpc_block['tx'] as &$tx_hash) {
				$rpc_transaction = $this->coin_rpc->getrawtransaction($tx_hash, true);
				$tx_hash_to_pos[$tx_hash] = $tx_pos;
				array_push($rpc_transactions, $rpc_transaction);
				$tx_pos++;
			}
		}
		else {
			$rpc_transactions = [];
			foreach ($web_api_block['transactions'] as &$web_api_transaction) {
				array_push($rpc_transactions, (array) $web_api_transaction);
				$tx_hash_to_pos[$web_api_transaction->tx_hash] = $tx_pos;
				$tx_pos++;
			}
		}
		
		$inputs_q = "SELECT io.*, t.tx_hash FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE t.blockchain_id=".$this->db_blockchain['blockchain_id']." AND (";
		$block_num_inputs = 0;
		foreach ($rpc_transactions as &$rpc_transaction) {
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				if (!empty($rpc_transaction['vin']) && empty($rpc_transaction['vin'][0]['coinbase'])) {
					foreach ($rpc_transaction['vin'] as &$vin) {
						$inputs_q .= "(t.tx_hash='".$vin['txid']."' AND io.out_index=".$vin['vout'].") OR ";
						$block_num_inputs++;
					}
				}
			}
			else {
				if (count($rpc_transaction['inputs']) > 0) {
					foreach ($rpc_transaction['inputs'] as &$an_input) {
						$inputs_q .= "(t.tx_hash='".$an_input->tx_hash."' AND io.out_index=".$an_input->out_index.") OR ";
						$block_num_inputs++;
					}
				}
			}
		}
		
		$max_inputs_for_fast_mode = 3000;
		
		if ($block_num_inputs > $max_inputs_for_fast_mode) {
			if ($print_debug) $this->app->print_debug("Block has ".$block_num_inputs."/".$max_inputs_for_fast_mode." inputs; switching to slow mode.");
			$any_error = $this->coind_add_block($block_hash, $block_height, false, $print_debug);
		}
		else {
			$prev_block = $this->fetch_block_by_id($db_block['block_id']-1);
			$this->app->run_query("UPDATE blocks SET time_mined=:time_mined, num_transactions=:num_transactions, sec_since_prev_block=:sec_since_prev_block WHERE internal_block_id=:internal_block_id;", [
				'time_mined' => $time_mined,
				'num_transactions' => $num_tx_in_block,
				'internal_block_id' => $db_block['internal_block_id'],
				'sec_since_prev_block' => empty($prev_block['time_mined']) ? null : $time_mined - $prev_block['time_mined']
			]);
			
			$tx_hash_csv = "'".implode("','", array_keys($tx_hash_to_pos))."'";
			
			if (empty(AppSettings::getParam('sqlite_db'))) {
				$this->app->run_query("DELETE t.*, io.*, gio.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id=:blockchain_id AND t.tx_hash IN (".$tx_hash_csv.");", [
					'blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
			}
			else {
				$this->app->run_query("DELETE FROM transaction_game_ios WHERE io_id IN (SELECT io.io_id FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.tx_hash IN (".$tx_hash_csv."));", [
					'blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
				
				$this->app->run_query("DELETE FROM transaction_ios WHERE io_id IN (SELECT io.io_id FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.tx_hash IN (".$tx_hash_csv."));", [
					'blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
				
				$this->app->run_query("DELETE FROM transactions WHERE tx_hash IN (".$tx_hash_csv.") AND blockchain_id=:blockchain_id;", [
					'blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
			}
			
			if (count($rpc_transactions) > 0) {
				// Insert to transactions table
				$insert_q = "INSERT INTO transactions (blockchain_id, block_id, transaction_desc, tx_hash, time_created, position_in_block, num_inputs, num_outputs, has_all_outputs) VALUES ";
				$tx_pos = 0;
				$time = time();
				foreach ($rpc_transactions as &$rpc_transaction) {
					if ($this->db_blockchain['p2p_mode'] == "rpc") {
						$tx_num_in = count($rpc_transaction['vin']);
						$tx_num_out = count($rpc_transaction['vout']);
						$tx_hash = $rpc_transaction['txid'];
					}
					else {
						$tx_num_in = count($rpc_transaction['inputs']);
						$tx_num_out = count($rpc_transaction['outputs']);
						$tx_hash = $rpc_transaction['tx_hash'];
					}
					
					$insert_q .= "(".$this->db_blockchain['blockchain_id'].", ".$block_height.", '".($tx_pos == 0 ? "coinbase" : "transaction")."', '".$tx_hash."', ".$time.", ".$tx_pos.", ".$tx_num_in.", ".$tx_num_out.", 1), ";
					$tx_pos++;
				}
				$insert_q = substr($insert_q, 0, -2).";";
				$this->app->run_query($insert_q);
			}
			
			$db_transactions = $this->app->run_query("SELECT * FROM transactions WHERE blockchain_id=".$this->db_blockchain['blockchain_id']." AND tx_hash IN (".$tx_hash_csv.");")->fetchAll();
			$db_transactions_by_hash = (array)(AppSettings::arrayToMapOnKey($db_transactions, "tx_hash"));
			
			// Insert and load addresses for all outputs
			$address_strings = [];
			$address_to_id = [];
			
			foreach ($rpc_transactions as &$rpc_transaction) {
				$vout_pos = 0;
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					foreach ($rpc_transaction['vout'] as &$vout) {
						list($address_text, $script_type, $address_error) = $this->vout_to_address_info($vout);
						
						if ($address_error) {
							if ($print_debug) echo "No address for ".$rpc_transaction['txid'].", vout #".$vout_pos."\n";
						}
						else {
							array_push($address_strings, $address_text);
							$address_to_id[$address_text] = null;
						}
						
						$vout_pos++;
					}
				}
				else {
					foreach ($rpc_transaction['outputs'] as &$vout) {
						array_push($address_strings, $vout->address);
						$address_to_id[$vout->address] = null;
					}
				}
			}
			
			$addresses_csv = "'".implode("','", $address_strings)."'";
			
			$db_existing_addresses = $this->app->run_query("SELECT * FROM addresses WHERE address IN (".$addresses_csv.") AND primary_blockchain_id=:blockchain_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			])->fetchAll();
			
			$db_existing_addresses_by_address = (array)(AppSettings::arrayToMapOnKey($db_existing_addresses, "address"));
			
			$insert_addresses_q = "INSERT ".(!empty(AppSettings::getParam('sqlite_db')) ? "OR " : "")."IGNORE INTO addresses (primary_blockchain_id, address, time_created, is_mine, vote_identifier, option_index, is_destroy_address, is_separator_address, is_passthrough_address) VALUES ";
			$time = time();
			$insert_addr_count = 0;
			
			foreach ($address_strings as &$address_string) {
				if (empty($db_existing_addresses_by_address[$address_string])) {
					$vote_identifier = $this->app->addr_text_to_vote_identifier($address_string);
					$option_index = $this->app->vote_identifier_to_option_index($vote_identifier);
					
					list($is_destroy_address, $is_separator_address, $is_passthrough_address) = $this->app->option_index_to_special_address_types($option_index);
					
					$insert_addresses_q .= "(".$this->db_blockchain['blockchain_id'].", '".$address_string."', ".$time.", 0, '".$vote_identifier."', '".$option_index."', ".(int)$is_destroy_address.", ".(int)$is_separator_address.", ".(int)$is_passthrough_address."), ";
					
					$insert_addr_count++;
				}
			}
			
			if ($insert_addr_count > 0) {
				if ($print_debug) $this->app->print_debug("Adding ".$insert_addr_count." addresses");
				$insert_addresses_q = substr($insert_addresses_q, 0, -2).";";
				$this->app->run_query($insert_addresses_q);
			}
			
			$db_existing_addresses = $this->app->run_query("SELECT * FROM addresses WHERE address IN (".$addresses_csv.") AND primary_blockchain_id=:blockchain_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			])->fetchAll();
			
			$db_existing_addresses_by_address = (array) (AppSettings::arrayToMapOnKey($db_existing_addresses, "address"));
			
			// Process all outputs
			$insert_outputs_q = "INSERT INTO transaction_ios (blockchain_id, address_id, option_index, spend_status, out_index, is_coinbase, is_mature, is_destroy, is_separator, is_passthrough, is_receiver, create_transaction_id, script_type, amount, create_block_id) VALUES ";
			$block_num_outputs = 0;
			$tx_position_in_block = 0;
			
			foreach ($rpc_transactions as &$rpc_transaction) {
				$out_index = 0;
				$last_regular_output_index = false;
				$first_passthrough_index = false;
				
				$vouts = $this->db_blockchain['p2p_mode'] == "rpc" ? $rpc_transaction['vout'] : $rpc_transaction['outputs'];
				$tx_hash = $this->db_blockchain['p2p_mode'] == "rpc" ? $rpc_transaction['txid'] : $rpc_transaction['tx_hash'];
				
				foreach ($vouts as &$vout) {
					if ($this->db_blockchain['p2p_mode'] == "rpc") {
						list($address_text, $script_type, $address_error) = $this->vout_to_address_info($vout);
						$output_value_int = (int)(pow(10, $this->db_blockchain['decimal_places'])*$vout['value']);
					}
					else {
						$address_text = $vout->address;
						$script_type = "pubkeyhash";
						$output_value_int = $vout->amount;
					}
					
					$this_addr = (array)($db_existing_addresses_by_address[$address_text]);
					
					if ($first_passthrough_index === false && $this_addr['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
					
					$is_receiver = 0;
					if ($first_passthrough_index !== false && $this_addr['is_destroy_address'] == 0 && $this_addr['is_separator_address'] == 0 && $this_addr['is_passthrough_address'] == 0) $is_receiver = 1;
					
					if ($this_addr['is_destroy_address'] == 1) {}
					else if ($this_addr['is_separator_address'] == 0 && $this_addr['is_passthrough_address'] == 0 && $is_receiver == 0) $last_regular_output_index = $out_index;
					
					$this_transaction_id = $db_transactions_by_hash[$tx_hash]->transaction_id;
					
					$is_coinbase = $tx_position_in_block == 0 ? 1 : 0;
					$is_mature = $is_coinbase ? 0 : 1;
					
					$insert_outputs_q .= "(".$this->db_blockchain['blockchain_id'].", ".$this_addr['address_id'].", '".$this_addr['option_index']."', 'unspent', '".$out_index."', '".$is_coinbase."', '".$is_mature."', '".$this_addr['is_destroy_address']."', '".$this_addr['is_separator_address']."', '".$this_addr['is_passthrough_address']."', '".$is_receiver."', ".$this_transaction_id.", '".$script_type."', '".$output_value_int."', ".$block_height."), ";
					
					$out_index++;
				}
				
				$block_num_outputs += count($vouts);
				$tx_position_in_block++;
			}
			
			if ($block_num_outputs > 0) {
				$insert_outputs_q = substr($insert_outputs_q, 0, -2).";";
				
				if ($print_debug) $this->app->print_debug("Inserting ".$block_num_outputs." outputs");
				
				$this->app->run_query($insert_outputs_q);
			}
			
			// Process all inputs
			$full_input_info_by_tx_id = [];
			
			if ($block_num_inputs > 0) {
				$inputs_q = substr($inputs_q, 0, -4).");";
				//if ($print_debug) $this->app->print_debug($inputs_q);
				$block_inputs = $this->app->run_query($inputs_q)->fetchAll();
				
				if ($print_debug) $this->app->print_debug("Processing ".count($block_inputs)." inputs");
				
				if (count($block_inputs) > 0) {
					$block_inputs_by_id = [];
					foreach ($block_inputs as &$block_input) {
						$id = $block_input['out_index']."-".$block_input['tx_hash'];
						$block_inputs_by_id[$id] = $block_input;
					}
					
					$input_io_ids = array_keys(AppSettings::arrayToMapOnKey($block_inputs, "io_id"));
					
					$this->app->run_query("DELETE FROM transaction_ios WHERE io_id IN (".implode(",", $input_io_ids).");");
					
					$io_fields = [
						'io_id',
						'blockchain_id',
						'address_id',
						'user_id',
						'option_index',
						'spend_status',
						'out_index',
						'in_index',
						'is_destroy',
						'is_separator',
						'is_passthrough',
						'is_receiver',
						'create_transaction_id',
						'spend_transaction_id',
						'script_type',
						'amount',
						'coin_blocks_created',
						'coin_blocks_destroyed',
						'create_block_id',
						'spend_block_id'
					];
					
					$insert_spend_ios_q = "INSERT INTO transaction_ios (".implode(", ", $io_fields).") VALUES ";
					
					foreach ($rpc_transactions as &$rpc_transaction) {
						$tx_hash = $this->db_blockchain['p2p_mode'] == "rpc" ? $rpc_transaction['txid'] : $rpc_transaction['tx_hash'];
						$is_coinbase = false;
						
						if ($this->db_blockchain['p2p_mode'] == "rpc") {
							if (!empty($rpc_transaction['vin'])) {
								if (!empty($rpc_transaction['vin'][0]['coinbase'])) $is_coinbase = true;
								$vins = $rpc_transaction['vin'];
							}
							else $vins = [];
						}
						else {
							$vins = $rpc_transaction['inputs'];
							if (count($vins) == 0) $is_coinbase = true;
						}
						
						if (!$is_coinbase) {
							$in_index = 0;
							$processed_inputs_this_tx = 0;
							$processed_inputs_amount_sum = 0;
							
							foreach ($vins as &$vin) {
								$vin_identifier = $this->db_blockchain['p2p_mode'] == "rpc" ? $vin['vout']."-".$vin['txid'] : $vin->out_index."-".$vin->tx_hash;
								
								if (!empty($block_inputs_by_id[$vin_identifier])) {
									$this_block_input = $block_inputs_by_id[$vin_identifier];
									
									$this_block_input['spend_status'] = 'spent';
									$this_block_input['in_index'] = $in_index;
									$this_block_input['spend_transaction_id'] = $db_transactions_by_hash[$tx_hash]->transaction_id;
									$this_block_input['spend_block_id'] = $block_height;
									$this_block_input['coin_blocks_created'] = ($block_height-$this_block_input['create_block_id'])*$this_block_input['amount'];
									
									$this_input_sql = "(";
									foreach ($io_fields as &$io_field) {
										$this_input_sql .= "'".$this_block_input[$io_field]."', ";
									}
									$this_input_sql = substr($this_input_sql, 0, -2)."), ";
									$insert_spend_ios_q .= $this_input_sql;
									$processed_inputs_this_tx++;
									$processed_inputs_amount_sum += $this_block_input['amount'];
								}
								
								$in_index++;
							}
							
							if ($processed_inputs_this_tx == count($vins)) {
								$full_input_info_by_tx_id[$db_transactions_by_hash[$tx_hash]->transaction_id] = [
									'input_sum' => $processed_inputs_amount_sum
								];
							}
						}
					}
					
					$insert_spend_ios_q = substr($insert_spend_ios_q, 0, -2).";";
					$this->app->run_query($insert_spend_ios_q);
				}
			}
			
			// Set block & tx meta data
			if (count($full_input_info_by_tx_id) > 0) {
				$this->app->run_query("UPDATE transactions SET has_all_inputs=1 WHERE transaction_id IN (".implode(",", array_keys($full_input_info_by_tx_id)).");");
				
				$inner_q = "SELECT infot.transaction_id, SUM(io.amount) AS io_amount_sum FROM transaction_ios io JOIN transactions infot ON io.spend_transaction_id=infot.transaction_id WHERE infot.block_id=".$block_height." AND infot.blockchain_id=".$this->db_blockchain['blockchain_id']." AND infot.has_all_inputs=1 GROUP BY infot.transaction_id";
				
				if (empty(AppSettings::getParam('sqlite_db'))) {
					$this->app->run_query("UPDATE transactions t INNER JOIN (".$inner_q.") info ON t.transaction_id=info.transaction_id SET t.amount=info.io_amount_sum WHERE t.blockchain_id=".$this->db_blockchain['blockchain_id']." AND t.block_id=".$block_height.";");
				}
				else {
					$info_by_tx = $this->app->run_query($inner_q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
					$tx_ids = array_keys($info_by_tx);
					
					if (count($tx_ids) > 0) {
						$amount_sums = array_column(array_values($info_by_tx), 0);
						$this->app->bulk_mapped_update_query("transactions", ['amount' => $amount_sums], ['transaction_id' => $tx_ids]);
					}
				}
			}
			
			$inner_q = "SELECT infot.transaction_id, SUM(io.amount) AS io_amount_sum FROM transaction_ios io JOIN transactions infot ON io.create_transaction_id=infot.transaction_id WHERE infot.block_id=".$block_height." AND infot.blockchain_id=".$this->db_blockchain['blockchain_id']." AND infot.has_all_outputs=1 GROUP BY infot.transaction_id";
			
			if (empty(AppSettings::getParam('sqlite_db'))) {
				$this->app->run_query("UPDATE transactions t INNER JOIN (".$inner_q.") info ON t.transaction_id=info.transaction_id SET t.output_sum=info.io_amount_sum WHERE t.blockchain_id=".$this->db_blockchain['blockchain_id']." AND t.block_id=".$block_height.";");
			}
			else {
				$info_by_tx = $this->app->run_query($inner_q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
				$tx_ids = array_keys($info_by_tx);
				
				if (count($tx_ids) > 0) {
					$amount_sums = array_column(array_values($info_by_tx), 0);
					$this->app->bulk_mapped_update_query("transactions", ['amount' => $amount_sums], ['transaction_id' => $tx_ids]);
				}
			}
			
			$this->app->run_query("UPDATE transactions SET fee_amount=amount-output_sum WHERE blockchain_id=".$this->db_blockchain['blockchain_id']." AND block_id=".$block_height." AND has_all_inputs=1 AND has_all_outputs=1 AND output_sum IS NOT NULL;");
			
			$this->set_block_stats($db_block);
			
			$postprocessing_error = $this->coind_postprocess_block($block_height, $db_block, $ref_time);
			if ($postprocessing_error) $any_error = true;
		}
		
		if ($any_error) $this->delete_blocks_from_height($block_height);
		
		if ($print_debug) $this->app->print_debug("Added block #".$block_height." in ".round(microtime(true)-$ref_time, 4)." sec");
		
		return $any_error;
	}
	
	public function coind_postprocess_block(&$block_height, &$db_block, $block_loading_start_time) {
		$any_error = false;
		list($verification_error, $ios_in_error, $ios_out_error) = BlockchainVerifier::verifyBlock($this->app, $this->db_blockchain['blockchain_id'], $block_height);
		if ($verification_error) $any_error = true;
		
		$update_block_params = [
			'add_load_time' => (microtime(true)-$block_loading_start_time),
			'internal_block_id' => $db_block['internal_block_id']
		];
		$update_block_q = "UPDATE blocks SET ";
		if (!$any_error) {
			$update_block_q .= "locally_saved=1, time_loaded=:time_loaded, ";
			$update_block_params['time_loaded'] = time();
			$db_block['locally_saved'] = 1;
			$this->set_last_complete_block($db_block['block_id']);
			
			// Render transactions caches html for all transactions in a block
			// Slow to run, but makes block explorer much faster
			// So run when blockchain is fully loaded, but not when doing initial sync
			if ($db_block['block_id'] > $this->last_block_id()-5) {
				$this->render_transactions_in_block($db_block, false);
			}
		}
		$update_block_q .= "load_time=load_time+:add_load_time WHERE internal_block_id=:internal_block_id;";
		$this->app->run_query($update_block_q, $update_block_params);
		
		return $any_error;
	}
	
	// Loads a block for blockchains with p2p_mode="rpc" (bitcoin etc)
	public function coind_add_block($block_hash, $block_height, $headers_only, $print_debug=false) {
		$start_time = microtime(true);
		$any_error = false;
		$this->load_coin_rpc();
		
		$db_block = $this->coind_prep_add_block($block_hash, $block_height, $print_debug);
		
		if ($this->coin_rpc && $db_block['locally_saved'] == 0 && !$headers_only) {
			$lastblock_rpc = $this->coin_rpc->getblock($block_hash);
			
			if (isset($lastblock_rpc['time'])) {
				if ($db_block['num_transactions'] == "") {
					$prev_block = $this->fetch_block_by_id($db_block['block_id']-1);
					$this->app->run_query("UPDATE blocks SET time_mined=:time_mined, num_transactions=:num_transactions, sec_since_prev_block=:sec_since_prev_block WHERE internal_block_id=:internal_block_id;", [
						'time_mined' => $lastblock_rpc['time'],
						'num_transactions' => count($lastblock_rpc['tx']),
						'internal_block_id' => $db_block['internal_block_id'],
						'sec_since_prev_block' => $prev_block['time_mined'] ? $lastblock_rpc['time'] - $prev_block['time_mined'] : null
					]);
				}
				
				$start_time = microtime(true);
				
				if ($print_debug) $this->app->print_debug("Loading ".count($lastblock_rpc['tx'])." in block ".$db_block['block_id']);
				
				for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
					$tx_hash = $lastblock_rpc['tx'][$i];
					$successful = true;
					$add_tx_ref_time = microtime(true);
					$require_inputs = false;
					$db_transaction = $this->add_transaction($tx_hash, $block_height, $require_inputs, $successful, $i, [false], $print_debug);
					if ($print_debug) {
						$add_tx_time = microtime(true)-$add_tx_ref_time;
						echo ". ";
						if ($add_tx_time > 1) echo "#".$i." took ".round($add_tx_time, 4)." sec ";
						$this->app->flush_buffers();
					}
					if (!$successful) {
						$any_error = true;
						$i = count($lastblock_rpc['tx']);
						
						if ($print_debug) $this->app->print_debug("Failed to add tx ".$tx_hash);
					}
				}
				
				if (!$any_error) {
					$this->set_block_stats($db_block);
					$this->try_start_games($block_height);
				}
				
				$postprocessing_error = $this->coind_postprocess_block($block_height, $db_block, $start_time);
				if ($postprocessing_error) $any_error = true;
				
				if ($any_error) {
					$this->delete_blocks_from_height($block_height);
				}
				
				if ($print_debug) $this->app->print_debug((microtime(true)-$start_time)." sec");
			}
			else {
				$message = $this->db_blockchain['blockchain_name']." error, no block time for #".$block_height.": ".$block_hash;
				$this->app->log_message($message);
				if ($print_debug) $this->app->print_debug($message);
				$any_error = true;
			}
		}
		
		if ($any_error) {
			$message = $this->db_blockchain['blockchain_name']." error loading block #".$block_height;
			if ($print_debug) $this->app->print_message($message);
			$this->app->log_message($message);
		}
		
		return $any_error;
	}
	
	public function try_start_games($block_height) {
		$db_start_games = $this->app->run_query("SELECT * FROM games WHERE blockchain_id=:blockchain_id AND start_condition='fixed_block' AND game_starting_block=:game_starting_block AND game_status='published';", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'game_starting_block' => $block_height
		]);
		
		while ($db_start_game = $db_start_games->fetch()) {
			$start_game = new Game($this, $db_start_game['game_id']);
			list($start_game_error, $start_game_error_message) = $start_game->start_game();
		}
	}
	
	public function add_transaction($tx_hash, $block_height, $require_inputs, &$successful, $position_in_block, $only_vout_params, $print_debug) {
		// A UTXO may be created before the blockchain first required block and spent in a transaction after the first required block
		// The transaction where it was created needs to be added to the db so that the UTXOs create block is known
		// In this case, add_transaction will be called for the UTXOs creation transaction, with only_vout set (to the UTXOs out index)
		$only_vout = $only_vout_params[0];
		if ($only_vout !== false) {
			$spend_transaction_id = $only_vout_params[1];
			$spend_block_id = $only_vout_params[2];
			$spend_in_index = $only_vout_params[3];
		}
		else {
			$spend_transaction_id = false;
			$spend_block_id = false;
			$spend_in_index = false;
		}
		
		$successful = false;
		$successfully_processed_inputs = true;
		$start_time = microtime(true);
		$benchmark_time = $start_time;
		$this->load_coin_rpc();
		
		$add_transaction = true;
		
		// If using RPC P2P mode, get the transaction by RPC and find its block height if not set
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			try {
				if ((string) $this->db_blockchain['first_required_block'] == "") {
					$gettransaction = $this->coin_rpc->gettransaction($tx_hash);
					$transaction_rpc = $this->coin_rpc->decoderawtransaction($gettransaction['hex']);
				}
				else $transaction_rpc = $this->coin_rpc->getrawtransaction($tx_hash, true);
				
				if (!$transaction_rpc) $add_transaction = false;
				else if ((string) $block_height == "") {
					if (!empty($transaction_rpc['blockhash'])) {
						$tx_block = $this->fetch_block_by_hash($transaction_rpc['blockhash']);
						
						if ($tx_block) $block_height = $tx_block['block_id'];
						else {
							if ($this->db_blockchain['supports_getblockheader'] == 1) $rpc_block = $this->coin_rpc->getblockheader($transaction_rpc['blockhash']);
							else $rpc_block = $this->coin_rpc->getblock($transaction_rpc['blockhash']);
							
							if ($rpc_block['height']) {
								$block_height = $rpc_block['height'];
								$this->set_block_hash($block_height, $transaction_rpc['blockhash']);
							}
						}
					}
				}
			}
			catch (Exception $e) {
				$message = "getrawtransaction($tx_hash) failed.\n";
				if ($print_debug) $this->app->print_debug($message);
				$add_transaction = false;
			}
		}
		
		if (!$add_transaction) {
			if ($print_debug) echo "TX failed pre-checks\n";
			return false;
		}
		
		// Check for existing TX and maybe reset its outputs if it's being confirmed (for transaction malleability)
		$existing_tx = $this->fetch_transaction_by_hash($tx_hash);
		
		if ($existing_tx) {
			$db_transaction_id = $existing_tx['transaction_id'];
			
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				// Now handle cases where we are adding by RPC and there's an existing transaction
				
				// If only_vout is set, we are adding an output before the first require block
				// and therefore need to run add_transaction without deleting anything
				
				if ((string) $only_vout == "") {
					// If existing tx is unconfirmed and we are trying to add as unconfirmed, load_unconfirmed_transactions is just trying to add unnecessarily so skip
					
					// Else if existing tx matches the block we're adding in, why was this function called?
					// (maybe trying to load blocks before first required block without resetting the blockchain first?) So skip
					
					// Else we are confirming an unconfirmed transaction so delete outputs and re-add (solves transaction malleability)
					
					if ((string) $block_height == "" && (string) $existing_tx['block_id'] == "") {
						$add_transaction = false;
						$successful = true;
					}
					else if ($existing_tx['block_id'] == $block_height) {
						$add_transaction = false;
						$successful = true;
					}
					else {
						$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.create_transaction_id=:transaction_id;", [
							'transaction_id' => $existing_tx['transaction_id']
						]);
					}
				}
			}
		}
		else if ($this->db_blockchain['p2p_mode'] != "rpc") $add_transaction = false;
		// If this is not an RPC blockchain, existing transaction is required.
		// "none" and "web_api" blockchains have no coin daemon that can give data about the TX
		// so they first insert transactions and then call this function to process the TX
		
		if (!$add_transaction) return false;
		
		// Step 1, process the inputs
		$spend_io_ids = [];
		$input_sum = 0;
		$coin_blocks_destroyed = 0;
		$coin_rounds_destroyed = 0;
		
		$has_all_inputs = true;
		if ((string) $only_vout != "") $has_all_inputs = false;
		
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$transaction_type = $existing_tx['transaction_desc'];
			
			$spend_ios = $this->app->run_query("SELECT * FROM transaction_ios WHERE spend_transaction_id=:spend_transaction_id;", [
				'spend_transaction_id' => $db_transaction_id
			]);
			
			while ($spend_io = $spend_ios->fetch()) {
				$spend_io_ids[count($spend_io_ids)] = $spend_io['io_id'];
				$input_sum += $spend_io['amount'];
				
				if ((string)$block_height !== "") {
					$this_io_cbd = ($block_height - $spend_io['create_block_id'])*$spend_io['amount'];
					$coin_blocks_destroyed += $this_io_cbd;
					$this->app->run_query("UPDATE transaction_ios SET spend_status='spent', spend_block_id=:spend_block_id, coin_blocks_created=:coin_blocks_created WHERE io_id=:io_id;", [
						'spend_block_id' => $block_height,
						'coin_blocks_created' => $this_io_cbd,
						'io_id' => $spend_io['io_id']
					]);
				}
				else {
					$this->app->run_query("UPDATE transaction_ios SET spend_status='spent' WHERE io_id=:io_id;", [
						'io_id' => $spend_io['io_id']
					]);
				}
			}
		}
		else {
			$outputs = $transaction_rpc["vout"];
			$inputs = $transaction_rpc["vin"];
			
			if (count($inputs) == 1 && !empty($inputs[0]['coinbase'])) $transaction_type = "coinbase";
			else $transaction_type = "transaction";
			
			if (!$existing_tx) {
				$new_tx_params = [
					'blockchain_id' => $this->db_blockchain['blockchain_id'],
					'transaction_desc' => $transaction_type,
					'tx_hash' => $tx_hash,
					'num_inputs' => count($inputs),
					'num_outputs' => count($outputs),
					'time_created' => time()
				];
				$this->app->run_insert_query("transactions", $new_tx_params);
				$db_transaction_id = $this->app->last_insert_id();
			}
			
			$benchmark_time = microtime(true);
			
			if ($transaction_type == "transaction" && ($require_inputs || $block_height >= $this->db_blockchain['first_required_block'])) {
				for ($in_index=0; $in_index<count($inputs); $in_index++) {
					$spend_io_params = [
						'blockchain_id' => $this->db_blockchain['blockchain_id'],
						'tx_hash' => $inputs[$in_index]["txid"],
						'out_index' => $inputs[$in_index]["vout"]
					];
					$spend_io_q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.tx_hash=:tx_hash AND i.out_index=:out_index;";
					$spend_io = $this->app->run_query($spend_io_q, $spend_io_params)->fetch();
					
					if (!$spend_io) {
						$has_all_inputs = false;
						
						if ($require_inputs) {
							$child_successful = true;
							$new_tx = $this->add_transaction($inputs[$in_index]["txid"], false, false, $child_successful, false, [
								$inputs[$in_index]["vout"],
								$db_transaction_id,
								$block_height,
								$in_index
							], $print_debug);
							
							$spend_io = $this->app->run_query($spend_io_q, $spend_io_params)->fetch();
							
							if (!$spend_io) {
								$successfully_processed_inputs = false;
								$error_message = "Failed to create inputs for tx #".$db_transaction_id.", created tx #".$new_tx['transaction_id']." then looked for tx_hash=".$inputs[$in_index]['txid'].", vout=".$inputs[$in_index]['vout'];
								$this->app->log_message($error_message);
								if ($print_debug) $this->app->print_debug($error_message);
							}
						}
					}
					
					if ($successfully_processed_inputs && $spend_io) {
						$spend_io_ids[$in_index] = $spend_io['io_id'];
						$input_sum += (int) $spend_io['amount'];
						
						if ((string)$block_height !== "") {
							$this_io_cbd = ($block_height - $spend_io['create_block_id'])*$spend_io['amount'];
							$coin_blocks_destroyed += $this_io_cbd;
						}
						else $this_io_cbd = null;
						
						if ($spend_io['spend_transaction_id'] != $db_transaction_id) {
							$this->app->run_query("UPDATE transaction_ios SET spend_status='spent', spend_transaction_id=:spend_transaction_id, in_index=:in_index, coin_blocks_created=:coin_blocks_created WHERE io_id=:io_id;", [
								'spend_transaction_id' => $db_transaction_id,
								'in_index' => $in_index,
								'coin_blocks_created' => $this_io_cbd,
								'io_id' => $spend_io['io_id']
							]);
						}
					}
				}
			}
		}
		$benchmark_time = microtime(true);
		
		if (!$successfully_processed_inputs) return false;
		
		// Step 2, process outputs
		$output_io_ids = [];
		$output_io_indices = [];
		$output_io_address_ids = [];
		$output_is_destroy = [];
		$output_is_separator = [];
		$output_is_passthrough = [];
		$output_is_receiver = [];
		$separator_io_ids = [];
		$separator_address_ids = [];
		$output_sum = 0;
		$output_destroy_sum = 0;
		$last_regular_output_index = false;
		$first_passthrough_index = false;
		$is_coinbase = $position_in_block === 0 ? 1 : 0;
		$is_mature = $is_coinbase ? 0 : 1;
		
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$outputs = [];
			
			if ((string) $block_height !== "") {
				$this->app->run_query("UPDATE transaction_ios SET spend_status='unspent', create_block_id=:create_block_id WHERE create_transaction_id=:create_transaction_id;", [
					'create_block_id' => $block_height,
					'create_transaction_id' => $db_transaction_id
				]);
			}
			
			$out_ios = $this->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id ORDER BY io.out_index ASC;", [
				'create_transaction_id' => $db_transaction_id
			]);
			
			$out_index=0;
			
			while ($out_io = $out_ios->fetch()) {
				if ($first_passthrough_index === false && $out_io['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
				
				$outputs[$out_index] = array("value"=>$out_io['amount']/pow(10,$this->db_blockchain['decimal_places']));
				
				$output_address = $this->create_or_fetch_address($out_io['address'], false, null);
				$output_io_indices[$out_index] = $output_address['option_index'];
				
				$output_io_ids[$out_index] = $out_io['io_id'];
				$output_io_address_ids[$out_index] = $output_address['address_id'];
				$output_is_destroy[$out_index] = $out_io['is_destroy_address'];
				$output_is_separator[$out_index] = $out_io['is_separator_address'];
				$output_is_passthrough[$out_index] = $out_io['is_passthrough_address'];
				
				$output_is_receiver[$out_index] = 0;
				if ($first_passthrough_index !== false && $out_io['is_destroy_address'] == 0 && $out_io['is_separator_address'] == 0 && $out_io['is_passthrough_address'] == 0) $output_is_receiver[$out_index] = 1;
				
				if ($output_is_separator[$out_index] == 1) {
					array_push($separator_io_ids, $out_io['io_id']);
					array_push($separator_address_ids, $output_address['address_id']);
				}
				
				$output_sum += $out_io['amount'];
				if ($out_io['is_destroy_address'] == 1) {
					$output_destroy_sum += $out_io['amount'];
				}
				else if ($out_io['is_separator_address'] == 0 && $out_io['is_passthrough_address'] == 0 && $output_is_receiver[$out_index] == 0) $last_regular_output_index = $out_index;
				$out_index++;
			}
		}
		else {
			$from_vout = 0;
			$to_vout = count($outputs)-1;
			if ($only_vout !== false) {
				$from_vout = $only_vout;
				$to_vout = $only_vout;
			}
			
			for ($out_index=$from_vout; $out_index<=$to_vout; $out_index++) {
				$output_spend_status = $block_height === false ? "unconfirmed" : "unspent";
				if ($spend_transaction_id) $output_spend_status = "spent";
				
				list($address_text, $script_type, $address_error) = $this->vout_to_address_info($outputs[$out_index]);
				
				if (!$address_error) {
					$output_address = $this->create_or_fetch_address($address_text, false, null);
					
					if ($first_passthrough_index === false && $output_address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
					
					$new_io_amount = (int)($outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']));
					
					$new_io_params = [
						'spend_status' => $output_spend_status,
						'blockchain_id' => $this->db_blockchain['blockchain_id'],
						'script_type' => $script_type,
						'out_index' => $out_index,
						'is_coinbase' => $is_coinbase,
						'is_mature' => $is_mature,
						'address_id' => $output_address['address_id'],
						'create_transaction_id' => $db_transaction_id,
						'amount' => $new_io_amount,
						'is_destroy' => $output_address['is_destroy_address'],
						'is_separator' => $output_address['is_separator_address'],
						'is_passthrough' => $output_address['is_passthrough_address']
					];
					if ($output_address['user_id'] > 0) {
						$new_io_params['user_id'] = $output_address['user_id'];
					}
					if ($output_address['option_index'] != "") {
						$new_io_params['option_index'] = $output_address['option_index'];
						$output_io_indices[$out_index] = $output_address['option_index'];
					}
					else $output_io_indices[$out_index] = false;
					
					if ($spend_transaction_id) {
						$new_io_params['spend_transaction_id'] = $spend_transaction_id;
						$new_io_params['in_index'] = $spend_in_index;
						
						if ($spend_block_id !== false) {
							$this_io_cbc = ($spend_block_id-$block_height)*$new_io_amount;
							$new_io_params['coin_blocks_created'] = $this_io_cbc;
						}
					}
					
					if ((string)$block_height !== "") {
						$new_io_params['create_block_id'] = $block_height;
					}
					
					$output_io_address_ids[$out_index] = $output_address['address_id'];
					$output_is_destroy[$out_index] = $output_address['is_destroy_address'];
					$output_is_separator[$out_index] = $output_address['is_separator_address'];
					$output_is_passthrough[$out_index] = $output_address['is_passthrough_address'];
					
					$output_is_receiver[$out_index] = 0;
					if ($first_passthrough_index !== false && $output_is_destroy[$out_index] == 0 && $output_is_separator[$out_index] == 0 && $output_is_passthrough[$out_index] == 0) $output_is_receiver[$out_index] = 1;
					
					$new_io_params['is_receiver'] = $output_is_receiver[$out_index];
					
					$this->app->run_insert_query("transaction_ios", $new_io_params);
					$io_id = $this->app->last_insert_id();
					
					$output_io_ids[$out_index] = $io_id;
					$output_address_ids[$out_index] = $output_address['address_id'];
					
					if ($output_is_separator[$out_index] == 1) {
						array_push($separator_io_ids, $io_id);
						array_push($separator_address_ids, $output_address['address_id']);
					}
					
					$output_sum += $outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']);
					if ($output_address['is_destroy_address'] == 1) {
						$output_destroy_sum += $outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']);
					}
					else if ($output_address['is_separator_address'] == 0 && $output_address['is_passthrough_address'] == 0 && $output_is_receiver[$out_index] == 0) $last_regular_output_index = $out_index;
				}
			}
		}
		
		// Step 3, if tx is associated with games, process this transaction through each associated game
		if (count($spend_io_ids) > 0 && (string) $block_height == "" && $only_vout === false) {
			$ref_block_id = $this->last_block_id()+1;
			
			$events_by_option_id = [];
			
			$db_color_games = $this->app->run_query("SELECT g.game_id, SUM(gio.colored_amount) AS game_amount_sum, SUM(gio.colored_amount*(:ref_block_id-io.create_block_id)) AS ref_coin_block_sum FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN games g ON gio.game_id=g.game_id WHERE io.io_id IN (".implode(",", array_map('intval', $spend_io_ids)).") GROUP BY gio.game_id ORDER BY g.game_id ASC;", [
				'ref_block_id' => $ref_block_id
			]);
			
			while ($db_color_game = $db_color_games->fetch()) {
				$color_game = new Game($this, $db_color_game['game_id']);
				$ref_round_id = $color_game->block_to_round($ref_block_id);

				$tx_game_input_sum = $db_color_game['game_amount_sum'];
				$cbd_in = $db_color_game['ref_coin_block_sum'];
				
				$input_ios = $this->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.io_id IN (".implode(",", array_map('intval', $spend_io_ids)).") AND gio.game_id=:game_id;", [
					'game_id' => $color_game->db_game['game_id']
				])->fetchAll();
				
				$crd_in = 0;
				foreach ($input_ios as $input_io) {
					$crd_in += $input_io['colored_amount']*($ref_round_id-$input_io['create_round_id']);
					
					if ($input_io['is_game_coinbase'] == 1) {
						if (empty($events_by_option_id[$input_io['option_id']])) {
							$db_event = $this->app->run_query("SELECT * FROM events WHERE event_id=:event_id;", [
								'event_id' => $input_io['event_id']
							])->fetch();
							$events_by_option_id[$input_io['option_id']] = new Event($color_game, $db_event, false);
						}
						
						$resolved_before_spent = $input_io['resolved_before_spent'];
						
						if ($ref_block_id < $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
							$resolved_before_spent = 0;
						}
						else if ($block_height >= $events_by_option_id[$input_io['option_id']]->db_event['event_payout_block']) {
							$resolved_before_spent = 1;
						}
						
						if ($resolved_before_spent != $input_io['resolved_before_spent']) {
							$this->app->run_query("UPDATE transaction_game_ios SET resolved_before_spent=:resolved_before_spent WHERE game_io_id=:game_io_id;", [
								'resolved_before_spent' => $resolved_before_spent,
								'game_io_id' => $input_io['game_io_id']
							]);
						}
					}
				}
				
				$tx_chain_input_sum = $this->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", array_map('intval', $spend_io_ids)).");")->fetch()['SUM(amount)'];
				
				$tx_chain_destroy_sum = 0;
				$tx_chain_output_sum = 0;
				$tx_chain_separator_sum = 0;
				$tx_chain_passthrough_sum = 0;
				$tx_chain_receiver_sum = 0;
				
				for ($out_index=0; $out_index<count($outputs); $out_index++) {
					$tx_chain_output_sum += $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
					if ($output_is_destroy[$out_index] == 1) $tx_chain_destroy_sum += $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
					if ($output_is_separator[$out_index] == 1) $tx_chain_separator_sum += $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
					if ($output_is_passthrough[$out_index] == 1) $tx_chain_passthrough_sum += $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
					if ($output_is_receiver[$out_index] == 1) $tx_chain_receiver_sum += $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
				}
				$tx_chain_regular_sum = $tx_chain_output_sum - $tx_chain_destroy_sum - $tx_chain_separator_sum - $tx_chain_passthrough_sum - $tx_chain_receiver_sum;
				
				$tx_game_nondestroy_amount = floor($tx_game_input_sum*(($tx_chain_regular_sum+$tx_chain_separator_sum+$tx_chain_passthrough_sum+$tx_chain_receiver_sum)/$tx_chain_output_sum));
				$tx_game_destroy_amount = $tx_game_input_sum-$tx_game_nondestroy_amount;
				
				$game_amount_sum = 0;
				$game_destroy_sum = 0;
				$game_out_index = 0;
				$next_separator_i = 0;
				
				$insert_q = "INSERT INTO transaction_game_ios (game_id, is_game_coinbase, io_id, address_id, game_out_index, ref_block_id, ref_coin_blocks, ref_round_id, ref_coin_rounds, colored_amount, destroy_amount, option_id, contract_parts, event_id, effectiveness_factor, effective_destroy_amount, is_resolved, resolved_before_spent) VALUES ";
				$num_gios_added = 0;
				
				for ($out_index=0; $out_index<count($outputs); $out_index++) {
					if ($output_is_destroy[$out_index] == 0 && $output_is_separator[$out_index] == 0 && $output_is_passthrough[$out_index] == 0 && $output_is_receiver[$out_index] == 0) {
						$payout_insert_q = "";
						$io_amount = $outputs[$out_index]["value"]*pow(10,$color_game->db_game['decimal_places']);
						
						$gio_amount = floor($tx_game_nondestroy_amount*$io_amount/$tx_chain_regular_sum);
						$cbd = floor($cbd_in*$io_amount/$tx_chain_regular_sum);
						$crd = floor($crd_in*$io_amount/$tx_chain_regular_sum);
						
						if ($out_index == $last_regular_output_index) $this_destroy_amount = $tx_game_destroy_amount-$game_destroy_sum;
						else $this_destroy_amount = floor($tx_game_destroy_amount*$io_amount/$tx_chain_regular_sum);
						
						$game_destroy_sum += $this_destroy_amount;
						
						$insert_q .= "('".$color_game->db_game['game_id']."', '0', '".$output_io_ids[$out_index]."', '".$output_io_address_ids[$out_index]."', '".$game_out_index."', '".$ref_block_id."', '".$cbd."', '".$ref_round_id."', '".$crd."', ";
						$game_out_index++;
						
						if ($output_io_indices[$out_index] !== false) {
							$option_id = $color_game->option_index_to_option_id_in_block($output_io_indices[$out_index], $ref_block_id);
							if ($option_id) {
								$using_separator = false;
								if (!empty($separator_io_ids[$next_separator_i])) {
									$payout_io_id = $separator_io_ids[$next_separator_i];
									$payout_address_id = $separator_address_ids[$next_separator_i];
									$next_separator_i++;
									$using_separator = true;
								}
								else {
									$payout_io_id = $output_io_ids[$out_index];
									$payout_address_id = $output_io_address_ids[$out_index];
								}
								
								if (!empty($events_by_option_id[$option_id])) $event = $events_by_option_id[$option_id];
								else {
									$db_event = $this->app->run_query("SELECT ev.* FROM options op JOIN events ev ON op.event_id=ev.event_id WHERE op.option_id=:option_id;", [
										'option_id' => $option_id
									])->fetch();
									$events_by_option_id[$option_id] = new Event($color_game, $db_event, false);
									$event = $events_by_option_id[$option_id];
								}
								$effectiveness_factor = $event->block_id_to_effectiveness_factor($ref_block_id);
								
								$effective_destroy_amount = floor($this_destroy_amount*$effectiveness_factor);
								
								$insert_q .= "'".$gio_amount."', '".$this_destroy_amount."', '".$option_id."', '".$color_game->db_game['default_contract_parts']."', '".$event->db_event['event_id']."', '".$effectiveness_factor."', '".$effective_destroy_amount."', 0, null";
								
								$payout_is_resolved = 0;
								if ($this_destroy_amount == 0 && $color_game->db_game['exponential_inflation_rate'] == 0) $payout_is_resolved=1;
								$this_is_resolved = $payout_is_resolved;
								if ($using_separator) $this_is_resolved = 1;
								
								$payout_insert_q = "('".$color_game->db_game['game_id']."', 1, '".$payout_io_id."', '".$payout_address_id."', '".$game_out_index."', null, 0, null, 0, 0, 0, '".$option_id."', '".$color_game->db_game['default_contract_parts']."', '".$event->db_event['event_id']."', null, 0, ".$payout_is_resolved.", 1), ";
								$game_out_index++;
							}
							else $insert_q .= "'".($gio_amount+$this_destroy_amount)."', 0, null, null, null, null, 0, 1, 1";
						}
						else $insert_q .= "'".$gio_amount."', '".$this_destroy_amount."', null, null, null, null, 0, 1, 1";
						
						$insert_q .= "), ";
						if ($payout_insert_q != "") $insert_q .= $payout_insert_q;
						
						$game_amount_sum += $gio_amount;
						$num_gios_added++;
					}
				}
				
				$this->app->dbh->beginTransaction();
				
				if ($num_gios_added > 0) {
					$insert_q = substr($insert_q, 0, -2).";";
					$this->app->run_query($insert_q);
					if (empty(AppSettings::getParam('sqlite_db'))) {
						$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.parent_io_id=gio.game_io_id-1 WHERE io.create_transaction_id=:create_transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=1;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
						$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.payout_io_id=gio.game_io_id+1 WHERE gio.event_id IS NOT NULL AND io.create_transaction_id=:create_transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=0;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
					}
					else {
						$this->app->run_query("UPDATE transaction_game_ios SET parent_io_id=game_io_id-1 WHERE io_id IN (SELECT io_id FROM transaction_ios WHERE create_transaction_id=:create_transaction_id) AND game_id=:game_id AND is_game_coinbase=1;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
						$this->app->run_query("UPDATE transaction_game_ios SET payout_io_id=game_io_id+1 WHERE event_id IS NOT NULL AND io_id IN (SELECT io_id FROM transaction_ios WHERE create_transaction_id=:create_transaction_id) AND game_id=:game_id AND is_game_coinbase=0;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
					}
				}
				
				$unresolved_inputs = $this->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_game_coinbase=1 AND gio.resolved_before_spent=0 ORDER BY io.in_index ASC;", [
					'transaction_id' => $db_transaction_id,
					'game_id' => $color_game->db_game['game_id']
				])->fetchAll();
				
				$receiver_outputs = $this->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.is_receiver=1 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction_id])->fetchAll();
				
				if (count($unresolved_inputs) > 0 && count($receiver_outputs) > 0) {
					$insert_q = "INSERT INTO transaction_game_ios (parent_io_id, game_id, io_id, address_id, game_out_index, is_game_coinbase, colored_amount, destroy_amount, coin_blocks_destroyed, coin_rounds_destroyed, option_id, contract_parts, event_id, is_resolved, resolved_before_spent) VALUES ";
					
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
								
								$insert_q .= "('".$unresolved_input['parent_io_id']."', '".$color_game->db_game['game_id']."', '".$this_receiver_output['io_id']."', '".$this_receiver_output['address_id']."', '".$game_out_index."', 1, 0, 0, 0, 0, '".$unresolved_input['option_id']."', '".$contract_parts."', '".$unresolved_input['event_id']."', 0, 1), ";
								$game_out_index++;
							}
						}
						
						$receiver_output_index += $outputs_per_unresolved_input;
					}
					
					$insert_q = substr($insert_q, 0, -2).";";
					$this->app->run_query($insert_q);
				}
				
				$this->app->dbh->commit();
			}
		}
		
		// Step 4, update load time, fee & block height for this transaction
		$benchmark_time = microtime(true);
		
		$fee_amount = ($input_sum-$output_sum);
		if ($transaction_type == "coinbase" || !$has_all_inputs || (string) $only_vout != "") $fee_amount = 0;
		
		$update_tx_params = [
			'add_load_time' => (microtime(true)-$start_time),
			'amount' => $output_sum,
			'fee_amount' => $fee_amount,
			'transaction_id' => $db_transaction_id
		];
		$update_tx_q = "UPDATE transactions SET load_time=load_time+:add_load_time";
		if ((string) $only_vout == "") $update_tx_q .= ", has_all_outputs=1";
		if ($has_all_inputs) $update_tx_q .= ", has_all_inputs=1";
		if ((string)$block_height !== "") {
			$update_tx_q .= ", block_id=:block_id";
			$update_tx_params['block_id'] = $block_height;
		}
		if ($position_in_block !== false) {
			$update_tx_q .= ", position_in_block=:position_in_block";
			$update_tx_params['position_in_block'] = $position_in_block;
		}
		$update_tx_q .= ", amount=:amount, fee_amount=:fee_amount WHERE transaction_id=:transaction_id;";
		$this->app->run_query($update_tx_q, $update_tx_params);
		
		$successful = true;
		
		return $this->app->fetch_transaction_by_id($db_transaction_id);
	}
	
	public function walletnotify($tx_hash, $skip_set_site_constant) {
		$existing_tx = $this->fetch_transaction_by_hash($tx_hash);
		
		if (!$existing_tx) {
			$require_inputs = true;
			$successful = true;
			$this->add_transaction($tx_hash, false, $require_inputs, $successful, false, [false], false);
		}
	}
	
	public function sync_blockchain($print_debug) {
		$last_block_id = $this->db_blockchain['last_complete_block'];
		
		if ($last_block_id < 0) {
			if ($print_debug) $this->app->print_debug("Tried to load block #".$last_block_id);
			return false;
		}
		else if ((string) $this->db_blockchain['first_required_block'] == "") {
			list($any_error, $light_sync_error) = $this->light_sync($print_debug);
			if ($any_error && $print_debug) $this->app->print_debug($light_sync_error);
			return false;
		}
		
		if ($print_debug) $this->app->print_debug("Syncing ".$this->db_blockchain['blockchain_name']);
		
		$this->load_coin_rpc();
		
		$last_block = $this->fetch_block_by_id($last_block_id);
		
		if (!$last_block && $last_block_id > 1) {
			if ($print_debug) $this->app->print_debug("Previous block ".$last_block_id." does not exist.");
			return false;
		}
		
		if ($last_block) {
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				if ($last_block['block_hash'] == "") {
					$last_block_hash = $this->coin_rpc->getblockhash((int) $last_block['block_id']);
					
					if (!is_string($last_block_hash)) {
						if ($print_debug) $this->app->print_debug("Coin daemon returned an invalid block hash at height ".$last_block['block_id']);
						return false;
					}
					
					$coind_headers_error = $this->coind_add_block($last_block_hash, $last_block['block_id'], TRUE, $print_debug);
					$last_block = $this->fetch_block_by_internal_id($last_block['internal_block_id']);
				}
				
				if ($print_debug) $this->app->print_debug("Checking for fork on block #".$last_block['block_id']);
				$this->resolve_potential_fork_on_block($last_block);
			}
			
			if ($last_block['block_id']%10 == 0) $this->set_average_seconds_per_block(false);
		}
		
		if ($print_debug) $this->app->print_debug("Loading new blocks...");
		$this->load_new_blocks($print_debug);
		
		if ($print_debug) $this->app->print_debug("Loading all blocks...");
		$this->load_all_blocks(TRUE, $print_debug, 30);
		
		if ($this->db_blockchain['load_unconfirmed_transactions'] == 1 && $this->db_blockchain['last_complete_block'] == $this->last_block_id()) {
			if ($print_debug) $this->app->print_debug("Loading unconfirmed transactions...");
			$txt = $this->load_unconfirmed_transactions(30);
			if ($print_debug) $this->app->print_debug($txt);
		}
		
		if ($print_debug) $this->app->print_debug("Done with ".$this->db_blockchain['blockchain_name']);
		
		return true;
	}
	
	public function load_new_blocks($print_debug) {
		if ($this->db_blockchain['p2p_mode'] == "none") {}
		else {
			$last_block_id = $this->last_block_id();
			
			if ($last_block_id !== false) {
				$last_block = $this->fetch_block_by_id($last_block_id);
				$block_height = $last_block['block_id'];
			}
			
			$info_ok = null;
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				$this->load_coin_rpc();
				if ($this->coin_rpc) {
					$info = $this->coin_rpc->getblockchaininfo();
					if ($info && array_key_exists('headers', $info)) {
						$actual_block_height = (int) $info['headers'];
						$info_ok = true;
					}
					else $info_ok = false;
				}
				else $info_ok = false;
			}
			else if ($this->db_blockchain['p2p_mode'] == "web_api") {
				$info = $this->web_api_fetch_blockchain();
				if ($info && array_key_exists('last_block_id', $info)) {
					$actual_block_height = $info['last_block_id'];
					$info_ok = true;
				}
				else $info_ok = false;
			}
			else $info_ok = false;
			
			if ($info_ok) {
				$add_from_block_id = $last_block_id === false ? $this->db_blockchain['first_required_block'] : $last_block_id+1;
				
				if ($actual_block_height >= $add_from_block_id) {
					if ($print_debug) $this->app->print_debug("Quick adding blocks ".$add_from_block_id.":".$actual_block_height);
					
					$start_q = "INSERT INTO blocks (blockchain_id, block_id, time_created) VALUES ";
					$modulo = 0;
					$new_blocks_q = $start_q;
					for ($block_id = $add_from_block_id; $block_id <= $actual_block_height; $block_id++) {
						if ($modulo == 1000) {
							$new_blocks_q = substr($new_blocks_q, 0, -2).";";
							$this->app->run_query($new_blocks_q);
							$modulo = 0;
							$new_blocks_q = $start_q;
						}
						else $modulo++;
						
						$new_blocks_q .= "('".$this->db_blockchain['blockchain_id']."', '".$block_id."', '".time()."'), ";
					}
					if ($modulo > 0) {
						$new_blocks_q = substr($new_blocks_q, 0, -2).";";
						$this->app->run_query($new_blocks_q);
					}
				}
			}
			else if ($print_debug) $this->app->print_debug("Failed to determine actual block height for ".$this->db_blockchain['blockchain_name']);
		}
	}
	
	public function load_all_block_headers($required_blocks_only, $max_execution_time, $print_debug) {
		$start_time = microtime(true);
		$log_text = "";
		$this->load_coin_rpc();
		
		// Load headers for blocks with NULL block hash
		$keep_looping = true;
		do {
			$unknown_block_params = [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
			];
			$unknown_block_q = "SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_hash IS NULL";
			if ($required_blocks_only && $this->db_blockchain['first_required_block'] > 0) {
				$unknown_block_q .= " AND block_id >= :block_id";
				$unknown_block_params['block_id'] = $this->db_blockchain['first_required_block'];
			}
			$unknown_block_q .= " ORDER BY block_id DESC LIMIT 1;";
			$unknown_block = $this->app->run_query($unknown_block_q, $unknown_block_params)->fetch();
			
			if ($unknown_block) {
				$unknown_block_hash = $this->coin_rpc->getblockhash((int) $unknown_block['block_id']);
				if (!is_string($unknown_block_hash)) $coind_error = true;
				else $coind_error = $this->coind_add_block($unknown_block_hash, $unknown_block['block_id'], TRUE, $print_debug);
				
				$log_text .= $unknown_block['block_id']." ";
				if ($coind_error || (microtime(true)-$start_time) >= $max_execution_time) $keep_looping = false;
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
		
		return $log_text;
	}
	
	public function load_all_blocks($required_blocks_only, $print_debug, $max_execution_time) {
		// This function only runs for RPC blockchains and web api blockchains which load from a peer
		// Authoritative web api blockchains create all their own blocks via Blockchain->new_block()
		if ($this->db_blockchain['p2p_mode'] == "none") return false;
		else if ($required_blocks_only && (string) $this->db_blockchain['first_required_block'] === "") return false;
		
		$this->load_coin_rpc();
		$keep_looping = true;
		$loop_i = 0;
		$load_at_once = 20;
		
		$last_complete_block_id = $this->db_blockchain['last_complete_block'];
		$load_from_block = $last_complete_block_id+1;
		$load_to_block = $load_from_block+$load_at_once-1;
		
		if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$ref_api_blocks = $this->web_api_fetch_blocks($load_from_block, $load_to_block, false);
		}
		else $ref_api_blocks = [];
		
		$start_time = microtime(true);
		
		do {
			$ref_time = microtime(true);
			$blocks_loaded = 0;
			
			$last_complete_block_id = $this->db_blockchain['last_complete_block'];
			$load_from_block = $last_complete_block_id+1;
			$load_to_block = $load_from_block+$load_at_once-1;
			
			$load_blocks = $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :from_block_id  AND block_id <= :to_block_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'from_block_id' => $load_from_block,
				'to_block_id' => $load_to_block
			])->fetchAll();
			$this_loop_blocks_to_load = count($load_blocks);
			$load_block_pos = 0;
			
			while ($keep_looping && $load_block_pos < count($load_blocks)) {
				$unknown_block = $load_blocks[$load_block_pos];
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					$fetchblockhash_error = false;
					
					if (empty($unknown_block['block_hash'])) {
						$unknown_block_hash = $this->coin_rpc->getblockhash((int)$unknown_block['block_id']);
						
						if (is_string($unknown_block_hash)) {
							$this->set_block_hash($unknown_block['block_id'], $unknown_block_hash);
							$unknown_block = $this->fetch_block_by_internal_id($unknown_block['internal_block_id']);
						}
						else $fetchblockhash_error = true;
					}
					
					if ($fetchblockhash_error) $keep_looping = false;
					else {
						$web_api_block = null;
						$coind_error = $this->add_block_fast($unknown_block['block_hash'], $unknown_block['block_id'], $web_api_block, $print_debug);
						
						if ($coind_error) $keep_looping = false;
						else $blocks_loaded++;
					}
				}
				else {
					if ($loop_i >= count($ref_api_blocks)) {
						$last_complete_block_id = $this->db_blockchain['last_complete_block'];
						$load_from_block = $last_complete_block_id+1;
						$load_to_block = $load_from_block+$load_at_once-1;
						
						$loop_i = 0;
						$ref_api_blocks = $this->web_api_fetch_blocks($load_from_block, $load_to_block, false);
					}
					if (empty($ref_api_blocks[$loop_i])) $keep_looping = false;
					else {
						$ref_api_blocks[$loop_i] = (array)($ref_api_blocks[$loop_i]);
						$web_api_block_successful = $this->web_api_add_block($unknown_block, $ref_api_blocks[$loop_i], false, $print_debug);
						
						if ($web_api_block_successful) $blocks_loaded++;
						else $keep_looping = false;
					}
				}
				$loop_i++;
				
				if (microtime(true)-$start_time >= $max_execution_time) $keep_looping = false;
				
				$load_block_pos++;
			}
			
			$ref_time_x = microtime(true);
			$this->app->run_query("UPDATE transaction_ios SET is_mature=1 WHERE blockchain_id=:blockchain_id AND is_mature=0 AND create_block_id <= :mature_block;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'mature_block' => $this->db_blockchain['last_complete_block'] - $this->db_blockchain['coinbase_maturity'] + 1
			]);
			
			if ($print_debug) $this->app->print_debug("Loaded ".number_format($blocks_loaded)." (to block ".$this->db_blockchain['last_complete_block'].") in ".round(microtime(true)-$ref_time, 6)." sec, set mature took ".round(microtime(true)-$ref_time_x, 8)." sec");
			
			if ($this_loop_blocks_to_load < $load_at_once) $keep_looping = false;
		}
		while ($keep_looping);
	}
	
	public function resolve_potential_fork_on_block(&$db_block) {
		$this->load_coin_rpc();
		$rpc_block = $this->coin_rpc->getblock($db_block['block_hash']);
		
		if (isset($rpc_block['confirmations']) && $rpc_block['confirmations'] < 0) {
			$this->app->log_message("Detected a chain fork at ".$this->db_blockchain['blockchain_name']." block #".$db_block['block_id']);
			
			$delete_block_height = $db_block['block_id'];
			$rpc_delete_block = $rpc_block;
			$keep_looping = true;
			do {
				$rpc_prev_block = $this->coin_rpc->getblock($rpc_delete_block['previousblockhash']);
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
	
	public function load_unconfirmed_transactions($max_execution_time) {
		$start_time = microtime(true);
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$this->load_coin_rpc();
			$unconfirmed_txs = $this->coin_rpc->getrawmempool();
			
			if (empty($unconfirmed_txs['code']) && !empty($unconfirmed_txs)) {
				for ($i=0; $i<count($unconfirmed_txs); $i++) {
					$this->walletnotify($unconfirmed_txs[$i], TRUE);
					if ($max_execution_time && (microtime(true)-$start_time) > $max_execution_time) $i=count($unconfirmed_txs);
				}
			}
		}
		else if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$remote_url = $this->authoritative_peer['base_url']."/api/unconfirmed_transactions/".$this->db_blockchain['url_identifier'];
			$remote_response_raw = file_get_contents($remote_url);
			
			if ($remote_response_raw) {
				$remote_response = json_decode($remote_response_raw);
				
				foreach ($remote_response->transactions as $json_unconfirmed_tx) {
					$json_unconfirmed_tx = (array) $json_unconfirmed_tx;
					
					$existing_tx = $this->fetch_transaction_by_hash($json_unconfirmed_tx['tx_hash']);
					
					if (!$existing_tx) {
						$transaction_id = $this->add_transaction_from_web_api(false, $json_unconfirmed_tx);
						
						$successful = true;
						$db_transaction = $this->add_transaction($json_unconfirmed_tx['tx_hash'], false, true, $successful, false, [false], false);
					}
				}
			}
		}
	}
	
	public function delete_blocks_from_height($block_height) {
		// Reset IOs that have been confirmed spent ahead of this block
		$this->app->run_query("UPDATE transaction_ios SET coin_blocks_created=0, spend_transaction_id=NULL, spend_status='unspent', in_index=NULL, spend_block_id=NULL WHERE blockchain_id=:blockchain_id AND spend_block_id >= :spend_block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'spend_block_id' => $block_height
		]);
		
		// Reset IOs that have been spent but the spend has not been confirmed
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->app->run_query("UPDATE transaction_ios io JOIN transactions t ON io.spend_transaction_id=t.transaction_id SET io.coin_blocks_created=0, io.spend_transaction_id=NULL, io.spend_status='unspent', io.in_index=NULL, io.spend_block_id=NULL WHERE t.blockchain_id=:blockchain_id AND t.block_id IS NULL;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
		else {
			$this->app->run_query("UPDATE transaction_ios SET coin_blocks_created=0, spend_transaction_id=NULL, spend_status='unspent', in_index=NULL, spend_block_id=NULL WHERE spend_transaction_id IN (SELECT transaction_id FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL);", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
		
		// Delete any confirmed transactions and IOs created from this block
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->app->run_query("DELETE io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.block_id >= :create_block_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'create_block_id' => $block_height
			]);
		}
		else {
			$this->app->run_query("DELETE FROM transaction_ios WHERE create_transaction_id IN (SELECT transaction_id FROM transactions WHERE blockchain_id=:blockchain_id AND block_id >= :create_block_id);", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'create_block_id' => $block_height
			]);
		}
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id AND block_id >= :block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_height
		]);
		
		// Delete any outputs of unconfirmed transactions
		if (empty(AppSettings::getParam('sqlite_db'))) {
			$this->app->run_query("DELETE io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.block_id IS NULL;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
		else {
			$this->app->run_query("DELETE FROM transaction_ios WHERE create_transaction_id IN (SELECT transaction_id FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL);", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
		
		// Delete any unconfirmed transactions
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		
		// Delete records from the blocks table
		$this->app->run_query("DELETE FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_height
		]);
		
		$this->set_last_complete_block($block_height > $this->db_blockchain['first_required_block'] ? $block_height-1 : null);
		$this->set_processed_my_addresses_to_block($block_height > 1 ? $block_height-1 : null);
		
		$associated_games = $this->associated_games([]);
		
		foreach ($associated_games as $associated_game) {
			if ((string) $associated_game->db_game['loaded_until_block'] == "") $associated_game->set_loaded_until_block(null);
			
			if ($associated_game->db_game['loaded_until_block'] > $block_height && $block_height >= $associated_game->db_game['game_starting_block']) {
				$associated_game->schedule_game_reset($block_height);
			}
		}
	}
	
	public function unset_first_required_block() {
		$this->app->run_query("UPDATE blockchains SET first_required_block=NULL WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->set_first_required_block();
	}
	
	public function set_first_required_block() {
		$this->load_coin_rpc();
		$first_required_block = "";
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$info = $this->coin_rpc->getblockchaininfo();
			$first_required_block = (int) $info['headers'];
		}
		else if ($this->db_blockchain['p2p_mode'] == "web_api") {
			$info = $this->web_api_fetch_blockchain();
			$first_required_block = 1;
		}
		
		$min_starting_block = (int)($this->app->run_query("SELECT MIN(game_starting_block) FROM games WHERE game_status IN ('published', 'running') AND blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch()['MIN(game_starting_block)']);
		
		if ($min_starting_block > 0 && ($first_required_block === "" || $min_starting_block < $first_required_block)) $first_required_block = $min_starting_block;
		
		if ($first_required_block !== "") $this->db_blockchain['first_required_block'] = $first_required_block;
		else {
			$this->db_blockchain['first_required_block'] = "";
			$first_required_block = null;
		}
		
		$this->app->run_query("UPDATE blockchains SET first_required_block=:first_required_block WHERE blockchain_id=:blockchain_id;", [
			'first_required_block' => $first_required_block,
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
	}
	
	public function reset_blockchain($print_debug=false) {
		$ref_time = microtime(true);
		
		$associated_games = $this->associated_games(false);
		for ($i=0; $i<count($associated_games); $i++) {
			$associated_games[$i]->delete_reset_game('reset');
		}
		
		$this->app->dbh->beginTransaction();
		
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->app->run_query("DELETE FROM transaction_ios WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		
		$delete_limit = 50000;
		$last_block_id = $this->last_block_id();
		$delete_queries = ceil(($last_block_id+1)/$delete_limit);
		
		for ($del_i=0; $del_i<$delete_queries; $del_i++) {
			$this->app->run_query("DELETE FROM blocks WHERE blockchain_id=:blockchain_id AND block_id>=:from_block_id AND block_id<=:to_block_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'from_block_id' => $del_i*$delete_limit,
				'to_block_id' => ($del_i+1)*$delete_limit
			]);
			
			if ($print_debug) $this->app->print_debug("Deleting blocks: ".($del_i+1)."/".$delete_queries." at ".round(microtime(true)-$ref_time, 4));
		}
		
		$this->set_last_complete_block(null);
		$this->set_processed_my_addresses_to_block(null);
		
		$this->app->dbh->commit();
	}
	
	public function block_next_prev_links($block, $explore_mode) {
		$log_text = "";
		$prev_link_target = false;
		if ($explore_mode == "unconfirmed") $prev_link_target = "blocks/".$this->last_block_id();
		else if ($block['block_id'] > 1) $prev_link_target = "blocks/".($block['block_id']-1);
		if ($prev_link_target) $log_text .= '<a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/'.$prev_link_target.'" style="margin-right: 30px;">&larr; Previous Block</a>';
		
		$next_link_target = false;
		if ($explore_mode == "unconfirmed") {}
		else if ($block['block_id'] == $this->last_block_id()) $next_link_target = "transactions/unconfirmed";
		else if ($block['block_id'] < $this->last_block_id()) $next_link_target = "blocks/".($block['block_id']+1);
		if ($next_link_target) $log_text .= '<a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/'.$next_link_target.'">Next Block &rarr;</a>';
		
		return $log_text;
	}
	
	public function render_transactions_in_block(&$block, $unconfirmed_only) {
		if (!$unconfirmed_only && $block['locally_saved'] == 1 && !empty($block['transactions_html'])) return $block['transactions_html'];
		else {
			$log_text = "";
			
			$relevant_tx_params = [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			];
			$relevant_tx_q = "SELECT * FROM transactions t WHERE t.blockchain_id=:blockchain_id AND t.block_id";
			if ($unconfirmed_only) {
				$relevant_tx_q .= " IS NULL";
			}
			else {
				$relevant_tx_q .= "=:block_id";
				$relevant_tx_params['block_id'] = $block['block_id'];
			}
			$relevant_tx_q .= " ORDER BY t.position_in_block ASC;";
			$relevant_transactions = $this->app->run_query($relevant_tx_q, $relevant_tx_params);
			
			while ($transaction = $relevant_transactions->fetch()) {
				$log_text .= $this->render_transaction($transaction, false, false);
			}
			
			if (!$unconfirmed_only && $block['locally_saved'] == 1) {
				$this->app->run_query("UPDATE blocks SET transactions_html=:transactions_html WHERE internal_block_id=:internal_block_id;", [
					'transactions_html' => $log_text,
					'internal_block_id' => $block['internal_block_id']
				]);
				$block['transactions_html'] = $log_text;
			}
			return $log_text;
		}
	}
	
	public function render_transaction($transaction, $selected_address_id, $selected_io_id) {
		$html = "";
		$html .= '<div class="row bordered_row"><div class="col-md-12">';
		
		if ($transaction['block_id'] != "") {
			if ($transaction['position_in_block'] == "") $html .= "Confirmed";
			else $html .= "#".(int)$transaction['position_in_block'];
			$html .= " in block <a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>, ";
		}
		$amount_disp = $this->app->format_bignum($transaction['amount']/pow(10,$this->db_blockchain['decimal_places']));
		$html .= (int)$transaction['num_inputs']." input".($transaction['num_inputs']==1 ? "" : "s").", ".(int)$transaction['num_outputs']." output".($transaction['num_outputs']==1 ? "" : "s").", ".$amount_disp." ".($amount_disp==1 ? $this->db_blockchain['coin_name'] : $this->db_blockchain['coin_name_plural']);
		
		$transaction_fee = $transaction['fee_amount'];
		if ($transaction['transaction_desc'] != "coinbase") {
			$fee_disp = $this->app->format_bignum($transaction_fee/pow(10,$this->db_blockchain['decimal_places']));
			$html .= ", ".$fee_disp;
			$html .= " tx fee";
		}
		if ($transaction['block_id'] == "") $html .= ", not yet confirmed";
		$html .= '. <br/><a href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/transactions/'.$transaction['tx_hash'].'" class="display_address" style="max-width: 100%; overflow: hidden;">TX:&nbsp;'.$transaction['tx_hash'].'</a>';
		
		$html .= '</div><div class="col-md-6">';
		
		if ($transaction['transaction_desc'] == 'coinbase') {
			$html .= "Miner found a block.";
		}
		else {
			$tx_inputs = $this->app->run_query("SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id JOIN addresses a ON i.address_id=a.address_id WHERE i.spend_transaction_id=:spend_transaction_id ORDER BY i.in_index ASC;", [
				'spend_transaction_id' => $transaction['transaction_id']
			]);
			$input_sum = 0;
			while ($input = $tx_inputs->fetch()) {
				$amount_disp = $this->app->format_bignum($input['amount']/pow(10,$this->db_blockchain['decimal_places']));
				$html .= '<p>';
				$html .= '<a class="display_address"';
				if ($input['address_id'] == $selected_address_id) $html .= ' style="font-weight: bold; color: #000;"';
				$html .= ' href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$input['address'].'">';
				if ($input['is_destroy'] == 1) $html .= '[D] ';
				if ($input['is_separator'] == 1) $html .= '[S] ';
				if ($input['is_passthrough'] == 1) $html .= '[P] ';
				if ($input['is_receiver'] == 1) $html .= '[R] ';
				$html .= $input['address'].'</a>';
				
				$html .= "<br/>\n";
				if ($input['io_id'] == $selected_io_id) $html .= "<b>";
				else $html .= "<a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/utxo/".$input['tx_hash']."/".$input['out_index']."\">";
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
		$tx_outputs = $this->app->run_query("SELECT i.*, a.* FROM transaction_ios i JOIN addresses a ON i.address_id=a.address_id WHERE i.create_transaction_id=:create_transaction_id ORDER BY i.out_index ASC;", [
			'create_transaction_id' => $transaction['transaction_id']
		]);
		$output_sum = 0;
		while ($output = $tx_outputs->fetch()) {
			$html .= '<p>';
			$html .= '<a class="display_address"';
			if ($output['address_id'] == $selected_address_id) $html .= ' style="font-weight: bold; color: #000;"';
			$html .= ' href="/explorer/blockchains/'.$this->db_blockchain['url_identifier'].'/addresses/'.$output['address'].'">';
			if ($output['is_destroy'] == 1) $html .= '[D] ';
			if ($output['is_separator'] == 1) $html .= '[S] ';
			if ($output['is_passthrough'] == 1) $html .= '[P] ';
			if ($output['is_receiver'] == 1) $html .= '[R] ';
			$html .= $output['address']."</a><br/>\n";
			
			if ($output['io_id'] == $selected_io_id) $html .= "<b>";
			else $html .= "<a href=\"/explorer/blockchains/".$this->db_blockchain['url_identifier']."/utxo/".$transaction['tx_hash']."/".$output['out_index']."\">";
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
		
		$block_params = [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'from_block_id' => $from_block_id,
			'to_block_id' => $to_block_id
		];
		if ($game) $block_q = "SELECT gb.* FROM blocks b JOIN game_blocks gb ON b.block_id=gb.block_id";
		else $block_q = "SELECT * FROM blocks b";
		$block_q .= " WHERE b.blockchain_id=:blockchain_id AND b.block_id >= :from_block_id AND b.block_id <= :to_block_id";
		if ($game) {
			$block_q .= " AND gb.game_id=:game_id";
			$block_params['game_id'] = $game->db_game['game_id'];
		}
		if ($complete_blocks_only) $block_q .= " AND b.locally_saved=1";
		$block_q .= " ORDER BY b.block_id DESC;";
		$blocks = $this->app->run_query($block_q, $block_params);
		
		while ($block = $blocks->fetch()) {
			if ($game) $block_sum_disp = $this->app->format_bignum($block['sum_coins_out']/pow(10,$game->db_game['decimal_places']));
			else $block_sum_disp = $this->app->format_bignum($block['sum_coins_out']/pow(10,$this->db_blockchain['decimal_places']));
			
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-3\">";
			$html .= "<a href=\"/explorer/";
			if ($game) $html .= "games/".$game->db_game['url_identifier'];
			else $html .= "blockchains/".$this->db_blockchain['url_identifier'];
			$html .= "/blocks/".$block['block_id']."\">Block #".$block['block_id']."</a>";
			if ((string) $this->db_blockchain['first_required_block'] != "" && $block['locally_saved'] == 0 && $block['block_id'] >= $this->db_blockchain['first_required_block']) $html .= "&nbsp;(Pending)";
			$html .= "</div>";
			$html .= "<div class=\"col-sm-2";
			$html .= "\" style=\"text-align: right;\">".number_format($block['num_transactions']);
			$html .= "&nbsp;transactions</div>\n";
			$html .= "<div class=\"col-sm-2\" style=\"text-align: right;\">".$block_sum_disp."&nbsp;";
			if ($game) $html .= $block_sum_disp==1 ? $game->db_game['coin_name'] : $game->db_game['coin_name_plural'];
			else $html .= $block_sum_disp==1 ? $this->db_blockchain['coin_name'] : $this->db_blockchain['coin_name_plural'];
			$html .= "</div>\n";
			$html .= "</div>\n";
		}
		return $html;
	}
	
	public function set_block_stats(&$block) {
		$out_stat = $this->app->run_query("SELECT COUNT(*) ios_out, SUM(io.amount) coins_out FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
			'block_id' => $block['block_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch();
		
		$num_ios_out = $out_stat['ios_out'];
		$sum_coins_out = $out_stat['coins_out'];
		
		$num_transactions = $this->app->run_query("SELECT COUNT(*) FROM transactions WHERE block_id=:block_id AND blockchain_id=:blockchain_id;", [
			'block_id' => $block['block_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch()['COUNT(*)'];
		
		$in_stat = $this->app->run_query("SELECT COUNT(*) ios_in, SUM(io.amount) coins_in FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
			'block_id' => $block['block_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch();
		
		$num_ios_in = $in_stat['ios_in'];
		$sum_coins_in = $in_stat['coins_in'];
		
		$update_blocks_params = [
			'num_ios_in' => $num_ios_in,
			'num_ios_out' => $num_ios_out,
			'sum_coins_in' => $sum_coins_in,
			'sum_coins_out' => $sum_coins_out,
			'internal_block_id' => $block['internal_block_id']
		];
		$update_blocks_q = "UPDATE blocks SET ";
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$update_blocks_q .= "num_transactions=:num_transactions, ";
			$update_blocks_params['num_transactions'] = $num_transactions;
		}
		$update_blocks_q .= "num_ios_in=:num_ios_in, num_ios_out=:num_ios_out, sum_coins_in=:sum_coins_in, sum_coins_out=:sum_coins_out WHERE internal_block_id=:internal_block_id;";
		$this->app->run_query($update_blocks_q, $update_blocks_params);
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") $block['num_transactions'] = $num_transactions;
		$block['num_ios_in'] = $num_ios_in;
		$block['num_ios_out'] = $num_ios_out;
		$block['sum_coins_in'] = $sum_coins_in;
		$block['sum_coins_out'] = $sum_coins_out;
		
		return $num_transactions;
	}
	
	public function set_block_hash_by_height($block_height) {
		$this->load_coin_rpc();
		
		try {
			$block_hash = $this->coin_rpc->getblockhash((int) $block_height);
			
			if (is_string($block_hash)) {
				$this->set_block_hash($block_height, $block_hash);
			}
		}
		catch (Exception $e) {}
	}
	
	public function set_block_hash($block_id, $block_hash) {
		$this->app->run_query("UPDATE blocks SET block_hash=:block_hash WHERE blockchain_id=:blockchain_id AND block_id=:block_id;", [
			'block_hash' => $block_hash,
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_id
		]);
		$this->db_blockchain['block_hash'] = $block_hash;
	}
	
	public function total_paid_to_address(&$db_address, $confirmed_only) {
		$balance_q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE t.blockchain_id=:blockchain_id AND io.address_id=:address_id";
		if ($confirmed_only) $balance_q .= " AND t.block_id IS NOT NULL";
		
		return $this->app->run_query($balance_q, [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'address_id' => $db_address['address_id']
		])->fetch()['SUM(io.amount)'];
	}
	
	public function address_balance_at_block(&$db_address, $block_id) {
		$balance_params = [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'address_id' => $db_address['address_id']
		];
		$balance_q = "SELECT SUM(amount) FROM transaction_ios WHERE blockchain_id=:blockchain_id AND address_id=:address_id";
		if ($block_id) {
			$balance_q .= " AND create_block_id <= :block_id AND (spend_status IN ('unspent','unconfirmed') OR spend_block_id>:block_id);";
			$balance_params['block_id'] = $block_id;
		}
		else {
			$balance_q .= " AND spend_status IN ('unspent','unconfirmed');";
		}
		return $this->app->run_query($balance_q, $balance_params)->fetch()['SUM(amount)'];
	}
	
	public function create_or_fetch_address($address, $force_is_mine, $account_id) {
		$db_address = $this->app->fetch_address($address);
		if ($db_address) return $db_address;
		
		$vote_identifier = $this->app->addr_text_to_vote_identifier($address);
		$option_index = $this->app->vote_identifier_to_option_index($vote_identifier);
		
		if ($force_is_mine) $is_mine=1;
		else {
			$this->load_coin_rpc();
			
			if ($this->coin_rpc) {
				$address_info = $this->coin_rpc->getaddressinfo($address);
				
				if (!empty($address_info['ismine'])) $is_mine = 1;
				else $is_mine=0;
			}
			else $is_mine=0;
		}
		
		list($is_destroy_address, $is_separator_address, $is_passthrough_address) = $this->app->option_index_to_special_address_types($option_index);
		
		$this->app->run_insert_query("addresses", [
			'primary_blockchain_id' => $this->db_blockchain['blockchain_id'],
			'address' => $address,
			'time_created' => time(),
			'is_mine' => $is_mine,
			'vote_identifier' => $vote_identifier,
			'option_index' => $option_index,
			'is_destroy_address' => $is_destroy_address,
			'is_separator_address' => $is_separator_address,
			'is_passthrough_address' => $is_passthrough_address
		]);
		$output_address_id = $this->app->last_insert_id();
		
		if ($is_mine == 1) {
			$this->app->insert_address_key([
				'currency_id' => $this->currency_id(),
				'address_id' => $output_address_id,
				'account_id' => $account_id,
				'pub_key' => $address,
				'option_index' => $option_index,
				'primary_blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
		
		return $this->app->fetch_address_by_id($output_address_id);
	}
	
	public function account_balance($account_id, $include_unconfirmed=false, $immature_only=false) {
		if ($immature_only) {
			return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.is_mature=0 AND k.account_id=:account_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'account_id' => $account_id
			])->fetch(PDO::FETCH_NUM)[0]);
		}
		else if ($include_unconfirmed) {
			return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id=:account_id;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'account_id' => $account_id
			])->fetch(PDO::FETCH_NUM)[0]);
		}
		else {
			return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.spend_status='unspent' AND k.account_id=:account_id AND io.create_block_id IS NOT NULL;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'account_id' => $account_id
			])->fetch(PDO::FETCH_NUM)[0]);
		}
	}

	public function set_blockchain_creator(&$user) {
		$this->app->run_query("UPDATE blockchains SET creator_id=:user_id WHERE blockchain_id=:blockchain_id;", [
			'user_id' => $user->db_user['user_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->db_blockchain['creator_id'] = $user->db_user['user_id'];
	}
	
	// Returns the transaction ID if successful or false if not successful
	public function create_transaction($type, $amounts, $block_id, $io_ids, $address_ids, $transaction_fee, &$error_message) {
		// Step 1, sanity checks
		if ($transaction_fee < 0) {
			$error_message = "Tried creating a transaction with a negative transaction fee ($transaction_fee).\n";
			return false;
		}
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
			if ($amounts[$i] <= 0) {
				$error_message = "Invalid amount ".$amounts[$i]." for output #".$i;
				return false;
			}
			$amount += $amounts[$i];
		}
		$utxo_balance = 0;
		
		if ($type != "coinbase") {
			$utxo_balance = (int)($this->app->run_query("SELECT SUM(amount) FROM transaction_ios WHERE io_id IN (".implode(",", array_map("intval", $io_ids)).");")->fetch(PDO::FETCH_NUM)[0]);
		}
		
		$raw_txin = [];
		$raw_txout = [];
		$affected_input_ids = [];
		$created_input_ids = [];
		
		if (count($amounts) != count($address_ids) || ($type != "coinbase" && $utxo_balance != $amount)) {
			$error_message = "Invalid balance or inputs.";
			return false;
		}
		
		$num_inputs = 0;
		if ($io_ids) $num_inputs = count($io_ids);
		
		// Step 2, insert the transaction if it's a web api blockchain
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$tx_hash = $this->app->random_hex_string(32);
			$new_tx_params = [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'fee_amount' => $transaction_fee,
				'num_inputs' => $num_inputs,
				'num_outputs' => count($amounts),
				'tx_hash' => $tx_hash,
				'transaction_desc' => $type,
				'amount' => $amount,
				'time_created' => time(),
				'has_all_inputs' => 1,
				'has_all_outputs' => 1
			];
			
			if ($block_id !== false) {
				$new_tx_params['block_id'] = $block_id;
			}
			$this->app->run_insert_query("transactions", $new_tx_params);
			$transaction_id = $this->app->last_insert_id();
		}
		
		// Step 3, process the inputs
		$input_sum = 0;
		$coin_blocks_destroyed = 0;
		
		if ($type == "coinbase") {}
		else {
			$tx_inputs = $this->app->run_query("SELECT *, io.address_id AS address_id, io.amount AS amount FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_status IN ('unspent','unconfirmed') AND io.blockchain_id=:blockchain_id AND io.io_id IN (".implode(",", array_map("intval", $io_ids)).") ORDER BY io.amount ASC;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
			
			$ref_block_id = $this->last_block_id()+1;
			$ref_cbd = 0;
			
			$in_index = 0;
			
			while ($transaction_input = $tx_inputs->fetch()) {
				if ($input_sum < $amount) {
					$update_input_params = [
						'io_id' => $transaction_input['io_id']
					];
					$update_input_q = "UPDATE transaction_ios SET spend_status='spent'";
					
					if ($block_id !== false) {
						$update_input_q .= ", spend_block_id=:block_id";
						$update_input_params['block_id'] = $block_id;
					}
					
					if (!empty($transaction_id)) {
						$update_input_q .= ", spend_transaction_id=:transaction_id, in_index=:in_index";
						$update_input_params['transaction_id'] = $transaction_id;
						$update_input_params['in_index'] = $in_index;
					}
					
					$update_input_q .= " WHERE io_id=:io_id;";
					$this->app->run_query($update_input_q, $update_input_params);
					
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
				$in_index++;
			}
		}
		
		// Step 4, create the outputs
		$output_error = false;
		$out_index = 0;
		$first_passthrough_index = false;
		
		for ($out_index=0; $out_index<count($amounts); $out_index++) {
			if (!$output_error) {
				$address_id = $address_ids[$out_index];
				
				if ($address_id) {
					$address = $this->app->fetch_address_by_id($address_id);
					
					if ($first_passthrough_index === false && $address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
					
					if ($this->db_blockchain['p2p_mode'] != "rpc") {
						$spend_status = $type == "coinbase" ? "unspent" : "unconfirmed";
						
						$new_output_params = [
							'blockchain_id' => $this->db_blockchain['blockchain_id'],
							'spend_status' => $spend_status,
							'out_index' => $out_index,
							'is_destroy' => $address['is_destroy_address'],
							'is_separator' => $address['is_separator_address'],
							'is_passthrough' => $address['is_passthrough_address'],
							'is_receiver' => ($first_passthrough_index !== false && $address['is_destroy_address']+$address['is_separator_address']+$address['is_passthrough_address'] == 0) ? 1 : 0,
							'address_id' => $address_id,
							'option_index' => $address['option_index'],
							'create_transaction_id' => $transaction_id,
							'amount' => $amounts[$out_index],
							'script_type' => 'pubkeyhash'
						];
						if (!empty($address['user_id'])) {
							$new_output_params['user_id'] = $address['user_id'];
						}
						
						if ($block_id !== false) {
							if ($input_sum == 0) $output_cbd = 0;
							else $output_cbd = floor($coin_blocks_destroyed*($amounts[$out_index]/$input_sum));
							
							if ($input_sum == 0) $output_crd = 0;
							else $output_crd = floor($coin_rounds_destroyed*($amounts[$out_index]/$input_sum));
							
							$new_output_params['coin_blocks_destroyed'] = $output_cbd;
						}
						if ($block_id !== false) {
							$new_output_params['create_block_id'] = $block_id;
						}
						
						$this->app->run_insert_query("transaction_ios", $new_output_params);
						$created_input_ids[count($created_input_ids)] = $this->app->last_insert_id();
					}
					else if ($this->db_blockchain['p2p_mode'] == "rpc") {
						if (empty($raw_txout[$address['address']])) $raw_txout[$address['address']] = 0;
						$raw_txout[$address['address']] += $amounts[$out_index]/pow(10,$this->db_blockchain['decimal_places']);
					}
				}
				else $output_error = true;
			}
		}
		
		if ($output_error) {
			$error_message = "There was an error creating the outputs.";
			return false;
		}
		
		// Step 5, broadcast the transaction via RPC for RPC blockchains
		// Or process it like any read transaction for web api blockchains
		$broadcast_successful = false;
		$this->load_coin_rpc();
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			try {
				foreach ($raw_txout as $addr => $amount) {
					$raw_txout[$addr] = sprintf('%.'.$this->db_blockchain['decimal_places'].'F', $amount);
				}
				$raw_transaction = $this->coin_rpc->createrawtransaction($raw_txin, $raw_txout);
				try {
					$signed_raw_transaction = $this->coin_rpc->signrawtransactionwithwallet($raw_transaction);
				}
				catch (Exception $e) {
					$signed_raw_transaction = $this->coin_rpc->signrawtransaction($raw_transaction);
				}
				$decoded_transaction = $this->coin_rpc->decoderawtransaction($signed_raw_transaction['hex']);
				$tx_hash = $decoded_transaction['txid'];
				
				$sendraw_response = $this->coin_rpc->sendrawtransaction($signed_raw_transaction['hex']);
				
				if (isset($sendraw_response['message'])) {
					$this->app->cancel_transaction($transaction_id ?? null, $affected_input_ids, $created_input_ids);
					$error_message = $sendraw_response['message'];
					$broadcast_successful = false;
				}
				else {
					$verified_tx_hash = $sendraw_response;
					
					$this->walletnotify($verified_tx_hash, FALSE);
					
					$db_transaction = $this->fetch_transaction_by_hash($verified_tx_hash);
					
					if ($db_transaction) {
						$transaction_id = $db_transaction['transaction_id'];
						$error_message = "Success!";
						$broadcast_successful = true;
					}
					else {
						$error_message = "Failed to find the TX in db after sending.";
						$broadcast_successful = false;
					}
				}
			}
			catch (Exception $e) {
				$error_message = "There was an error with one of the RPC calls.";
				$broadcast_successful = false;
			}
		}
		else {
			$broadcast_successful = true;
			
			$add_successful = null;
			$this->add_transaction($tx_hash, $block_id, true, $add_successful, false, [false], false);
			
			if ($this->db_blockchain['p2p_mode'] == "web_api") {
				$this->web_api_push_transaction($transaction_id);
			}
			
			$error_message = "Finished adding the transaction";
		}
		
		if ($broadcast_successful) return $transaction_id;
		else return false;
	}
	
	public function new_block(&$log_text) {
		// This function only runs for blockchains with p2p_mode='none'
		$last_block_id = (int) $this->last_block_id();
		$prev_block = $this->fetch_block_by_id($last_block_id);
		
		$this->app->run_insert_query("blocks", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $last_block_id+1,
			'block_hash' => $this->app->random_hex_string(64),
			'time_created' => time(),
			'time_loaded' => time(),
			'time_mined' => time(),
			'sec_since_prev_block' => empty($prev_block) ? null : time()-$prev_block['time_mined'],
			'locally_saved' => 0
		]);
		$internal_block_id = $this->app->last_insert_id();
		
		$block = $this->fetch_block_by_internal_id($internal_block_id);
		$created_block_id = $block['block_id'];
		$mining_block_id = $created_block_id+1;
		
		$log_text .= "Created block $created_block_id<br/>\n";
		
		$num_transactions = 0;
		
		$ref_account = false;
		$mined_address_str = $this->app->random_string(34);
		$mined_address = $this->create_or_fetch_address($mined_address_str, true, null);
		
		$mined_error = false;
		$mined_transaction_id = $this->create_transaction('coinbase', array($this->db_blockchain['initial_pow_reward']), $created_block_id, false, array($mined_address['address_id']), 0, $mined_error);
		$num_transactions++;
		$this->app->run_query("UPDATE transactions SET position_in_block='0' WHERE transaction_id=:transaction_id;", [
			'transaction_id' => $mined_transaction_id
		]);
		
		// Include all unconfirmed TXs in the just-mined block
		$unconfirmed_txs = $this->app->run_query("SELECT * FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$fee_sum = 0;
		$tx_error = false;
		
		while ($unconfirmed_tx = $unconfirmed_txs->fetch()) {
			$coins_in = $this->app->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->app->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if (($coins_in > 0 && $coins_in >= $coins_out) || $unconfirmed_tx['transaction_desc'] == "coinbase") {
				$fee_amount = $coins_in - $coins_out;
				
				$successful = true;
				$db_transaction = $this->add_transaction($unconfirmed_tx['tx_hash'], $created_block_id, true, $successful, $num_transactions, [false], false);
				if (!$successful) {
					$tx_error = true;
					$log_text .= "failed to add tx ".$unconfirmed_tx['tx_hash']."<br/>\n";
				}
				
				$fee_sum += $fee_amount;
				$num_transactions++;
			}
		}
		
		list($verification_any_error) = BlockchainVerifier::verifyBlock($this->app, $this->db_blockchain['blockchain_id'], $created_block_id);
		
		if ($verification_any_error || $tx_error) {
			$msg = "Block verification failed when creating ".$this->db_blockchain['blockchain_name']." block ".$created_block_id;
			$this->app->log_message($msg);
			$log_text .= $message;
			
			$this->delete_blocks_from_height($created_block_id);
			
			return false;
		}
		else {
			$this->app->run_query("UPDATE blocks SET num_transactions=:num_transactions, locally_saved=1 WHERE internal_block_id=:internal_block_id;", [
				'num_transactions' => $num_transactions,
				'internal_block_id' => $internal_block_id
			]);
			$block['locally_saved'] = 1;
			$block['num_transactions'] = $num_transactions;
			$this->set_last_complete_block($block['block_id']);
			$this->set_block_stats($block);
			$this->render_transactions_in_block($block, false);
			
			return $created_block_id;
		}
	}
	
	public function set_last_hash_time($time) {
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$this->app->run_query("UPDATE blockchains SET last_hash_time=:last_hash_time WHERE blockchain_id=:blockchain_id;", [
				'last_hash_time' => $time,
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
		}
	}
	
	public function games_by_transaction($db_transaction) {
		return $this->app->run_query("SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE (io.create_transaction_id=:transaction_id OR io.spend_transaction_id=:transaction_id) GROUP BY g.game_id ORDER BY g.game_id ASC;", [
			'transaction_id' => $db_transaction['transaction_id']
		])->fetchAll();
	}
	
	public function games_by_io($io_id) {
		return $this->app->run_query("SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id WHERE gio.io_id=:io_id GROUP BY g.game_id ORDER BY g.game_id ASC;", [
			'io_id' => $io_id
		])->fetchAll();
	}
	
	public function games_by_address($db_address) {
		return $this->app->run_query("SELECT g.* FROM games g JOIN transaction_game_ios gio ON g.game_id=gio.game_id JOIN transaction_ios io ON gio.io_id=io.io_id WHERE io.address_id=:address_id AND io.blockchain_id=:blockchain_id GROUP BY g.game_id ORDER BY g.game_id ASC;", [
			'address_id' => $db_address['address_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetchAll();
	}
	
	public function web_api_fetch_block($block_height) {
		$remote_url = $this->authoritative_peer['base_url']."/api/block/".$this->db_blockchain['url_identifier']."/".$block_height;
		$remote_response_raw = file_get_contents($remote_url);
		return get_object_vars(json_decode($remote_response_raw));
	}
	
	public function web_api_fetch_blocks($from_block_height, $to_block_height, $no_cache) {
		$remote_url = $this->authoritative_peer['base_url']."/api/blocks/".$this->db_blockchain['url_identifier']."/".$from_block_height.":".$to_block_height;
		if ($no_cache) $remote_url .= "?no_cache=1";
		
		if ($raw_response = file_get_contents($remote_url)) {
			if ($remote_response = json_decode($raw_response)) return $remote_response->blocks;
			else return [];
		}
		else return [];
	}
	
	public function web_api_fetch_blockchain() {
		$remote_url = $this->authoritative_peer['base_url']."/api/blockchain/".$this->db_blockchain['url_identifier']."/";
		if ($remote_response_raw = file_get_contents($remote_url)) {
			if ($remote_response_json = json_decode($remote_response_raw)) {
				return get_object_vars($remote_response_json);
			}
			else return null;
		}
		else return null;
	}
	
	public function web_api_push_transaction($transaction_id) {
		$tx = $this->app->run_query("SELECT transaction_id, block_id, transaction_desc, tx_hash, amount, fee_amount, time_created, position_in_block, num_inputs, num_outputs FROM transactions WHERE transaction_id=:transaction_id;", [
			'transaction_id' => $transaction_id
		])->fetch(PDO::FETCH_ASSOC);
		
		if ($tx) {
			list($inputs, $outputs) = $this->app->web_api_transaction_ios($tx['transaction_id']);
			
			unset($tx['transaction_id']);
			$tx['inputs'] = $inputs;
			$tx['outputs'] = $outputs;
			$data_txt = json_encode($tx, JSON_PRETTY_PRINT);
			$data['data'] = $data_txt;
			
			$url = $this->authoritative_peer['base_url']."/api/transactions/".$this->db_blockchain['url_identifier']."/post/";
			
			$remote_response = $this->app->curl_post_request($url, $data, false);
		}
	}
	
	public function add_transaction_from_web_api($block_height, &$tx) {
		$this->app->dbh->beginTransaction();
		
		$new_tx_params = [
			'time_created' => time(),
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'transaction_desc' => $tx['transaction_desc'],
			'tx_hash' => $tx['tx_hash'],
			'amount' => $tx['amount'],
			'fee_amount' => $tx['fee_amount'],
			'num_inputs' => (int)$tx['num_inputs'],
			'num_outputs' => (int)$tx['num_outputs']
		];
		if ((string)$block_height !== "") {
			$new_tx_params['block_id'] = $block_height;
			$new_tx_params['position_in_block'] = (int)$tx['position_in_block'];
		}
		$this->app->run_insert_query("transactions", $new_tx_params);
		$transaction_id = $this->app->last_insert_id();
		
		for ($in_index=0; $in_index<count($tx['inputs']); $in_index++) {
			$tx_input = get_object_vars($tx['inputs'][$in_index]);
			
			if (empty(AppSettings::getParam('sqlite_db'))) {
				$update_input_q = "UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id SET io.spend_transaction_id=:transaction_id, io.in_index=:in_index";
				
				$update_input_params = [
					'transaction_id' => $transaction_id,
					'in_index' => $in_index,
					'blockchain_id' => $this->db_blockchain['blockchain_id'],
					'tx_hash' => $tx_input['tx_hash'],
					'out_index' => $tx_input['out_index']
				];
				
				if ((string)$block_height !== "") {
					$update_input_q .= ", io.spend_block_id=:spend_block_id";
					$update_input_params['spend_block_id'] = $block_height;
				}
				
				$update_input_q .= " WHERE t.blockchain_id=:blockchain_id AND t.tx_hash=:tx_hash AND io.out_index=:out_index;";
				
				$this->app->run_query($update_input_q, $update_input_params);
			}
			else {
				$update_input_q = "UPDATE transaction_ios SET spend_transaction_id=:transaction_id, in_index=:in_index";
				
				$update_input_params = [
					'transaction_id' => $transaction_id,
					'in_index' => $in_index,
					'blockchain_id' => $this->db_blockchain['blockchain_id'],
					'tx_hash' => $tx_input['tx_hash'],
					'out_index' => $tx_input['out_index']
				];
				
				if ((string)$block_height !== "") {
					$update_input_q .= ", spend_block_id=:spend_block_id";
					$update_input_params['spend_block_id'] = $block_height;
				}
				
				$update_input_q .= " WHERE out_index=:out_index AND create_transaction_id IN (SELECT transaction_id FROM transactions WHERE blockchain_id=:blockchain_id AND tx_hash=:tx_hash);";
				
				$this->app->run_query($update_input_q, $update_input_params);
			}
		}
		
		$first_passthrough_index = false;
		
		for ($out_index=0; $out_index<count($tx['outputs']); $out_index++) {
			$tx_output = get_object_vars($tx['outputs'][$out_index]);
			$db_address = $this->create_or_fetch_address($tx_output['address'], false, null);
			
			if ($first_passthrough_index === false && $db_address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
			
			$new_output_params = [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'out_index' => $out_index,
				'address_id' => $db_address['address_id'],
				'option_index' => $tx_output['option_index'],
				'create_block_id' => $block_height,
				'create_transaction_id' => $transaction_id,
				'amount' => $tx_output['amount'],
				'is_destroy' => $db_address['is_destroy_address'],
				'is_separator' => $db_address['is_separator_address'],
				'is_passthrough' => $db_address['is_passthrough_address'],
				'is_receiver' => ($first_passthrough_index !== false && $db_address['is_destroy_address']+$db_address['is_separator_address']+$db_address['is_passthrough_address'] == 0) ? 1 : 0
			];
			
			if ((string)$block_height !== "") {
				$new_output_params["spend_status"] = "unspent";
			}
			
			$this->app->run_insert_query("transaction_ios", $new_output_params);
		}
		
		$this->app->dbh->commit();
		
		return $transaction_id;
	}
	
	public function seconds_per_block($target_or_average) {
		if ($target_or_average == "target") return $this->db_blockchain['seconds_per_block'];
		else {
			if (empty($this->db_blockchain['average_seconds_per_block'])) return $this->db_blockchain['seconds_per_block'];
			else return $this->db_blockchain['average_seconds_per_block'];
		}
	}
	
	public function transactions_by_event($event_id) {
		return $this->app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE t.blockchain_id=:blockchain_id AND gio.event_id=:event_id GROUP BY t.transaction_id ORDER BY t.block_id ASC, t.position_in_block ASC;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'event_id' => $event_id
		]);
	}
	
	public function generate_addresses($time_limit_seconds) {
		$start_time = microtime(true);
		$this->load_coin_rpc();
		$new_addr_count = 0;
		$error_message = "";
		
		do {
			$new_addr_txt = $this->coin_rpc->getnewaddress("", "legacy");
			$new_addr_db = $this->create_or_fetch_address($new_addr_txt, true, null);
			$new_addr_count++;
		}
		while (microtime(true) <= $start_time+$time_limit_seconds);
		
		$error_message .= "Added ".$new_addr_count." ".$this->db_blockchain['blockchain_name']." addresses.\n";
		return $error_message;
	}
	
	public function set_average_seconds_per_block($force_set) {
		$avg = $this->app->run_query("SELECT AVG(sec_since_prev_block) FROM `blocks` WHERE blockchain_id=:blockchain_id AND sec_since_prev_block < :max_seconds_per_block AND sec_since_prev_block>1 AND block_id>:block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'max_seconds_per_block' => ($this->seconds_per_block('target')*10),
			'block_id' => $this->db_blockchain['last_complete_block']-100
		])->fetch();
		
		$this->app->run_query("UPDATE blockchains SET average_seconds_per_block=:average_seconds_per_block WHERE blockchain_id=:blockchain_id;", [
			'average_seconds_per_block' => $avg['AVG(sec_since_prev_block)'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->db_blockchain['average_seconds_per_block'] = $avg['AVG(sec_since_prev_block)'];
	}
	
	public function last_active_time() {
		$recent_block = $this->most_recently_loaded_block();
		
		if (!empty($this->db_blockchain['rpc_last_time_connected'])) $blockchain_last_active = $this->db_blockchain['rpc_last_time_connected'];
		else $blockchain_last_active = false;
		
		if (!empty($recent_block['time_loaded']) && $recent_block['time_loaded'] > $blockchain_last_active) $blockchain_last_active = $recent_block['time_loaded'];
		
		return $blockchain_last_active;
	}
	
	public function light_sync($print_debug=false) {
		$any_error = false;
		$error_message = "";
		
		if ((string) $this->db_blockchain['first_required_block'] == "") {
			if ($this->db_blockchain['p2p_mode'] == "rpc") {
				$this->load_coin_rpc();
				
				$this->load_new_blocks($print_debug);
				
				$last_block_id = $this->last_block_id();
				$mining_block_id = $last_block_id+1;
				
				$rpc_transactions = $this->coin_rpc->listreceivedbyaddress(0);
				
				$tx_by_confirmations = AppSettings::arrayToMapOnKey($rpc_transactions, "confirmations", true);
				
				$relevant_confirmations = array_keys($tx_by_confirmations);
				
				rsort($relevant_confirmations, SORT_NUMERIC);
				
				if ($print_debug) echo "Processing ".count($rpc_transactions)." transactions.\n";
				
				foreach ($relevant_confirmations as $confirmation_count) {
					foreach ($tx_by_confirmations[$confirmation_count] as $rpc_tx_by_address) {
						foreach ($rpc_tx_by_address->txids as $tx_hash) {
							$confirmed_block_id = null;
							
							$rpc_transaction = (object) $this->coin_rpc->gettransaction($tx_hash);
							
							if (!empty($rpc_transaction->confirmations)) {
								$expected_block_height = $mining_block_id - $rpc_transaction->confirmations;
								$expected_db_block = $this->fetch_block_by_id($expected_block_height);
								
								if (empty($expected_db_block['block_hash'])) {
									$this->set_block_hash_by_height($expected_db_block['block_id']);
									$expected_db_block = $this->fetch_block_by_id($expected_block_height);
								}
								
								if ($expected_db_block['block_hash'] == $rpc_transaction->blockhash) $confirmed_block_id = $expected_db_block['block_id'];
								else {
									$tx_rpc_block = (object) $this->coin_rpc->getblock($rpc_transaction->blockhash);
									$corrected_db_block = $this->fetch_block_by_id($tx_rpc_block->height);
									
									if (empty($corrected_db_block['block_hash'])) $this->set_block_hash_by_height($tx_rpc_block->height);
									
									$corrected_db_block = $this->fetch_block_by_id($tx_rpc_block->height);
									
									$confirmed_block_id = $corrected_db_block['block_id'];
								}
							}
							
							$existing_tx = $this->fetch_transaction_by_hash($rpc_transaction->txid);
							
							if ($print_debug) echo "Processing tx: ".$rpc_transaction->txid." (block #".$confirmed_block_id."): ";
							
							if ($existing_tx && $existing_tx['block_id'] == $confirmed_block_id && $existing_tx['has_all_outputs']) {
								if ($print_debug) echo "skip\n";
							}
							else {
								$require_inputs = false;
								$tx_successful = null;
								$transaction = $this->add_transaction($rpc_transaction->txid, $confirmed_block_id, $require_inputs, $tx_successful, $rpc_transaction->blockindex, [false], $print_debug);
								
								if ($print_debug) {
									if ($tx_successful) echo "successful";
									else echo "failed";
									echo "\n";
								}
							}
						}
					}
				}
			}
			else {
				$any_error = true;
				$error_message = "Light mode only applies to RPC blockchains.";
			}
		}
		else {
			$any_error = true;
			$error_message = $this->db_blockchain['blockchain_name']." doesn't use light mode.";
		}
		
		return [$any_error, $error_message];
	}
	
	public function spendable_ios_in_blockchain_account($account_id) {
		$spendable_io_params = [
			'account_id' => $account_id
		];
		$spendable_io_q = "SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status IN ('unspent') AND k.account_id=:account_id ORDER BY io.io_id ASC;";
		return $this->app->run_query($spendable_io_q, $spendable_io_params);
	}
	
	public function fetch_coinbase_ios($unclaimed_only, $unspent_only, $mature_only, $limit) {
		$coinbase_io_q = "SELECT io.* FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN address_keys ak ON io.address_id=ak.address_id WHERE t.blockchain_id=:blockchain_id AND t.transaction_desc='coinbase'";
		
		$coinbase_io_params = [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		];
		
		if ($unspent_only) {
			$coinbase_io_q .= " AND io.spend_status='unspent'";
		}
		
		if ($unclaimed_only) {
			$coinbase_io_q .= " AND ak.account_id IS NULL";
		}
		
		if ($mature_only) {
			$coinbase_io_q .= " AND io.create_block_id <= :mature_at_block_height";
			$coinbase_io_params['mature_at_block_height'] = $this->last_block_id()-$this->db_blockchain['coinbase_maturity']+1;
		}
		
		$coinbase_io_q .= " ORDER BY t.block_id ASC";
		
		if ($limit) {
			$coinbase_io_q .= " LIMIT :limit";
			$coinbase_io_params['limit'] = $limit;
		}
		
		return $this->app->run_limited_query($coinbase_io_q, $coinbase_io_params)->fetchAll();
	}
	
	public function set_processed_my_addresses_to_block($block_id) {
		$this->app->run_query("UPDATE blockchains SET processed_my_addresses_to_block=:processed_my_addresses_to_block WHERE blockchain_id=:blockchain_id;", [
			'processed_my_addresses_to_block' => $block_id,
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		
		$this->db_blockchain['processed_my_addresses_to_block'] = $block_id;
	}
	
	public function fetch_tx_by_position_in_block($block_height, $position_in_block) {
		return $this->app->run_query("SELECT * FROM transactions WHERE blockchain_id=:blockchain_id AND block_id=:block_id AND position_in_block=:position_in_block;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_height,
			'position_in_block' => $position_in_block
		])->fetch();
	}
	
	public function fetch_io_by_position_in_tx($transaction, $position) {
		return $this->app->run_query("SELECT * FROM transaction_ios WHERE create_transaction_id=:transaction_id AND out_index=:out_index;", [
			'transaction_id' => $transaction['transaction_id'],
			'out_index' => $position
		])->fetch();
	}
}
?>