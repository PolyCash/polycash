<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$user_game = $app->fetch_user_game_by_api_key($_REQUEST['api_key']);

if ($user_game) {
	$user = new User($app, $user_game['user_id']);
	$blockchain = new Blockchain($app, $user_game['blockchain_id']);
	$game = new Game($blockchain, $user_game['game_id']);
	
	$buy_or_sell = "buy";
	if (!empty($_REQUEST['sell'])) $buy_or_sell = "sell";
	
	$fee = (float) $user_game['transaction_fee'];
	
	$last_block_id = $blockchain->last_block_id();
	$mining_block_id = $last_block_id+1;
	$round_id = $game->block_to_round($mining_block_id);
	$coins_per_vote = $app->coins_per_vote($game->db_game);
	$fee_amount = (int) ($fee*pow(10, $blockchain->db_blockchain['decimal_places']));
	
	$account = $app->fetch_account_by_id($user_game['account_id']);
	
	if ($account) {
		$mature_balance = $user->mature_balance($game, $user_game);
		$user_pending_bets = $game->user_pending_bets($user_game);
		$account_value = $game->account_balance($user_game['account_id'])+$user_pending_bets;
		
		$hours_between_applications = 36;
		$sec_between_applications = 60*60*$hours_between_applications;
		$rand_sec_offset = rand(0, $sec_between_applications*2);
		
		$app->set_strategy_time_next_apply($user_game['strategy_id'], time()+$rand_sec_offset);
		
		if (($mature_balance > $account_value*0.35 && time() > $user_game['time_next_apply']) || !empty($_REQUEST['force'])) {
			$db_events = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block <= :mining_block_id AND event_final_block > :mining_block_id AND track_max_price != 0 ORDER BY event_index ASC;", [
				'game_id' => $game->db_game['game_id'],
				'mining_block_id' => $mining_block_id
			])->fetchAll();
			
			$num_events = count($db_events);
			
			if ($num_events > 0) {
				$selected_events = [];
				$events_by_ratio_diff = [];
				$event_info_by_id = [];
				
				for ($event_i=0; $event_i<count($db_events); $event_i++) {
					$options = $app->run_query("SELECT * FROM options op JOIN entities en ON op.entity_id=en.entity_id WHERE op.event_id=:event_id ORDER BY op.event_option_index ASC;", ['event_id'=>$db_events[$event_i]['event_id']])->fetchAll();
					
					$this_currency = $app->fetch_currency_by_id($options[0]['currency_id']);
					
					$buy_inflation_stake = $coins_per_vote*($options[0]['votes'] + $options[0]['unconfirmed_votes']);
					$buy_burn_stake = $options[0]['effective_destroy_score']+$options[0]['unconfirmed_effective_destroy_score'];
					$buy_stake = $buy_inflation_stake+$buy_burn_stake;
					
					$sell_inflation_stake = $coins_per_vote*($options[1]['votes'] + $options[1]['unconfirmed_votes']);
					$sell_burn_stake = $options[1]['effective_destroy_score']+$options[1]['unconfirmed_effective_destroy_score'];
					$sell_stake = $sell_inflation_stake+$sell_burn_stake;
					
					$market_price_info = $app->exchange_rate_between_currencies(1, $this_currency['currency_id'], time(), 6);
					$market_price = $market_price_info['exchange_rate'];
					
					$market_ratio = ($market_price-$db_events[$event_i]['track_min_price'])/($db_events[$event_i]['track_max_price']-$db_events[$event_i]['track_min_price']);
					
					if ($buy_stake + $sell_stake == 0) {
						$event_info_by_id[$db_events[$event_i]['event_id']] = ['buy_stake'=>$buy_stake, 'sell_stake'=>$sell_stake, 'currency'=>$this_currency, 'market_ratio'=>$market_ratio];
						
						array_push($selected_events, $db_events[$event_i]);
					}
					else {
						$our_ratio = $buy_stake/($buy_stake+$sell_stake);
						$our_price = $our_ratio*($db_events[$event_i]['track_max_price']-$db_events[$event_i]['track_min_price'])+$db_events[$event_i]['track_min_price'];
						
						$ratio_diff = abs($our_ratio-$market_ratio);
						
						$events_by_ratio_diff[(string)$ratio_diff] = $db_events[$event_i];
						
						$event_info_by_id[$db_events[$event_i]['event_id']] = ['buy_stake'=>$buy_stake, 'sell_stake'=>$sell_stake, 'currency'=>$this_currency, 'our_ratio'=>$our_ratio, 'market_ratio'=>$market_ratio];
					}
				}
				
				krsort($events_by_ratio_diff);
				$events_needed = min(count($db_events)-count($selected_events), 2);
				$events_by_ratio_arr = array_values($events_by_ratio_diff);
				
				for ($add_i=0; $add_i<count($events_by_ratio_arr); $add_i++) {
					array_push($selected_events, $events_by_ratio_arr[$add_i]);
				}
				
				$amount_mode = "per_event";
				if (!empty($_REQUEST['amount_mode']) && $_REQUEST['amount_mode'] == "inflation_only") $amount_mode = "inflation_only";
				
				if ($amount_mode == "per_event") {
					$frac_mature_bal = 0.3;
					
					$coins_per_event = floor($mature_balance*$frac_mature_bal/count($selected_events));
				}
				else {
					list($user_votes, $votes_value) = $thisuser->user_current_votes($game, $blockchain->last_block_id(), $round_id, $user_game);
					$coins_per_event = ceil($votes_value/count($selected_events));
				}
				
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*count($selected_events);
					
					$spendable_ios_in_account = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
					
					$mandatory_bets = 0;
					$io_amount_sum = 0;
					$game_amount_sum = 0;
					$io_ids = [];
					$keep_looping = true;
					
					while ($keep_looping && $io = $spendable_ios_in_account->fetch()) {
						$game_amount_sum += $io['coins'];
						$io_amount_sum += $io['amount'];
						
						if ($game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] > 0) {
							if ($game->db_game['payout_weight'] == "coin_block") $votes = $io['coin_blocks'];
							else if ($game->db_game['payout_weight'] == "coin_round") $votes = $io['coin_rounds'];
							$this_mandatory_bets = floor($votes*$coins_per_vote);
						}
						else $this_mandatory_bets = 0;
						
						$mandatory_bets += $this_mandatory_bets;
						array_push($io_ids, $io['io_id']);
						
						$burn_game_amount = $total_cost-$mandatory_bets;
						if ($amount_mode != "inflation_only" && $game_amount_sum >= $burn_game_amount*1.2) $keep_looping = false;
					}
					
					$recycle_ios = $app->fetch_recycle_ios_in_account($account['account_id'], false);
					
					foreach ($recycle_ios as $recycle_io) {
						array_push($io_ids, $recycle_io['io_id']);
						$io_amount_sum += $recycle_io['amount'];
					}
					
					if ($burn_game_amount < 0 || $burn_game_amount > $game_amount_sum) die("Failed to determine a valid burn amount (".$burn_game_amount." vs ".$game_amount_sum.").");
					
					$burn_address = $app->fetch_addresses_in_account($account, 0, 1)[0];
					$separator_addresses = $app->fetch_addresses_in_account($account, 1, count($selected_events));
					$separator_frac = 0.25;
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					$burn_io_amount = ceil($io_nonfee_amount*$burn_game_amount/$game_amount_sum);
					$nonburn_io_amount = $io_nonfee_amount-$burn_io_amount;
					$io_amount_per_event = floor($nonburn_io_amount/$num_events);
					
					$io_amounts = array($burn_io_amount);
					$address_ids = array($burn_address['address_id']);
					$io_spent_sum = $burn_io_amount;
					
					$bet_i = 0;
					
					foreach ($selected_events as $db_event) {
						$this_event = new Event($game, false, $db_event['event_id']);
						
						$event_starting_block = $game->blockchain->fetch_block_by_id($db_event['event_starting_block']);
						$event_final_block = $game->blockchain->fetch_block_by_id($db_event['event_final_block']);
						if ($event_final_block && !empty($event_final_block['time_mined'])) $event_to_time = $event_final_block['time_mined'];
						else $event_to_time = time();
						
						$info = $event_info_by_id[$db_event['event_id']];
						
						$buy_option = $app->fetch_option_by_event_option_index($db_event['event_id'], 0);
						$sell_option = $app->fetch_option_by_event_option_index($db_event['event_id'], 1);
						
						$this_option = $buy_or_sell == "buy" ? $buy_option : $sell_option;
						
						$this_address = $app->fetch_addresses_in_account($account, $this_option['option_index'], 1)[0];
						
						if (!$this_address) {
							$app->output_message(8, "Cancelling transaction.. ".$this_option['name']." has no address.", false);
							die();
						}

						$io_separator_amount = floor($io_amount_per_event*$separator_frac);
						$io_regular_amount = $io_amount_per_event-$io_separator_amount;
						
						array_push($io_amounts, $io_regular_amount);
						array_push($address_ids, $this_address['address_id']);
						
						array_push($io_amounts, $io_separator_amount);
						array_push($address_ids, $separator_addresses[$bet_i%count($separator_addresses)]['address_id']);
						
						$bet_i++;
						
						$io_spent_sum += $io_separator_amount+$io_regular_amount;
					}
					$overshoot_amount = $io_spent_sum-$io_nonfee_amount;
					$io_amounts[count($io_amounts)-1] -= $overshoot_amount;
					
					$error_message = false;
					$transaction_id = $blockchain->create_transaction("transaction", $io_amounts, false, $io_ids, $address_ids, $fee_amount, $error_message);
					
					if ($transaction_id) {
						$transaction = $app->fetch_transaction_by_id($transaction_id);
						
						$app->output_message(1, "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction['tx_hash']."/\">View Transaction</a>", false);
					}
					else $app->output_message(7, "TX Error: ".$error_message, false);
				}
				else $app->output_message(6, "Invalid coins_per_event.\n", false);
			}
			else $app->output_message(5, "There are no events running right now.\n", false);
		}
		else $app->output_message(3, "Skipping.. this strategy was applied recently.\n", false);
	}
	else $app->output_message(4, "Invalid account ID.\n");
}
else $app->output_message(2, "Error: the api_key you supplied does not match any user_game.\n", false);
?>