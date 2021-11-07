<?php
class EmpirecoinClassicGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;

		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": "16 largest modern empires",
			"protocol_version": 0,
			"url_identifier": "empirecoin-classic",
			"name": "Empirecoin Classic",
			"module": "EmpirecoinClassic",
			"category_id": 31,
			"decimal_places": 4,
			"finite_events": false,
			"save_every_definition": false,
			"event_type_name": "competition",
			"event_type_name_plural": "competitions",
			"event_rule": "single_event_series",
			"event_entity_type_id": 0,
			"option_group_id": 5,
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.005,
			"pos_reward": 0,
			"round_length": 50,
			"payout_weight": "coin_block",
			"final_round": false,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "on",
			"sellout_confirmations": 0,
			"coin_name": "empirecoin",
			"coin_name_plural": "empirecoins",
			"coin_abbreviation": "EMP",
			"escrow_address": "",
			"genesis_tx_hash": "",
			"genesis_amount": 100000000000000,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 0.15,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary"
		}';
		$this->load();
	}
	
	public function load() {
		$game_def = json_decode($this->game_def_base_txt);
		$this->game_def = $game_def;
	}
	
	public function num_events_in_round($round, $rounds_per_tournament) {
		return 1;
	}
	
	public function event_index_to_round($event_index, $rounds_per_tournament) {
		return 1+$event_index;
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		return [];
	}
}
?>