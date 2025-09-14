<?php
class AmericanFootballSeasonManager {
	public $app;
	
	public function __construct(&$game_definition, &$app, &$game) {
		$this->game_definition = $game_definition;
		$this->app = $app;
		$this->game = $game;
	}

	public function add_events($force_run=false) {
		$this->game_definition->initialize_module_info($this->game);
		$this->game_definition->read_module_info($this->game);

		if (!empty($this->game_definition->module_info['last_add_events_time'])) $last_add_events_time = $this->game_definition->module_info['last_add_events_time'];
		else $last_add_events_time = 0;
		
		$frequency_sec = 60*5;
		$betting_days = 21;
		
		if ($last_add_events_time < time()-$frequency_sec || $force_run) {
			$api_events = [];
			$team_entity_type = $this->app->check_set_entity_type("american football team");
			$general_entity_type = $this->app->check_set_entity_type("general entity");
			
			$max_info = $this->app->run_query("SELECT MAX(event_starting_time), MAX(event_index) FROM game_defined_events WHERE game_id=:game_id;", ['game_id' => $this->game->db_game['game_id']])->fetch();
			
			if (!empty($max_info['MAX(event_index)'])) $add_event_index = $max_info['MAX(event_index)']+1;
			else $add_event_index = 0;
			
			$scrape_url = "http://opensourcebets.com/api/fixtures?sport=american_football&from_date=".date("Y-m-d")."&to_date=2026-03-01";
			echo $scrape_url."\n";
			$scrape_response = file_get_contents($scrape_url);
			$scrape_obj = json_decode($scrape_response) or die('failed to decode json '.$scrape_url.'<br/><pre>'.$scrape_response.'</pre>');
			
			echo $scrape_url."\n";
			echo "Found ".$scrape_obj->num_fixtures." fixtures.<br/>\n";
			
			$seen_i = 0;
			$json_events = [];
			
			foreach ($scrape_obj->fixtures as $api_fixture) {
				$match_start_time = strtotime($api_fixture->fixture_time);
				$betting_end_time = $match_start_time-3600;
				$betting_start_time = max(strtotime(date("Y-m-d H").":00:00"), $match_start_time-(3600*24*$betting_days));
				
				$external_identifier = $api_fixture->fixture_id;
				
				$event_name = $api_fixture->teams[0]->team_name." vs ".$api_fixture->teams[1]->team_name." on ".(new DateTime("now", new DateTimeZone('America/New_York')))->setTimestamp($match_start_time)->format("M j, Y");
				
				$existing_gde = $this->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND external_identifier=:external_identifier;", [
					'game_id' => $this->game->db_game['game_id'],
					'external_identifier' => $external_identifier
				])->fetch();
				
				if (!$existing_gde) {
					if ($betting_end_time > time()+(3600*2)) {
						$payout_time = $match_start_time+(3600*24);
						
						$sport = "American Football";
						$league = $api_fixture->league;
						$home = $api_fixture->teams[0]->team_name;
						$away = $api_fixture->teams[1]->team_name;
						
						if (isset($api_fixture->odds->bovada->moneyline->$home) && isset($api_fixture->odds->bovada->moneyline->$away) && isset($api_fixture->odds->bovada->moneyline->tie)) {
							$home_odds = $api_fixture->odds->bovada->moneyline->$home;
							$away_odds = $api_fixture->odds->bovada->moneyline->$away;
							$tie_odds = $api_fixture->odds->bovada->moneyline->tie;
							
							$prob_sum = 1/$home_odds + 1/$away_odds + 1/$tie_odds;
							
							$adjusted_home_prob = (1/$home_odds)/$prob_sum;
							$adjusted_away_prob = (1/$away_odds)/$prob_sum;
							$adjusted_tie_prob = (1/$tie_odds)/$prob_sum;
						}

						$home_entity = $this->app->check_set_entity($team_entity_type['entity_type_id'], $home);
						$away_entity = $this->app->check_set_entity($team_entity_type['entity_type_id'], $away);
						$tie_entity = $this->app->check_set_entity($general_entity_type['entity_type_id'], "Tie");
						
						if (empty($home_entity['default_image_id']) && !empty($api_fixture->teams[0]->team_image)) {
							$home_logo_url = $api_fixture->teams[0]->team_image;
							$message = "";
							$db_image = $this->app->set_entity_image_from_url($home_logo_url, $home_entity['entity_id'], $message);
						}
						
						if (empty($away_entity['default_image_id']) && !empty($api_fixture->teams[1]->team_image)) {
							$away_logo_url = $api_fixture->teams[1]->team_image;
							$message = "";
							$db_image = $this->app->set_entity_image_from_url($away_logo_url, $away_entity['entity_id'], $message);
						}
						
						$formatted_betting_end_time = date("Y-m-d G:i:s", $betting_end_time);
						$formatted_betting_start_time = date("Y-m-d G:i:s", $betting_start_time);
						$formatted_payout_time = date("Y-m-d G:i:s", $payout_time);
						$event_date = date("Y-m-d", $match_start_time);
						
						if (!empty($sport)) {
							$sport_entity = $this->app->check_set_entity($general_entity_type['entity_type_id'], $sport);
							$sport_entity_id = $sport_entity['entity_id'];
						}
						else $sport_entity_id = null;
						
						if (!empty($league)) {
							$league_entity = $this->app->check_set_entity($general_entity_type['entity_type_id'], $league);
							$league_entity_id = $league_entity['entity_id'];
						}
						else $league_entity_id = null;
						
						$json_event = [
							'event_starting_time' => $formatted_betting_start_time,
							'event_final_time' => $formatted_betting_end_time,
							'event_payout_time' => $formatted_payout_time,
							'sport_entity_id' => $sport_entity_id,
							'league_entity_id' => $league_entity_id,
							'event_name' => $event_name,
							'external_identifier' => $external_identifier,
							'payout_rate' => 1,
							'option_name' => 'team',
							'option_name_plural' => 'teams',
							'options' => [
								'home' => [
									'entity_id' => $home_entity['entity_id'],
									'prob' => $adjusted_home_prob ?? null,
									'name' => $home." to win",
								],
								'away' => [
									'entity_id' => $away_entity['entity_id'],
									'prob' => $adjusted_away_prob ?? null,
									'name' => $away." to win",
								]
							]
						];
						
						$json_events[$seen_i] = $json_event;
						$event_times[$seen_i] = $match_start_time;
						$seen_i++;
					} else {
						echo "Excluded due to time, external identifier: ".$external_identifier."\n";
					}
				} else {
					echo "Skipping existing GDE with external identifier: ".$external_identifier."\n";
				}
			}
			
			echo "Adding ".count($json_events)."<br/>\n";
			
			if (count($json_events) > 0) {
				array_multisort($event_times, $json_events);
				
				$verbatim_vars = [
					'event_starting_time',
					'event_final_time',
					'event_payout_time',
					'sport_entity_id',
					'league_entity_id',
					'event_name',
					'external_identifier',
					'payout_rate',
					'option_name',
					'option_name_plural'
				];
				
				foreach ($json_events as $json_event) {
					$gde_params = [
						'game_id' => $this->game->db_game['game_id'],
						'event_index' => $add_event_index
					];
					$gde_q = "INSERT INTO game_defined_events SET game_id=:game_id, event_index=:event_index";
					foreach ($verbatim_vars as $verbatim_var) {
						$gde_q .= ", ".$verbatim_var."=:".$verbatim_var;
						$gde_params[$verbatim_var] = $json_event[$verbatim_var];
					}
					$this->app->run_query($gde_q, $gde_params);
					
					$option_index = 0;
					foreach ($json_event['options'] as $home_away => $option) {
						$this->app->run_query("INSERT INTO game_defined_options SET game_id=:game_id, entity_id=:entity_id, event_index=:event_index, option_index=:option_index, name=:name, target_probability=:target_probability;", [
							'game_id' => $this->game->db_game['game_id'],
							'entity_id' => $option['entity_id'],
							'event_index' => $add_event_index,
							'option_index' => $option_index,
							'name' => $option['name'],
							'target_probability' => $option['prob']
						]);
						$option_index++;
					}
					
					$add_event_index++;
				}
				
				$this->game->set_event_blocks(null, false);
				
				$show_internal_params = true;
				
				list($game_def_hash, $game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				list($actual_game_def_hash, $actual_game_def) = GameDefinition::export_game_definition($this->game, "actual", $show_internal_params, false);
				
				if ($game_def_hash != $actual_game_def_hash) {
					$log_message = GameDefinition::migrate_game_definitions($this->game, null, "apply_defined_to_actual", $show_internal_params, $actual_game_def, $game_def);
				}
			}
			
			$this->game_definition->module_info['last_add_events_time'] = time();
			$this->game_definition->save_module_info($this->game);
		}
		else echo "Ran recently, skipping..\n";
	}
	
	public function set_outcomes($force_run=false) {
		$this->game_definition->initialize_module_info($this->game);
		$this->game_definition->read_module_info($this->game);
		
		if (!empty($this->game_definition->module_info['last_set_outcomes_time'])) $last_set_outcomes_time = $this->game_definition->module_info['last_set_outcomes_time'];
		else $last_set_outcomes_time = 0;
		
		$frequency_sec = 120;
		
		if ($last_set_outcomes_time < time()-$frequency_sec || $force_run) {
			$change_count = 0;
			
			$pastdue_r = $this->app->run_query("SELECT * FROM game_defined_events WHERE game_id='".$this->game->db_game['game_id']."' AND outcome_index IS NULL AND event_final_block <= ".$this->game->blockchain->last_block_id()." AND external_identifier IS NOT NULL ORDER BY event_index ASC;");
			
			echo "Checking ".$pastdue_r->rowCount()." pending events.<br/>\n";
			
			while ($pastdue_event = $pastdue_r->fetch()) {
				$scrape_url = "http://opensourcebets.com/api/fixtures?fixture_id=".$pastdue_event['external_identifier'];
				echo $scrape_url."\n";
				$scrape_response_raw = file_get_contents($scrape_url);
				
				if ($scrape_response_raw) {
					$scrape_obj = json_decode($scrape_response_raw);
					
					if (!empty($scrape_obj->fixtures[0]->outcome)) {
						if ($scrape_obj->fixtures[0]->outcome->outcome_type == "successful") {
							if ($scrape_obj->fixtures[0]->outcome->is_tie) $outcome_index = -1;
							else $outcome_index = ($scrape_obj->fixtures[0]->outcome->winner->position == 0) ? 0 : 1;
						}
						else $outcome_index = -1;
						
						$this->game->set_game_defined_outcome($pastdue_event['event_index'], $outcome_index);
						
						$change_count++;
					}
				}
			}
			
			GameDefinition::set_cached_definition_hashes($this->game);
			
			if ($change_count > 0) {
				$show_internal_params = true;
				
				list($game_def_hash, $game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
				list($actual_game_def_hash, $actual_game_def) = GameDefinition::export_game_definition($this->game, "actual", $show_internal_params, false);
				
				if ($game_def_hash != $actual_game_def_hash) {
					$log_message = GameDefinition::migrate_game_definitions($this->game, null, "apply_defined_to_actual", $show_internal_params, $actual_game_def, $game_def);
					echo "Migrating ".$actual_game_def_hash." to ".$game_def_hash."\n";
				} else {
					echo "Loaded and specified game definitions are the same.\n";
				}
			} else {
				echo "Found no changes to apply.\n";
			}
			
			$this->game_definition->module_info['last_set_outcomes_time'] = time();
			$this->game_definition->save_module_info($this->game);
		}
		else echo "Ran recently, skipping...\n";
	}
	
	public function set_blocks($force_run = false) {
		$this->game_definition->initialize_module_info($this->game);
		$this->game_definition->read_module_info($this->game);

		if (!empty($this->game_definition->module_info['last_set_blocks_time'])) $last_set_blocks_time = $this->game_definition->module_info['last_set_blocks_time'];
		else $last_set_blocks_time = 0;
		
		$frequency_sec = 60*60*6;
		
		if ($last_set_blocks_time < time()-$frequency_sec || $force_run) {
			$this->game->set_event_blocks(null, false);
			
			$show_internal_params = true;
			
			list($game_def_hash, $game_def) = GameDefinition::export_game_definition($this->game, "defined", $show_internal_params, false);
			list($actual_game_def_hash, $actual_game_def) = GameDefinition::export_game_definition($this->game, "actual", $show_internal_params, false);
			
			if ($game_def_hash != $actual_game_def_hash) {
				$log_message = GameDefinition::migrate_game_definitions($this->game, null, "apply_defined_to_actual", $show_internal_params, $actual_game_def, $game_def);
			}
			
			$this->game_definition->module_info['last_set_blocks_time'] = time();
			$this->game_definition->save_module_info($this->game);
		}
		else echo "Ran recently, skipping...\n";
	}
	
	public function fix_images($force_run = false) {
		if (!empty($this->game_definition->module_info['last_fix_images_time'])) $last_fix_images_time = $this->game_definition->module_info['last_fix_images_time'];
		else $last_fix_images_time = 0;
		
		$frequency_sec = 60*30;
		
		if ($last_fix_images_time < time()-$frequency_sec || $force_run) {
			$imageless_options = $this->app->run_query("SELECT * FROM game_defined_events gde JOIN game_defined_options gdo ON gde.event_index=gdo.event_index JOIN entities en ON gdo.entity_id=en.entity_id WHERE gdo.game_id=gde.game_id AND gde.game_id='".$this->game->db_game['game_id']."' AND gde.external_identifier IS NOT NULL AND en.default_image_id IS NULL ORDER BY gde.event_index ASC, gdo.option_index ASC;");
			
			echo "Trying to fix images for ".$imageless_options->rowCount()." options\n";
			$this->app->flush_buffers();
			
			while ($imageless_option = $imageless_options->fetch()) {
				$entity = $this->app->fetch_entity_by_id($imageless_option['entity_id']);
				
				if (empty($entity['default_image_id'])) {
					$scrape_url = "http://opensourcebets.com/api/fixtures?fixture_id=".$imageless_option['external_identifier'];
					$api_obj = json_decode(file_get_contents($scrape_url));
					
					if ($imageless_option['option_index'] == 0) $team_pos = 0;
					else if ($imageless_option['option_index'] == 1) $team_pos = null;
					else if ($imageless_option['option_index'] == 2) $team_pos = 1;
					
					if ($team_pos !== null && !empty($api_obj->fixtures[0]->teams[$team_pos]->team_image)) {
						$message = "";
						$db_image = $this->app->set_entity_image_from_url($api_obj->fixtures[0]->teams[$team_pos]->team_image, $imageless_option['entity_id'], $message);
						echo $message;
						$this->app->flush_buffers();
					}
					else echo "No image found for ".$imageless_option['name']."\n";
				}
				else echo "image just set.. skip\n";
			}

			$this->game_definition->module_info['last_fix_images_time'] = time();
			$this->game_definition->save_module_info($this->game);
		}
		else echo "Ran recently, skipping...\n";
	}
}
?>