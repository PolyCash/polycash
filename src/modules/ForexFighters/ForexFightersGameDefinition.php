<?php
class ForexFightersGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	public $currencies = [];

	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "datachain",
			"definitive_peer": "https://poly.cash",
			"option_group": "128 world currencies",
			"protocol_version": 1.001,
			"name": "Forex Fighters",
			"url_identifier": "forex-fighters",
			"module": "ForexFighters",
			"category_id": null,
			"decimal_places": 2,
			"finite_events": true,
			"save_every_definition": false,
			"max_simultaneous_options": 500,
			"event_type_name": "market",
			"event_type_name_plural": "markets",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"events_per_round": null,
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pow_reward_type": "none",
			"pow_fixed_reward": null,
			"round_length": 10,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"coin_name": "mimecoin",
			"coin_name_plural": "mimecoins",
			"coin_abbreviation": "MIME",
			"escrow_address": "Xufwr6itvTjXtVs9N48riMUk6MwW8iMP9E",
			"genesis_tx_hash": "32c0ea964e0c5238bf7a695a2ded81271baedeb34a623df65192ea92b019f5c0",
			"genesis_amount": 500000000,
			"game_starting_block": 236401,
			"default_payout_rate": 0.9999,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "linear",
			"view_mode": "default",
			"order_options_by": "option_index",
			"escrow_amounts": [
				{
					"type": "dynamic",
					"currency": "USD",
					"relative_amount": 1
				}
			]
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
	}
	
	public function load_currencies(&$game) {
		$this->currencies = [];
		
		$members = $this->app->run_query("SELECT *, en.entity_id AS entity_id FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id JOIN currencies c ON en.entity_name=c.name WHERE m.option_group_id=:option_group_id ORDER BY m.membership_id ASC;", ['option_group_id'=>$game->db_game['option_group_id']])->fetchAll();
		$currency_index = 0;
		
		foreach ($members as $db_member) {
			array_push($this->currencies, $db_member);
			$currency_index++;
		}
	}
	
	public function get_readable_number($range_length, $some_number) {
		$log10 = floor(log10($range_length));
		$roundto = pow(10, $log10);
		$readable_number = ceil($some_number/$roundto)*$roundto;
		return $readable_number;
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
			
			$this->load_currencies($game);
			
			$gde_r = $this->app->run_query("SELECT gde.* FROM game_defined_events gde LEFT JOIN entities sp ON gde.sport_entity_id=sp.entity_id LEFT JOIN entities le ON gde.league_entity_id=le.entity_id WHERE gde.game_id=:game_id AND gde.event_index>=:from_event_index AND gde.event_index<=:to_event_index;", [
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

					array_push($possible_outcomes, $new_gdo);
				}

				$event = [
					"event_index" => $db_gde['event_index'],
					"event_starting_block" => $db_gde['event_starting_block'],
					"event_final_block" => $db_gde['event_final_block'],
					"event_determined_to_block" => $db_gde['event_payout_block'],
					"event_payout_block" => $db_gde['event_payout_block'],
					"event_starting_time" => $db_gde['event_starting_time'],
					"event_final_time" => $db_gde['event_final_time'],
					"event_payout_time" => $db_gde['event_payout_time'],
					"event_name" => $db_gde['event_name'],
					"option_name" => "position",
					"option_name_plural" => "positions",
					"payout_rule" => "linear",
					"payout_rate" => $db_gde['payout_rate'],
					"outcome_index" => null,
					"track_min_price" => $db_gde['track_min_price'],
					"track_max_price" => $db_gde['track_max_price'],
					"track_max_price" => $db_gde['track_payout_price'],
					"track_name_short" => $tracked_currency['abbreviation'],
					"possible_outcomes" => $possible_outcomes,
				];

				array_push($events, $event);
			}
		}
		
		return $events;
	}

	public function regular_actions(&$game) {
		if (is_file(dirname(__FILE__)."/ForexFightersManager.php")) {
			include_once(dirname(__FILE__)."/ForexFightersManager.php");
			$manager = new ForexFightersManager($this, $this->app, $game);
			$manager->add_events();
			//$manager->set_outcomes();
			//$manager->set_blocks();
		}
	}
}
?>