<?php
class StakemoneySharesGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->game_def_base_txt = '{
			"blockchain_identifier": "litecoin",
			"option_group": null,
			"protocol_version": 0,
			"url_identifier": "stakemoney-shares",
			"name": "Stakemoney Shares",
			"module":  "StakemoneyShares",
			"category_id": 4,
			"decimal_places": 4,
			"finite_events": true,
			"save_every_definition": true,
			"event_type_name": "referendum",
			"event_type_name_plural": "referendums",
			"event_rule": "game_definition",
			"event_entity_type_id": 0,
			"option_group_id": 0,
			"events_per_round": 0,
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pos_reward": 0,
			"round_length": 20,
			"maturity": 0,
			"payout_weight": "coin_round",
			"final_round": false,
			"buyin_policy": "none",
			"game_buyin_cap": 0,
			"sellout_policy": "on",
			"sellout_confirmations": 0,
			"coin_name": "share",
			"coin_name_plural": "shares",
			"coin_abbreviation": "STK",
			"escrow_address": "LSF48seo4p3yQA6XgFJofeadF2ipfAUihy",
			"genesis_tx_hash": "d097c4d2cae65b335d4e96b4b091413ffbaa11ab9f3e90ec855e38d3c5bf5267",
			"genesis_amount": 100000000000000,
			"game_starting_block": 1290601,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0
		}';
		$this->load();
	}
	
	public function load() {
		$game_def = json_decode($this->game_def_base_txt);
		$this->game_def = $game_def;
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		return [];
	}
}
?>