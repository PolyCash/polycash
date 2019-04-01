<?php
class CryptoDuelsGameDefinition {
	public $app;
	public $game_def;
	public $events_per_round;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		
		$this->currency_name_to_code = array(
			'Bitcoin'=>'BTC',
			'Bitcoin Cash'=>'BCHABC',
			'Dash'=>'DASH',
			'Ethereum'=>'ETH',
			'Ethereum Classic'=>'ETC',
			'Litecoin'=>'LTC',
			'Monero'=>'XMR',
			'NEM'=>'XEM',
			'Ripple'=>'XRP'
		);
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": "9 significant cryptocurrencies",
			"protocol_version": 0,
			"name": "Crypto Duels",
			"url_identifier": "crypto-duels",
			"module": "CryptoDuels",
			"category_id": null,
			"decimal_places": 8,
			"event_type_name": "duel",
			"event_type_name_plural": "duels",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"event_entity_type_id": 0,
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.0005,
			"pos_reward": 0,
			"round_length": 50,
			"maturity": 0,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "none",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "duelcoin",
			"coin_name_plural": "duelcoins",
			"coin_abbreviation": "DUEL",
			"escrow_address": "fC3o5XEaC21LbZtDLAmWfsLFdpnfoUs8oE",
			"genesis_tx_hash": "e418811e42c7b350293b8b63f83c60d7",
			"genesis_amount": 100000000000000,
			"game_starting_block": 140601,
			"game_winning_rule": "none",
			"game_winning_field": "",
			"game_winning_inflation": 0,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default",
			"escrow_amounts": {
				"stakes": 100000		
			}
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
	}
	
	public function load_currencies(&$game) {
		$this->currencies = array();
		
		$member_q = "SELECT *, en.entity_id AS entity_id FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id JOIN currencies c ON en.entity_name=c.name WHERE m.option_group_id='".$game->db_game['option_group_id']."' ORDER BY m.membership_id ASC;";
		$member_r = $this->app->run_query($member_q);
		
		while ($db_member = $member_r->fetch()) {
			array_push($this->currencies, $db_member);
		}
	}
	
	public function events_starting_between_rounds(&$game, $from_round, $to_round, $round_length, $chain_starting_block) {
		if (empty($this->currencies)) $this->load_currencies($game);
		$num_pairs = count($this->currencies)*(count($this->currencies)-1);
		$events = array();
		
		for ($event_i=$from_round-1; $event_i<$to_round; $event_i++) {
			$event_pair_i = $event_i%$num_pairs;
			$first_currency_i = floor($event_pair_i/(count($this->currencies)-1));
			$second_currency_i = $event_pair_i%(count($this->currencies)-1);
			if ($second_currency_i >= $first_currency_i) $second_currency_i++;
			
			$possible_outcomes = [array("title" => $this->currencies[$first_currency_i]['entity_name'], "entity_id" => $this->currencies[$first_currency_i]['entity_id']), array("title" => $this->currencies[$second_currency_i]['entity_name'], "entity_id" => $this->currencies[$second_currency_i]['entity_id'])];
			
			$event = array(
				"event_index" => $event_i+1,
				"event_starting_block" => $chain_starting_block + $event_i*$round_length,
				"event_final_block" => $chain_starting_block + ($event_i+5)*$round_length - 1,
				"event_payout_block" => $chain_starting_block + ($event_i+5)*$round_length - 1,
				"event_name" => "Duel #".($event_i+1).": ".$this->currencies[$first_currency_i]['entity_name']." vs ".$this->currencies[$second_currency_i]['entity_name'],
				"option_name" => "outcome",
				"option_name_plural" => "outcomes",
				"payout_rule" => "binary",
				"outcome_index" => null,
				"possible_outcomes" => $possible_outcomes
			);
			array_push($events, $event);
		}
		
		return $events;
	}
	
	public function set_event_outcome(&$game, &$coin_rpc, $payout_event) {
		if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
			$start_block_hash = $coin_rpc->getblockhash((int)$payout_event->db_event['event_starting_block']);
			$final_block_hash = $coin_rpc->getblockhash((int)$payout_event->db_event['event_final_block']);
			
			$start_block = $coin_rpc->getblock($start_block_hash);
			$final_block = $coin_rpc->getblock($final_block_hash);
			
			$start_time = $start_block['time'];
			$final_time = $final_block['time'];
		}
		else {
			$start_block = $game->blockchain->fetch_block_by_id($payout_event->db_event['event_starting_block']);
			$final_block = $game->blockchain->fetch_block_by_id($payout_event->db_event['event_final_block']);
			
			$start_time = $start_block['time_mined'];
			$final_time = $final_block['time_mined'];
		}
		
		$btc_currency = $this->app->get_currency_by_abbreviation("BTC");
		
		$performances = array();
		$best_performance_index = false;
		$best_performance = false;
		$loop_index = 0;
		
		$option_q = "SELECT * FROM options WHERE event_id='".$payout_event->db_event['event_id']."' ORDER BY option_index ASC;";
		$option_r = $this->app->run_query($option_q);
		
		while ($db_option = $option_r->fetch()) {
			$currency_code = $this->currency_name_to_code[$db_option['name']];
			
			if ($db_option['name'] != "Bitcoin") {
				$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$currency_code."&start=".$start_time."&end=".$final_time;
				
				$db_currency = $this->app->run_query("SELECT * FROM currencies WHERE name=".$this->app->quote_escape($db_option['name']).";")->fetch();
				$currency_price = $this->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $start_time);
				
				if (!empty($currency_price['time_added'])) $last_price_time = $currency_price['time_added'];
				else $last_price_time = 0;
				
				$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
				$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
				$cached_url = $this->app->cached_url_info($poloniex_url);
				
				$cached_prices_q = "SELECT COUNT(*) FROM currency_prices WHERE cached_url_id='".$cached_url['cached_url_id']."';";
				$cached_prices_r = $this->app->run_query($cached_prices_q);
				$cached_prices = $cached_prices_r->fetch();
				$num_cached_prices = $cached_prices['COUNT(*)'];
				
				if ($num_cached_prices == 0) {
					$start_q = "INSERT INTO currency_prices (cached_url_id, currency_id, reference_currency_id, price, time_added) VALUES ";
					$q = $start_q;
					$modulo = 0;
					
					for ($i=count($poloniex_trades)-1; $i>=0; $i--) {
						$trade = $poloniex_trades[$i];
						
						if ($trade['type'] == "buy") {
							$trade_date = new DateTime($trade['date'], new DateTimeZone('UTC'));
							$trade_time = $trade_date->format('U');
							
							if ($modulo == 1000) {
								$q = substr($q, 0, strlen($q)-2).";";
								$this->app->run_query($q);
								$modulo = 0;
								$q = $start_q;
							}
							else $modulo++;
							
							$q .= "('".$cached_url['cached_url_id']."', '".$db_currency['currency_id']."', '".$btc_currency['currency_id']."', '".$trade['rate']."', '".$trade_time."'), ";
							
							$last_price_time = $trade_time;
						}
					}
					if ($modulo > 0) {
						$q = substr($q, 0, strlen($q)-2).";";
						$this->app->run_query($q);
					}
				}
				
				if (count($poloniex_trades) > 1) {
					$start_price = $this->app->currency_price_after_time($db_currency['currency_id'], $btc_currency['currency_id'], $start_time);
					$final_price = $this->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $final_time);
				}
				
				$performance = round(pow(10,8)*$final_price['price']/$start_price['price']);
			}
			else $performance = pow(10,8);
			
			array_push($performances, $performance);
			
			if ($best_performance_index === false || $performance >= $best_performance) {
				$best_performance = $performance;
				$best_performance_index = $loop_index;
			}
			$loop_index++;
		}
		
		$q = "UPDATE game_defined_events SET outcome_index=".$best_performance_index." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$payout_event->db_event['event_index']."';";
		$r = $this->app->run_query($q);
		
		$q = "UPDATE events SET outcome_index=".$best_performance_index." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$payout_event->db_event['event_index']."';";
		$r = $this->app->run_query($q);
		
		$payout_event->db_event['outcome_index'] = $best_performance_index;
		
		$log_text = $payout_event->set_outcome_from_db(true);
		return $log_text;
	}
	
	public function regular_actions(&$game) {
		if (empty($this->currencies)) $this->load_currencies($game);
		$btc_currency = $this->app->get_currency_by_abbreviation("BTC");
		$start_q = "INSERT INTO currency_prices (cached_url_id, currency_id, reference_currency_id, price, time_added) VALUES ";
		
		for ($i=1; $i<count($this->currencies); $i++) {
			$currency_price = $this->app->currency_price_at_time($this->currencies[$i]['currency_id'], $btc_currency['currency_id'], time());
			
			if ($currency_price) $last_price_time = max(time()-(3600*24*2), $currency_price['time_added']);
			else {
				$mining_block_id = $game->last_block_id()+1;
				$this_round = $game->block_to_round($mining_block_id);
				$round_firstblock = ($this_round-1)*$game->db_game['round_length']+1;
				$start_block = $game->blockchain->fetch_block_by_id($round_firstblock);
				$last_price_time = $start_block['time_mined'];
			}
			
			$q = $start_q;
			$modulo = 0;
			
			$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$this->currencies[$i]['abbreviation']."&start=".$last_price_time."&end=".time();
			$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
			$cached_url = $this->app->cached_url_info($poloniex_url);
			$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
			
			for ($j=count($poloniex_trades)-2; $j>=0; $j--) {
				$trade = $poloniex_trades[$j];
				$trade_date = new DateTime($trade['date'], new DateTimeZone('UTC'));
				$trade_time = $trade_date->format('U');
				
				if ($trade['type'] == "buy") {
					if ($last_price_time < $trade_time) {
						if ($modulo == 1000) {
							$q = substr($q, 0, strlen($q)-2).";";
							$this->app->run_query($q);
							$modulo = 0;
							$q = $start_q;
						}
						else $modulo++;
						
						$q .= "('".$cached_url['cached_url_id']."', '".$this->currencies[$i]['currency_id']."', '".$btc_currency['currency_id']."', '".$trade['rate']."', '".$trade_time."'), ";
					}
				}
			}
			
			if ($modulo > 0) {
				$q = substr($q, 0, strlen($q)-2).";";
				$this->app->run_query($q);
				$modulo = 0;
			}
		}
	}
}
?>