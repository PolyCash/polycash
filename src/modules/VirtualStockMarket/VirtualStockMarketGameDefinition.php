<?php
class VirtualStockMarketGameDefinition {
	public $app;
	public $game_def;
	public $events_per_round;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->iex_api_key = false;
		
		$this->currency_name_to_code = array(
			'Amazon'=>'AMZN',
			'Apple'=>'AAPL',
			'Facebook'=>'FB',
			'Google'=>'GOOG',
			'HP'=>'HPQ',
			'IBM'=>'IBM',
			'Intel'=>'INTC',
			'Microsoft'=>'MSFT',
			'Netflix'=>'NFLX',
			'Salesforce'=>'CRM',
			'Twitter'=>'TWTR',
			'Uber'=>'UBER',
		);
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "freechain",
			"definitive_peer": "https://poly.cash",
			"option_group": "12 American tech stocks",
			"protocol_version": 0,
			"name": "Virtual Stock Market",
			"url_identifier": "virtual-stock-market",
			"module": "VirtualStockMarket",
			"category_id": null,
			"decimal_places": 2,
			"finite_events": false,
			"save_every_definition": false,
			"max_simultaneous_options": 100,
			"event_type_name": "market",
			"event_type_name_plural": "markets",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0,
			"pow_reward_type": "none",
			"pow_fixed_reward": null,
			"round_length": 100,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "dollars",
			"coin_name_plural": "dollars",
			"coin_abbreviation": "USDF",
			"escrow_address": "g1Kx3LdcDK4N8KYHGgKmEf9295mXPV8m3K",
			"genesis_tx_hash": "f8973e795d5cbda07c7029066f75b8ef",
			"genesis_amount": 100000000000,
			"game_starting_block": 92301,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "linear",
			"view_mode": "default",
			"order_options_by": "option_index",
			"escrow_amounts": []
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
		
		$this->load_api_keys();
	}
	
	public function load_api_keys() {
		$fname = dirname(__FILE__)."/config.json";
		if (is_file($fname)) {
			$fh = fopen($fname, 'r');
			$config = json_decode(fread($fh, filesize($fname)));
			$this->iex_api_key = $config->iex_api_key;
		}
	}
	
	public function load_currencies(&$game) {
		$this->currencies = [];
		$this->name2currency_index = [];
		
		$members = $this->app->run_query("SELECT *, en.entity_id AS entity_id FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id JOIN currencies c ON en.entity_name=c.name WHERE m.option_group_id=:option_group_id ORDER BY en.entity_name ASC;", ['option_group_id'=>$game->db_game['option_group_id']]);
		$currency_index = 0;
		
		while ($db_member = $members->fetch()) {
			array_push($this->currencies, $db_member);
			$this->name2currency_index[$db_member['name']] = $currency_index;
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
		if (empty($this->currencies)) $this->load_currencies($game);
		
		$start_block = ($game->block_to_round($from_block)-1)*$game->db_game['round_length']+1;
		if ($from_block > $start_block) $start_block += $game->db_game['round_length'];
		$end_block = ($game->block_to_round($to_block)-1)*$game->db_game['round_length']+1;
		
		$events = [];
		
		if ($end_block >= $start_block) {
			$start_event_i = ($start_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			$end_event_i = ($end_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			
			$events_per_cycle = count($this->currencies);
			
			for ($event_i=$start_event_i; $event_i<=$end_event_i; $event_i++) {
				$currency_i = $event_i%$events_per_cycle;
				
				$blocks_to_payout = 7*24*3600/30;
				
				$event_starting_block = $game->db_game['game_starting_block'] + $event_i*$game->db_game['round_length'];
				$event_final_block = $event_starting_block + ($game->db_game['round_length']*count($this->currencies)) - 1;
				$event_payout_block = $event_starting_block + $blocks_to_payout - 1;
				
				$final_block = $game->blockchain->fetch_block_by_id($event_payout_block);
				if ($final_block) $ref_time = $final_block['time_mined'];
				else $ref_time = time();
				
				$price_usd_info = $this->app->exchange_rate_between_currencies(1, $this->currencies[$currency_i]['currency_id'], time(), 6);
				
				if (!empty($price_usd_info['exchange_rate'])) $price_usd = $price_usd_info['exchange_rate'];
				else $price_usd = 0;
				
				if (empty($price_usd) || $price_usd < 0 || $price_usd > pow(10, 6)) {
					$price_api_url = "https://cloud.iexapis.com/stable/stock/".$this->currencies[$currency_i]['abbreviation']."/quote?token=".$this->iex_api_key;
					if ($api_response_raw = @file_get_contents($price_api_url)) {
						if ($api_response = @json_decode($api_response_raw)) {
							$price_usd = (float) $api_response->latestPrice;
						}
					}
				}
				
				$price_max_target = $price_usd*1.05;
				$price_min_target = $price_usd*0.95;
				
				$log10 = floor(log10($price_usd));
				$round_targets_to = $price_usd == 0 ? 1 : pow(10, $log10-1);
				
				$price_min = floor($price_min_target/$round_targets_to)*$round_targets_to;
				$price_max = ceil($price_max_target/$round_targets_to)*$round_targets_to;
				
				$event_name = $this->currencies[$currency_i]['entity_name']." between $".$this->app->format_bignum($price_min)." and $".$this->app->format_bignum($price_max);
				
				$possible_outcomes = [
					[
						"title" => "Buy ".$this->currencies[$currency_i]['entity_name'],
						"entity_id" => $this->currencies[$currency_i]['entity_id']
					],
					[
						"title" => "Sell ".$this->currencies[$currency_i]['entity_name'],
						"entity_id" => $this->currencies[$currency_i]['entity_id']
					]
				];
				
				$event = [
					"event_index" => $event_i,
					"event_starting_block" => $event_starting_block,
					"event_final_block" => $event_final_block,
					"event_outcome_block" => $event_payout_block,
					"event_payout_block" => $event_payout_block,
					"event_name" => $event_name,
					"option_name" => "position",
					"option_name_plural" => "positions",
					"payout_rule" => "linear",
					"payout_rate"=>1,
					"outcome_index" => null,
					"track_min_price" => $price_min,
					"track_max_price" => $price_max,
					"track_name_short" => $this->currencies[$currency_i]['abbreviation'],
					"possible_outcomes" => $possible_outcomes
				];
				array_push($events, $event);
			}
		}
		
		return $events;
	}
	
	public function set_event_outcome(&$game, &$payout_event) {
		$log_text = "";
		
		if ((string)$payout_event->db_event['track_payout_price'] == "") {
			$payout_block = $game->blockchain->fetch_block_by_id($payout_event->db_event['event_payout_block']);
			
			if ($payout_block) {
				$currency = $this->app->get_currency_by_abbreviation($payout_event->db_event['track_name_short']);
				
				$ref_time = $payout_block['time_mined'];
				
				$currency_price_info = $this->app->exchange_rate_between_currencies(1, $currency['currency_id'], $ref_time, 6);
				
				$usd_price = $this->app->round_to($currency_price_info['exchange_rate'], 2, 6, false);
				$usd_price = max($payout_event->db_event['track_min_price'], min($payout_event->db_event['track_max_price'], $usd_price));
				
				$this->app->run_query("UPDATE game_defined_events SET track_payout_price=:track_payout_price WHERE game_id=:game_id AND event_index=:event_index;", [
					'track_payout_price' => $usd_price,
					'game_id' => $game->db_game['game_id'],
					'event_index' => $payout_event->db_event['event_index']
				]);
				
				$this->app->run_query("UPDATE events SET track_payout_price=:track_payout_price WHERE event_id=:event_id;", [
					'track_payout_price' => $usd_price,
					'event_id' => $payout_event->db_event['event_id']
				]);
				
				$payout_event->db_event['track_payout_price'] = $usd_price;
			}
		}
		
		return $log_text;
	}
	
	public function regular_actions(&$game) {
		if (empty($this->currencies)) $this->load_currencies($game);
		$btc_currency = $this->app->get_currency_by_abbreviation("BTC");
		$usd_currency = $this->app->get_currency_by_abbreviation("USD");
		$usd_to_btc = $this->app->exchange_rate_between_currencies($usd_currency['currency_id'], $btc_currency['currency_id'], time(), $btc_currency['currency_id']);
		
		$modulo = 0;
		$start_q = "INSERT INTO currency_prices (currency_id, reference_currency_id, price, time_added) VALUES ";
		$new_prices_q = $start_q;
		
		for ($i=0; $i<count($this->currencies); $i++) {
			$price_in_btc = $this->app->currency_price_at_time($this->currencies[$i]['currency_id'], $btc_currency['currency_id'], time());
			
			if ($price_in_btc) $last_price_time = max(time()-(3600*24*2), $price_in_btc['time_added']);
			else $last_price_time = false;
			
			if (!$last_price_time || $last_price_time < time()-(60*28)) {
				$ticker = $this->currency_name_to_code[$this->currencies[$i]['name']];
				$price_api_url = "https://cloud.iexapis.com/stable/stock/".$ticker."/quote?token=".$this->iex_api_key;
				$api_response = file_get_contents($price_api_url);
				
				$api_data = json_decode($api_response, true);
				$new_price_usd = (float)$api_data['latestPrice'];
				$new_price_in_btc = $new_price_usd/$usd_to_btc['exchange_rate'];
				
				if ($new_price_in_btc > 0) {
					$new_prices_q .= "('".$this->currencies[$i]['currency_id']."', '".$btc_currency['currency_id']."', ".$this->app->quote_escape($new_price_in_btc).", ".time()."), ";
					
					$modulo++;
				}
			}
		}
		
		if ($modulo > 0) {
			$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
			$this->app->run_query($new_prices_q);
			$modulo = 0;
		}
	}
}
?>