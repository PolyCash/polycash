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
		if ($this->db_blockchain['first_required_block'] === "") {
			$this->set_first_required_block();
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
		$complete_block_q = "SELECT MIN(block_id) FROM blocks WHERE blockchain_id=:blockchain_id";
		if ($this->db_blockchain['first_required_block'] !== "") {
			$complete_block_q .= " AND block_id >= :first_required_block";
			$complete_block_params['first_required_block'] = $this->db_blockchain['first_required_block'];
		}
		$complete_block_q .= " AND locally_saved=0;";
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
	
	public function web_api_add_block(&$db_block, &$api_block, $headers_only, $print_debug) {
		$start_time = microtime(true);
		$html = "";
		
		if (empty($api_block)) {
			$api_response = $this->web_api_fetch_block($db_block['block_id']);
			$api_block = get_object_vars($api_response['blocks'][0]);
		}
		
		if (!empty($api_block['block_hash'])) {
			if (empty($db_block['block_id'])) {
				$this->app->run_query("INSERT INTO blocks SET blockchain_id=:blockchain_id, block_hash=:block_hash, block_id=:block_id, time_created=:time_created, locally_saved=0;", [
					'blockchain_id' => $this->db_blockchain['blockchain_id'],
					'block_hash' => $api_block['block_hash'],
					'block_id' => $db_block['block_id'],
					'time_created' => time()
				]);
				$internal_block_id = $this->app->last_insert_id();
				$db_block = $this->fetch_block_by_internal_id($internal_block_id);
			}
			
			if (empty($db_block['block_hash'])) {
				$this->app->run_query("UPDATE blocks SET block_hash=:block_hash WHERE internal_block_id=:internal_block_id;", [
					'block_hash' => $api_block['block_hash'],
					'internal_block_id' => $db_block['internal_block_id']
				]);
			}
			
			if ($db_block['locally_saved'] == 0 && !$headers_only) {
				if ($db_block['num_transactions'] == "") {
					$prev_block = $this->fetch_block_by_id($db_block['block_id']-1);
					$this->app->run_query("UPDATE blocks SET time_mined=:time_mined, sec_since_prev_block=:sec_since_prev_block, num_transactions=:num_transactions WHERE internal_block_id=:internal_block_id;", [
						'time_mined' =>$api_block['time_mined'],
						'num_transactions' => count($api_block['transactions']),
						'internal_block_id' => $db_block['internal_block_id'],
						'sec_since_prev_block' => $prev_block['time_mined'] ? $db_block['time_mined']-$prev_block['time_mined'] : null
					]);
				}
				
				$coins_created = 0;
				
				$tx_error = false;
				
				for ($i=0; $i<count($api_block['transactions']); $i++) {
					$tx = get_object_vars($api_block['transactions'][$i]);
					$tx_hash = $tx['tx_hash'];
					
					$existing_tx = $this->fetch_transaction_by_hash($tx_hash);
					
					if (!$existing_tx) {
						$transaction_id = $this->add_transaction_from_web_api($db_block['block_id'], $tx);
					}
					
					$successful = true;
					$db_transaction = $this->add_transaction($tx_hash, $db_block['block_id'], true, $successful, $i, [false], $print_debug);
					
					if ($db_transaction['transaction_desc'] != "transaction") $coins_created += $db_transaction['amount'];
				}
				
				if (!$tx_error) {
					$this->app->run_query("UPDATE blocks SET locally_saved=1, time_loaded=:time_loaded WHERE internal_block_id=:internal_block_id;", [
						'time_loaded' => time(),
						'internal_block_id' => $db_block['internal_block_id']
					]);
					$db_block['locally_saved'] = 1;
					$this->render_transactions_in_block($db_block, false);
				}
				$this->set_block_stats($db_block);
				
				$this->app->run_query("UPDATE blocks SET load_time=load_time+:add_load_time WHERE internal_block_id=:internal_block_id;", [
					'add_load_time' => (microtime(true)-$start_time),
					'internal_block_id' => $db_block['internal_block_id']
				]);
				
				$html .= "Took ".(microtime(true)-$start_time)." sec to add block #".$db_block['block_id']."<br/>\n";
			}
		}
		
		return $html;
	}
	
	public function coind_add_block($block_hash, $block_height, $headers_only, $print_debug) {
		$start_time = microtime(true);
		$any_error = false;
		$this->load_coin_rpc();
		
		$db_block = $this->fetch_block_by_id($block_height);
		
		if ($db_block && $db_block['locally_saved'] == 0 && !empty($db_block['num_transactions'])) {
			$message = "Incomplete block found, resetting ".$this->db_blockchain['blockchain_name']." from block ".$block_height;
			$this->app->log_message($message);
			
			if ($print_debug) {
				echo $message."\n";
				$this->app->flush_buffers();
			}
			$this->delete_blocks_from_height($block_height);
			$this->load_new_blocks($print_debug);
			$db_block = $this->fetch_block_by_id($block_height);
		}
		
		if (!$db_block) {
			$this->app->run_query("INSERT INTO blocks SET blockchain_id=:blockchain_id, block_hash=:block_hash, block_id=:block_id, time_created=:time_created, locally_saved=0;", [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'block_hash' => $block_hash,
				'block_id' => $block_height,
				'time_created' => time()
			]);
			$internal_block_id = $this->app->last_insert_id();
			$db_block = $this->fetch_block_by_internal_id($internal_block_id);
		}
		
		if (empty($db_block['block_hash'])) {
			$this->app->run_query("UPDATE blocks SET block_hash=:block_hash WHERE internal_block_id=:internal_block_id;", [
				'block_hash' => $block_hash,
				'internal_block_id' => $db_block['internal_block_id']
			]);
		}
		
		if ($this->coin_rpc && $db_block['locally_saved'] == 0 && !$headers_only) {
			try {
				$lastblock_rpc = $this->coin_rpc->getblock($block_hash);
			}
			catch (Exception $e) {
				var_dump($e);
				die("RPC failed to get block $block_hash");
			}
			
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
			if ($print_debug) {
				echo "\nLoading ".count($lastblock_rpc['tx'])." in block ".$db_block['block_id'];
				$this->app->flush_buffers();
			}
			for ($i=0; $i<count($lastblock_rpc['tx']); $i++) {
				$tx_hash = $lastblock_rpc['tx'][$i];
				$successful = true;
				$db_transaction = $this->add_transaction($tx_hash, $block_height, true, $successful, $i, [false], $print_debug);
				if ($print_debug) {
					echo ". ";
					$this->app->flush_buffers();
				}
				if (!$successful) {
					$any_error = true;
					$i = count($lastblock_rpc['tx']);
					
					if ($print_debug) {
						echo "Failed to add tx ".$tx_hash."\n";
						$this->app->flush_buffers();
					}
				}
			}
			
			if (!$any_error) {
				$this->set_block_stats($db_block);
				
				$this->try_start_games($block_height);
			}
			
			$update_block_params = [
				'add_load_time' => (microtime(true)-$start_time),
				'internal_block_id' => $db_block['internal_block_id']
			];
			$update_block_q = "UPDATE blocks SET ";
			if (!$any_error) {
				$update_block_q .= "locally_saved=1, time_loaded=:time_loaded, ";
				$update_block_params['time_loaded'] = time();
				$db_block['locally_saved'] = 1;
				$this->render_transactions_in_block($db_block, false);
			}
			$update_block_q .= "load_time=load_time+:add_load_time WHERE internal_block_id=:internal_block_id;";
			$this->app->run_query($update_block_q, $update_block_params);
			
			if ($print_debug) {
				echo (microtime(true)-$start_time)." sec<br/>";
				$this->app->flush_buffers();
			}
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
			$start_game->start_game();
		}
	}
	
	public function add_transaction($tx_hash, $block_height, $require_inputs, &$successful, $position_in_block, $only_vout_params, $show_debug) {
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
		
		$successful = true;
		$start_time = microtime(true);
		$benchmark_time = $start_time;
		$this->load_coin_rpc();
		
		$add_transaction = true;
		
		// Get the transaction by RPC and find its block height if not set
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			try {
				$transaction_rpc = $this->coin_rpc->getrawtransaction($tx_hash, true);
				
				if ($block_height === false) {
					if (!empty($transaction_rpc['blockhash'])) {
						$tx_block = $this->fetch_block_by_hash($transaction_rpc['blockhash']);
						
						if ($tx_block) $block_height = $tx_block['block_id'];
						else {
							if ($this->db_blockchain['supports_getblockheader'] == 1) {
								$rpc_block = $this->coin_rpc->getblockheader($transaction_rpc['blockhash']);
							}
							else {
								$rpc_block = $this->coin_rpc->getblock($transaction_rpc['blockhash']);
							}
							$block_height = $rpc_block['height'];
							$this->set_block_hash($block_height, $transaction_rpc['blockhash']);
						}
					}
				}
			}
			catch (Exception $e) {
				$message = "getrawtransaction($tx_hash) failed.\n";
				
				if ($show_debug) {
					echo $message;
					$this->app->flush_buffers();
				}
				$add_transaction = false;
			}
		}
		
		if ($add_transaction) {
			// Check for existing TX and maybe reset its outputs if it's being confirmed (for transaction malleability)
			$existing_tx = $this->fetch_transaction_by_hash($tx_hash);
			
			if ($existing_tx) {
				$db_transaction_id = $existing_tx['transaction_id'];
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					// Now handle cases where we are adding by RPC and there's an existing transaction
					
					// If only_vout is set, we are adding an output before the first require block
					// and therefore need to run add_transaction without deleting anything
					
					if ($only_vout === false) {
						// If existing tx is unconfirmed and we are trying to add as unconfirmed, load_unconfirmed_transactions is just trying to add unnecessarily so skip
						
						// Else if existing tx matches the block we're adding in, why was this function called?
						// (maybe trying to load blocks before first required block without resetting the blockchain first?) So skip
						
						// Else we are confirming an unconfirmed transaction so delete outputs and re-add (solves transaction malleability)
						
						if ($block_height === false && $existing_tx['block_id'] == "") $add_transaction = false;
						else if ($existing_tx['block_id'] == $block_height) $add_transaction = false;
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
		}
		
		if (!$add_transaction) return false;
		
		$spend_io_ids = [];
		$input_sum = 0;
		$coin_blocks_destroyed = 0;
		$coin_rounds_destroyed = 0;
		
		if ($this->db_blockchain['p2p_mode'] != "rpc") {
			$transaction_type = $existing_tx['transaction_desc'];
			
			$spend_ios = $this->app->run_query("SELECT * FROM transaction_ios WHERE spend_transaction_id=:spend_transaction_id;", [
				'spend_transaction_id' => $db_transaction_id
			]);
			
			while ($spend_io = $spend_ios->fetch()) {
				$spend_io_ids[count($spend_io_ids)] = $spend_io['io_id'];
				$input_sum += $spend_io['amount'];
				
				if ($block_height === false) {
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
			if ($add_transaction) {
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
					$new_tx_q = "INSERT INTO transactions SET blockchain_id=:blockchain_id, transaction_desc=:transaction_desc, tx_hash=:tx_hash, num_inputs=:num_inputs, num_outputs=:num_outputs, time_created=:time_created;";
					$this->app->run_query($new_tx_q, $new_tx_params);
					$db_transaction_id = $this->app->last_insert_id();
				}
				
				$benchmark_time = microtime(true);
				
				if ($transaction_type == "transaction" && $require_inputs) {
					for ($in_index=0; $in_index<count($inputs); $in_index++) {
						$spend_io_params = [
							'blockchain_id' => $this->db_blockchain['blockchain_id'],
							'tx_hash' => $inputs[$in_index]["txid"],
							'out_index' => $inputs[$in_index]["vout"]
						];
						$spend_io_q = "SELECT * FROM transactions t JOIN transaction_ios i ON t.transaction_id=i.create_transaction_id WHERE t.blockchain_id=:blockchain_id AND t.tx_hash=:tx_hash AND i.out_index=:out_index;";
						$spend_io = $this->app->run_query($spend_io_q, $spend_io_params)->fetch();
						
						if (!$spend_io) {
							$child_successful = true;
							$new_tx = $this->add_transaction($inputs[$in_index]["txid"], false, false, $child_successful, false, [
								$inputs[$in_index]["vout"],
								$db_transaction_id,
								$block_height,
								$in_index
							], $show_debug);
							
							$spend_io = $this->app->run_query($spend_io_q, $spend_io_params)->fetch();
							
							if (!$spend_io) {
								$successful = false;
								$error_message = "Failed to create inputs for tx #".$db_transaction_id.", created tx #".$new_tx['transaction_id']." then looked for tx_hash=".$inputs[$in_index]['txid'].", vout=".$inputs[$in_index]['vout'];
								$this->app->log_message($error_message);
								if ($show_debug) {
									echo $error_message."\n";
									$this->app->flush_buffers();
								}
							}
						}
						
						if ($successful) {
							$spend_io_ids[$in_index] = $spend_io['io_id'];
							$input_sum += (int) $spend_io['amount'];
							
							if ($block_height !== false) {
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
		}
		$benchmark_time = microtime(true);
		
		if ($this->db_blockchain['p2p_mode'] != "rpc" || $successful) {
			$output_io_ids = [];
			$output_io_indices = [];
			$output_io_address_ids = [];
			$output_is_destroy = [];
			$output_is_separator = [];
			$output_is_passthrough = [];
			$output_is_receiver = [];
			$separator_io_ids = [];
			$output_sum = 0;
			$output_destroy_sum = 0;
			$last_regular_output_index = false;
			$first_passthrough_index = false;
			
			if ($this->db_blockchain['p2p_mode'] != "rpc") {
				$outputs = [];
				
				if ($block_height !== false) {
					$this->app->run_query("UPDATE transaction_ios SET spend_status='unspent', create_block_id=:create_block_id WHERE create_transaction_id=:create_transaction_id;", [
						'create_block_id' => $block_height,
						'create_transaction_id' => $existing_tx['transaction_id']
					]);
				}
				
				$out_ios = $this->app->run_query("SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:create_transaction_id ORDER BY io.out_index ASC;", [
					'create_transaction_id' => $existing_tx['transaction_id']
				]);
				
				$out_index=0;
				
				while ($out_io = $out_ios->fetch()) {
					if ($first_passthrough_index == false && $out_io['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
					
					$outputs[$out_index] = array("value"=>$out_io['amount']/pow(10,$this->db_blockchain['decimal_places']));
					
					$output_address = $this->create_or_fetch_address($out_io['address'], true, false, true, false, false);
					$output_io_indices[$out_index] = $output_address['option_index'];
					
					$output_io_ids[$out_index] = $out_io['io_id'];
					$output_io_address_ids[$out_index] = $output_address['address_id'];
					$output_is_destroy[$out_index] = $out_io['is_destroy_address'];
					$output_is_separator[$out_index] = $out_io['is_separator_address'];
					$output_is_passthrough[$out_index] = $out_io['is_passthrough_address'];
					
					$output_is_receiver[$out_index] = 0;
					if ($first_passthrough_index !== false && $out_io['is_destroy_address'] == 0 && $out_io['is_separator_address'] == 0 && $out_io['is_passthrough_address'] == 0) $output_is_receiver[$out_index] = 1;
					
					if ($output_is_separator[$out_index] == 1) array_push($separator_io_ids, $out_io['io_id']);
					
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
					
					if (!empty($outputs[$out_index]["scriptPubKey"]) && (!empty($outputs[$out_index]["scriptPubKey"]["addresses"]) || !empty($outputs[$out_index]["scriptPubKey"]["hex"]))) {
						$script_type = $outputs[$out_index]["scriptPubKey"]["type"];
						
						if (!empty($outputs[$out_index]["scriptPubKey"]["addresses"])) $address_text = $outputs[$out_index]["scriptPubKey"]["addresses"][0];
						else $address_text = $outputs[$out_index]["scriptPubKey"]["hex"];
						if (strlen($address_text) > 50) $address_text = substr($address_text, 0, 50);
						
						$output_address = $this->create_or_fetch_address($address_text, true, false, true, false, false);
						
						if ($first_passthrough_index == false && $output_address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
						
						$new_io_amount = (int)($outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']));
						
						$new_io_params = [
							'spend_status' => $output_spend_status,
							'blockchain_id' => $this->db_blockchain['blockchain_id'],
							'script_type' => $script_type,
							'out_index' => $out_index,
							'address_id' => $output_address['address_id'],
							'create_transaction_id' => $db_transaction_id,
							'amount' => $new_io_amount,
							'is_destroy' => $output_address['is_destroy_address'],
							'is_separator' => $output_address['is_separator_address'],
							'is_passthrough' => $output_address['is_passthrough_address']
						];
						$new_io_q = "INSERT INTO transaction_ios SET spend_status=:spend_status, blockchain_id=:blockchain_id, script_type=:script_type, out_index=:out_index, address_id=:address_id";
						if ($output_address['user_id'] > 0) {
							$new_io_q .= ", user_id=:user_id";
							$new_io_params['user_id'] = $output_address['user_id'];
						}
						if ($output_address['option_index'] != "") {
							$new_io_q .= ", option_index=:option_index";
							$new_io_params['option_index'] = $output_address['option_index'];
							$output_io_indices[$out_index] = $output_address['option_index'];
						}
						else $output_io_indices[$out_index] = false;
						
						if ($spend_transaction_id) {
							$new_io_q .= ", spend_transaction_id=:spend_transaction_id, in_index=:in_index";
							$new_io_params['spend_transaction_id'] = $spend_transaction_id;
							$new_io_params['in_index'] = $spend_in_index;
							
							if ($spend_block_id !== false) {
								$new_io_q .= ", coin_blocks_created=:coin_blocks_created";
								$this_io_cbc = ($spend_block_id-$block_height)*$new_io_amount;
								$new_io_params['coin_blocks_created'] = $this_io_cbc;
							}
						}
						
						$new_io_q .= ", create_transaction_id=:create_transaction_id, amount=:amount";
						if ($block_height !== false) {
							$new_io_q .= ", create_block_id=:create_block_id";
							$new_io_params['create_block_id'] = $block_height;
						}
						$new_io_q .= ", is_destroy=:is_destroy, is_separator=:is_separator, is_passthrough=:is_passthrough";
						
						$output_io_address_ids[$out_index] = $output_address['address_id'];
						$output_is_destroy[$out_index] = $output_address['is_destroy_address'];
						$output_is_separator[$out_index] = $output_address['is_separator_address'];
						$output_is_passthrough[$out_index] = $output_address['is_passthrough_address'];
						
						$output_is_receiver[$out_index] = 0;
						if ($first_passthrough_index !== false && $output_is_destroy[$out_index] == 0 && $output_is_separator[$out_index] == 0 && $output_is_passthrough[$out_index] == 0) $output_is_receiver[$out_index] = 1;
						
						$new_io_params['is_receiver'] = $output_is_receiver[$out_index];
						$new_io_q .= ", is_receiver=:is_receiver";
						
						$this->app->run_query($new_io_q, $new_io_params);
						$io_id = $this->app->last_insert_id();
						
						$output_io_ids[$out_index] = $io_id;
						if ($output_is_separator[$out_index] == 1) array_push($separator_io_ids, $io_id);
						
						$output_sum += $outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']);
						if ($output_address['is_destroy_address'] == 1) {
							$output_destroy_sum += $outputs[$out_index]["value"]*pow(10,$this->db_blockchain['decimal_places']);
						}
						else if ($output_address['is_separator_address'] == 0 && $output_address['is_passthrough_address'] == 0 && $output_is_receiver[$out_index] == 0) $last_regular_output_index = $out_index;
					}
				}
			}
			
			if (count($spend_io_ids) > 0 && $block_height === false) {
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
						
						if ($input_io['is_coinbase'] == 1) {
							if (empty($events_by_option_id[$input_io['option_id']])) {
								$db_event = $this->app->run_query("SELECT ev.*, et.* FROM events ev JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE ev.event_id=:event_id;", [
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
					
					$insert_q = "INSERT INTO transaction_game_ios (game_id, is_coinbase, io_id, game_out_index, colored_amount, destroy_amount, ref_block_id, ref_coin_blocks, ref_round_id, ref_coin_rounds, option_id, contract_parts, event_id, effectiveness_factor, effective_destroy_amount, is_resolved, resolved_before_spent) VALUES ";
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
							
							$insert_q .= "('".$color_game->db_game['game_id']."', '0', '".$output_io_ids[$out_index]."', '".$game_out_index."', '".$gio_amount."', '".$this_destroy_amount."', '".$ref_block_id."', '".$cbd."', '".$ref_round_id."', '".$crd."', ";
							$game_out_index++;
							
							if ($output_io_indices[$out_index] !== false) {
								$option_id = $color_game->option_index_to_option_id_in_block($output_io_indices[$out_index], $ref_block_id);
								if ($option_id) {
									$using_separator = false;
									if (!empty($separator_io_ids[$next_separator_i])) {
										$payout_io_id = $separator_io_ids[$next_separator_i];
										$next_separator_i++;
										$using_separator = true;
									}
									else $payout_io_id = $output_io_ids[$out_index];
									
									if (!empty($events_by_option_id[$option_id])) $event = $events_by_option_id[$option_id];
									else {
										$db_event = $this->app->run_query("SELECT ev.*, et.* FROM options op JOIN events ev ON op.event_id=ev.event_id JOIN event_types et ON ev.event_type_id=et.event_type_id WHERE op.option_id=:option_id;", [
											'option_id' => $option_id
										])->fetch();
										$events_by_option_id[$option_id] = new Event($color_game, $db_event, false);
										$event = $events_by_option_id[$option_id];
									}
									$effectiveness_factor = $event->block_id_to_effectiveness_factor($ref_block_id);
									
									$effective_destroy_amount = floor($this_destroy_amount*$effectiveness_factor);
									
									$insert_q .= "'".$option_id."', '".$color_game->db_game['default_contract_parts']."', '".$event->db_event['event_id']."', '".$effectiveness_factor."', '".$effective_destroy_amount."', 0, null";
									
									$payout_is_resolved = 0;
									if ($this_destroy_amount == 0 && $color_game->db_game['exponential_inflation_rate'] == 0) $payout_is_resolved=1;
									$this_is_resolved = $payout_is_resolved;
									if ($using_separator) $this_is_resolved = 1;
									
									$payout_insert_q = "('".$color_game->db_game['game_id']."', 1, '".$payout_io_id."', '".$game_out_index."', 0, 0, null, 0, null, 0, '".$option_id."', '".$color_game->db_game['default_contract_parts']."', '".$event->db_event['event_id']."', null, 0, ".$payout_is_resolved.", 1), ";
									$game_out_index++;
								}
								else $insert_q .= "null, null, null, null, 0, null, null";
							}
							else $insert_q .= "null, null, null, null, 0, null, null";
							
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
						$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.parent_io_id=gio.game_io_id-1 WHERE io.create_transaction_id=:create_transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=1;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
						$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON gio.io_id=io.io_id SET gio.payout_io_id=gio.game_io_id+1 WHERE gio.event_id IS NOT NULL AND io.create_transaction_id=:create_transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=0;", [
							'create_transaction_id' => $db_transaction_id,
							'game_id' => $color_game->db_game['game_id']
						]);
					}
					
					$unresolved_inputs = $this->app->run_query("SELECT * FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE io.spend_transaction_id=:transaction_id AND gio.game_id=:game_id AND gio.is_coinbase=1 AND gio.resolved_before_spent=0 ORDER BY io.in_index ASC;", [
						'transaction_id' => $db_transaction_id,
						'game_id' => $color_game->db_game['game_id']
					])->fetchAll();
					
					$receiver_outputs = $this->app->run_query("SELECT io.*, a.* FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id=:transaction_id AND io.is_receiver=1 ORDER BY io.out_index ASC;", ['transaction_id'=>$db_transaction_id])->fetchAll();
					
					if (count($unresolved_inputs) > 0 && count($receiver_outputs) > 0) {
						$insert_q = "INSERT INTO transaction_game_ios (parent_io_id, game_id, io_id, game_out_index, is_coinbase, colored_amount, destroy_amount, coin_blocks_destroyed, coin_rounds_destroyed, option_id, contract_parts, event_id, is_resolved, resolved_before_spent) VALUES ";
						
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
									
									$insert_q .= "('".$unresolved_input['parent_io_id']."', '".$color_game->db_game['game_id']."', '".$this_receiver_output['io_id']."', '".$game_out_index."', 1, 0, 0, 0, 0, '".$unresolved_input['option_id']."', '".$contract_parts."', '".$unresolved_input['event_id']."', 0, 1), ";
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
			
			$benchmark_time = microtime(true);
			
			$fee_amount = ($input_sum-$output_sum);
			if ($transaction_type == "coinbase" || !$require_inputs) $fee_amount = 0;
			
			$update_tx_params = [
				'add_load_time' => (microtime(true)-$start_time),
				'amount' => $output_sum,
				'fee_amount' => $fee_amount,
				'transaction_id' => $db_transaction_id
			];
			$update_tx_q = "UPDATE transactions SET load_time=load_time+:add_load_time";
			if (!$only_vout) $update_tx_q .= ", has_all_outputs=1";
			if ($require_inputs) $update_tx_q .= ", has_all_inputs=1";
			if ($block_height !== false) {
				$update_tx_q .= ", block_id=:block_id";
				$update_tx_params['block_id'] = $block_height;
			}
			if ($position_in_block !== false) {
				$update_tx_q .= ", position_in_block=:position_in_block";
				$update_tx_params['position_in_block'] = $position_in_block;
			}
			$update_tx_q .= ", amount=:amount, fee_amount=:fee_amount WHERE transaction_id=:transaction_id;";
			$this->app->run_query($update_tx_q, $update_tx_params);
			
			$db_transaction = $this->app->fetch_transaction_by_id($db_transaction_id);
			return $db_transaction;
		}
		else {
			return false;
		}
	}
	
	public function walletnotify($tx_hash, $skip_set_site_constant) {
		$require_inputs = true;
		$successful = true;
		$this->add_transaction($tx_hash, false, $require_inputs, $successful, false, [false], false);
	}
	
	public function sync_coind($print_debug) {
		$html = "";
		$last_block_id = $this->last_complete_block_id();
		
		if ($last_block_id >= 0) {
			$txt = "Running Blockchain->sync_coind() for ".$this->db_blockchain['blockchain_name']."\n";
			if ($print_debug) {
				echo $txt;
				$this->app->flush_buffers();
			}
			else $html .= $txt;
			$this->load_coin_rpc();
			
			$startblock_params = [
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'block_id' => $last_block_id
			];
			$startblock_q = "SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id=:block_id;";
			$startblock_r = $this->app->run_query($startblock_q, $startblock_params);
			
			if ($startblock_r->rowCount() == 0) {
				if ($last_block_id == 0) {
					$this->add_genesis_block();
					$startblock_r = $this->app->run_query($startblock_q, $startblock_params);
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
					$last_block_hash = $this->coin_rpc->getblockhash((int) $last_block['block_id']);
					$coind_headers_error = $this->coind_add_block($last_block_hash, $last_block['block_id'], TRUE, $print_debug);
					$last_block = $this->fetch_block_by_internal_id($last_block['internal_block_id']);
				}
				
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					$txt = "Resolving potential fork on block #".$last_block['block_id']."\n";
					if ($print_debug) echo $txt;
					else $html .= $txt;
					$this->resolve_potential_fork_on_block($last_block);
				}
				
				if ($last_block['block_id']%10 == 0) $this->set_average_seconds_per_block(false);
				
				$txt = "Loading new blocks...\n";
				if ($print_debug) {
					echo $txt;
					$this->app->flush_buffers();
				}
				else $html .= $txt;
				$txt = $this->load_new_blocks($print_debug);
				if ($print_debug) {
					echo $txt;
					$this->app->flush_buffers();
				}
				else $html .= $txt;
				
				$txt = "Loading all blocks...\n";
				if ($print_debug) {
					echo $txt;
					$this->app->flush_buffers();
				}
				else $html .= $txt;
				
				$this->load_all_blocks(TRUE, $print_debug, 180);
				
				if ($this->db_blockchain['p2p_mode'] == "rpc" && $this->db_blockchain['load_unconfirmed_transactions'] == 1 && $this->last_complete_block_id() == $this->last_block_id()) {
					$txt = "Loading unconfirmed transactions...\n";
					if ($print_debug) {
						echo $txt;
						$this->app->flush_buffers();
					}
					else $html .= $txt;
					$txt = $this->load_unconfirmed_transactions(30);
					if ($print_debug) {
						echo $txt;
						$this->app->flush_buffers();
					}
					else $html .= $txt;
				}
				
				$txt = "Done syncing ".$this->db_blockchain['blockchain_name']."\n";
				if ($print_debug) {
					echo $txt;
					$this->app->flush_buffers();
				}
				else $html .= $txt;
			}
		}
		
		return $html;
	}
	
	public function load_new_blocks($print_debug) {
		$last_block_id = $this->last_block_id();
		$last_block = $this->fetch_block_by_id($last_block_id);
		$block_height = $last_block['block_id'];
		$this->load_coin_rpc();
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$info = $this->coin_rpc->getblockchaininfo();
			$actual_block_height = (int) $info['headers'];
		}
		else {
			$info = $this->web_api_fetch_blockchain();
			$last_block_id = $this->last_block_id();
			$actual_block_height = $info['last_block_id'];
		}
		
		if ($actual_block_height && $last_block_id < $actual_block_height) {
			if ($print_debug) echo "Quick adding blocks ".$last_block_id.":".$actual_block_height."\n";
			
			$start_q = "INSERT INTO blocks (blockchain_id, block_id, time_created) VALUES ";
			$modulo = 0;
			$new_blocks_q = $start_q;
			for ($block_id = $last_block_id+1; $block_id <= $actual_block_height; $block_id++) {
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
	
	public function load_all_block_headers($required_blocks_only, $max_execution_time, $print_debug) {
		$start_time = microtime(true);
		$html = "";
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
				$coind_error = $this->coind_add_block($unknown_block_hash, $unknown_block['block_id'], TRUE, $print_debug);
				
				$html .= $unknown_block['block_id']." ";
				if ($coind_error || (microtime(true)-$start_time) >= $max_execution_time) $keep_looping = false;
			}
			else $keep_looping = false;
		}
		while ($keep_looping);
		
		return $html;
	}
	
	public function load_all_blocks($required_blocks_only, $print_debug, $max_execution_time) {
		$start_time = microtime(true);
		
		if ($required_blocks_only && $this->db_blockchain['first_required_block'] === "") {}
		else {
			$this->load_coin_rpc();
			$keep_looping = true;
			$loop_i = 0;
			$load_at_once = 100;
			
			$last_complete_block_id = $this->last_complete_block_id();
			$load_from_block = $last_complete_block_id+1;
			$load_to_block = $load_from_block+$load_at_once-1;
			
			if ($this->db_blockchain['p2p_mode'] == "web_api") {
				$ref_api_blocks = $this->web_api_fetch_blocks($load_from_block, $load_to_block);
			}
			else $ref_api_blocks = [];
			
			do {
				$ref_time = microtime(true);
				$blocks_loaded = 0;
				
				$last_complete_block_id = $this->last_complete_block_id();
				$load_from_block = $last_complete_block_id+1;
				$load_to_block = $load_from_block+$load_at_once-1;
				
				$load_blocks = $this->app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :from_block_id  AND block_id <= :to_block_id;", [
					'blockchain_id' => $this->db_blockchain['blockchain_id'],
					'from_block_id' => $load_from_block,
					'to_block_id' => $load_to_block
				]);
				$this_loop_blocks_to_load = $load_blocks->rowCount();
				
				while ($keep_looping && $unknown_block = $load_blocks->fetch()) {
					if ($this->db_blockchain['p2p_mode'] == "rpc") {
						if (empty($unknown_block['block_hash'])) {
							$unknown_block_hash = $this->coin_rpc->getblockhash((int)$unknown_block['block_id']);
							$this->set_block_hash($unknown_block['block_id'], $unknown_block_hash);
							$unknown_block = $this->fetch_block_by_internal_id($unknown_block['internal_block_id']);
						}
						$coind_error = $this->coind_add_block($unknown_block['block_hash'], $unknown_block['block_id'], false, $print_debug);
						if ($coind_error) $keep_looping = false;
						else $blocks_loaded++;
					}
					else {
						if ($loop_i >= count($ref_api_blocks)) {
							$last_complete_block_id = $this->last_complete_block_id();
							$load_from_block = $last_complete_block_id+1;
							$load_to_block = $load_from_block+$load_at_once-1;
							
							$loop_i = 0;
							$ref_api_blocks = $this->web_api_fetch_blocks($load_from_block, $load_to_block);
						}
						if (empty($ref_api_blocks[$loop_i])) $keep_looping = false;
						else {
							$ref_api_blocks[$loop_i] = get_object_vars($ref_api_blocks[$loop_i]);
							$this->web_api_add_block($unknown_block, $ref_api_blocks[$loop_i], false, $print_debug);
							$blocks_loaded++;
						}
					}
					$loop_i++;
					
					if (microtime(true)-$start_time >= $max_execution_time) $keep_looping = false;
				}
				
				if ($print_debug) {
					echo "Loaded ".number_format($blocks_loaded)." in ".round(microtime(true)-$ref_time, 6)." sec\n";
					$this->app->flush_buffers();
				}
				
				if ($this_loop_blocks_to_load < $load_at_once) $keep_looping = false;
			}
			while ($keep_looping);
		}
	}
	
	public function resolve_potential_fork_on_block(&$db_block) {
		$this->load_coin_rpc();
		$rpc_block = $this->coin_rpc->getblock($db_block['block_hash']);
		
		if ($rpc_block['confirmations'] < 0) {
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
		$this->load_coin_rpc();
		$unconfirmed_txs = $this->coin_rpc->getrawmempool();
		
		for ($i=0; $i<count($unconfirmed_txs); $i++) {
			$this->walletnotify($unconfirmed_txs[$i], TRUE);
			if ($max_execution_time && (microtime(true)-$start_time) > $max_execution_time) $i=count($unconfirmed_txs);
		}
	}
	
	public function insert_initial_blocks() {
		$this->load_coin_rpc();
		
		$db_block_height = $this->app->run_query("SELECT MAX(block_id) FROM blocks WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch()['MAX(block_id)'];
		if ((string)$db_block_height == "") $db_block_height = 0;
		
		if ($this->db_blockchain['p2p_mode'] == "rpc") {
			$info = $this->coin_rpc->getblockchaininfo();
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
		$new_blocks_q = $start_insert;
		for ($block_i=$db_block_height+1; $block_i<$block_height; $block_i++) {
			if ($modulo == 1000) {
				$new_blocks_q = substr($new_blocks_q, 0, -2).";";
				$this->app->run_query($new_blocks_q);
				$modulo = 0;
				$new_blocks_q = $start_insert;
				$html .= ". ";
			}
			else $modulo++;
		
			$new_blocks_q .= "('".$this->db_blockchain['blockchain_id']."', '".$block_i."', '".time()."'), ";
		}
		if ($modulo > 0) {
			$new_blocks_q = substr($new_blocks_q, 0, -2).";";
			$this->app->run_query($new_blocks_q);
			$html .= ". ";
		}
		return $html;
	}
	
	public function delete_blocks_from_height($block_height) {
		$this->app->run_query("DELETE s.* FROM game_sellouts s JOIN games g ON s.game_id=g.game_id WHERE g.blockchain_id=:blockchain_id AND s.in_block_id >= :sellout_block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'sellout_block_id' => $block_height
		]);
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id AND block_id >= :block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_height
		]);
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id AND block_id IS NULL;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id=:blockchain_id AND io.create_block_id >= :create_block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'create_block_id' => $block_height
		]);
		$this->app->run_query("DELETE io.*, gio.* FROM transaction_ios io LEFT JOIN transaction_game_ios gio ON gio.io_id=io.io_id WHERE io.blockchain_id=:blockchain_id AND io.create_block_id IS NULL;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->app->run_query("UPDATE transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id SET gio.spend_round_id=NULL, io.coin_blocks_created=0, gio.coin_rounds_created=0, gio.votes=0, io.spend_transaction_id=NULL, io.spend_count=NULL, io.spend_status='unspent', io.in_index=NULL, gio.payout_io_id=NULL WHERE io.blockchain_id=:blockchain_id AND io.spend_block_id >= :spend_block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'spend_block_id' => $block_height
		]);
		$this->app->run_query("DELETE FROM blocks WHERE blockchain_id=:blockchain_id AND block_id >= :block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $block_height
		]);
	}
	
	public function add_genesis_block() {
		$html = "";
		
		if ($this->db_blockchain['p2p_mode'] == "none") {
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
			$this->load_coin_rpc();
			$genesis_block_hash = $this->coin_rpc->getblockhash(0);
			$rpc_block = new block($this->coin_rpc->getblock($genesis_block_hash), 0, $genesis_block_hash);
			$genesis_tx_hash = $rpc_block->json_obj['tx'][0];
			$nextblock_hash = $rpc_block->json_obj['nextblockhash'];
		}
		
		$this->app->run_query("DELETE t.*, io.* FROM transactions t LEFT JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.tx_hash=:tx_hash AND t.blockchain_id=:blockchain_id;", [
			'tx_hash' => $genesis_tx_hash,
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		
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
			
			$this->app->run_query("UPDATE blockchains SET genesis_address=:genesis_address WHERE blockchain_id=:blockchain_id;", [
				'genesis_address' => $genesis_address,
				'blockchain_id' => $this->db_blockchain['blockchain_id']
			]);
			
			$this->db_blockchain['genesis_address'] = $genesis_address;
		}
		
		$force_is_mine = false;
		if ($this->db_blockchain['p2p_mode'] == "none") $force_is_mine = true;
		
		$output_address = $this->create_or_fetch_address($genesis_address, true, false, false, $force_is_mine, false);
		$html .= "genesis hash: ".$genesis_block_hash."<br/>\n";
		
		$this->app->run_query("INSERT INTO transactions SET blockchain_id=:blockchain_id, amount=:amount, transaction_desc='coinbase', tx_hash=:tx_hash, block_id='0', position_in_block=0, time_created=:time_created, num_inputs=0, num_outputs=1, has_all_inputs=1, has_all_outputs=1;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'amount' => $this->db_blockchain['initial_pow_reward'],
			'tx_hash' => $genesis_tx_hash,
			'time_created' => time()
		]);
		$transaction_id = $this->app->last_insert_id();
		
		$this->app->run_query("INSERT INTO transaction_ios SET spend_status='unspent', blockchain_id=:blockchain_id, user_id=NULL, address_id=:address_id, is_destroy=0, is_separator=0, is_passthrough=0, is_receiver=0, create_transaction_id=:create_transaction_id, amount=:amount, create_block_id='0';", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'address_id' => $output_address['address_id'],
			'create_transaction_id' => $transaction_id,
			'amount' => $this->db_blockchain['initial_pow_reward']
		]);
		$genesis_io_id = $this->app->last_insert_id();
		
		$this->app->run_query("INSERT INTO blocks SET blockchain_id=:blockchain_id, block_hash=:block_hash, block_id='0', time_created=:current_time, time_loaded=:current_time, time_mined=:current_time, num_transactions=1, locally_saved=1, sec_since_prev_block=0;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_hash' => $genesis_block_hash,
			'current_time' => time()
		]);
		
		$html .= "Added the genesis transaction!<br/>\n";
		$this->app->log_message($html);
		
		$returnvals['log_text'] = $html;
		$returnvals['genesis_hash'] = $genesis_tx_hash;
		$returnvals['nextblockhash'] = $nextblock_hash;
		return $returnvals;
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
			$first_required_block = (int) $info['last_block_id'];
		}
		
		$min_starting_block = (int)($this->app->run_query("SELECT MIN(game_starting_block) FROM games WHERE game_status IN ('published', 'running') AND blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		])->fetch()['MIN(game_starting_block)']);
		
		if ($min_starting_block > 0 && ($first_required_block == "" || $min_starting_block < $first_required_block)) $first_required_block = $min_starting_block;
		
		if ($first_required_block != "") $this->db_blockchain['first_required_block'] = $first_required_block;
		else {
			$this->db_blockchain['first_required_block'] = "";
			$first_required_block = null;
		}
		
		$this->app->run_query("UPDATE blockchains SET first_required_block=:first_required_block WHERE blockchain_id=:blockchain_id;", [
			'first_required_block' => $first_required_block,
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
	}
	
	public function sync_initial($from_block_id) {
		$html = "";
		$start_time = microtime(true);
		$this->load_coin_rpc();
		
		if ($this->db_blockchain['first_required_block'] == "") $this->set_first_required_block();
		
		$blocks = [];
		$transactions = [];
		$block_height = 0;
		
		$keep_looping = true;
		
		$new_transaction_count = 0;
		
		if (!empty($from_block_id)) {
			if (!empty($from_block_id)) {
				$block_height = $from_block_id-1;
			}
			
			$db_prev_block = $this->fetch_block_by_id($block_height);
			
			if ($db_prev_block) {
				if ($this->db_blockchain['p2p_mode'] == "rpc") {
					$temp_block = $this->coin_rpc->getblock($db_prev_block['block_hash']);
					$current_hash = $temp_block['nextblockhash'];
				}
				$this->delete_blocks_from_height($block_height+1);
			}
			else die("Error, block $block_height was not found.");
		}
		else {
			if ($this->db_blockchain['p2p_mode'] == "none") {
				$this->app->run_query("UPDATE blockchains SET genesis_address=NULL WHERE blockchain_id=:blockchain_id;", [
					'blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
				$this->db_blockchain['genesis_address'] = "";
			}
			
			$this->reset_blockchain();
			
			if ($this->db_blockchain['p2p_mode'] == "none") {
				$returnvals = $this->add_genesis_block();
				$current_hash = $returnvals['nextblockhash'];
			}
		}
		
		$html .= $this->insert_initial_blocks();
		$last_block_id = $this->last_block_id();
		if ($this->db_blockchain['p2p_mode'] == "rpc") $this->set_block_hash_by_height($last_block_id);
		
		$html .= "<br/>Finished inserting blocks at ".(microtime(true) - $start_time)." sec<br/>\n";
		
		return $html;
	}
	
	public function reset_blockchain() {
		$associated_games = $this->associated_games(false);
		for ($i=0; $i<count($associated_games); $i++) {
			$associated_games[$i]->delete_reset_game('reset');
		}
		
		$this->app->run_query("DELETE FROM transactions WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->app->run_query("DELETE FROM transaction_ios WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->app->run_query("DELETE FROM blocks WHERE blockchain_id=:blockchain_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
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
	
	public function render_transactions_in_block(&$block, $unconfirmed_only) {
		if (!$unconfirmed_only && $block['locally_saved'] == 1 && !empty($block['transactions_html'])) return $block['transactions_html'];
		else {
			$html = "";
			
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
				$html .= $this->render_transaction($transaction, false, false);
			}
			
			if (!$unconfirmed_only && $block['locally_saved'] == 1) {
				$this->app->run_query("UPDATE blocks SET transactions_html=:transactions_html WHERE internal_block_id=:internal_block_id;", [
					'transactions_html' => $html,
					'internal_block_id' => $block['internal_block_id']
				]);
				$block['transactions_html'] = $html;
			}
			return $html;
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
		$html .= (int)$transaction['num_inputs']." inputs, ".(int)$transaction['num_outputs']." outputs, ".$this->app->format_bignum($transaction['amount']/pow(10,$this->db_blockchain['decimal_places']))." ".$this->db_blockchain['coin_name_plural'];
		
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
			if ($game) $block_sum_disp = $block['sum_coins_out']/pow(10,$game->db_game['decimal_places']);
			else $block_sum_disp = $block['sum_coins_out']/pow(10,$this->db_blockchain['decimal_places']);
			
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-3\">";
			$html .= "<a href=\"/explorer/";
			if ($game) $html .= "games/".$game->db_game['url_identifier'];
			else $html .= "blockchains/".$this->db_blockchain['url_identifier'];
			$html .= "/blocks/".$block['block_id']."\">Block #".$block['block_id']."</a>";
			if ($block['locally_saved'] == 0 && $block['block_id'] >= $this->db_blockchain['first_required_block']) $html .= "&nbsp;(Pending)";
			$html .= "</div>";
			$html .= "<div class=\"col-sm-2";
			$html .= "\" style=\"text-align: right;\">".number_format($block['num_transactions']);
			$html .= "&nbsp;transactions</div>\n";
			$html .= "<div class=\"col-sm-2\" style=\"text-align: right;\">".$this->app->format_bignum($block_sum_disp)."&nbsp;";
			if ($game) $html .= $game->db_game['coin_name_plural'];
			else $html .= $this->db_blockchain['coin_name_plural'];
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
			$this->set_block_hash($block_height, $block_hash);
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
		$balance_q = "SELECT SUM(amount) FROM transaction_ios WHERE blockchain_id=:blockchain_id AND address_id=:address_id";
		if ($confirmed_only) $balance_q .= " AND spend_status IN ('spent','unspent')";
		
		return $this->app->run_query($balance_q, [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'address_id' => $db_address['address_id']
		])->fetch()['SUM(amount)'];
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
	
	public function create_or_fetch_address($address, $check_existing, $delete_optionless, $claimable, $force_is_mine, $account_id) {
		if ($check_existing) {
			$db_address = $this->app->fetch_address($address);
			if ($db_address) return $db_address;
		}
		$vote_identifier = $this->app->addr_text_to_vote_identifier($address);
		$option_index = $this->app->vote_identifier_to_option_index($vote_identifier);
		
		if ($option_index !== false || !$delete_optionless) {
			if ($force_is_mine) $is_mine=1;
			else {
				$this->load_coin_rpc();
				
				if ($this->coin_rpc) {
					$validate_address = $this->coin_rpc->validateaddress($address);
					
					if (!empty($validate_address['ismine'])) $is_mine = 1;
					else $is_mine=0;
				}
				else $is_mine=0;
			}
			
			list($is_destroy_address, $is_separator_address, $is_passthrough_address) = $this->app->option_index_to_special_address_types($option_index);
			
			$this->app->run_query("INSERT INTO addresses SET primary_blockchain_id=:primary_blockchain_id, address=:address, time_created=:time_created, is_mine=:is_mine, vote_identifier=:vote_identifier, option_index=:option_index, is_destroy_address=:is_destroy_address, is_separator_address=:is_separator_address, is_passthrough_address=:is_passthrough_address;", [
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
					'account_id' => null,
					'pub_key' => $address,
					'option_index' => $option_index,
					'primary_blockchain_id' => $this->db_blockchain['blockchain_id']
				]);
			}
			
			return $this->app->fetch_address_by_id($output_address_id);
		}
		else return false;
	}
	
	public function account_balance($account_id) {
		return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND io.spend_status='unspent' AND k.account_id=:account_id AND io.create_block_id IS NOT NULL;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'account_id' => $account_id
		])->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function user_immature_balance(&$user_game) {
		return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.blockchain_id=:blockchain_id AND k.account_id=:account_id AND (io.create_block_id > :block_id OR io.create_block_id IS NULL);", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'account_id' => $user_game['account_id'],
			'block_id' => $this->last_block_id()
		])->fetch(PDO::FETCH_NUM)[0]);
	}

	public function user_mature_balance(&$user_game) {
		return (int)($this->app->run_query("SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE io.spend_status='unspent' AND io.spend_transaction_id IS NULL AND io.blockchain_id=:blockchain_id AND k.account_id=:account_id AND io.create_block_id <= :block_id;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'account_id' => $user_game['account_id'],
			'block_id' => $this->last_block_id()
		])->fetch(PDO::FETCH_NUM)[0]);
	}
	
	public function set_blockchain_creator(&$user) {
		$this->app->run_query("UPDATE blockchains SET creator_id=:user_id WHERE blockchain_id=:blockchain_id;", [
			'user_id' => $user->db_user['user_id'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->db_blockchain['creator_id'] = $user->db_user['user_id'];
	}
	
	public function create_transaction($type, $amounts, $block_id, $io_ids, $address_ids, $transaction_fee, &$error_message) {
		if ($transaction_fee < 0) {
			$error_message = "Tried creating a transaction with a negative transaction fee ($transaction_fee).\n";
			return false;
		}
		
		$amount = $transaction_fee;
		for ($i=0; $i<count($amounts); $i++) {
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
		
		if (($type == "coinbase" || $utxo_balance == $amount) && count($amounts) == count($address_ids)) {
			$num_inputs = 0;
			if ($io_ids) $num_inputs = count($io_ids);
			
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
					'time_created' => time()
				];
				$new_tx_q = "INSERT INTO transactions SET blockchain_id=:blockchain_id, fee_amount=:fee_amount, has_all_inputs=1, has_all_outputs=1, num_inputs=:num_inputs, num_outputs=:num_outputs, tx_hash=:tx_hash, transaction_desc=:transaction_desc, amount=:amount";
				if ($block_id !== false) {
					$new_tx_q .= ", block_id=:block_id";
					$new_tx_params['block_id'] = $block_id;
				}
				$new_tx_q .= ", time_created=:time_created;";
				$this->app->run_query($new_tx_q, $new_tx_params);
				$transaction_id = $this->app->last_insert_id();
			}
			
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
						$update_input_q = "UPDATE transaction_ios SET spend_count=spend_count+1";
						if (!empty($transaction_id)) {
							$update_input_q .= ", spend_transaction_id=:transaction_id, in_index=:in_index, spend_transaction_ids=CONCAT(spend_transaction_ids, ':transaction_id,')";
							$update_input_params['transaction_id'] = $transaction_id;
							$update_input_params['in_index'] = $in_index;
						}
						if ($block_id !== false) {
							$update_input_q .= ", spend_status='spent', spend_block_id=:block_id";
							$update_input_params['block_id'] = $block_id;
						}
						else $update_input_q .= ", spend_status='unconfirmed'";
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
			
			$output_error = false;
			$out_index = 0;
			$first_passthrough_index = false;
			
			for ($out_index=0; $out_index<count($amounts); $out_index++) {
				if (!$output_error) {
					$address_id = $address_ids[$out_index];
					
					if ($address_id) {
						$address = $this->app->fetch_address_by_id($address_id);
						
						if ($first_passthrough_index == false && $address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
						
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
								'amount' => $amounts[$out_index]
							];
							$new_output_q = "INSERT INTO transaction_ios SET blockchain_id=:blockchain_id, spend_status=:spend_status, out_index=:out_index, script_type='pubkeyhash', ";
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
								
								$new_output_q .= "coin_blocks_destroyed=:output_cbd, ";
								$new_output_params['output_cbd'] = $output_cbd;
							}
							if ($block_id !== false) {
								$new_output_q .= "create_block_id=:block_id, ";
								$new_output_params['block_id'] = $block_id;
							}
							$new_output_q .= "create_transaction_id=:create_transaction_id, amount=:amount;";
							
							$this->app->run_query($new_output_q, $new_output_params);
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
				//$this->blockchain->app->cancel_transaction($transaction_id, $affected_input_ids, false);
				return false;
			}
			else {
				$successful = false;
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
							$error_message = $sendraw_response['message'];
							return false;
						}
						else {
							$verified_tx_hash = $sendraw_response;
							
							$this->walletnotify($verified_tx_hash, FALSE);
							
							$db_transaction = $this->fetch_transaction_by_hash($verified_tx_hash);
							
							if ($db_transaction) {
								$error_message = "Success!";
								return $db_transaction['transaction_id'];
							}
							else {
								$error_message = "Failed to find the TX in db after sending.";
								return false;
							}
						}
					}
					catch (Exception $e) {
						$error_message = "There was an error with one of the RPC calls.";
						return false;
					}
				}
				$this->add_transaction($tx_hash, $block_id, true, $successful, false, [false], false);
				
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
		// This function only runs for blockchains with p2p_mode='none'
		$last_block_id = (int) $this->last_block_id();
		$prev_block = $this->fetch_block_by_id($last_block_id);
		
		$this->app->run_query("INSERT INTO blocks SET blockchain_id=:blockchain_id, block_id=:block_id, block_hash=:block_hash, time_created=:current_time, time_loaded=:current_time, time_mined=:current_time, sec_since_prev_block=:sec_since_prev_block, locally_saved=0;", [
			'blockchain_id' => $this->db_blockchain['blockchain_id'],
			'block_id' => $last_block_id+1,
			'block_hash' => $this->app->random_hex_string(64),
			'current_time' => time(),
			'sec_since_prev_block' => $prev_block['time_mined'] ? time()-$prev_block['time_mined'] : null
		]);
		$internal_block_id = $this->app->last_insert_id();
		
		$block = $this->fetch_block_by_internal_id($internal_block_id);
		$created_block_id = $block['block_id'];
		$mining_block_id = $created_block_id+1;
		
		$log_text .= "Created block $created_block_id<br/>\n";
		
		$num_transactions = 0;
		
		$ref_account = false;
		$mined_address_str = $this->app->random_string(34);
		$mined_address = $this->create_or_fetch_address($mined_address_str, false, false, false, true, false);
		
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
			else $tx_error = true;
		}
		
		$this->app->run_query("UPDATE blocks SET num_transactions=:num_transactions, locally_saved=1 WHERE internal_block_id=:internal_block_id;", [
			'num_transactions' => $num_transactions,
			'internal_block_id' => $internal_block_id
		]);
		$block['locally_saved'] = 1;
		$block['num_transactions'] = $num_transactions;
		$this->set_block_stats($block);
		$this->render_transactions_in_block($block, false);
		
		return $created_block_id;
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
	
	public function web_api_fetch_blocks($from_block_height, $to_block_height) {
		$remote_url = $this->authoritative_peer['base_url']."/api/blocks/".$this->db_blockchain['url_identifier']."/".$from_block_height.":".$to_block_height;
		$remote_response = json_decode(file_get_contents($remote_url));
		return $remote_response->blocks;
	}
	
	public function web_api_fetch_blockchain() {
		$remote_url = $this->authoritative_peer['base_url']."/api/blockchain/".$this->db_blockchain['url_identifier']."/";
		$remote_response_raw = file_get_contents($remote_url);
		return get_object_vars(json_decode($remote_response_raw));
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
		$new_tx_q = "INSERT INTO transactions SET time_created=:time_created, blockchain_id=:blockchain_id";
		if ($block_height !== false) {
			$new_tx_q .= ", block_id=:block_id, position_in_block=:position_in_block";
			$new_tx_params['block_id'] = $block_height;
			$new_tx_params['position_in_block'] = (int)$tx['position_in_block'];
		}
		$new_tx_q .= ", transaction_desc=:transaction_desc, tx_hash=:tx_hash, amount=:amount, fee_amount=:fee_amount, num_inputs=:num_inputs, num_outputs=:num_outputs;";
		$this->app->run_query($new_tx_q, $new_tx_params);
		$transaction_id = $this->app->last_insert_id();
		
		for ($in_index=0; $in_index<count($tx['inputs']); $in_index++) {
			$tx_input = get_object_vars($tx['inputs'][$in_index]);
			$this->app->run_query("UPDATE transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id SET io.spend_transaction_id=:transaction_id, io.in_index=:in_index WHERE t.blockchain_id=:blockchain_id AND t.tx_hash=:tx_hash AND io.out_index=:out_index;", [
				'transaction_id' => $transaction_id,
				'in_index' => $in_index,
				'blockchain_id' => $this->db_blockchain['blockchain_id'],
				'tx_hash' => $tx_input['tx_hash'],
				'out_index' => $tx_input['out_index']
			]);
		}
		
		$first_passthrough_index = false;
		
		for ($out_index=0; $out_index<count($tx['outputs']); $out_index++) {
			$tx_output = get_object_vars($tx['outputs'][$out_index]);
			$db_address = $this->create_or_fetch_address($tx_output['address'], true, false, false, false, false);
			
			if ($first_passthrough_index == false && $db_address['is_passthrough_address'] == 1) $first_passthrough_index = $out_index;
			
			$new_output_q = "INSERT INTO transaction_ios SET blockchain_id=:blockchain_id, out_index=:out_index, address_id=:address_id, option_index=:option_index, create_block_id=:create_block_id, create_transaction_id=:create_transaction_id, amount=:amount";
			if ($block_height !== false) $new_output_q .= ", spend_status='unspent'";
			$new_output_q .= ", is_destroy=:is_destroy, is_separator=:is_separator, is_passthrough=:is_passthrough, is_receiver=:is_receiver;";
			$this->app->run_query($new_output_q, [
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
			]);
		}
		
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
			$new_addr_db = $this->create_or_fetch_address($new_addr_txt, false, false, false, true, false);
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
			'block_id' => $this->last_complete_block_id()-100
		])->fetch();
		
		$this->app->run_query("UPDATE blockchains SET average_seconds_per_block=:average_seconds_per_block WHERE blockchain_id=:blockchain_id;", [
			'average_seconds_per_block' => $avg['AVG(sec_since_prev_block)'],
			'blockchain_id' => $this->db_blockchain['blockchain_id']
		]);
		$this->db_blockchain['average_seconds_per_block'] = $avg['AVG(sec_since_prev_block)'];
	}
}
?>