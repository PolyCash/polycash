<?php
class MonsterDuelsGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->base_monsters = [];
		$this->remaining_monster_option_indexes = [];
		$this->max_attack_damage = 47;

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
			"order_options_by": "bets",
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

	public function fetch_base_monsters($event) {
		$baseOptions = $this->app->run_query("SELECT op.option_index, op.entity_id, op.event_option_index, en.entity_name, en.hp, en.best_attack_name, en.level, en.color, en.body_shape, i.image_id, i.access_key, i.extension FROM options op JOIN entities en ON op.entity_id=en.entity_id LEFT JOIN images i ON en.default_image_id=i.image_id WHERE op.event_id=:event_id", ['event_id' => $event->db_event['event_id']])->fetchAll(PDO::FETCH_ASSOC);

		$baseOptionsFormatted = [];
		
		foreach ($baseOptions as $baseOption) {
			$baseOptionsFormatted[] = [
				'entity_id' => (int) $baseOption['entity_id'],
				'entity_name' => $baseOption['entity_name'],
				'event_option_index' => (int) $baseOption['event_option_index'],
				'hp' => (int) $baseOption['hp'],
				'best_attack_name' => $baseOption['best_attack_name'],
				'level' => $baseOption['level'],
				'color' => $baseOption['color'],
				'body_shape' => $baseOption['body_shape'],
				'image_url' => "/images/custom/".$baseOption['image_id']."_".$baseOption['access_key'].".".$baseOption['extension'],
				'eliminated' => false,
			];
		}

		return $baseOptionsFormatted;
	}

	public function fetch_base_monsters_by_gde($gde) {
		$baseOptions = $this->app->run_query("SELECT gdo.option_index AS event_option_index, gdo.entity_id, en.entity_name, en.hp, en.best_attack_name, en.level, en.color, en.body_shape, i.image_id, i.access_key, i.extension FROM game_defined_options gdo JOIN entities en ON gdo.entity_id=en.entity_id LEFT JOIN images i ON en.default_image_id=i.image_id WHERE game_id=:game_id AND gdo.event_index=:event_index", ['game_id' => $gde['game_id'], 'event_index' => $gde['event_index']])->fetchAll(PDO::FETCH_ASSOC);

		$baseOptionsFormatted = [];

		foreach ($baseOptions as $baseOption) {
			$baseOptionsFormatted[] = [
				'entity_id' => (int) $baseOption['entity_id'],
				'entity_name' => $baseOption['entity_name'],
				'event_option_index' => (int) $baseOption['event_option_index'],
				'hp' => (int) $baseOption['hp'],
				'best_attack_name' => $baseOption['best_attack_name'],
				'level' => $baseOption['level'],
				'color' => $baseOption['color'],
				'body_shape' => $baseOption['body_shape'],
				'image_url' => "/images/custom/".$baseOption['image_id']."_".$baseOption['access_key'].".".$baseOption['extension'],
				'eliminated' => false,
			];
		}

		return $baseOptionsFormatted;
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

	public function being_determined_event(&$game) {
		$fetchEventQuery = "SELECT * FROM events WHERE game_id=:game_id AND event_final_time < :current_time AND event_payout_time >= :current_time ORDER BY event_index ASC LIMIT 1;";

		$fetchEventParams = [
			'game_id' => $game->db_game['game_id'],
			'current_time' => date("Y-m-d H:i:s"),
		];

		$db_event = $this->app->run_query($fetchEventQuery, $fetchEventParams)->fetch();
		
		if (!$db_event) return null;
		
		return new Event($game, $db_event, $db_event['event_id']);
	}

	public function set_outcome(&$game, &$game_defined_event) {
		$from_time = strtotime($game_defined_event['event_final_time']);
		$to_time = strtotime($game_defined_event['event_payout_time']) - 1;

		$seeds_response_raw = file_get_contents("http://opensourcebets.com/api/seeds/default?from_time=".$from_time."&to_time=".$to_time);
		if (!$seeds_response_raw) return "";
		$seeds_response = json_decode($seeds_response_raw, true);
		if (!$seeds_response || !$seeds_response['seeds']) return "";

		$seeds = $seeds_response['seeds'];

		$this->base_monsters = $game->module->fetch_base_monsters_by_gde($game_defined_event);

		$monster_pos = 0;
		foreach ($this->base_monsters as &$monster) {
			$monster['remaining_hp'] = $monster['hp'];
			$monster['eliminated'] = false;
			$this->remaining_monster_option_indexes[$monster_pos] = $monster['event_option_index'];
			$monster_pos++;
		}

		$attacking_monster_option_index = 0;

		foreach ($seeds as $seed) {
			$duel_number = $seed['position'] - $seeds[0]['position'] + 1;

			$attacking_monster_base_pos = $this->option_index_to_base_pos($attacking_monster_option_index);

			$attacking_monster = $this->base_monsters[$attacking_monster_base_pos];

			$attacking_monster_pos_in_remaining = $this->option_index_to_remaining_pos($attacking_monster['event_option_index']);

			$randInt = (int) $seed['seed'];

			$defending_monster_option_indexes = $this->remaining_monster_option_indexes;
			unset($defending_monster_option_indexes[$attacking_monster_pos_in_remaining]);
			$defending_monster_option_indexes = array_values($defending_monster_option_indexes);

			$defending_monster_option_index = $defending_monster_option_indexes[$randInt%count($defending_monster_option_indexes)];

			$defending_monster_base_pos = $this->option_index_to_base_pos($defending_monster_option_index);

			$defending_monster = &$this->base_monsters[$defending_monster_base_pos];

			$attack_damage = $randInt%$this->max_attack_damage;

			$defending_monster['remaining_hp'] = max(0, $defending_monster['remaining_hp'] - $attack_damage);

			if ($defending_monster['remaining_hp'] == 0) {
				$defending_monster['eliminated'] = true;
				$pos_to_remove = array_search($defending_monster_option_index, $this->remaining_monster_option_indexes);
				unset($this->remaining_monster_option_indexes[$pos_to_remove]);
				$this->remaining_monster_option_indexes = array_values($this->remaining_monster_option_indexes);
			}

			if (count($this->remaining_monster_option_indexes) < 2) {
				$outcome_index = $this->option_index_to_base_pos($this->remaining_monster_option_indexes[0]);
				$game->set_game_defined_outcome($game_defined_event['event_index'], $outcome_index);
				//$event->set_outcome_index($outcome_index);
				return true;
			}

			$next_attacker_remaining_pos = $attacking_monster_pos_in_remaining;
			if (!$defending_monster['eliminated'] || $defending_monster['event_option_index'] > $attacking_monster['event_option_index']) {
				$next_attacker_remaining_pos++;
			}

			$attacking_monster_option_index = $this->remaining_monster_option_indexes[$next_attacker_remaining_pos%count($this->remaining_monster_option_indexes)];
		}

		return false;
	}

	public function option_index_to_base_pos($option_index) {
		foreach ($this->base_monsters as $pos => $base_monster) {
			if ($base_monster['event_option_index'] == $option_index) return $pos;
		}
		return null;
	}

	public function option_index_to_remaining_pos($option_index) {
		foreach ($this->remaining_monster_option_indexes as $pos => $remaining_option_index) {
			if ($remaining_option_index == $option_index) return $pos;
		}
		return null;
	}
}
?>