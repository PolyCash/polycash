<?php
class Forex32GameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	public $currencies = [];
	public $module_info = null;

	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "datachain",
			"definitive_peer": "https://poly.cash",
			"option_group": "32 highly traded currencies",
			"protocol_version": 1.002,
			"name": "Forex 32",
			"url_identifier": "forex-32",
			"module": "Forex32",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": true,
			"save_every_definition": false,
			"recommended_keep_definitions_hours": 48,
			"max_simultaneous_options": 128,
			"event_type_name": "market",
			"event_type_name_plural": "markets",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"events_per_round": null,
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pow_reward_type": "none",
			"initial_pow_reward": null,
			"blocks_per_pow_reward_ajustment": null,
			"pow_fixed_reward": null,
			"round_length": 100,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"coin_name": "mimecoin",
			"coin_name_plural": "mimecoins",
			"coin_abbreviation": "MIME",
			"escrow_address": "XbBMr6efhaMSf52BZfS2cQDuGipeV9kAuD",
			"genesis_tx_hash": "60ba570967ffbd8849f732009459d9dcc56f4502986a04c28d7f3ca588df821f/1",
			"genesis_amount": 100000000,
			"game_starting_block": 304801,
			"default_payout_rate": 0.9999,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "linear",
			"view_mode": "default",
			"order_options_by": "option_index",
			"order_events_by": "volume",
			"target_option_block_score": null,
			"escrow_amounts": [
				{
					"type": "dynamic",
					"currency": "USD",
					"relative_amount": 1
				}
			]
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
		
		$this->module_info = [
			'next_add_events_time' => 0,
			'next_set_outcomes_time' => 0,
			'next_set_blocks_time' => 0,
		];
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
					"event_determined_to_block" => null,
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
					"track_payout_price" => $db_gde['track_payout_price'],
					"track_name_short" => $db_gde['track_name_short'],
					"possible_outcomes" => $possible_outcomes,
				];

				array_push($events, $event);
			}
		}
		
		return $events;
	}

	public function regular_actions(&$game) {
		if (is_file(dirname(__FILE__)."/Forex32Manager.php") && !$game->get_definitive_peer()) {
			include_once(dirname(__FILE__)."/Forex32Manager.php");
			$manager = new Forex32Manager($this, $this->app, $game);
			if (method_exists($manager, "regular_actions")) $manager->regular_actions();
		}
	}
}
?>