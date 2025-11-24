<?php
class Forex32Manager {
	public $app;

	public function __construct(&$game_definition, &$app, &$game) {
		$this->game_definition = $game_definition;
		$this->app = $app;
		$this->game = $game;
	}

	public function regular_actions($force=false, $print_debug=false) {		
		$process_lock_name = "game_manager_regular_actions_".$this->game->db_game['game_id'];
		$process_locked = $this->app->check_process_running($process_lock_name);
		
		if (!$process_locked && $this->app->lock_process($process_lock_name)) {
			if (empty($this->game->db_game['definitive_game_peer_id'])) {
				$this->change_game_def($force, $print_debug);
			}
		}
		else if ($print_debug) $this->app->print_debug("Game manager regular actions process is already running.");
	}

	public function show_next_run_times() {
		$str = "Add events ".($this->game_definition->module_info['next_add_events_time'] == 0 ? "on next run" : "in ".$this->app->format_seconds($this->game_definition->module_info['next_add_events_time']-time()))."\n";
		$str .= "Set blocks ".($this->game_definition->module_info['next_set_blocks_time'] == 0 ? "on next run" : "in ".$this->app->format_seconds($this->game_definition->module_info['next_set_blocks_time']-time()))."\n";
		$str .= "Set outcomes ".($this->game_definition->module_info['next_set_outcomes_time'] == 0 ? "on next run" : "in ".$this->app->format_seconds($this->game_definition->module_info['next_set_outcomes_time']-time()))."\n";
		return $str;
	}

	public function fetch_events_needing_outcome_change() {
		return $this->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_payout_time <= NOW() AND track_payout_price IS NULL ORDER BY event_index ASC;", [
			'game_id' => $this->game->db_game['game_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}

	public function change_game_def($force=false, $print_debug=false) {
		if ($this->game->last_block_id() != $this->game->blockchain->last_block_id()) {
			if ($print_debug) $this->app->print_debug("Game is not fully loading, skipping make changes to game definition for game #".$this->game->db_game['game_id']);
			return;
		}

		$this->game_definition->initialize_module_info($this->game);
		$this->game_definition->read_module_info($this->game);

		if (!empty($this->game_definition->module_info['next_add_events_time'])) $next_add_events_time = $this->game_definition->module_info['next_add_events_time'];
		else $next_add_events_time = 0;
		$add_events = time() >= $next_add_events_time;

		if (!empty($this->game_definition->module_info['next_set_blocks_time'])) $next_set_blocks_time = $this->game_definition->module_info['next_set_blocks_time'];
		else $next_set_blocks_time = 0;
		$set_blocks = time() >= $next_set_blocks_time;

		if (!empty($this->game_definition->module_info['next_set_outcomes_time'])) $next_set_outcomes_time = $this->game_definition->module_info['next_set_outcomes_time'];
		else $next_set_outcomes_time = 0;
		$set_outcomes = time() >= $next_set_outcomes_time && count($this->fetch_events_needing_outcome_change()) > 0;

		if ($force || $add_events || $set_blocks || $set_outcomes) {
			if ($print_debug) $this->app->print_debug("Add events? ".json_encode($add_events).", set blocks? ".json_encode($set_blocks).", set outcomes? ".json_encode($set_outcomes));

			$this->game->lock_game_definition();

			$show_internal_params = false;
			list($initial_game_def_hash, $initial_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
			GameDefinition::check_set_game_definition($this->app, $initial_game_def_hash, $initial_game_def);

			if ($add_events) {
				$num_added = $this->add_events(true, $print_debug);
			}

			if ($set_blocks) {
				$num_set_blocks = $this->custom_set_event_blocks($print_debug);
			}

			if ($set_outcomes) {
				$num_set_outcome = $this->set_outcomes($print_debug);
			}

			if ($num_added > 0 || $num_set_blocks > 0 || $num_set_outcome > 0) {
				list($final_game_def_hash, $final_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->app, $final_game_def_hash, $final_game_def);
				
				if ($final_game_def_hash != $initial_game_def_hash) {
					$log_message = GameDefinition::migrate_game_definitions($this->game, null, "changed_by_module", $show_internal_params, $initial_game_def, $final_game_def);
					if ($print_debug) $this->app->print_debug("Migrating ".$initial_game_def_hash." to ".$final_game_def_hash."\n".$log_message);
				}
			}

			$this->game->unlock_game_definition();

			if ($add_events) $next_add_events_time = time() + (60*2);
			if ($set_blocks) $next_set_blocks_time = strtotime(date("Y-m-d H:00")." +1 hour");
			if ($set_outcomes || $next_set_outcomes_time == 0) $next_set_outcomes_time = time() + (60*2);

			$merge_times_sec = 180;
			if (abs($next_add_events_time - $next_set_blocks_time) <= $merge_times_sec) {
				$next_add_events_time_mod = max($next_add_events_time, $next_set_blocks_time);
				$next_set_blocks_time_mod = $next_add_events_time_mod;
			}
			if (abs($next_add_events_time - $next_set_outcomes_time) <= $merge_times_sec) {
				$next_add_events_time_mod = max($next_add_events_time, $next_set_outcomes_time);
				$next_set_outcomes_time_mod = $next_add_events_time_mod;
			}
			if (abs($next_set_blocks_time - $next_set_outcomes_time) <= $merge_times_sec) {
				$next_set_blocks_time_mod = max($next_set_blocks_time, $next_set_outcomes_time);
				$next_set_outcomes_time_mod = $next_set_blocks_time_mod;
			}

			if (isset($next_add_events_time_mod)) $next_add_events_time = $next_add_events_time_mod;
			if (isset($next_set_blocks_time_mod)) $next_set_blocks_time = $next_set_blocks_time_mod;
			if (isset($next_set_outcomes_time_mod)) $next_set_outcomes_time = $next_set_outcomes_time_mod;
			
			$this->game_definition->module_info['next_add_events_time'] = $next_add_events_time;
			$this->game_definition->module_info['next_set_blocks_time'] = $next_set_blocks_time;
			$this->game_definition->module_info['next_set_outcomes_time'] = $next_set_outcomes_time;

			$this->game_definition->save_module_info($this->game);

			if ($print_debug) $this->app->print_debug("Next times:\n".$this->show_next_run_times($this->game_definition->module_info));
		}
		else if ($print_debug) $this->app->print_debug("Change game definition doesn't need to run right now. Next times:\n".$this->show_next_run_times($this->game_definition->module_info));
	}

	public function add_events($skip_record_migration=false, $print_debug=false) {
		$this->game_definition->load_currencies($this->game);

		$hours_per_event_cohort = 6;
		$event_length_hours = 78;

		$game_starting_time = new Datetime("2024-10-08 0:00:00");
		$game_starting_block = $this->game->db_game['game_starting_block'];

		$hours_since_start = (time() - $game_starting_time->getTimestamp())/3600;

		$current_cohort = ceil($hours_since_start/$hours_per_event_cohort);

		$max_gde = (string)$this->game->max_gde_index();

		if ($max_gde === "") $existing_events_cohort = null;
		else $existing_events_cohort = floor($max_gde/count($this->game_definition->currencies));

		$add_count = 0;

		if ($existing_events_cohort === null || $current_cohort > $existing_events_cohort) {
			if (!$skip_record_migration) {
				$this->game->lock_game_definition();
				$show_internal_params = false;
				list($initial_game_def_hash, $initial_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->app, $initial_game_def_hash, $initial_game_def);
			}

			$any_stopping_error = false;

			$json_events = [];

			$start_cohort = $existing_events_cohort === null ? 0 : $existing_events_cohort+1;

			if ($print_debug) $this->app->print_debug("Adding cohorts ".$start_cohort.":".$current_cohort." (".count($this->game_definition->currencies)." currencies)");

			$event_i = $max_gde === "" ? 0 : $max_gde+1;

			$latestPriceByCurrencyId = [];

			for ($cohort=$start_cohort; $cohort<=$current_cohort; $cohort++) {
				$ref_time = microtime(true);
				$starting_time = (clone $game_starting_time)->modify("+".($hours_per_event_cohort*$cohort)." hours");
				$final_time = (clone $starting_time)->modify("+".$hours_per_event_cohort." hours");
				$payout_time = (clone $starting_time)->modify("+".$event_length_hours." hours")->modify("-30 minutes");
				$event_starting_block = $this->game->time_to_block_in_game($starting_time->getTimestamp());
				$event_final_block = $this->game->time_to_block_in_game($final_time->getTimestamp());
				$event_payout_block = $this->game->time_to_block_in_game($payout_time->getTimestamp());

				if ($event_starting_block === null || $event_final_block === null || $event_payout_block === null) {
					$any_stopping_error = true;
					break;
				}

				for ($currency_i=0; $currency_i<count($this->game_definition->currencies); $currency_i++) {
					$tracked_currency = $this->game_definition->currencies[$currency_i];

					if (array_key_exists($tracked_currency['currency_id'], $latestPriceByCurrencyId)) $price_usd = $latestPriceByCurrencyId[$tracked_currency['currency_id']];
					else {
						$track_price_info = $this->app->exchange_rate_between_currencies(1, $tracked_currency['currency_id'], time(), $this->app->get_reference_currency()['currency_id']);

						if ($track_price_info['time'] >= time()-(30*60)) {
							$price_usd = $track_price_info['exchange_rate'];
						}
						else {
							$any_stopping_error = true;
							break 2;
						}

						$latestPriceByCurrencyId[$tracked_currency['currency_id']] = $price_usd;
					}

					$price_min_target = $price_usd*0.98;
					$price_max_target = $price_usd*1.02;

					$price_min = $this->app->to_significant_digits($price_min_target, 2, true);
					if ($this->app->first_digit($price_min) == "1") $price_min = $this->app->to_significant_digits($price_min_target*2, 2, true)/2;

					$price_max = $this->app->to_significant_digits($price_max_target, 2, "err_higher");
					if ($this->app->first_digit($price_max) == "1") $price_max = $this->app->to_significant_digits($price_max_target*2, 2, "err_higher")/2;

					$event_name = ucfirst($tracked_currency['name'])." between $".$this->app->format_bignum($price_min, false)." and $".$this->app->format_bignum($price_max, false);

					$possible_outcomes = [
						[
							"name" => "Buy ".$tracked_currency['abbreviation'],
							"entity_id" => $tracked_currency['entity_id']
						],
						[
							"name" => "Sell ".$tracked_currency['abbreviation'],
							"entity_id" => $tracked_currency['entity_id']
						]
					];

					$event = [
						"event_index" => $event_i,
						"event_starting_block" => $event_starting_block,
						"event_final_block" => $event_final_block,
						"event_determined_to_block" => null,
						"event_payout_block" => $event_payout_block,
						"event_starting_time" => $starting_time->format("Y-m-d H:i:s"),
						"event_final_time" => $final_time->format("Y-m-d H:i:s"),
						"event_payout_time" => $payout_time->format("Y-m-d H:i:s"),
						"event_name" => $event_name,
						"option_name" => "position",
						"option_name_plural" => "positions",
						"payout_rule" => "linear",
						"payout_rate" => $this->game->db_game['default_payout_rate'],
						"outcome_index" => null,
						"track_min_price" => $price_min,
						"track_max_price" => $price_max,
						"track_name_short" => $tracked_currency['abbreviation'],
						"options" => $possible_outcomes
					];

					array_push($json_events, $event);

					$event_i++;
				}
			}

			if ($any_stopping_error) {
				if ($print_debug) $this->app->print_debug("There was an error generating events, skipping.");
			} else {
				$verbatim_vars = [
					"event_index",
					"event_starting_block",
					"event_final_block",
					"event_determined_to_block",
					"event_payout_block",
					"event_starting_time",
					"event_final_time",
					"event_payout_time",
					"event_name",
					"option_name",
					"option_name_plural",
					"payout_rule",
					"payout_rate",
					"outcome_index",
					"track_min_price",
					"track_max_price",
					"track_name_short",
				];

				$add_count = count($json_events);
				if ($print_debug) $this->app->print_debug("Adding ".$add_count." events.");

				$this->app->dbh->beginTransaction();

				foreach ($json_events as $json_event) {
					$gde_params = [
						'game_id' => $this->game->db_game['game_id'],
					];
					$gde_q = "INSERT INTO game_defined_events SET game_id=:game_id";
					foreach ($verbatim_vars as $verbatim_var) {
						$gde_q .= ", ".$verbatim_var."=:".$verbatim_var;
						$gde_params[$verbatim_var] = $json_event[$verbatim_var];
					}
					$this->app->run_query($gde_q, $gde_params);

					$option_index = 0;
					foreach ($json_event['options'] as $option) {
						$this->app->run_query("INSERT INTO game_defined_options SET game_id=:game_id, entity_id=:entity_id, event_index=:event_index, option_index=:option_index, name=:name;", [
							'game_id' => $this->game->db_game['game_id'],
							'entity_id' => $option['entity_id'],
							'event_index' => $json_event['event_index'],
							'option_index' => $option_index,
							'name' => $option['name'],
						]);
						$option_index++;
					}
				}

				$this->app->dbh->commit();

				if (!$skip_record_migration) {
					list($final_game_def_hash, $final_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
					GameDefinition::check_set_game_definition($this->app, $final_game_def_hash, $final_game_def);
					GameDefinition::record_migration($this->game, null, "added_events_by_module", $show_internal_params, $initial_game_def, $final_game_def);
					$this->game->unlock_game_definition();
				}

				if ($print_debug) $this->app->print_debug("Done adding events.");
			}
		}
		else if ($print_debug) $this->app->print_debug("Did not need to add any events.");

		return $add_count;
	}

	public function custom_set_event_blocks($print_debug=false) {
		$update_events_by_index = [];
		
		$event_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_starting_time IS NOT NULL AND event_final_time >= NOW() ORDER BY event_index ASC;";
		$event_arr = $this->app->run_query($event_q, [
			'game_id' => $this->game->db_game['game_id'],
		])->fetchAll();
		
		foreach ($event_arr as $gde) {
			$update_events_by_index[$gde['event_index']] = $gde;
		}
		
		$event_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_starting_time IS NOT NULL AND event_payout_time >= :from_payout_time AND event_payout_time <= :to_payout_time ORDER BY event_index ASC;";
		$event_arr = $this->app->run_query($event_q, [
			'game_id' => $this->game->db_game['game_id'],
			'from_payout_time' => date("Y-m-d H:i:s", strtotime("-200 minutes")),
			'to_payout_time' => date("Y-m-d H:i:s", strtotime("+200 minutes")),
		])->fetchAll();
		
		foreach ($event_arr as $gde) {
			if (!isset($update_events_by_index[$gde['event_index']])) $update_events_by_index[$gde['event_index']] = $gde;
		}
		
		$event_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_starting_time IS NOT NULL AND event_starting_time >= :from_starting_time AND event_starting_time <= :to_starting_time ORDER BY event_index ASC;";
		$event_arr = $this->app->run_query($event_q, [
			'game_id' => $this->game->db_game['game_id'],
			'from_starting_time' => date("Y-m-d H:i:s", strtotime("-200 minutes")),
			'to_starting_time' => date("Y-m-d H:i:s", strtotime("+200 minutes")),
		])->fetchAll();
		
		foreach ($event_arr as $gde) {
			if (!isset($update_events_by_index[$gde['event_index']])) $update_events_by_index[$gde['event_index']] = $gde;
		}
		
		$event_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_final_time IS NOT NULL AND event_final_time >= :from_final_time AND event_final_time <= :to_final_time ORDER BY event_index ASC;";
		$event_arr = $this->app->run_query($event_q, [
			'game_id' => $this->game->db_game['game_id'],
			'from_final_time' => date("Y-m-d H:i:s", strtotime("-200 minutes")),
			'to_final_time' => date("Y-m-d H:i:s", strtotime("+200 minutes")),
		])->fetchAll();
		
		foreach ($event_arr as $gde) {
			if (!isset($update_events_by_index[$gde['event_index']])) $update_events_by_index[$gde['event_index']] = $gde;
		}
		
		$event_arr = $this->app->run_query("SELECT gde.* FROM game_defined_events gde JOIN blocks b ON gde.event_payout_block=b.block_id AND b.blockchain_id=:blockchain_id WHERE gde.game_id=:game_id AND gde.event_payout_time < FROM_UNIXTIME(b.time_mined) ORDER BY gde.event_index DESC;", [
			'blockchain_id' => $this->game->blockchain->db_blockchain['blockchain_id'],
			'game_id' => $this->game->db_game['game_id'],
		])->fetchAll();
		
		foreach ($event_arr as $gde) {
			if (!isset($update_events_by_index[$gde['event_index']])) $update_events_by_index[$gde['event_index']] = $gde;
		}
		
		if ($print_debug) echo "Changing blocks for ".count($update_events_by_index)." events.\n";
		
		$set_count = 0;
		$time_to_prev_block_cache = [];
		$time_to_next_block_cache = [];
		foreach ($update_events_by_index as $eventIndex => $gde) {
			$this->game->set_gde_blocks_by_time($gde, $time_to_prev_block_cache, $time_to_next_block_cache);
			if ($print_debug && $set_count%100 == 0) echo $set_count."/".count($update_events_by_index)."\n";
			$set_count++;
		}
		
		return $set_count;
	}

	public function set_outcomes($print_debug=false) {
		$num_changed = 0;
		$change_gdes = $this->fetch_events_needing_outcome_change();

		if (count($change_gdes) > 0) {
			$reference_currency = $this->app->get_reference_currency();
			foreach ($change_gdes as $change_gde) {
				$changed = GameDefinition::set_track_payout_price($this->game, $change_gde, $reference_currency);
				if ($changed) $num_changed++;
			}
		}

		return $num_changed;
	}
}
