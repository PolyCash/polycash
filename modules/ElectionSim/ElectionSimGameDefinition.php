<?php
class ElectionSimGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;

		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"protocol_version": 0,
			"category_id": 31,
			"url_identifier": "virtual-election",
			"name": "Virtual Election 2018",
			"event_type_name": "election",
			"event_type_name_plural": "elections",
			"event_rule": "all_pairs",
			"event_entity_type_id": 0,
			"option_group_id": 6,
			"events_per_round": 2,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.01,
			"pos_reward": 0,
			"round_length": 100,
			"maturity": 0,
			"payout_weight": "coin_block",
			"final_round": false,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "on",
			"sellout_confirmations": 0,
			"coin_name": "dollar",
			"coin_name_plural": "dollars",
			"coin_abbreviation": "USD",
			"escrow_address": "2XGAfem85egbprCGKyeXFaDHmYkNfhFbCM",
			"genesis_tx_hash": "cb0fbe0a0bdfdb48a3ff3cd108030fcf",
			"genesis_amount": 10000000000000,
			"game_starting_block": 1,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 0.7,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0
		}';
		$this->load();
	}
	
	public function load() {
		$game_def = json_decode($this->game_def_base_txt);
		$this->game_def = $game_def;
	}
	
	public function events_between_rounds($from_round, $to_round, $round_length, $chain_starting_block) {
		/*if (!empty($this->game_def->final_round) && $to_round > $this->game_def->final_round) $to_round = $this->game_def->final_round;
		
		$rounds_per_tournament = $this->get_rounds_per_tournament();
		$events = array();
		$general_entity_type = $this->app->check_set_entity_type("general entity");
		
		for ($round=$from_round; $round<=$to_round; $round++) {
			$meta_round = floor(($round-1)/$rounds_per_tournament);
			$this_round = ($round-1)%$rounds_per_tournament+1;
			$rounds_left = $rounds_per_tournament - ($this_round+1);
			$num_events = $this->num_events_in_round($round, $rounds_per_tournament);
			$prevround_offset = $this->round_to_prevround_offset($round, false);
			$event_index = $prevround_offset;
			
			for ($thisround_event_i=0; $thisround_event_i<$num_events; $thisround_event_i++) {
				$possible_outcomes = array();
				$game = false;
				$event_name = $this->generate_event_labels($possible_outcomes, $round, $this_round, $thisround_event_i, $general_entity_type['entity_type_id'], $event_index, $game);
				
				$event = array(
					"event_index" => $event_index,
					"next_event_index" => $this->event_index_to_next_event_index($event_index),
					"event_starting_block" => $chain_starting_block+($round-1)*$round_length,
					"event_final_block" => $chain_starting_block+$round*$round_length-1,
					"event_payout_block" => $chain_starting_block+$round*$round_length-1,
					"option_block_rule" => "football_match",
					"event_name" => $event_name,
					"option_name" => "outcome",
					"option_name_plural" => "outcomes",
					"outcome_index" => null,
					"possible_outcomes" => $possible_outcomes
				);
				
				array_push($events, $event);
				$event_index++;
			}
		}
		
		return $events;*/
	}
	
	public function num_events_in_round($round, $rounds_per_tournament) {
		return 2;
	}
	
	public function event_index_to_round($event_index, $rounds_per_tournament) {
		return 1+floor($event_index/2);
	}
}
?>