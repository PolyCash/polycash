<?php
class GameDefinition {
	public static function migration_types() {
		return [
			'create_game_by_ui' => [
				'label' => 'Create game by UI',
			],
			'change_event_by_ui' => [
				'label' => 'Change event by UI',
			],
			'create_event_by_ui' => [
				'label' => 'Create event by UI',
			],
			'delete_option_by_ui' => [
				'label' => 'Delete option by UI',
			],
			'apply_defined_to_actual' => [
				'label' => 'Apply defined to actual',
			],
			'create_from_text' => [
				'label' => 'Create from text',
			],
			'set_from_text' => [
				'label' => 'Set from text',
			],
			'set_from_peer' => [
				'label' => 'Set from peer',
			],
			'set_blocks_by_ui' => [
				'label' => 'Set blocks by UI',
			],
			'changed_by_module' => [
				'label' => 'Changed by module',
			],
			'set_outcomes' => [
				'label' => 'Set outcomes',
			],
		];
	}
	
	public static function export_game_definition(&$game, $definition_mode, $show_internal_params, $cached_ok) {
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
			else if ($var_type == "float") {
				$var_val = $game->db_game[$var_name] == "" ? null : (float) $game->db_game[$var_name];
			}
			else if ($var_type == "bool") {
				if ($game->db_game[$var_name]) $var_val = true;
				else $var_val = false;
			}
			else if ($var_name == "module" && $game->db_game['hide_module']) $var_val = null;
			else $var_val = $game->db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		$escrow_amounts = [];
		$db_escrow_amounts = EscrowAmount::fetch_escrow_amounts_in_game($game, $definition_mode);
		$escrow_position = 0;
		
		while ($escrow_amount = $db_escrow_amounts->fetch()) {
			$this_escrow_amount = [
				'type' => $escrow_amount['escrow_type'],
				'currency' => $escrow_amount['abbreviation']
			];
			
			if ($escrow_amount['escrow_type'] == "fixed") $this_escrow_amount['amount'] = (float) $escrow_amount['amount'];
			else $this_escrow_amount['relative_amount'] = (float) $escrow_amount['relative_amount'];
			
			array_push($escrow_amounts, $this_escrow_amount);
			
			$escrow_position++;
		}
		
		$game_definition['escrow_amounts'] = $escrow_amounts;
		
		$event_verbatim_vars = $app->event_verbatim_vars();
		$events_obj = [];
		
		if ($game->db_game['finite_events']) {
			if ($definition_mode == "defined") {
				$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM game_defined_events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
				$options_q = "SELECT event_index, name, entity_id, target_probability FROM game_defined_options WHERE game_id=:game_id ORDER BY event_index ASC, option_index ASC;";
			}
			else {
				$events_q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC;";
				$options_q = "SELECT ev.event_index, op.name, op.entity_id, op.target_probability FROM events ev JOIN options op ON ev.event_id=op.event_id WHERE ev.game_id=:game_id ORDER BY ev.event_index ASC, op.event_option_index ASC;";
			}
			$db_events = $app->run_query($events_q, ['game_id'=>$game->db_game['game_id']]);
			$db_options = $app->run_query($options_q, ['game_id'=>$game->db_game['game_id']])->fetchAll(PDO::FETCH_ASSOC);
			$options_by_event_index = AppSettings::arrayToMapOnKey($db_options, "event_index", true);
			
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
				
				$j = 0;
				foreach ($options_by_event_index[$db_event['event_index']] as $option) {
					$possible_outcome = ["title"=>$option->name];
					if ($show_internal_params) {
						if (!empty($option->target_probability)) $possible_outcome['target_probability'] = $option->target_probability;
						if (!empty($option->entity_id)) $possible_outcome['entity_id'] = $option->entity_id;
					}
					$temp_event['possible_outcomes'][$j] = $possible_outcome;
					$j++;
				}
				$events_obj[$i] = $temp_event;
				$i++;
			}
			
			$game_definition['events'] = $events_obj;
		}
		
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
		if ($db_game_def) {
			$app->set_game_def_accessed_at($db_game_def['game_definition_id'], time());
			return $db_game_def['definition'];
		}
		else return false;
	}

	public static function check_game_definition_exists(&$app, &$game_def_hash) {
		$db_game_def = $app->run_query("SELECT 1 FROM game_definitions WHERE definition_hash=:definition_hash;", ['definition_hash'=>$game_def_hash])->fetch();
		return !empty($db_game_def);
	}

	public static function check_set_game_definition(&$app, &$game_def_hash, &$game_def, $game=null) {
		if (!self::check_game_definition_exists($app, $game_def_hash)) {
			if (is_string($game_def)) $game_def_str = &$game_def;
			else $game_def_str = self::game_def_to_text($game_def);

			$create_game_def_params = [
				'definition_hash' => $game_def_hash,
				'definition' => $game_def_str,
				'last_accessed_at' => time(),
			];
			if ($game) $create_game_def_params['game_id'] = $game->db_game['game_id'];
			$app->run_insert_query("game_definitions", $create_game_def_params);
		}
	}
	
	public static function set_cached_definition_hashes(&$game, $print_debug=false) {
		$show_internal_params = false;

		if (!$game->game_definition_is_locked()) {
			list($defined_game_def_hash, $defined_game_def) = self::export_game_definition($game, "defined", $show_internal_params, false);
			self::check_set_game_definition($game->blockchain->app, $defined_game_def_hash, $defined_game_def, $game);

			if ($print_debug) echo "Defined: ".$defined_game_def_hash."\n";

			$game->blockchain->app->run_query("UPDATE games SET defined_cached_definition_hash=:defined_cached_definition_hash, defined_cached_definition_time=:defined_cached_definition_time WHERE game_id=:game_id;", [
				'defined_cached_definition_hash' => $defined_game_def_hash,
				'defined_cached_definition_time' => time(),
				'game_id' => $game->db_game['game_id']
			]);
			$game->db_game['defined_cached_definition_hash'] = $defined_game_def_hash;
		}
		else if ($print_debug) $game->blockchain->app->print_debug("Skipping cache defined; game definition is locked.");

		if (!empty($game->db_game['events_until_block']) && $game->db_game['events_until_block'] >= $game->blockchain->last_block_id()) {
			list($actual_game_def_hash, $actual_game_def) = self::export_game_definition($game, "actual", $show_internal_params, false);
			self::check_set_game_definition($game->blockchain->app, $actual_game_def_hash, $actual_game_def, $game);

			if ($print_debug) $game->blockchain->app->print_debug("Actual: ".$actual_game_def_hash);

			$game->blockchain->app->run_query("UPDATE games SET cached_definition_hash=:cached_definition_hash, cached_definition_time=:cached_definition_time WHERE game_id=:game_id;", [
				'cached_definition_hash' => $actual_game_def_hash,
				'cached_definition_time' => time(),
				'game_id' => $game->db_game['game_id']
			]);
			$game->db_game['cached_definition_hash'] = $actual_game_def_hash;
		}
		else if ($print_debug) $game->blockchain->app->print_debug("Skipping cache actual definition; game events are not fully loaded.");
	}

	public static function record_migration(&$game, $user_id, $migration_type, $show_internal_params, &$initial_game_def, &$final_game_def) {
		$initial_game_def_str = self::game_def_to_text($initial_game_def);
		$final_game_def_str = self::game_def_to_text($final_game_def);
		
		$initial_hash = self::game_def_to_hash($initial_game_def_str);
		$final_hash = self::game_def_to_hash($final_game_def_str);
		
		self::check_set_game_definition($game->blockchain->app, $initial_hash, $initial_game_def, $game);
		self::check_set_game_definition($game->blockchain->app, $final_hash, $final_game_def, $game);
		
		$new_migration_params = [
			'game_id' => $game->db_game['game_id'],
			'user_id' => $user_id,
			'migration_time' => time(),
			'migration_type' => $migration_type,
			'internal_params' => (int) $show_internal_params,
			'from_hash' => $initial_hash,
			'to_hash' => $final_hash,
		];
		
		$game->blockchain->app->run_insert_query("game_definition_migrations", $new_migration_params);
		
		return $game->blockchain->app->run_query("SELECT * FROM game_definition_migrations WHERE migration_id=:migration_id;", ['migration_id' => $game->blockchain->app->last_insert_id()])->fetch();
	}
	
	public static function analyze_definition_differences($app, $from_def, $to_def) {
		// Base Parameters
		$base_params = $app->game_definition_verbatim_vars();
		$base_param_differences = [];
		
		foreach ($base_params as $base_param) {
			list($var_type, $var_name, $var_required) = $base_param;
			
			$from_val = empty($from_def->$var_name) ? null : $from_def->$var_name;
			$to_val = empty($to_def->$var_name) ? null : $to_def->$var_name;
			
			if ((string)$from_val != (string)$to_val) {
				array_push($base_param_differences, [
					'parameter' => $var_name,
					'from_value' => $from_val,
					'to_value' => $to_val
				]);
			}
		}
		
		// Escrow Amounts
		$from_escrow_amounts = empty($from_def->escrow_amounts) ? [] : $from_def->escrow_amounts;
		$to_escrow_amounts = empty($to_def->escrow_amounts) ? [] : $to_def->escrow_amounts;
		
		$escrow_differences = [
			'added' => [],
			'removed' => [],
			'changed' => []
		];
		$matched_escrow_amounts = min(count($from_escrow_amounts), count($to_escrow_amounts));
		
		if (count($from_escrow_amounts) != count($to_escrow_amounts)) {
			if (count($from_escrow_amounts) > count($to_escrow_amounts)) {
				$escrow_differences['removed'] = array_slice($from_escrow_amounts, count($to_escrow_amounts), count($from_escrow_amounts) - count($to_escrow_amounts));
			}
			else {
				$escrow_differences['added'] = array_slice($to_escrow_amounts, count($from_escrow_amounts), count($to_escrow_amounts) - count($from_escrow_amounts));
			}
		}
		
		if ($matched_escrow_amounts > 0) {
			$changed_escrows = [];
			for ($escrow_pos=0; $escrow_pos<$matched_escrow_amounts; $escrow_pos++) {
				if (json_encode($from_escrow_amounts[$escrow_pos]) != json_encode($to_escrow_amounts[$escrow_pos])) {
					array_push($changed_escrows, [
						'from' => $from_escrow_amounts[$escrow_pos],
						'to' => $to_escrow_amounts[$escrow_pos]
					]);
				}
			}
			$escrow_differences['changed'] = $changed_escrows;
		}
		
		// Events
		$matched_events = min(count($from_def->events), count($to_def->events));
		$event_differences = [
			'new_events' => 0,
			'removed_events' => 0,
			'block_changed_events' => 0,
			'outcome_changed_events' => 0,
			'other_changed_events' => 0
		];
		
		if (count($from_def->events) != count($to_def->events)) {
			if (count($to_def->events) - count($from_def->events) > 0) {
				$event_differences['new_events'] = count($to_def->events) - count($from_def->events);
			}
			else {
				$event_differences['removed_events'] = count($from_def->events) - count($to_def->events);
			}
		}
		
		if ($matched_events > 0) {
			for ($event_pos=0; $event_pos<$matched_events; $event_pos++) {
				if (json_encode($from_def->events[$event_pos]) != json_encode($to_def->events[$event_pos])) {
					$ref_from_event = clone $from_def->events[$event_pos];
					$ref_from_event->event_starting_block = null;
					$ref_from_event->event_final_block = null;
					$ref_from_event->event_determined_to_block = null;
					$ref_from_event->event_payout_block = null;
					
					$ref_to_event = clone $to_def->events[$event_pos];
					$ref_to_event->event_starting_block = null;
					$ref_to_event->event_final_block = null;
					$ref_to_event->event_determined_to_block = null;
					$ref_to_event->event_payout_block = null;
					
					if (json_encode($ref_from_event) == json_encode($ref_to_event)) $event_differences['block_changed_events']++;
					else {
						$ref_from_event->outcome_index = null;
						$ref_to_event->outcome_index = null;
						$ref_from_event->track_payout_price = null;
						$ref_to_event->track_payout_price = null;
						
						if (json_encode($ref_from_event) == json_encode($ref_to_event)) $event_differences['outcome_changed_events']++;
						else $event_differences['other_changed_events']++;
					}
				}
			}
		}
		
		$differences = [
			'base_params' => $base_param_differences,
			'escrow' => $escrow_differences,
			'events' => $event_differences
		];
		
		$difference_summary_lines = [];
		if (count($differences['base_params']) > 0) {
			array_push($difference_summary_lines, count($differences['base_params'])." game parameter".(count($differences['base_params']) == 1 ? " was" : "s were")." changed");
		}
		if (count($differences['escrow']['added']) > 0) {
			array_push($difference_summary_lines, count($differences['escrow']['added'])." new amount".(count($differences['escrow']['added']) != 1 ? "s were" : " was")." added to the escrow");
		}
		if (count($differences['escrow']['removed']) > 0) {
			array_push($difference_summary_lines, count($differences['escrow']['removed'])." amount".(count($differences['escrow']['removed']) != 1 ? "s were" : " was")." removed from the escrow");
		}
		if (count($differences['escrow']['changed']) > 0) {
			array_push($difference_summary_lines, count($differences['escrow']['changed'])." escrow amount".(count($differences['escrow']['changed']) == 1 ? " was" : "s were")." changed");
		}
		if ($differences['events']['new_events'] > 0) {
			array_push($difference_summary_lines, number_format($differences['events']['new_events'])." new event".($differences['events']['new_events'] == 1 ? " was " : "s were")." added");
		}
		if ($differences['events']['removed_events'] > 0) {
			array_push($difference_summary_lines, number_format($differences['events']['removed_events'])." event".($differences['events']['removed_events'] == 1 ? " was" : "s were")." removed");
		}
		if ($differences['events']['block_changed_events'] > 0) {
			array_push($difference_summary_lines, "Blocks were changed in ".number_format($differences['events']['block_changed_events'])." event".($differences['events']['block_changed_events'] == 1 ? "" : "s"));
		}
		if ($differences['events']['outcome_changed_events'] > 0) {
			array_push($difference_summary_lines, "Outcomes were changed for ".number_format($differences['events']['outcome_changed_events'])." event".($differences['events']['outcome_changed_events'] == 1 ? "" : "s"));
		}
		if ($differences['events']['other_changed_events'] > 0) {
			array_push($difference_summary_lines, $differences['events']['other_changed_events']." event".($differences['events']['other_changed_events'] != 1 ? "s were" : " was")." changed");
		}

		if (count($difference_summary_lines) == 0) array_push($difference_summary_lines, "No changes were found.");

		return [$differences, $difference_summary_lines];
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
		
		$game->lock_game_definition();
		
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
			$escrow_position = 0;
			
			foreach ($new_game_obj['escrow_amounts'] as $an_escrow_amount) {
				$an_escrow_amount = (array) $an_escrow_amount;
				$escrow_currency = $game->blockchain->app->fetch_currency_by_abbreviation($an_escrow_amount['currency']);
				
				if ($escrow_currency) {
					EscrowAmount::insert_escrow_amount($game->blockchain->app, $game->db_game['game_id'], $escrow_currency['currency_id'], "actual", $escrow_position, $an_escrow_amount);
					
					$escrow_position++;
				}
			}
		}
		
		$event_verbatim_vars = $game->blockchain->app->event_verbatim_vars();
		
		$num_initial_events = 0;
		if (!empty($initial_game_obj['events'])) $num_initial_events = count($initial_game_obj['events']);
		$num_new_events = 0;
		if (!empty($new_game_obj['events'])) $num_new_events = count($new_game_obj['events']);
		
		$matched_events = min($num_initial_events, $num_new_events);
		
		$events_start_at_index = isset($new_game_obj['events'][0]) ? $new_game_obj['events'][0]->event_index : 0;

		for ($i=0; $i<$matched_events; $i++) {
			$initial_event_text = self::game_def_to_text($initial_game_obj['events'][$i]);
			
			if (self::game_def_to_text($new_game_obj['events'][$i]) != $initial_event_text) {
				// If only thing changed is outcome, only need to reset from the payout block
				// otherwise need to reset from the event starting block.

				$new_event_no_outcome = clone $new_game_obj['events'][$i];
				$initial_event_no_outcome = clone $initial_game_obj['events'][$i];
				
				$initial_event_no_outcome->outcome_index = null;
				$initial_event_no_outcome->track_payout_price = null;
				$initial_event_no_outcome->event_payout_block = null;
				
				$new_event_no_outcome->outcome_index = null;
				$new_event_no_outcome->track_payout_price = null;
				$new_event_no_outcome->event_payout_block = null;
				
				if (self::game_def_to_text($initial_event_no_outcome) == self::game_def_to_text($new_event_no_outcome)) {
					$reset_block = $game->blockchain->app->min_excluding_false(array($reset_block, $initial_game_obj['events'][$i]->event_payout_block, $new_game_obj['events'][$i]->event_payout_block));
					
					$game->update_game_defined_event($i+$events_start_at_index, [
						'event_payout_block' => $new_game_obj['events'][$i]->event_payout_block,
						'track_payout_price' => isset($new_game_obj['events'][$i]->track_payout_price) ? $new_game_obj['events'][$i]->track_payout_price : null,
						'outcome_index' => $new_game_obj['events'][$i]->outcome_index,
					], true);
				}
				else {
					$reset_block = $game->blockchain->app->min_excluding_false(array($reset_block, $initial_game_obj['events'][$i]->event_starting_block, $new_game_obj['events'][$i]->event_starting_block));
					
					if ($reset_event_index === false) $reset_event_index = $new_game_obj['events'][$i]->event_index;
				}
			}
		}
		
		if (!empty($new_game_obj['events']) && count($new_game_obj['events']) > 0) {
			for ($i=$matched_events; $i<count($new_game_obj['events']); $i++) {
				$reset_block = $game->blockchain->app->min_excluding_false(array($reset_block, $new_game_obj['events'][$i]->event_starting_block));
			}
		}
		
		if (count($new_game_obj['events']) < count($initial_game_obj['events'])) {
			$delete_from_event_index = count($new_game_obj['events'])+$events_start_at_index;
			$game->blockchain->app->run_query("DELETE FROM game_defined_options WHERE game_id=:game_id AND event_index >= :event_index;", [
				'game_id' => $game->db_game['game_id'],
				'event_index' => $delete_from_event_index,
			]);
			$game->blockchain->app->run_query("DELETE FROM game_defined_events WHERE game_id=:game_id AND event_index >= :event_index;", [
				'game_id' => $game->db_game['game_id'],
				'event_index' => $delete_from_event_index,
			]);
			$log_message .= "Deleting events from index ".$delete_from_event_index."\n";
		}

		$set_events_from_index = $game->blockchain->app->min_excluding_false(array($reset_event_index, $matched_events));
		
		if ($set_events_from_index !== false) {
			$log_message .= "Resetting events from #".$set_events_from_index."\n";
			
			$events_earliest_affected_block = $game->event_index_to_affected_block($set_events_from_index);
			$reset_block = $game->blockchain->app->min_excluding_false([$reset_block, $events_earliest_affected_block]);
			
			$set_events_from_pos = $set_events_from_index;
			
			if (!is_numeric($reset_block)) $reset_block = $new_game_obj['events'][$set_events_from_pos]->event_starting_block;
			
			if (!empty($new_game_obj['events']) && count($new_game_obj['events']) > 0) {
				for ($event_pos=$set_events_from_pos-$events_start_at_index; $event_pos<count($new_game_obj['events']); $event_pos++) {
					if (!empty($new_game_obj['events'][$event_pos])) {
						$gde = get_object_vars($new_game_obj['events'][$event_pos]);
						$game->blockchain->app->check_set_gde($game, $gde, $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id'], $general_entity_type['entity_type_id']);
					}
				}
			}
		}
		
		if (!is_numeric($reset_block)) $reset_block = $game->blockchain->last_block_id();
		
		$log_message .= "Resetting blocks from #".$reset_block."\n";
		
		$migration = self::record_migration($game, $user_id, $migration_type, $show_internal_params, $initial_game_def, $new_game_def);
		
		$game->schedule_game_reset($reset_block, $set_events_from_index, $migration['migration_id']);
		
		$game->unlock_game_definition();
		
		$game_extra_info = json_decode($game->db_game['extra_info']);
		
		if (!empty($game_extra_info->reset_from_block)) $log_message .= "Adjusted reset block: ".$game_extra_info->reset_from_block."\n";
		if (!empty($game_extra_info->reset_from_event_index)) $log_message .= "Adjusted event index: ".$game_extra_info->reset_from_event_index."\n";
		
		$game->update_db_game();

		return $log_message;
	}
	
	public static function set_game_from_definition(&$app, &$game_definition, &$thisuser, &$error_message, &$db_game, $permission_override) {
		$game = false;
		$decode_error = false;
		$is_new_game = false;
		
		if (is_object($game_definition)) $game_def = $game_definition;
		else {
			if ($game_def = json_decode($game_definition)) {}
			else {
				$decode_error = true;
				$error_message .= "Error: the game definition you entered could not be imported: ".$app->json_decode_error_code_to_string(json_last_error())."\n";
			}
		}
		
		if (!$decode_error) {
			$module_ok = true;
			if (!empty($game_def->module)) {
				$db_module = $app->check_module($game_def->module);
				if (!$db_module) $module_ok = false;
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
						
						$app->run_insert_query("blockchains", [
							'blockchain_name' => "Private Chain ".$chain_id,
							'url_identifier' => $url_identifier,
							'decimal_places' => $decimal_places,
							'initial_pow_reward' => $chain_pow_reward,
							'online' => 1,
							'p2p_mode' => 'none',
							'coin_name' => 'chaincoin',
							'coin_name_plural' => 'chaincoins',
							'seconds_per_block' => 30
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
									
									if (!array_key_exists("short_description", $new_game_params)) $new_game_params['short_description'] = "";
									
									$game = Game::create_game($blockchain, $new_game_params);
									
									$is_new_game = true;
									
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
									$escrow_position = 0;
									
									foreach ($game_def->escrow_amounts as $escrow_amount) {
										$escrow_amount = (array) $escrow_amount;
										
										$escrow_currency = $app->fetch_currency_by_abbreviation($escrow_amount['currency']);
										
										if ($escrow_currency) {
											EscrowAmount::insert_escrow_amount($app, $game->db_game['game_id'], $escrow_currency['currency_id'], "defined", $escrow_position, $escrow_amount);
											
											$escrow_position++;
										}
									}
								}
								
								$show_internal_params = false;
								list($from_game_def_hash, $from_game_def) = self::export_game_definition($game, "defined", $show_internal_params, false);
								self::check_set_game_definition($app, $from_game_def_hash, $from_game_def, $game);
								
								$to_game_def_str = self::game_def_to_text($game_def);
								$to_game_def_hash = self::game_def_to_hash($to_game_def_str);
								self::check_set_game_definition($app, $to_game_def_hash, $game_def, $game);
								
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
			else $error_message .= "Failed to import game, you don't have the ".$game_def->module." module installed.\n";
		}
		
		return [$game, $is_new_game, $error_message];
	}

	public static function set_track_payout_price(&$game, &$game_defined_event, &$reference_currency) {
		$changed_game_definition = false;

		$db_block = $game->blockchain->fetch_block_by_id($game_defined_event['event_payout_block']);
		if ($db_block) $ref_time = $db_block['time_mined'];
		else $ref_time = time();

		$track_entity = $game->blockchain->app->fetch_entity_by_id($game_defined_event['track_entity_id']);
		$track_price_info = $game->blockchain->app->exchange_rate_between_currencies(1, $track_entity['currency_id'], $ref_time, $reference_currency['currency_id']);

		$track_price_usd = $game->blockchain->app->to_significant_digits($track_price_info['exchange_rate'], 8);

		if ((string)$track_price_usd !== (string)$game_defined_event['track_payout_price']) {
			$game->blockchain->app->run_query("UPDATE game_defined_events SET track_payout_price=:track_payout_price WHERE game_id=:game_id AND event_index=:event_index;", [
				'track_payout_price' => $track_price_usd,
				'game_id' => $game->db_game['game_id'],
				'event_index' => $game_defined_event['event_index']
			]);

			$game_defined_event['track_payout_price'] = $track_price_usd;

			$changed_game_definition = true;
		}

		return $changed_game_definition;
	}
}
