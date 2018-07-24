<?php
class EmpirecoinClassicGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;

		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"protocol_version": 0,
			"category_id": 31,
			"url_identifier": "empirecoin-classic",
			"name": "Empirecoin Classic",
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
			"maturity": 0,
			"payout_weight": "coin_block",
			"final_round": false,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "on",
			"sellout_confirmations": 0,
			"coin_name": "empirecoin",
			"coin_name_plural": "empirecoins",
			"coin_abbreviation": "EMP",
			"escrow_address": "bVDQZzaQAqGF5eyapGzfMXcW8Knd8kwu64",
			"genesis_tx_hash": "819d6ff4b8ab1b1ba0c875f203ee535b",
			"genesis_amount": 100000000000000,
			"game_starting_block": 1,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 0.15,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0
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
	
	public function events_between_rounds($from_round_id, $to_round_id) {
		return array();
	}
}
?>