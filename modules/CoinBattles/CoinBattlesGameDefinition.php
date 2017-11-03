<?php
class CoinBattlesGameDefinition {
	public $app;
	public $game_def;
	public $events_per_round;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->events_per_round = array('BTC'=>'Bitcoin', 'DASH'=>'Dash', 'ETH'=>'Ethereum', 'ETC'=>'Ethereum Classic', 'LTC'=>'Litecoin', 'XMR'=>'Monero', 'XEM'=>'NEM', 'XRP'=>'Ripple');
		$this->game_def_base_txt = '{
			"blockchain_identifier": "litecoin",
			"protocol_version": 0,
			"category_id": 4,
			"url_identifier": "coin-battles",
			"name": "Coin Battles",
			"event_type_name": "battle",
			"event_type_name_plural": "battles",
			"event_rule": "game_definition",
			"event_entity_type_id": 0,
			"option_group_id": 0,
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.001,
			"pos_reward": 0,
			"round_length": 20,
			"maturity": 0,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "on",
			"sellout_confirmations": 0,
			"coin_name": "coinblock",
			"coin_name_plural": "coinblocks",
			"coin_abbreviation": "CBL",
			"escrow_address": "LKahZLuDcT8Rnq7qQW8FdpDB59v5HZmTqi",
			"genesis_tx_hash": "15fa21fc67701dfb87dd455c16600ac9abe4badaef6c46da378df63562a70d9b",
			"genesis_amount": 100000000000,
			"game_starting_block": 1193501,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0
		}';
		$this->load();
	}
	
	public function load() {
		$game_def = json_decode($this->game_def_base_txt);

		$blockchain_r = $this->app->run_query("SELECT * FROM blockchains WHERE url_identifier='".$game_def->blockchain_identifier."';");

		if ($blockchain_r->rowCount() > 0) {
			$db_blockchain = $blockchain_r->fetch();
			
			try {
				$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
			}
			catch (Exception $e) {
				echo "Error, failed to load RPC connection for ".$db_blockchain['blockchain_name'].".<br/>\n";
				die();
			}
			
			$chain_starting_block = $game_def->game_starting_block;
			try {
				$chain_last_block = (int) $coin_rpc->getblockcount();
			}
			catch (Exception $e) {}
			
			$chain_events_until_block = $chain_last_block + $game_def->round_length;

			$defined_rounds = ceil(($chain_events_until_block - $chain_starting_block)/$game_def->round_length);

			$game_def->events = $this->events_between_rounds(1, $defined_rounds, $game_def->round_length, $chain_starting_block);
			
			$this->game_def = $game_def;
		}
		else echo "No blockchain found matching that identifier.";
	}
	
	public function events_between_rounds($from_round, $to_round, $round_length, $chain_starting_block) {
		$general_entity_type = $this->app->check_set_entity_type("general entity");
		$events = array();
		
		for ($round = $from_round; $round<=$to_round; $round++) {
			$possible_outcomes = array();
			
			foreach ($this->events_per_round as $currency_code => $currency_name) {
				$entity = $this->app->check_set_entity($general_entity_type['entity_type_id'], $currency_name);
				array_push($possible_outcomes, array("title" => $currency_name." wins", "entity_id" => $entity['entity_id']));
			}
			
			$event = array(
				"event_starting_block" => $chain_starting_block+$round*$round_length,
				"event_final_block" => $chain_starting_block+($round+1)*$round_length-1,
				"event_payout_block" => $chain_starting_block+($round+1)*$round_length-1,
				"event_name" => "Coin Battle #".$round,
				"option_name" => "outcome",
				"option_name_plural" => "outcomes",
				"outcome_index" => null,
				"possible_outcomes" => $possible_outcomes
			);
			array_push($events, $event);
		}
		return $events;
	}
	
	public function  add_oracle_urls(&$game, &$coin_rpc) {
		$until_block = $game->blockchain->last_block_id();
		
		$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND event_final_block<=".$until_block." ORDER BY event_index ASC;";
		$r = $this->app->run_query($q);
		
		while ($gde = $r->fetch()) {
			$start_block_hash = $coin_rpc->getblockhash((int)$gde['event_starting_block']);
			$final_block_hash = $coin_rpc->getblockhash((int)$gde['event_final_block']);
			
			$start_block = $coin_rpc->getblock($start_block_hash);
			$final_block = $coin_rpc->getblock($final_block_hash);
			
			$start_time = $start_block['time'];
			$final_time = $final_block['time'];
			
			echo "Event ".$gde['event_index'].": ".$gde['event_name']."<br/>\n";
			
			$loop_index = 0;
			
			foreach ($this->events_per_round as $currency_code => $currency_name) {
				if ($loop_index > 0) {
					$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$currency_code."&start=".$start_time."&end=".$final_time;
					
					echo "Add async: ".$poloniex_url."<br/>\n";
					$poloniex_response = $this->app->async_fetch_url($poloniex_url, false);
				}
				$loop_index++;
			}
		}
	}
	
	public function set_event_outcome(&$game, &$coin_rpc, $db_event) {
		$start_block_hash = $coin_rpc->getblockhash((int)$db_event['event_starting_block']);
		$final_block_hash = $coin_rpc->getblockhash((int)$db_event['event_final_block']);
		
		$start_block = $coin_rpc->getblock($start_block_hash);
		$final_block = $coin_rpc->getblock($final_block_hash);
		
		$start_time = $start_block['time'];
		$final_time = $final_block['time'];
		
		echo "Event ".$db_event['event_index'].": ".$db_event['event_name']."<br/>\n";
		
		$performances = array();
		$best_performance_index = false;
		$best_performance = false;
		$loop_index = 0;
		
		foreach ($this->events_per_round as $currency_code => $currency_name) {
			if ($loop_index > 0) {
				$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$currency_code."&start=".$start_time."&end=".$final_time;
				
				echo $poloniex_url."<br/>\n";
				$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
				$poloniex_trades = json_decode($poloniex_response['cached_result']);
				
				if (count($poloniex_trades) > 1) {
					$start_trade = $poloniex_trades[0];
					$final_trade = $poloniex_trades[count($poloniex_trades)-1];
				}
				
				$performance = round(pow(10,6)*$final_trade->rate/$start_trade->rate);
			}
			else $performance = pow(10,6);
			
			array_push($performances, $performance);
			
			if ($best_performance_index === false || $performance >= $best_performance) {
				$best_performance = $performance;
				$best_performance_index = $loop_index;
			}
			$loop_index++;
		}
		
		$q = "UPDATE game_defined_events SET outcome_index=".$best_performance_index." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."';";
		$r = $this->app->run_query($q);
	}
}
?>