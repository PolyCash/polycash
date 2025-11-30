<?php
class MonsterDuelsManager {
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
		return $this->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND outcome_index IS NULL ORDER BY event_index ASC;", [
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

			$show_internal_params = true;
			list($initial_game_def_hash, $initial_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
			GameDefinition::check_set_game_definition($this->app, $initial_game_def_hash, $initial_game_def);

			if ($add_events) {
				$num_added = $this->add_events(true, $print_debug);
			} else {
				$num_added = 0;
			}

			if ($set_blocks) {
				$num_set_blocks = $this->custom_set_event_blocks($print_debug);
			} else {
				$num_set_blocks = 0;
			}

			if ($set_outcomes) {
				$num_set_outcome = $this->set_outcomes($print_debug);
			} else {
				$num_set_outcome = 0;
			}

			if ($num_added > 0 || $num_set_blocks > 0) {
				list($final_game_def_hash, $final_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->app, $final_game_def_hash, $final_game_def);
				
				if ($final_game_def_hash != $initial_game_def_hash) {
					$log_message = GameDefinition::migrate_game_definitions($this->game, null, "changed_by_module", $show_internal_params, $initial_game_def, $final_game_def);
					if ($print_debug) $this->app->print_debug("Migrating ".$initial_game_def_hash." to ".$final_game_def_hash."\n".$log_message);
				}
			}

			$this->game->unlock_game_definition();

			if ($add_events) $next_add_events_time = time() + (60*2);
			if ($set_blocks) $next_set_blocks_time = strtotime("+10 minutes");
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

	public function draft_monsters($formatted_monsters, $quantity_per_round, $determined_by_block) {
		$event_random_seed = hash("sha256", $determined_by_block['block_hash']);

		$chars_per_rand = 6;
		$num_random_numbers_needed = $quantity_per_round;

		$total_rand_chars_needed = $num_random_numbers_needed*$chars_per_rand;
		$random_data = $event_random_seed;
		$last_rand_hash = $event_random_seed;

		while (strlen($random_data) < $total_rand_chars_needed) {
			$last_rand_hash = hash("sha256", $last_rand_hash);
			$random_data .= $last_rand_hash;
		}

		$rand_numbers = [];
		for ($i=0; $i<$num_random_numbers_needed; $i++) {
			$rand_offset_start = $i*$chars_per_rand;
			$rand_chars = substr($random_data, $rand_offset_start, $chars_per_rand);
			$rand_num = hexdec($rand_chars);
			$rand_numbers[] = $rand_num;
		}

		$remaining_monsters = $formatted_monsters;
		$selected_monsters = [];
		$rand_pos = 0;
		for ($monster_pos=0; $monster_pos<$quantity_per_round; $monster_pos++) {
			$selected_monster_pos = $rand_numbers[$rand_pos]%count($remaining_monsters);
			$selected_monsters[] = $remaining_monsters[$selected_monster_pos];
			unset($remaining_monsters[$selected_monster_pos]);
			$remaining_monsters = array_values($remaining_monsters);
		}

		return $selected_monsters;
	}

	public function add_events($skip_record_migration=false, $print_debug=false) {
		$group = $this->game->blockchain->app->fetch_group_by_id($this->game->db_game['option_group_id']);
		list($monsters, $formatted_monsters) = $this->game->blockchain->app->group_details_json($group);
		$num_monsters = count($monsters);

		$minutes_per_event_cohort = $this->game_definition->minutes_per_event_cohort;

		$game_starting_time = new Datetime("2025-11-30 19:00:00");
		$game_starting_block = $this->game->db_game['game_starting_block'];

		$minutes_since_start = (time() - $game_starting_time->getTimestamp())/60;

		$current_cohort = ceil($minutes_since_start/$minutes_per_event_cohort);

		echo "minutes since start: $minutes_since_start, current cohort: $current_cohort\n";
		$max_gde = (string)$this->game->max_gde_index();

		if ($max_gde === "") $existing_events_cohort = null;
		else $existing_events_cohort = $max_gde;

		$add_count = 0;

		if ($existing_events_cohort === null || $current_cohort > $existing_events_cohort) {
			if (!$skip_record_migration) {
				$this->game->lock_game_definition();
				$show_internal_params = true;
				list($initial_game_def_hash, $initial_game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				GameDefinition::check_set_game_definition($this->app, $initial_game_def_hash, $initial_game_def);
			}

			$any_stopping_error = false;

			$json_events = [];

			$start_cohort = $existing_events_cohort === null ? 0 : $existing_events_cohort+1;

			if ($print_debug) $this->app->print_debug("Adding cohorts ".$start_cohort.":".$current_cohort);

			$event_i = $max_gde === "" ? 0 : $max_gde+1;

			for ($cohort=$start_cohort; $cohort<=$current_cohort; $cohort++) {
				$ref_time = microtime(true);
				$starting_time = (clone $game_starting_time)->modify("+".($minutes_per_event_cohort*$cohort)." minutes");
				$final_time = (clone $starting_time)->modify("+".$minutes_per_event_cohort." minutes");
				$event_starting_block = $this->game->time_to_block_in_game($starting_time->getTimestamp());
				$event_final_block = $this->game->time_to_block_in_game($final_time->getTimestamp(), true);

				$db_final_block = $this->game->blockchain->fetch_block_by_id($event_final_block);

				if ($db_final_block && $db_final_block['time_mined'] >= $final_time->getTimestamp()) {
					$payout_time_int = $db_final_block['time_mined'] + 8*60;
					$event_payout_block = $this->game->time_to_block_in_game($payout_time_int, true);
					$payout_time = new Datetime(date("Y-m-d H:i:s", $payout_time_int));
				}
				else {
					$payout_time = (clone $final_time)->modify("+40 minutes");
					$event_payout_block = $this->game->time_to_block_in_game($payout_time->getTimestamp(), true);
				}

				if ($event_starting_block === null || $event_final_block === null || $event_payout_block === null) {
					$any_stopping_error = true;
					break;
				}

				$determined_by_time = max((clone $game_starting_time)->getTimestamp(), (clone $starting_time)->modify("-1 hour")->getTimestamp());
				$determined_by_block_id = max($this->game->db_game['game_starting_block'], $this->game->time_to_block_in_game($determined_by_time));

				if (!$determined_by_block_id) {
					echo "Failed to fetch block at time ".date("Y-m-d H:i:s", $determined_by_time)." UTC, skipping cohort #".$cohort."\n";
					break;
				}

				$determined_by_block = $this->game->blockchain->fetch_block_by_id($determined_by_block_id);

				$drafted_monsters = $this->draft_monsters($formatted_monsters, 8, $determined_by_block);
				$possible_outcomes = [];
				
				foreach ($drafted_monsters as $drafted_monster) {
					$possible_outcomes[] = [
						"name" => $drafted_monster['entity_name'],
						"entity_id" => $drafted_monster['entity_id']
					];
				}

				$event = [
					"event_index" => $event_i,
					"event_starting_block" => $event_starting_block,
					"event_final_block" => $event_final_block,
					"event_determined_from_block" => $event_final_block+1,
					"event_determined_to_block" => $event_payout_block,
					"event_payout_block" => $event_payout_block,
					"event_starting_time" => $starting_time->format("Y-m-d H:i:s"),
					"event_final_time" => $final_time->format("Y-m-d H:i:s"),
					"event_payout_time" => $payout_time->format("Y-m-d H:i:s"),
					"event_name" => "Battle #".($event_i+1),
					"option_name" => "monster",
					"option_name_plural" => "monsters",
					"payout_rule" => "binary",
					"payout_rate" => $this->game->db_game['default_payout_rate'],
					"outcome_index" => null,
					"options" => $possible_outcomes
				];

				array_push($json_events, $event);

				$event_i++;
			}

			if ($any_stopping_error) {
				if ($print_debug) $this->app->print_debug("There was an error generating events, skipping.");
			} else {
				$verbatim_vars = [
					"event_index",
					"event_starting_block",
					"event_final_block",
					"event_determined_from_block",
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

		$event_arr = $this->app->run_query("SELECT gde.* FROM game_defined_events gde JOIN blocks b ON gde.event_payout_block=b.block_id AND b.blockchain_id=:blockchain_id WHERE gde.game_id=:game_id AND gde.event_payout_time < FROM_UNIXTIME(b.time_mined) ORDER BY gde.event_index DESC;", [
			'blockchain_id' => $this->game->blockchain->db_blockchain['blockchain_id'],
			'game_id' => $this->game->db_game['game_id'],
		])->fetchAll();

		foreach ($event_arr as $gde) {
			if (!isset($update_events_by_index[$gde['event_index']])) $update_events_by_index[$gde['event_index']] = $gde;
		}

		if ($print_debug) echo "Changing blocks for ".count($update_events_by_index)." events.\n";

		$set_count = 0;
		$num_changed = 0;
		$time_to_prev_block_cache = [];
		$time_to_next_block_cache = [];
		foreach ($update_events_by_index as $eventIndex => $gde) {
			// First revise event payout time if necessary
			$db_final_block = $this->game->blockchain->fetch_block_by_id($gde['event_final_block']);

			if ($db_final_block && $db_final_block['time_mined'] >= strtotime($gde['event_final_time'])) {
				$payout_time_int = $db_final_block['time_mined'] + 8*60;
				$event_payout_block = $this->game->time_to_block_in_game($payout_time_int, true);
				$payout_time = new Datetime(date("Y-m-d H:i:s", $payout_time_int));
			}
			else {
				$payout_time = (new Datetime($gde['event_final_time']))->modify("+40 minutes");
				$event_payout_block = $this->game->time_to_block_in_game($payout_time->getTimestamp(), true);
				$payout_time_int = $payout_time->getTimestamp();
			}

			if ($gde['event_payout_time'] != date("Y-m-d H:i:s", $payout_time_int) || $gde['event_payout_block'] != $event_payout_block) {
				$this->app->run_query("UPDATE game_defined_events SET event_payout_time=:event_payout_time, event_payout_block=:event_payout_block WHERE game_id=:game_id AND event_index=:event_index;", [
					'event_payout_time' => date("Y-m-d H:i:s", $payout_time_int),
					'event_payout_block' => $event_payout_block,
					'game_id' => $this->game->db_game['game_id'],
					'event_index' => $gde['event_index'],
				]);
				$gde['event_payout_time'] = date("Y-m-d H:i:s", $payout_time_int);
				$gde['event_payout_block'] = $event_payout_block;
			}

			// Now set blocks
			$changed = $this->game->set_gde_blocks_by_time($gde, $time_to_prev_block_cache, $time_to_next_block_cache, $final_block_select_after_time=true, $payout_block_select_after_time=true);
			if ($changed) $num_changed++;
			if ($print_debug && ($set_count+1)%100 == 0) echo "Changed ".$num_changed."/".$set_count."\n";
			$set_count++;
		}

		if ($print_debug) echo "Changed ".$num_changed."/".$set_count."\n";

		return $set_count;
	}

	public function set_outcomes($print_debug=false) {
		$num_changed = 0;
		$change_gdes = $this->fetch_events_needing_outcome_change();

		if ($print_debug) {
			$this->app->print_debug("Attempting to set outcome for ".count($change_gdes)." events.");
		}

		if (count($change_gdes) > 0) {
			foreach ($change_gdes as $change_gde) {
				$set_outcome = $this->game_definition->set_outcome($this->game, $change_gde);
				if ($set_outcome) $num_changed++;
			}
		}

		return $num_changed;
	}
}
