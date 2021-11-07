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
			"finite_events": false,
			"save_every_definition": false,
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
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default",
			"escrow_amounts": []
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
	}
	
	public function load_currencies(&$game) {
		$this->currencies = [];
		$this->name2currency_index = [];
		
		$members = $this->app->run_query("SELECT *, en.entity_id AS entity_id FROM option_group_memberships m JOIN entities en ON m.entity_id=en.entity_id JOIN currencies c ON en.entity_name=c.name WHERE m.option_group_id=:option_group_id ORDER BY m.membership_id ASC;", ['option_group_id' => $game->db_game['option_group_id']]);
		$currency_index = 0;
		
		while ($db_member = $members->fetch()) {
			array_push($this->currencies, $db_member);
			$this->name2currency_index[$db_member['name']] = $currency_index;
			$currency_index++;
		}
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$events = [];
		
		$start_block = ($game->block_to_round($from_block)-1)*$game->db_game['round_length']+1;
		if ($from_block > $start_block) $start_block += $game->db_game['round_length'];
		$end_block = ($game->block_to_round($to_block)-1)*$game->db_game['round_length']+1;
		
		if ($end_block >= $start_block) {
			$start_event_i = ($start_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			$end_event_i = ($end_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			
			if (empty($this->currencies)) $this->load_currencies($game);
			$num_pairs = count($this->currencies)*(count($this->currencies)-1);
			
			for ($event_i=$start_event_i; $event_i<=$end_event_i; $event_i++) {
				$event_pair_i = $event_i%$num_pairs;
				$first_currency_i = floor($event_pair_i/(count($this->currencies)-1));
				$second_currency_i = $event_pair_i%(count($this->currencies)-1);
				if ($second_currency_i >= $first_currency_i) $second_currency_i++;
				
				$possible_outcomes = [array("title" => $this->currencies[$first_currency_i]['entity_name'], "entity_id" => $this->currencies[$first_currency_i]['entity_id']), array("title" => $this->currencies[$second_currency_i]['entity_name'], "entity_id" => $this->currencies[$second_currency_i]['entity_id'])];
				
				$payout_block = $game->db_game['game_starting_block'] + ($event_i+5)*$game->db_game['round_length'] - 1;
				
				$event = array(
					"event_index" => $event_i+1,
					"event_starting_block" => $game->db_game['game_starting_block'] + $event_i*$game->db_game['round_length'],
					"event_final_block" => $game->db_game['game_starting_block'] + ($event_i+5)*$game->db_game['round_length'] - 1,
					"event_outcome_block" => $payout_block,
					"event_payout_block" => $payout_block,
					"event_name" => "Duel #".($event_i+1).": ".$this->currencies[$first_currency_i]['entity_name']." vs ".$this->currencies[$second_currency_i]['entity_name'],
					"option_name" => "outcome",
					"option_name_plural" => "outcomes",
					"payout_rule" => "binary",
					"payout_rate" => 1,
					"outcome_index" => null,
					"possible_outcomes" => $possible_outcomes
				);
				array_push($events, $event);
			}
		}
		
		return $events;
	}
	
	public function set_event_outcome(&$game, $payout_event) {
		if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
			$game->blockchain->load_coin_rpc();
			
			$start_block_hash = $game->blockchain->coin_rpc->getblockhash((int)$payout_event->db_event['event_starting_block']);
			$final_block_hash = $game->blockchain->coin_rpc->getblockhash((int)$payout_event->db_event['event_final_block']);
			
			$start_block = $game->blockchain->coin_rpc->getblock($start_block_hash);
			$final_block = $game->blockchain->coin_rpc->getblock($final_block_hash);
			
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
		
		$performances = [];
		$best_performance_index = false;
		$best_performance = false;
		$loop_index = 0;
		
		$db_options = $this->app->fetch_options_by_event($payout_event->db_event['event_id']);
		
		while ($db_option = $db_options->fetch()) {
			$currency_code = $this->currency_name_to_code[$db_option['name']];
			
			if ($db_option['name'] != "Bitcoin") {
				$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$currency_code."&start=".$start_time."&end=".$final_time;
				
				$db_currency = $this->app->fetch_currency_by_name($db_option['name']);
				$currency_price = $this->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $start_time);
				
				if (!empty($currency_price['time_added'])) $last_price_time = $currency_price['time_added'];
				else $last_price_time = 0;
				
				$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
				$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
				$cached_url = $this->app->cached_url_info($poloniex_url);
				
				$num_cached_prices = (int)$this->app->run_query("SELECT COUNT(*) FROM currency_prices WHERE cached_url_id=:cached_url_id;", ['cached_url_id' => $cached_url['cached_url_id']])->fetch()['COUNT(*)'];
				
				if ($num_cached_prices == 0) {
					$start_q = "INSERT INTO currency_prices (cached_url_id, currency_id, reference_currency_id, price, time_added) VALUES ";
					$new_prices_q = $start_q;
					$modulo = 0;
					
					for ($i=count($poloniex_trades)-1; $i>=0; $i--) {
						$trade = $poloniex_trades[$i];
						
						if ($trade['type'] == "buy") {
							$trade_date = new DateTime($trade['date'], new DateTimeZone('UTC'));
							$trade_time = $trade_date->format('U');
							
							if ($modulo == 1000) {
								$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
								$this->app->run_query($new_prices_q);
								$modulo = 0;
								$new_prices_q = $start_q;
							}
							else $modulo++;
							
							$new_prices_q .= "('".$cached_url['cached_url_id']."', '".$db_currency['currency_id']."', '".$btc_currency['currency_id']."', ".$this->app->quote_escape($trade['rate']).", ".$this->app->quote_escape($trade_time)."), ";
							
							$last_price_time = $trade_time;
						}
					}
					if ($modulo > 0) {
						$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
						$this->app->run_query($new_prices_q);
					}
				}
				
				if (count($poloniex_trades) > 1) {
					$start_price = $this->app->currency_price_after_time($db_currency['currency_id'], $btc_currency['currency_id'], $start_time, $final_time);
					$final_price = $this->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $final_time);
					
					if ($start_price && $start_price['price'] > 0) $performance = round(pow(10,8)*$final_price['price']/$start_price['price']);
					else $performance = 0;
				}
			}
			else $performance = pow(10,8);
			
			array_push($performances, $performance);
			
			if ($best_performance_index === false || $performance >= $best_performance) {
				$best_performance = $performance;
				$best_performance_index = $loop_index;
			}
			$loop_index++;
		}
		
		$game->set_game_defined_outcome($payout_event->db_event['event_index'], $best_performance_index);
		
		$this->app->run_query("UPDATE events SET outcome_index=:outcome_index WHERE game_id=:game_id AND event_index=:event_index;", [
			'outcome_index' => $best_performance_index,
			'game_id' => $game->db_game['game_id'],
			'event_index' => $payout_event->db_event['event_index']
		]);
		
		$payout_event->db_event['outcome_index'] = $best_performance_index;
		
		return "";
	}
	
	public function refresh_prices_by_event(&$game, &$db_event) {
		if (empty($this->currencies)) $this->load_currencies($game);
		$btc_currency = $this->app->get_currency_by_abbreviation("BTC");
		
		$starting_block = $game->blockchain->fetch_block_by_id($db_event['event_starting_block']);
		$final_block = $game->blockchain->fetch_block_by_id($db_event['event_final_block']);
		$start_q = "INSERT INTO currency_prices (cached_url_id, currency_id, reference_currency_id, price, time_added) VALUES ";
		$from_time = $starting_block['time_mined'];
		$to_time = $final_block['time_mined'];
		
		$db_options = $this->app->fetch_options_by_event($db_event['event_id']);
		
		while ($db_option = $db_options->fetch()) {
			if ($db_option['name'] != "Bitcoin") {
				$code = $this->currency_name_to_code[$db_option['name']];
				$this_currency = $this->currencies[$this->name2currency_index[$db_option['name']]];
				
				$this->app->run_query("DELETE FROM currency_prices WHERE currency_id=:currency_id AND time_added>:from_time AND time_added<:to_time;", [
					'currency_id' => $this_currency['currency_id'],
					'from_time' => $from_time,
					'to_time' => $to_time
				]);
				
				$new_prices_q = $start_q;
				$modulo = 0;
				
				$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$code."&start=".$from_time."&end=".$to_time;
				$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
				$cached_url = $this->app->cached_url_info($poloniex_url);
				$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
				
				if (!empty($poloniex_trades)) {
					for ($j=count($poloniex_trades)-1; $j>=0; $j--) {
						$trade = $poloniex_trades[$j];
						$trade_date = new DateTime($trade['date'], new DateTimeZone('UTC'));
						$trade_time = $trade_date->format('U');
						
						if ($trade['type'] == "buy") {
							if ($modulo == 1000) {
								$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
								$this->app->run_query($new_prices_q);
								$modulo = 0;
								$new_prices_q = $start_q;
							}
							else $modulo++;
							
							$new_prices_q .= "('".$cached_url['cached_url_id']."', '".$this_currency['currency_id']."', '".$btc_currency['currency_id']."', ".$this->app->quote_escape($trade['rate']).", ".$this->app->quote_escape($trade_time)."), ";
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
		
		$successful = true;
		return $successful;
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
			
			$new_prices_q = $start_q;
			$modulo = 0;
			
			$code = $this->currency_name_to_code[$this->currencies[$i]['name']];
			$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$code."&start=".$last_price_time."&end=".time();
			$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
			$cached_url = $this->app->cached_url_info($poloniex_url);
			$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
			
			for ($j=count($poloniex_trades)-1; $j>=0; $j--) {
				$trade = $poloniex_trades[$j];
				$trade_date = new DateTime($trade['date'], new DateTimeZone('UTC'));
				$trade_time = $trade_date->format('U');
				
				if ($trade['type'] == "buy") {
					if ($modulo == 1000) {
						$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
						$this->app->run_query($new_prices_q);
						$modulo = 0;
						$new_prices_q = $start_q;
					}
					else $modulo++;
					
					$new_prices_q .= "('".$cached_url['cached_url_id']."', '".$this->currencies[$i]['currency_id']."', '".$btc_currency['currency_id']."', ".$this->app->quote_escape($trade['rate']).", ".$this->app->quote_escape($trade_time)."), ";
				}
			}
			
			if ($modulo > 0) {
				$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
				$this->app->run_query($new_prices_q);
				$modulo = 0;
			}
		}
	}
}
?>