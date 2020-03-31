<?php
class eSportsGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": null,
			"protocol_version": 0,
			"name": "PolyCash eSports",
			"url_identifier": "polycash-esports",
			"module": "eSports",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": true,
			"save_every_definition": true,
			"event_type_name": "match",
			"event_type_name_plural": "matches",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"event_entity_type_id": 0,
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pos_reward": 0,
			"round_length": 1,
			"maturity": 0,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "dollars",
			"coin_name_plural": "dollars",
			"coin_abbreviation": "USDF",
			"escrow_address": "D7MBXDEHXGQm2hWhPr4pQo3tRP6bjVV8D5",
			"genesis_tx_hash": "d431ad8a1c2ebd9522e1c2b1bdff871b",
			"genesis_amount": 1000000000000,
			"game_starting_block": 322971,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default",
			"escrow_amounts": {
				"dollars": 100000000
			}
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
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
					"event_name" => $db_gde['event_name'],
					"option_name" => $db_gde['option_name'],
					"option_name_plural" => $db_gde['option_name_plural'],
					"payout_rule" => $db_gde['payout_rule'],
					"payout_rate" => 1,
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
	
	public function regular_actions(&$game) {}
}
?>