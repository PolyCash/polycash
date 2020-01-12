<?php
class GameDefinition {
	public static function migration_types() {
		return [
			'create_game_by_ui',
			'change_event_by_ui',
			'create_event_by_ui',
			'delete_option_by_ui',
			'apply_defined_to_actual',
			'create_from_text',
			'set_from_text',
			'set_from_peer'
		];
	}
	
	public static function fetch_game_definition(&$game, $definition_mode, $show_internal_params, $cached_ok) {
		$app = $game->blockchain->app;
		
		// $definition_mode is "defined" or "actual"
		$game_definition = [];
		$game_definition['blockchain_identifier'] = $game->blockchain->db_blockchain['url_identifier'];
		
		if ($game->db_game['option_group_id'] > 0) {
			$db_group = $app->fetch_group_by_id($game->db_game['option_group_id']);
			
			$game_definition['option_group'] = $db_group['description'];
		}
		else $game_definition['option_group'] = null;
		
		$verbatim_vars = $app->game_definition_verbatim_vars();
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($game->db_game[$var_name] == "0" || $game->db_game[$var_name] > 0) $var_val = (int) $game->db_game[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $game->db_game[$var_name];
			else if ($var_type == "bool") {
				if ($game->db_game[$var_name]) $var_val = true;
				else $var_val = false;
			}
			else if ($var_name == "module" && $game->db_game['hide_module']) $var_val = null;
			else $var_val = $game->db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		$escrow_amounts = [];
		
		if ($definition_mode == "actual") {
			$escrow_amounts_q = "SELECT * FROM game_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id ORDER BY c.short_name_plural ASC;";
		}
		else if ($definition_mode == "defined") {
			$escrow_amounts_q = "SELECT * FROM game_defined_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id ORDER BY c.short_name_plural ASC;";
		}
		
		$db_escrow_amounts = $app->run_query($escrow_amounts_q, ['game_id'=>$game->db_game['game_id']]);
		
		while ($escrow_amount = $db_escrow_amounts->fetch()) {
			$escrow_amounts[$escrow_amount['short_name_plural']] = (float) $escrow_amount['amount'];
		}
		
		$game_definition['escrow_amounts'] = $escrow_amounts;
		
		$event_verbatim_vars = $app->event_verbatim_vars();
		$events_obj = [];
		
		if ($definition_mode == "defined") {
			$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM game_defined_events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
		}
		else {
			$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
		}
		$db_events = $app->run_query($events_q, ['game_id'=>$game->db_game['game_id']]);
		
		$i=0;
		while ($db_event = $db_events->fetch()) {
			$temp_event = [];
			
			for ($j=0; $j<count($event_verbatim_vars); $j++) {
				$var_type = $event_verbatim_vars[$j][0];
				$var_name = $event_verbatim_vars[$j][1];
				
				if ($db_event['payout_rule'] != "linear" && in_array($var_name, ['track_max_price','track_min_price','track_payout_price','track_name_short'])) {}
				else {
					$var_val = $db_event[$var_name];
					
					if ($var_type == "int" && $var_val != "") $var_val = (int) $var_val;
					else if ($var_type == "float" && $var_val != "") $var_val = (float) $var_val;
					
					$temp_event[$var_name] = $var_val;
				}
			}
			
			if (!empty($db_event['sport_name'])) $temp_event['sport'] = $db_event['sport_name'];
			if (!empty($db_event['league_name'])) $temp_event['league'] = $db_event['league_name'];
			if (!empty($db_event['external_identifier']) && $show_internal_params) $temp_event['external_identifier'] = $db_event['external_identifier'];
			
			if ($definition_mode == "defined") {
				$db_options = $app->fetch_game_defined_options($game->db_game['game_id'], $db_event['event_index'], false, false);
			}
			else {
				$db_options = $app->fetch_options_by_event($db_event['event_id']);
			}
			
			$j = 0;
			while ($option = $db_options->fetch()) {
				$possible_outcome = ["title"=>$option['name']];
				if ($show_internal_params) {
					if (!empty($option['target_probability'])) $possible_outcome['target_probability'] = $option['target_probability'];
					if (!empty($option['entity_id'])) $possible_outcome['entity_id'] = $option['entity_id'];
				}
				$temp_event['possible_outcomes'][$j] = $possible_outcome;
				$j++;
			}
			$events_obj[$i] = $temp_event;
			$i++;
		}
		$game_definition['events'] = $events_obj;
		
		$game_def_str = self::game_def_to_text($game_definition);
		$game_def_hash = self::game_def_to_hash($game_def_str);
		$game_definition = json_decode($game_def_str);
		
		return [$game_def_hash, $game_definition];
	}
	
	public static function shorten_game_def_hash(&$hash) {
		return substr($hash, 0, 16);
	}
	
	public static function game_def_to_hash(&$game_def_str) {
		return AppSettings::standardHash($game_def_str);
	}
	
	public static function game_def_to_text(&$game_def) {
		return json_encode($game_def, JSON_PRETTY_PRINT);
	}
	
	public static function get_game_definition_by_hash(&$app, &$game_def_hash) {
		$db_game_def = $app->run_query("SELECT * FROM game_definitions WHERE definition_hash=:definition_hash;", ['definition_hash'=>$game_def_hash])->fetch();
		if ($db_game_def) return $db_game_def['definition'];
		else return false;
	}
	
	public static function check_set_game_definition(&$app, &$game_def_hash, &$game_def) {
		$existing_def = self::get_game_definition_by_hash($app, $game_def_hash);
		
		if (!$existing_def) {
			$app->run_query("INSERT INTO game_definitions SET definition_hash=:definition_hash, definition=:definition;", [
				'definition_hash' => $game_def_hash,
				'definition' => self::game_def_to_text($game_def)
			]);
		}
	}
	
	public static function set_cached_definition_hashes(&$game) {
		$show_internal_params = false;
		
		list($actual_game_def_hash, $actual_game_def) = self::fetch_game_definition($game, "actual", $show_internal_params, false);
		self::check_set_game_definition($game->blockchain->app, $actual_game_def_hash, $actual_game_def);
		
		if ($game->db_game['cached_definition_hash'] != $actual_game_def_hash) {
			$game->blockchain->app->run_query("UPDATE games SET cached_definition_hash=:cached_definition_hash, cached_definition_time=:cached_definition_time WHERE game_id=:game_id;", [
				'cached_definition_hash' => $actual_game_def_hash,
				'cached_definition_time' => time(),
				'game_id' => $game->db_game['game_id']
			]);
			$game->db_game['cached_definition_hash'] = $actual_game_def_hash;
		}
		
		list($defined_game_def_hash, $defined_game_def) = self::fetch_game_definition($game, "defined", $show_internal_params, false);
		self::check_set_game_definition($game->blockchain->app, $defined_game_def_hash, $defined_game_def);
		
		if ($game->db_game['defined_cached_definition_hash'] != $defined_game_def_hash) {
			$game->blockchain->app->run_query("UPDATE games SET defined_cached_definition_hash=:defined_cached_definition_hash WHERE game_id=:game_id;", [
				'defined_cached_definition_hash' => $defined_game_def_hash,
				'game_id' => $game->db_game['game_id']
			]);
			$game->db_game['defined_cached_definition_hash'] = $defined_game_def_hash;
		}
	}
	
	public static function record_migration(&$game, $user_id, $migration_type, $show_internal_params, &$initial_game_def, &$final_game_def) {
		$initial_game_def_str = self::game_def_to_text($initial_game_def);
		$final_game_def_str = self::game_def_to_text($final_game_def);
		
		$initial_hash = self::game_def_to_hash($initial_game_def_str);
		$final_hash = self::game_def_to_hash($final_game_def_str);
		
		self::check_set_game_definition($game->blockchain->app, $initial_hash, $initial_game_def);
		self::check_set_game_definition($game->blockchain->app, $final_hash, $initial_game_def);
		
		$new_migration_q = "INSERT INTO game_definition_migrations SET game_id=:game_id, user_id=:user_id, migration_time=:migration_time, migration_type=:migration_type, internal_params=:internal_params, from_hash=:from_hash, to_hash=:to_hash;";
		$new_migration_params = [
			'game_id' => $game->db_game['game_id'],
			'user_id' => $user_id,
			'migration_time' => time(),
			'migration_type' => $migration_type,
			'internal_params' => (int) $show_internal_params,
			'from_hash' => $initial_hash,
			'to_hash' => $final_hash,
		];
		
		$game->blockchain->app->run_query($new_migration_q, $new_migration_params);
	}
	
	public static function migrate_game_definitions(&$game, $user_id, $migration_type, $show_internal_params, &$initial_game_def, &$new_game_def) {
		$log_message = "";
		
		if (is_array($initial_game_def)) $initial_game_obj = $initial_game_def;
		else $initial_game_obj = get_object_vars($initial_game_def);
		
		if (is_array($new_game_def)) $new_game_obj = $new_game_def;
		else $new_game_obj = get_object_vars($new_game_def);
		
		$min_starting_block = min($initial_game_obj['game_starting_block'], $new_game_obj['game_starting_block']);
		
		$verbatim_vars = $game->blockchain->app->game_definition_verbatim_vars();
		$reset_block = false;
		$reset_event_index = false;
		
		$sports_entity_type = $game->blockchain->app->check_set_entity_type("sports");
		$leagues_entity_type = $game->blockchain->app->check_set_entity_type("leagues");
		$general_entity_type = $game->blockchain->app->check_set_entity_type("general entity");
		
		// Check if any base params are different. If so, reset from game starting block
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var = $verbatim_vars[$i];
			if ($var[2] == true) {
				if ((string)$initial_game_obj[$var[1]] != (string)$new_game_obj[$var[1]]) {
					$reset_block = $min_starting_block;
					
					$game->blockchain->app->run_query("UPDATE games SET ".$var[1]."=:".$var[1]." WHERE game_id=:game_id;", [
						$var[1] => $new_game_obj[$var[1]],
						'game_id' => $game->db_game['game_id']
					]);
				}
			}
		}
		
		$game->blockchain->app->run_query("DELETE FROM game_escrow_amounts WHERE game_id=:game_id;", ['game_id'=>$game->db_game['game_id']]);
		
		if (!empty($new_game_obj['escrow_amounts'])) {
			foreach ($new_game_obj['escrow_amounts'] as $currency_identifier => $amount) {
				$escrow_currency = $game->blockchain->app->run_query("SELECT * FROM currencies WHERE short_name_plural=:currency_identifier;", [
					'currency_identifier' => $currency_identifier
				])->fetch();
				
				if ($escrow_currency) {
					$game->blockchain->app->run_query("INSERT INTO game_escrow_amounts SET game_id=:game_id, currency_id=:currency_id, amount=:amount;", [
						'game_id' => $game->db_game['game_id'],
						'currency_id' => $escrow_currency['currency_id'],
						'amount' => $amount
					]);
				}
			}
		}
		
		$event_verbatim_vars = $game->blockchain->app->event_verbatim_vars();
		
		if (!empty($new_game_obj['events']) && count($new_game_obj['events']) > 0) $event_array_pos_to_index_offset = $new_game_obj['events'][0]->event_index;
		else $event_array_pos_to_index_offset = 0;
		
		$num_initial_events = 0;
		if (!empty($initial_game_obj['events'])) $num_initial_events = count($initial_game_obj['events']);
		$num_new_events = 0;
		if (!empty($new_game_obj['events'])) $num_new_events = count($new_game_obj['events']);
		
		$matched_events = min($num_initial_events, $num_new_events);
		
		for ($i=0; $i<$matched_events; $i++) {
			$initial_event_text = self::game_def_to_text($initial_game_obj['events'][$i]);
			
			if (self::game_def_to_text($new_game_obj['events'][$i]) != $initial_event_text) {
				$reset_block = $game->blockchain->app->min_excluding_false(array($reset_block, $initial_game_obj['events'][$i]->event_starting_block, $new_game_obj['events'][$i]->event_starting_block));
				
				if ($reset_event_index === false) $reset_event_index = $new_game_obj['events'][$i]->event_index;
			}
		}
		
		if (!empty($new_game_obj['events']) && count($new_game_obj['events']) > 0) {
			for ($i=$matched_events; $i<count($new_game_obj['events']); $i++) {
				$reset_block = $game->blockchain->app->min_excluding_false(array($reset_block, $new_game_obj['events'][$i]->event_starting_block));
			}
		}
		
		$set_events_from_index = $game->blockchain->app->min_excluding_false(array($reset_event_index, $matched_events+1));
		
		if ($set_events_from_index !== false) {
			$log_message .= "Resetting events from #".$set_events_from_index."\n";
			$game->reset_events_from_index($set_events_from_index);
			
			$set_events_from_pos = $set_events_from_index-$event_array_pos_to_index_offset;
			
			if (!is_numeric($reset_block)) $reset_block = $new_game_obj['events'][$set_events_from_pos]->event_starting_block;
			
			if (!empty($new_game_obj['events']) && count($new_game_obj['events']) > 0) {
				for ($event_pos=$set_events_from_pos; $event_pos<=count($new_game_obj['events']); $event_pos++) {
					$event_index = $event_pos+$event_array_pos_to_index_offset;
					
					if (!empty($new_game_obj['events'][$event_pos])) {
						$gde = get_object_vars($new_game_obj['events'][$event_pos]);
						$game->blockchain->app->check_set_gde($game, $gde, $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id'], $general_entity_type['entity_type_id']);
					}
				}
			}
		}
		
		if (is_numeric($reset_block)) {
			$log_message .= "Resetting blocks from #".$reset_block."\n";
			$game->reset_blocks_from_block($reset_block);
		}
		else $log_message .= "Failed to determine a reset block.\n";
		
		self::record_migration($game, $user_id, $migration_type, $show_internal_params, $initial_game_def, $new_game_def);
		
		$game->update_db_game();
		
		return $log_message;
	}
	
	public static function set_game_from_definition(&$app, &$game_definition, &$thisuser, &$error_message, &$db_game, $permission_override) {
		$game = false;
		$decode_error = false;
		
		if (is_object($game_definition)) $game_def = $game_definition;
		else {
			if ($game_def = json_decode($game_definition)) {}
			else {
				$decode_error = true;
				$error_message .= "Error: the game definition you entered could not be imported. Please make sure to enter properly formatted JSON.\n";
			}
		}
		
		if (!$decode_error) {
			$module_ok = true;
			if (!empty($game_def->module)) {
				if (!$app->check_module($game_def->module)) $module_ok = false;
			}
			
			if ($module_ok) {
				if (!empty($game_def->blockchain_identifier)) {
					$new_private_blockchain = false;
					
					if ($game_def->blockchain_identifier == "private") {
						$new_private_blockchain = true;
						$chain_id = $app->random_string(6);
						$decimal_places = 8;
						$url_identifier = "private-chain-".$chain_id;
						$chain_pow_reward = 25*pow(10,$decimal_places);
						
						$app->run_query("INSERT INTO blockchains SET online=1, p2p_mode='none', blockchain_name=:blockchain_name, url_identifier=:url_identifier, coin_name='chaincoin', coin_name_plural='chaincoins', seconds_per_block=30, decimal_places=:decimal_places, initial_pow_reward=:initial_pow_reward;", [
							'blockchain_name' => "Private Chain ".$chain_id,
							'url_identifier' => $url_identifier,
							'decimal_places' => $decimal_places,
							'initial_pow_reward' => $chain_pow_reward
						]);
						$blockchain_id = $app->last_insert_id();
						
						$new_blockchain = new Blockchain($app, $blockchain_id);
						if ($thisuser) $new_blockchain->set_blockchain_creator($thisuser);
						
						$game_def->blockchain_identifier = $url_identifier;
					}
					
					$db_blockchain = $app->fetch_blockchain_by_identifier($game_def->blockchain_identifier);
					
					if ($db_blockchain) {
						$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
						
						$game_def->url_identifier = $app->normalize_uri_part($game_def->url_identifier);
						
						if (!empty($game_def->url_identifier)) {
							$verbatim_vars = $app->game_definition_verbatim_vars();
							
							$permission_to_change = false;
							$migration_type = $permission_override ? "set_from_peer" : "create_from_text";
							
							$db_url_matched_game = $app->fetch_game_by_identifier($game_def->url_identifier);
							
							if ($db_url_matched_game) {
								if ($db_url_matched_game['blockchain_id'] == $blockchain->db_blockchain['blockchain_id']) {
									$url_matched_game = new Game($blockchain, $db_url_matched_game['game_id']);
									
									if ($permission_override) $permission_to_change = true;
									else {
										if ($thisuser) {
											$permission_to_change = $app->user_can_edit_game($thisuser, $url_matched_game);
											
											$migration_type = "set_from_text";
											
											if (!$permission_to_change) $error_message .= "Error: you can't edit this game.\n";
										}
										else $error_message .= "Permission denied. You must be logged in.\n";
									}
									
									if ($permission_to_change) $game = $url_matched_game;
								}
								else $error_message .= "Error: invalid game.blockchain_id.\n";
							}
							else $permission_to_change = true;
							
							if ($permission_to_change) {
								if (!$game) {
									$db_group = false;
									if (!empty($game_def->option_group)) {
										$db_group = $app->fetch_group_by_description($game_def->option_group);
										
										if (!$db_group) {
											$import_error = "";
											$app->import_group_from_file($game_def->option_group, $import_error);
											
											$db_group = $app->fetch_group_by_description($game_def->option_group);
										}
									}
									
									$new_game_params = [
										'featured' => 1,
										'game_status' => 'published'
									];
									if ($thisuser) $new_game_params['creator_id'] = $thisuser->db_user['user_id'];
									if ($db_group) $new_game_params['option_group_id'] = $db_group['group_id'];
									
									for ($i=0; $i<count($verbatim_vars); $i++) {
										$var_type = $verbatim_vars[$i][0];
										$var_name = $verbatim_vars[$i][1];
										
										if ($game_def->$var_name != "") {
											$new_game_params[$var_name] = $game_def->$var_name;
										}
									}
									
									$game = Game::create_game($blockchain, $new_game_params);
									
									if (!empty($game_def->module)) {
										$app->run_query("UPDATE modules SET primary_game_id=:primary_game_id WHERE module_name=:module_name AND primary_game_id IS NULL;", [
											'primary_game_id' => $game->db_game['game_id'],
											'module_name' => $game_def->module
										]);
									}
								}
								
								if (!empty($game_def->definitive_peer)) {
									$definitive_game_peer = $game->get_game_peer_by_server_name($game_def->definitive_peer);
									
									if ($definitive_game_peer) {
										$app->run_query("UPDATE games SET definitive_game_peer_id=:definitive_game_peer_id WHERE game_id=:game_id;", [
											'definitive_game_peer_id' => $definitive_game_peer['game_peer_id'],
											'game_id' => $game->db_game['game_id']
										]);
										$game->db_game['definitive_game_peer_id'] = $definitive_game_peer['game_peer_id'];
									}
								}
								
								$app->run_query("DELETE FROM game_defined_escrow_amounts WHERE game_id=:game_id;", ['game_id'=>$game->db_game['game_id']]);
								
								if (!empty($game_def->escrow_amounts)) {
									foreach ($game_def->escrow_amounts as $currency_identifier => $amount) {
										$escrow_currency = $app->run_query("SELECT * FROM currencies WHERE short_name_plural=:currency_identifier;", [
											'currency_identifier'=>$currency_identifier
										])->fetch();
										
										if ($escrow_currency) {
											$app->run_query("INSERT INTO game_defined_escrow_amounts SET game_id=:game_id, currency_id=:currency_id, amount=:amount;", [
												'game_id' => $game->db_game['game_id'],
												'currency_id' => $escrow_currency['currency_id'],
												'amount' => $amount
											]);
										}
									}
								}
								
								$show_internal_params = false;
								list($from_game_def_hash, $from_game_def) = self::fetch_game_definition($game, "defined", $show_internal_params, false);
								self::check_set_game_definition($app, $from_game_def_hash, $from_game_def);
								
								$to_game_def_str = self::game_def_to_text($game_def);
								$to_game_def_hash = self::game_def_to_hash($to_game_def_str);
								self::check_set_game_definition($app, $to_game_def_hash, $game_def);
								
								if ($from_game_def_hash != $to_game_def_hash) {
									$error_message .= self::migrate_game_definitions($game, empty($thisuser) ? null : $thisuser->db_user['user_id'], $migration_type, false, $from_game_def, $game_def);
								}
								else $error_message .= "Found no changes to apply.\n";
								
								$game->update_db_game();
								$db_game = $game->db_game;
							}
						}
						else $error_message .= "Error, invalid game URL identifier.\n";
					}
					else {
						if ($new_private_blockchain) {
							$app->run_query("DELETE FROM blockchains WHERE blockchain_id=:blockchain_id;", [
								'blockchain_id'=>$new_blockchain->db_blockchain['blockchain_id']
							]);
						}
						$error_message .= "Error, failed to identify the right blockchain.\n";
					}
				}
				else $error_message .= "Error, blockchain url identifier was empty.\n";
			}
			else $error_message .= "Error, invalid module.\n";
		}
		
		return $game;
	}
}
