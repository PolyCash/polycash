<?php
class CoinBattlesGameDefinition {
	public $app;
	public $game_def;
	public $events_per_round;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->currencies = false;
		$this->events_per_round = array('BTC'=>'Bitcoin', 'BCH'=>'Bitcoin Cash', 'DASH'=>'Dash', 'ETH'=>'Ethereum', 'ETC'=>'Ethereum Classic', 'LTC'=>'Litecoin', 'XMR'=>'Monero', 'XEM'=>'NEM', 'XRP'=>'Ripple');
		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": "9 significant cryptocurrencies",
			"protocol_version": 0,
			"name": "Coin Battles",
			"url_identifier": "coin-battles",
			"module": "CoinBattles",
			"category_id": 4,
			"decimal_places": 8,
			"finite_events": false,
			"save_every_definition": false,
			"event_type_name": "battle",
			"event_type_name_plural": "battles",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"event_entity_type_id": 0,
			"events_per_round": 1,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.01,
			"pos_reward": 0,
			"round_length": 1000,
			"payout_weight": "coin_block",
			"final_round": null,
			"buyin_policy": "none",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "battlecoin",
			"coin_name_plural": "battlecoins",
			"coin_abbreviation": "BTL",
			"escrow_address": "",
			"genesis_tx_hash": "",
			"genesis_amount": 100000000000000,
			"game_starting_block": 1,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.9,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default"
		}';
		$this->load();
	}
	
	public function load() {
		AppSettings::addJsDependency("chart.js");
		
		$game_def = json_decode($this->game_def_base_txt);

		$db_blockchain = $this->app->fetch_blockchain_by_identifier($game_def->blockchain_identifier);

		if ($db_blockchain) {
			$blockchain = new Blockchain($this->app, $db_blockchain['blockchain_id']);
			$blockchain->load_coin_rpc();
			
			if ($db_blockchain['p2p_mode'] == "rpc") {
				try {
					$chain_last_block = (int) $blockchain->coin_rpc->getblockcount();
				}
				catch (Exception $e) {}
			}
			else $chain_last_block = $blockchain->last_block_id();
			
			$this->load_currencies();
			
			$chain_starting_block = $game_def->game_starting_block;
			
			$chain_events_until_block = $chain_last_block + $game_def->round_length;

			$defined_rounds = ceil(($chain_events_until_block - $chain_starting_block)/$game_def->round_length);

			$this->game_def = $game_def;
		}
		else echo "No blockchain found matching that identifier.";
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$general_entity_type = $this->app->check_set_entity_type("general entity");
		$events = [];
		
		$start_block = ($game->block_to_round($from_block)-1)*$game->db_game['round_length']+1;
		if ($from_block > $start_block) $start_block += $game->db_game['round_length'];
		$end_block = ($game->block_to_round($to_block)-1)*$game->db_game['round_length']+1;
		
		if ($end_block >= $start_block) {
			$start_event_i = ($start_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			$end_event_i = ($end_block-$game->db_game['game_starting_block'])/$game->db_game['round_length'];
			
			$events_per_cycle = count($this->currencies);
			
			for ($event_i=$start_event_i; $event_i<=$end_event_i; $event_i++) {
				$possible_outcomes = [];
				
				foreach ($this->events_per_round as $currency_code => $currency_name) {
					$entity = $this->app->check_set_entity($general_entity_type['entity_type_id'], $currency_name);
					array_push($possible_outcomes, array("title" => $currency_name, "entity_id" => $entity['entity_id']));
				}
				
				$payout_block = $game->db_game['game_starting_block']+($event_i+1)*$game->db_game['round_length']-1;
				
				$event = array(
					"event_index" => $event_i,
					"event_starting_block" => $game->db_game['game_starting_block']+$event_i*$game->db_game['round_length'],
					"event_final_block" => $game->db_game['game_starting_block']+($event_i+1)*$game->db_game['round_length']-1,
					"event_outcome_block" => $payout_block,
					"event_payout_block" => $payout_block,
					"event_name" => "Coin Battle #".($event_i+1),
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
	
	public function add_oracle_urls(&$game) {
		$until_block = $game->blockchain->last_block_id();
		
		if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") $game->blockchain->load_coin_rpc();
		
		$gdes = $this->app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_final_block<=:ref_block ORDER BY event_index ASC;", [
			'game_id' => $game->db_game['game_id'],
			'ref_block' => $until_block
		]);
		
		while ($gde = $gdes->fetch()) {
			if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
				$start_block_hash = $game->blockchain->coin_rpc->getblockhash((int)$gde['event_starting_block']);
				$final_block_hash = $game->blockchain->coin_rpc->getblockhash((int)$gde['event_final_block']);
				
				$start_block = $game->blockchain->coin_rpc->getblock($start_block_hash);
				$final_block = $game->blockchain->coin_rpc->getblock($final_block_hash);
				
				$start_time = $start_block['time'];
				$final_time = $final_block['time'];
			}
			else {
				$start_block = $game->blockchain->fetch_block_by_id($gde['event_starting_block']);
				$final_block = $game->blockchain->fetch_block_by_id($gde['event_final_block']);
				
				$start_time = $start_block['time_mined'];
				$final_time = $final_block['time_mined'];
			}
			
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
	
	public function set_event_outcome(&$game, &$payout_event) {
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
		
		foreach ($this->events_per_round as $currency_code => $currency_name) {
			if ($loop_index > 0) {
				$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=BTC_".$currency_code."&start=".$start_time."&end=".$final_time;
				
				$db_currency = $this->app->fetch_currency_by_name($currency_name);
				$currency_price = $this->app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $start_time);
				
				if (!empty($currency_price['time_added'])) $last_price_time = $currency_price['time_added'];
				else $last_price_time = 0;
				
				$poloniex_response = $this->app->async_fetch_url($poloniex_url, true);
				$poloniex_trades = json_decode($poloniex_response['cached_result'], true);
				$cached_url = $this->app->cached_url_info($poloniex_url);
				
				$num_cached_prices = (int) $this->app->run_query("SELECT COUNT(*) FROM currency_prices WHERE cached_url_id=:cached_url_id;", [
					'cached_url_id' => $cached_url['cached_url_id']
				])->fetch()['COUNT(*)'];
				
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
		
		$game->set_game_defined_outcome($payout_event->db_event['event_index'], $best_performance_index);
		
		return "";
	}
	
	public function load_currencies() {
		$events_per_round = $this->events_per_round;
		$currencies = [];
		
		foreach ($events_per_round as $currency_code => $currency_name) {
			$db_currency = $this->app->fetch_currency_by_name($currency_name);
			
			array_push($currencies, $db_currency);
		}
		
		$this->currencies = $currencies;
	}
	
	public function regular_actions(&$game) {
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
							$new_prices_q = substr($new_prices_q, 0, strlen($new_prices_q)-2).";";
							$this->app->run_query($new_prices_q);
							$modulo = 0;
							$new_prices_q = $start_q;
						}
						else $modulo++;
						
						$new_prices_q .= "('".$cached_url['cached_url_id']."', '".$this->currencies[$i]['currency_id']."', '".$btc_currency['currency_id']."', ".$this->app->quote_escape($trade['rate']).", ".$this->app->quote_escape($trade_time)."), ";
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
	
	public function currency_chart(&$game, $from_block_id, $to_block_id) {
		$html = "";
		$js = "";
		
		if ($from_block_id === NULL) return false;
		
		$btc_currency = $this->app->get_currency_by_abbreviation("BTC");
		
		$start_time = microtime(true);
		
		$from_block = $game->blockchain->fetch_block_by_id($from_block_id);
		if (empty($from_block['time_mined'])) $from_block['time_mined'] = time();
		
		if (!empty($to_block_id)) {
			$to_block = $game->blockchain->fetch_block_by_id($to_block_id);
			if ($to_block) $to_time = $to_block['time_mined'];
			else $to_time = time();
		}
		else {
			$to_block = false;
			$to_time = time();
		}
		$seconds_elapsed = $to_time-$from_block['time_mined'];
		
		$max_vert_lines = 400;
		$seconds_per_vert_line = max(5, round($seconds_elapsed/$max_vert_lines));
		$time_first_vert_line = ceil($from_block['time_mined']/$seconds_per_vert_line)*$seconds_per_vert_line;

		$x_labels = [];
		$x_labels_txt = '[';
		$last_label_time = $time_first_vert_line;
		do {
			$label_time_disp = date("g:ia", $last_label_time);
			
			$x_labels_txt .= '"'.$label_time_disp.'", ';
			array_push($x_labels, $last_label_time);
			$last_label_time = $last_label_time + $seconds_per_vert_line;
		}
		while ($last_label_time < $to_time);
		
		$x_labels_txt = substr($x_labels_txt, 0, strlen($x_labels_txt)-2).']';
		
		$min_min = 0;
		$max_max = 0;
		
		for ($i=0; $i<count($this->currencies); $i++) {
			$initial_price = $this->app->currency_price_after_time($this->currencies[$i]['currency_id'], $btc_currency['currency_id'], $from_block['time_mined']);
			
			if ($initial_price) {
				$this->currencies[$i]['initial_price'] = $initial_price;
				
				$minmax = $this->app->run_query("SELECT MIN(price), MAX(price), MIN(time_added), MAX(time_added) FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:ref_currency_id AND time_added >= :from_time AND time_added <= :to_time;", [
					'currency_id' => $this->currencies[$i]['currency_id'],
					'ref_currency_id' => $btc_currency['currency_id'],
					'from_time' => $from_block['time_mined'],
					'to_time' => $to_time
				])->fetch();
				
				$min_price = $minmax['MIN(price)'];
				$max_price = $minmax['MAX(price)'];
				
				$min_performance = round(pow(10,8)*$min_price/$initial_price['price']);
				$max_performance = round(pow(10,8)*$max_price/$initial_price['price']);
				
				if ($min_performance < $min_min) $min_min = $min_performance;
				if ($max_performance > $max_max) $max_max = $max_performance;
			}
			else $this->currencies[$i]['initial_price'] = false;
		}
		
		$datasets_txt = "";
		
		$currencies[0]['final_performance'] = pow(10,8);
		$performances[$currencies[0]['final_performance']] = 0;
		
		for ($i=0; $i<count($this->currencies); $i++) {
			if ($i == 0 || !empty($this->currencies[$i]['initial_price'])) {
				$blue = round(255*$i/(count($this->currencies)-1));
				
				$relevant_performances = [];
				
				$db_prices = $this->app->run_query("SELECT * FROM currency_prices WHERE currency_id=:currency_id AND reference_currency_id=:ref_currency_id AND time_added >= :from_time AND time_added <= :to_time ORDER BY time_added ASC;", [
					'currency_id' => $this->currencies[$i]['currency_id'],
					'ref_currency_id' => $btc_currency['currency_id'],
					'from_time' => $from_block['time_mined'],
					'to_time' => $to_time
				]);
				$j = 0;
				while ($db_price = $db_prices->fetch()) {
					$performance = false;
					if ($j == 0 || $i == 0) $performance = pow(10,8);
					else $performance = round(pow(10,8)*$db_price['price']/$this->currencies[$i]['initial_price']['price']);
					
					array_push($relevant_performances, array("performance"=>$performance, "time_added"=>$db_price['time_added']));
					$j++;
				}
				
				$data_txt = "0, ";
				$l = 0;
				for ($k=0; $k<count($x_labels); $k++) {
					while (!empty($relevant_performances[$l]) && $relevant_performances[$l]['time_added'] < $x_labels[$k]) $l++;
					if (!empty($relevant_performances[$l])) {
						$performance = $relevant_performances[$l]['performance'];
						$data_txt .= round(($performance/pow(10,8) - 1)*100, 4).", ";
					}
				}
				
				$data_txt = substr($data_txt, 0, strlen($data_txt)-2);
				$datasets_txt .= "{
					label: '".$this->currencies[$i]['name']."',
					backgroundColor: 'rgba(255, ".$blue.", 0, 1)',
					borderColor: 'rgba(255, ".$blue.", 0, 1)',
					pointRadius: 0.5,
					borderWidth: 1,
					data: [".$data_txt."],
					fill: false,
				}, ";
				
				if ($i == 0) {
					$final_price = 0;
					$final_performance = pow(10,8);
				}
				else {
					$final_price = $this->app->currency_price_at_time($this->currencies[$i]['currency_id'], $btc_currency['currency_id'], $to_time);
					$final_performance = round(pow(10,8)*$final_price['price']/$this->currencies[$i]['initial_price']['price']);
				}
				$performances[$final_performance] = $i;
			}
		}
		$datasets_txt = substr($datasets_txt, 0, strlen($datasets_txt)-2);
		
		krsort($performances);
		
		$html = '
		<div class="container-fluid">
			<div class="row">
				<div class="col-md-8">
					<h4 style="margin: 0px; padding: 0px;">Performance of top cryptocurrencies against Bitcoin - Poloniex Buy Trades</h4>
					<canvas id="canvas"></canvas>';
		
		$stop_time = microtime(true);
		$load_time = $stop_time - $start_time;
		
		$html .= '
				</div>
				<div class="col-md-3" style="text-align: left;">
					<br/><br/>
					<b>Performances Rankings</b><br/>';
		
		$i = 1;
		foreach ($performances as $value => $index) {
			$currency = $this->currencies[$index];
			$html .= $i.". ".$currency['name'].'<div style="display: inline-block; float: right;">'.round(100*(($value/pow(10,8))-1), 2)."%</div><br/>\n";
			$i++;
		}
		
		$html .= '
				</div>
			</div>
		</div>'."\n";

		$js .= '
		var config = {
			type: "line",
			data: {
				labels: '.$x_labels_txt.',
				datasets: ['.$datasets_txt.']
			},
			options: {
				responsive: true,
				title:{
					display:true
				},
				animation: {
					duration: 0,
				},
				tooltips: {
					mode: "index",
					intersect: false,
				},
				scales: {
					xAxes: [{
						display: true
					}],
					yAxes: [{
						display: true
					}]
				},
				elements: {
					line: {
						tension: 0
					}
				}
			}
		};';

		$js .= '
		var ctx = document.getElementById("canvas").getContext("2d");
		window.myLine = new Chart(ctx, config);';
		
		return array($html, $js);
	}
}
?>