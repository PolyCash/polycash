<?php
class MonsterDuelsGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;

		$this->game_def_base_txt = '{
			"blockchain_identifier": "datacoin-local",
			"option_group": "282 phoenixdex fakemon",
			"protocol_version": 1.001,
			"name": "Monster Duels",
			"url_identifier": "monster-duels",
			"module": "MonsterDuels",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": true,
			"save_every_definition": false,
			"recommended_keep_definitions_hours": null,
			"max_simultaneous_options": 1024,
			"event_type_name": "battle",
			"event_type_name_plural": "battles",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pow_reward_type": "exponential",
			"initial_pow_reward": 0,
			"blocks_per_pow_reward_ajustment": 1000,
			"round_length": 10,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"coin_name": "duelcoin",
			"coin_name_plural": "duelcoins",
			"coin_abbreviation": "DUEL",
			"escrow_address": "T3EAGL4ZK1K8Xk38KYyFxRj5D9PfWUqFaH",
			"genesis_tx_hash": "b8264dfa506de2e0ebbf360456398037",
			"genesis_amount": 5000000,
			"game_starting_block": 193001,
			"default_payout_rate": 0.99,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 300,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default",
			"order_options_by": "option_index",
			"order_events_by": "event_index",
			"target_option_block_score": 100,
			"boost_votes_by_missing_out_votes": true,
			"set_being_determined_blocks_method": null,
			"escrow_amounts": [],
			"events": []
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

	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$events = [];

		$info = $this->app->run_query("SELECT MIN(event_index), MAX(event_index) FROM game_defined_events WHERE game_id=:game_id AND event_starting_block>=:from_block AND event_starting_block<=:to_block;", [
			'game_id' => $game->db_game['game_id'],
			'from_block' => $from_block,
			'to_block' => $to_block
		]);

		if ($info->rowCount() > 0) {
			$info = $info->fetch();

			$gde_r = $this->app->run_query("SELECT gde.* FROM game_defined_events gde WHERE gde.game_id=:game_id AND gde.event_index>=:from_event_index AND gde.event_index<=:to_event_index;", [
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
					"event_determined_from_block" => $db_gde['event_determined_from_block'],
					"event_determined_to_block" => $db_gde['event_determined_to_block'],
					"event_payout_block" => $db_gde['event_payout_block'],
					"event_starting_time" => $db_gde['event_starting_time'],
					"event_final_time" => $db_gde['event_final_time'],
					"event_payout_time" => $db_gde['event_payout_time'],
					"event_name" => $db_gde['event_name'],
					"option_name" => "monster",
					"option_name_plural" => "monsters",
					"payout_rule" => "binary",
					"payout_rate" => $db_gde['payout_rate'],
					"outcome_index" => $db_gde['outcome_index'],
					"possible_outcomes" => $possible_outcomes,
				];

				array_push($events, $event);
			}
		}

		return $events;
	}

	public function set_event_outcome(&$game, &$event) {
		$payout_block = $game->blockchain->fetch_block_by_id($event->db_event['event_payout_block']-1);

		list($options_by_score, $options_by_index, $is_tie, $score_disp, $in_progress_summary) = $event->option_block_info();

		if ($is_tie) {
			$block_hash_last_chars = substr($payout_block['block_hash'], strlen($payout_block['block_hash'])-8, 8);
			$random_number = hexdec($block_hash_last_chars);
			$outcome_index = $random_number%2;
		}
		else $outcome_index = $options_by_score[0]['event_option_index'];

		$game->set_game_defined_outcome($event->db_event['event_index'], $outcome_index);
		$event->set_outcome_index($outcome_index);

		return "";
	}
}
?>