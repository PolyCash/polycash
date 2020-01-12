<?php
class RockPaperScissorsGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "freechain",
			"option_group": null,
			"protocol_version": 0,
			"name": "Rock Paper Scissors",
			"url_identifier": "rock-paper-scissors",
			"module": "RockPaperScissors",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": false,
			"save_every_definition": false,
			"max_simultaneous_options": 100,
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
			"escrow_address": "",
			"genesis_tx_hash": "",
			"genesis_amount": 1000000000000,
			"game_starting_block": 51,
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
		
		$event_index = $from_block - $game->db_game['game_starting_block']+1;
		
		for ($block=$from_block; $block<=$to_block; $block++) {
			$event = [
				"event_index" => $event_index,
				"event_starting_block" => $block,
				"event_final_block" => $block+7,
				"event_outcome_block" => $block+7,
				"event_payout_block" => $block+8,
				"event_name" => "Match #".number_format($event_index),
				"option_name" => "hand",
				"option_name_plural" => "hands",
				"payout_rule" => "binary",
				"payout_rate" => 1,
				"outcome_index" => null,
				"possible_outcomes" => [
					[
						"title" => "Rock"
					],[
						"title" => "Paper"
					],[
						"title" => "Scissors"
					]
				]
			];
			
			array_push($events, $event);
			
			$event_index++;
		}
		
		return $events;
	}
	
	public function set_event_outcome(&$game, &$event) {
		$payout_block = $game->blockchain->fetch_block_by_id($event->db_event['event_payout_block']-1);
		
		$block_hash_last_chars = substr($payout_block['block_hash'], strlen($payout_block['block_hash'])-8, 8);
		$random_number = hexdec($block_hash_last_chars);
		$outcome_index = $random_number%3;
		
		$game->set_game_defined_outcome($event->db_event['event_index'], $outcome_index);
		$event->set_outcome_index($outcome_index);
		
		return "";
	}
}
?>