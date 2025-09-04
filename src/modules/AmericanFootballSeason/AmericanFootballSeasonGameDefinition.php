<?php
class AmericanFootballSeasonGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	public $module_info;
	
	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "datachain",
			"option_group": null,
			"protocol_version": 1.003,
			"name": "Football Cents 2025",
			"url_identifier": "football-cents-2025",
			"module": "AmericanFootballSeason",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": true,
			"save_every_definition": true,
			"recommended_keep_definitions_hours": null,
			"max_simultaneous_options": 1024,
			"event_type_name": "game",
			"event_type_name_plural": "games",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"inflation": "exponential",
			"exponential_inflation_rate": 0.0005,
			"pow_reward_type": "none",
			"initial_pow_reward": 0,
			"blocks_per_pow_reward_ajustment": 0,
			"round_length": 100,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"coin_name": "cent",
			"coin_name_plural": "cents",
			"coin_abbreviation": "FBC",
			"escrow_address": "XfsTGjwkoAbVQGzE93o6zG3g4gytqvYfCU",
			"genesis_tx_hash": "76ef82737d66ef70d63bf54241dd2484e2387700f3733729f866c1c908946027",
			"genesis_amount": 10000000,
			"game_starting_block": 446401,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": "0.00000000",
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"view_mode": "default",
			"order_options_by": "option_index",
			"order_events_by": "event_index",
			"target_option_block_score": null,
			"set_being_determined_blocks_method": "by_final_and_payout",
			"escrow_amounts": [
				{
					"type": "fixed",
					"currency": "USD",
					"amount": 10000000
				}
			],
			"events": []
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);

		$this->module_info = [];
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$events = [];
		
		$info = $this->app->run_query("SELECT MIN(event_index), MAX(event_index) FROM game_defined_events WHERE game_id=:game_id AND event_starting_block>=:from_block AND event_starting_block<=:to_block;", [
			'game_id' => $game->db_game['game_id'],
			'from_block' => $from_block,
			'to_block' => $to_block
		]);
		
		if ($info->rowCount() > 0) {
			$info = $info->fetch();
			
			$gde_r = $this->app->run_query("SELECT gde.*, sp.entity_name AS sport_name, le.entity_name AS league_name FROM game_defined_events gde LEFT JOIN entities sp ON gde.sport_entity_id=sp.entity_id LEFT JOIN entities le ON gde.league_entity_id=le.entity_id WHERE gde.game_id=:game_id AND gde.event_index>=:from_event_index AND gde.event_index<=:to_event_index;", [
				'game_id' => $game->db_game['game_id'],
				'from_event_index' => $info['MIN(event_index)'],
				'to_event_index' => $info['MAX(event_index)']
			]);
			
			while ($db_gde = $gde_r->fetch()) {
				$gdo_r = $this->app->fetch_game_defined_options($game->db_game['game_id'], $db_gde['event_index'], false, false);
				
				$possible_outcomes = [];
				while ($db_gdo = $gdo_r->fetch()) {
					$new_gdo = [
						"title"=>$db_gdo['name'],
						'entity_id'=>$db_gdo['entity_id']
					];
					if (!empty($db_gdo['target_probability'])) $new_gdo["target_probability"] = $db_gdo['target_probability'];
					
					array_push($possible_outcomes, $new_gdo);
				}
				
				$event = array(
					"event_index" => $db_gde['event_index'],
					"event_starting_block" => $db_gde['event_starting_block'],
					"event_final_block" => $db_gde['event_final_block'],
					"event_outcome_block" => $db_gde['event_outcome_block'],
					"event_payout_block" => $db_gde['event_payout_block'],
					"event_starting_time" => $db_gde['event_starting_time'],
					"event_final_time" => $db_gde['event_final_time'],
					"event_payout_time" => $db_gde['event_payout_time'],
					"event_determined_from_time" => $db_gde['event_determined_from_time'],
					"event_determined_to_time" => $db_gde['event_determined_to_time'],
					"event_name" => $db_gde['event_name'],
					"option_name" => $db_gde['option_name'],
					"option_name_plural" => $db_gde['option_name_plural'],
					"payout_rule" => $db_gde['payout_rule'],
					"payout_rate" => $db_gde['payout_rate'],
					"outcome_index" => $db_gde['outcome_index'],
					"possible_outcomes" => $possible_outcomes
				);
				if (!empty($db_gde['sport_name'])) $event["sport"] = $db_gde['sport_name'];
				if (!empty($db_gde['league_name'])) $event["league"] = $db_gde['league_name'];
				if (!empty($db_gde['external_identifier'])) $event["external_identifier"] = $db_gde['external_identifier'];
				
				array_push($events, $event);
			}
		}
		
		return $events;
	}
	
	public function regular_actions(&$game) {
		if (is_file(dirname(__FILE__)."/AmericanFootballSeasonManager.php")) {
			include_once(dirname(__FILE__)."/AmericanFootballSeasonManager.php");
			$manager = new AmericanFootballSeasonManager($this, $this->app, $game);
			$manager->add_events();
			$manager->set_outcomes();
			$manager->set_blocks();
			$manager->fix_images();
		}
	}

	public function module_info_fname(&$game) {
		return dirname(__FILE__).'/'.$game->db_game['game_id'].'.json';
	}

	public function initialize_module_info(&$game) {
		$module_info_fname = $this->module_info_fname($game);
		if (!is_file($module_info_fname)) {
			$module_info_fh = fopen($module_info_fname, 'w');
			fwrite($module_info_fh, json_encode($this->module_info, JSON_PRETTY_PRINT));
			fclose($module_info_fh);
		}
	}

	public function read_module_info(&$game) {
		$module_info_fname = $this->module_info_fname($game);
		$module_info_filesize = filesize($module_info_fname);
		if ($module_info_fh = fopen($module_info_fname, 'r')) {
			if ($module_info_filesize > 0) {
				$module_info_raw = fread($module_info_fh, filesize($module_info_fname));
				fclose($module_info_fh);
				$module_info = json_decode($module_info_raw, true);
				if (isset($module_info)) $this->module_info = $module_info;
			}
			else $this->app->print_debug("Failed to read module info, filesize: ".$module_info_filesize);
		}
		else $this->app->print_debug("Failed to read module info from ".$module_info_fname);
	}

	public function save_module_info(&$game) {
		$module_info_fname = $this->module_info_fname($game);
		$module_info_fh = fopen($module_info_fname, 'w');
		fwrite($module_info_fh, json_encode($this->module_info, JSON_PRETTY_PRINT)) or die("Failed to write ".$module_info_fname);
		fclose($module_info_fh);
	}
}
?>