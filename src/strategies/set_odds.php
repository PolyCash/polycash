<?php
require(dirname(__DIR__)."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['api_key', 'amount_per_event', 'force', 'amount_mode', 'aggressiveness'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

$user_game = $app->fetch_user_game_by_api_key($_REQUEST['api_key']);

if ($user_game) {
	$user = new User($app, $user_game['user_id']);
	$blockchain = new Blockchain($app, $user_game['blockchain_id']);
	$game = new Game($blockchain, $user_game['game_id']);
	
	$fee = (float) $user_game['transaction_fee'];
	
	$last_block_id = $blockchain->last_block_id();
	$mining_block_id = $last_block_id+1;
	$round_id = $game->block_to_round($mining_block_id);
	$coins_per_vote = $app->coins_per_vote($game->db_game);
	$fee_amount = (int) ($fee*pow(10, $blockchain->db_blockchain['decimal_places']));
	
	$amount_per_event = null;
	if (!empty($_REQUEST['amount_per_event'])) $amount_per_event = (float) $_REQUEST['amount_per_event'];
	
	$hours_between_applications = 0.1;
	$sec_between_applications = 60*60*$hours_between_applications;
	$rand_sec_offset = rand(0, $sec_between_applications*2);
	
	if ($game->last_block_id() != $blockchain->last_block_id()) {
		$app->output_message(9, "The game is not fully loaded.", false);
		die();
	}
	if (time() > $user_game['time_next_apply'] || !empty($_REQUEST['force'])) {
		$account = $app->fetch_account_by_id($user_game['account_id']);
		
		$app->set_strategy_time_next_apply($user_game['strategy_id'], time()+$rand_sec_offset);
		
		if ($account) {
			$event_params = [
				'game_id' => $game->db_game['game_id'],
				'mining_block_id' => $mining_block_id
			];
			$event_q = "events ev JOIN options op ON ev.event_id=op.event_id WHERE ev.game_id=:game_id AND op.target_probability IS NOT NULL";
			$event_q .= " AND ev.event_starting_block <= :mining_block_id AND ev.event_final_block > :mining_block_id";
			$event_q .= " AND ev.sum_score+ev.sum_unconfirmed_score=0 AND ev.destroy_score+ev.sum_unconfirmed_destroy_score=0 AND (ev.event_starting_time < ".AppSettings::sqlNow()." OR ev.event_starting_time IS NULL) AND (ev.event_final_time > ".AppSettings::sqlNow()." OR ev.event_final_time IS NULL)";
			$option_info = $app->run_query("SELECT COUNT(*) FROM ".$event_q.";", $event_params)->fetch();
			$db_events = $app->run_query("SELECT * FROM ".$event_q." GROUP BY ev.event_id ORDER BY ev.event_index ASC;", $event_params)->fetchAll();
			$num_events = count($db_events);
			
			if ($num_events > 0) {
				$amount_mode = "per_event";
				if (!empty($_REQUEST['amount_mode']) && $_REQUEST['amount_mode'] == "inflation_only") $amount_mode = "inflation_only";
				
				if ($amount_per_event > 0) {
					$coins_per_event = round($amount_per_event*pow(10, $game->db_game['decimal_places']));
				}
				else {
					if ($amount_mode == "per_event") {
						$aggressiveness = "low";
						$frac_mature_bal = 0.5;
						
						if (!empty($_REQUEST['aggressiveness']) && $_REQUEST['aggressiveness'] == "high") {
							$aggressiveness = "high";
							$frac_mature_bal = 0.5;
						}
						
						$mature_balance = $user->mature_balance($game, $user_game);
						$coins_per_event = floor($mature_balance*$frac_mature_bal/$num_events);
					}
					else {
						list($user_votes, $votes_value) = $user->user_current_votes($game, $last_block_id, $round_id, $user_game);
						$coins_per_event = ceil($votes_value/$num_events);
					}
				}
				
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*$num_events;
					
					$spendable_ios_in_account = $app->spendable_ios_in_account($account['account_id'], $game->db_game['game_id'], $round_id, $last_block_id);
					
					$mandatory_bets = 0;
					$io_amount_sum = 0;
					$game_amount_sum = 0;
					$io_ids = [];
					$keep_looping = true;
					
					foreach ($spendable_ios_in_account as $io) {
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
					
					if ($burn_game_amount < 0 || $burn_game_amount > $game_amount_sum*1.2) {
						$app->output_message(8, "You don't have enough money for this TX.", false);
						die();
					}
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					$game_coins_per_coin = $game_amount_sum/$io_nonfee_amount;
					
					$burn_address = $app->fetch_addresses_in_account($account, 0, 1)[0];
					$burn_amount = ceil($burn_game_amount/$game_coins_per_coin);
					
					$separator_addresses = $app->fetch_addresses_in_account($account, 1, (int)$option_info['COUNT(*)']);
					
					$io_nondestroy_amount = $io_nonfee_amount-$burn_amount;
					$io_nondestroy_per_event = floor($io_nondestroy_amount/$num_events);
					$io_separator_frac = AppSettings::recommendedSeparatorFrac($burn_amount, $io_nonfee_amount);
					
					$io_amounts = array($burn_amount);
					$address_ids = array($burn_address['address_id']);
					$io_spent_sum = $burn_amount;
					
					$bet_i = 0;
					
					foreach ($db_events as $db_event) {
						$options_by_event = $app->fetch_options_by_event($db_event['event_id'])->fetchAll(PDO::FETCH_ASSOC);
						
						$address_error = false;
						$thisevent_io_amounts = [];
						$thisevent_address_ids = [];
						
						$probability_sum = 0;
						foreach ($options_by_event as $option) {
							$probability_sum += $option['target_probability'];
						}
						$probability_adjuster = 1/$probability_sum;
						
						foreach ($options_by_event as $option) {
							$this_address = $app->fetch_addresses_in_account($account, $option['option_index'], 1)[0];
							
							if ($this_address) {
								$thisbet_io_amount = floor($io_nondestroy_per_event*$option['target_probability']*$probability_adjuster);
								$thisbet_io_separator_amount = floor($thisbet_io_amount*$io_separator_frac);
								$thisbet_io_regular_amount = $thisbet_io_amount-$thisbet_io_separator_amount;
								
								array_push($thisevent_io_amounts, $thisbet_io_regular_amount);
								array_push($thisevent_address_ids, $this_address['address_id']);
								
								array_push($thisevent_io_amounts, $thisbet_io_separator_amount);
								array_push($thisevent_address_ids, $separator_addresses[$bet_i%count($separator_addresses)]['address_id']);
							}
							else {
								$address_error = true;
								$app->output_message(8, "Cancelling transaction.. ".$option['name']." has no address.", false);
								die();
							}
							
							$bet_i++;
						}
						
						if (!$address_error) {
							for ($i=0; $i<count($thisevent_io_amounts); $i++) {
								array_push($io_amounts, $thisevent_io_amounts[$i]);
								array_push($address_ids, $thisevent_address_ids[$i]);
								$io_spent_sum += $thisevent_io_amounts[$i];
							}
						}
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
			else $app->output_message(5, "Failed to find any running events with target probabilities.\n", false);
		}
		else $app->output_message(4, "Invalid account ID.\n");
	}
	else $app->output_message(3, "Skipping.. this strategy was applied recently.\n", false);
}
else $app->output_message(2, "Error: the api_key you supplied does not match any user_game.\n", false);
?>