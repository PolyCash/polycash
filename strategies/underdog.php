<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

$api_key = $_REQUEST['api_key'];

$q = "SELECT *, u.user_id AS user_id, g.game_id AS game_id FROM users u JOIN user_games ug ON u.user_id=ug.user_id JOIN games g ON ug.game_id=g.game_id JOIN user_strategies s ON ug.strategy_id=s.strategy_id LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id WHERE ug.api_access_code=".$app->quote_escape($api_key).";";
$r = $app->run_query($q);

if ($r->rowCount() > 0) {
	$user_game = $r->fetch();
	$user = new User($app, $user_game['user_id']);
	$blockchain = new Blockchain($app, $user_game['blockchain_id']);
	$game = new Game($blockchain, $user_game['game_id']);
	
	$fee = (float) $user_game['transaction_fee'];
	
	$mining_block_id = $blockchain->last_block_id()+1;
	$round_id = $game->block_to_round($mining_block_id);
	$coins_per_vote = $app->coins_per_vote($game->db_game);
	$fee_amount = (int) ($fee*pow(10, $blockchain->db_blockchain['decimal_places']));
	
	$hours_between_applications = 8;
	$sec_between_applications = 60*60*$hours_between_applications;
	$rand_sec_offset = rand(0, $sec_between_applications*2);
	
	if ($user_game['time_next_apply'] == "" || $user_game['time_next_apply'] <= time() || !empty($_REQUEST['force'])) {
		$account = $app->fetch_account_by_id($user_game['account_id']);
		
		if ($account) {
			$event_q = "SELECT * FROM events ev JOIN options op ON ev.event_id=op.event_id WHERE ev.game_id='".$game->db_game['game_id']."' AND op.target_probability IS NOT NULL";
			$event_q .= " AND ev.event_starting_block<=".$mining_block_id." AND ev.event_final_block>=".$mining_block_id;
			$event_q .= " AND ev.event_starting_time < NOW() AND ev.event_final_time > NOW() GROUP BY ev.event_id ORDER BY ev.event_index ASC;";
			$event_r = $app->run_query($event_q);
			$num_events = $event_r->rowCount();
			
			if ($num_events > 0) {
				$amount_mode = "per_event";
				if (!empty($_REQUEST['amount_mode']) && $_REQUEST['amount_mode'] == "inflation_only") $amount_mode = "inflation_only";
				
				if ($amount_mode == "per_event") {
					$aggressiveness = "low";
					$frac_mature_bal = 0.5;
					
					if (!empty($_REQUEST['aggressiveness']) && $_REQUEST['aggressiveness'] == "high") {
						$aggressiveness = "high";
						$frac_mature_bal = 0.5;
					}
					
					$mature_balance = $user->mature_balance($game, $user_game);
					$coins_per_event = ($mature_balance*$frac_mature_bal/$num_events)/pow(10, $game->db_game['decimal_places']);
				}
				else {
					list($user_votes, $votes_value) = $thisuser->user_current_votes($game, $blockchain->last_block_id(), $round_id, $user_game);
					$coins_per_event = ceil($votes_value/$num_events)/pow(10, $game->db_game['decimal_places']);
				}
				
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*$num_events*pow(10, $game->db_game['decimal_places']);
					
					$q = "SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount)*(".($blockchain->last_block_id()+1)."-io.create_block_id) AS coin_blocks, SUM(gio.colored_amount*(".$round_id."-gio.create_round_id)) AS coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' GROUP BY gio.io_id";
					if ($game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] > 0) $q .= " HAVING(".$game->db_game['payout_weight']."s*".$coins_per_vote.") < ".$total_cost;
					$q .= " ORDER BY coins ASC;";
					$r = $app->run_query($q);
					
					$mandatory_bets = 0;
					$io_amount_sum = 0;
					$game_amount_sum = 0;
					$io_ids = array();
					$keep_looping = true;
					
					while ($keep_looping && $io = $r->fetch()) {
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
					
					$q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' AND a.is_destroy_address=1 AND io.spend_status='unspent' ORDER BY io.amount DESC;";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$recycle_io = $r->fetch();
						array_push($io_ids, $recycle_io['io_id']);
						$io_amount_sum += $recycle_io['amount'];
					}
					
					if ($burn_game_amount < 0 || $burn_game_amount > $game_amount_sum) die("Failed to determine a valid burn amount (".$burn_game_amount." vs ".$game_amount_sum.").");
					
					$burn_address = $app->fetch_addresses_in_account($account['account_id'], 0, 1)[0];
					$separator_addresses = $app->fetch_addresses_in_account($account['account_id'], 1, $num_events);
					$separator_frac = 0.25;
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					$burn_io_amount = ceil($io_nonfee_amount*$burn_game_amount/$game_amount_sum);
					$nonburn_io_amount = $io_nonfee_amount-$burn_io_amount;
					$io_amount_per_event = floor($nonburn_io_amount/$num_events);
					
					$io_amounts = array($burn_io_amount);
					$address_ids = array($burn_address['address_id']);
					$io_spent_sum = $burn_io_amount;
					$bet_i = 0;
					
					while ($db_event = $event_r->fetch()) {
						$option_q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY target_probability ASC LIMIT 1;";
						$option_r = $app->run_query($option_q);
						$option = $option_r->fetch();
						
						$address_error = false;
						$thisevent_io_amounts = array();
						$thisevent_address_ids = array();
						
						$this_address = $app->fetch_addresses_in_account($account['account_id'], $option['option_index'], 1)[0];
						
						if ($this_address) {
							$io_separator_amount = floor($io_amount_per_event*$separator_frac);
							$io_regular_amount = $io_amount_per_event-$io_separator_amount;
							
							array_push($thisevent_io_amounts, $io_regular_amount);
							array_push($thisevent_address_ids, $this_address['address_id']);
							
							array_push($thisevent_io_amounts, $io_separator_amount);
							array_push($thisevent_address_ids, $separator_addresses[$bet_i%count($separator_addresses)]['address_id']);
						}
						else {
							$address_error = true;
							$app->output_message(8, "Cancelling transaction.. ".$option['name']." has no address.", false);
							die();
						}
						
						$bet_i++;
						
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
						$strategy_q = "UPDATE user_strategies SET time_next_apply='".(time()+$rand_sec_offset)."' WHERE strategy_id='".$user_game['strategy_id']."';";
						$strategy_r = $app->run_query($strategy_q);
						
						$app->output_message(1, "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>", false);
					}
					else {
						$app->output_message(7, "TX Error: ".$error_message, false);
					}
				}
				else $app->output_message(6, "Invalid coins_per_event.\n", false);
			}
			else $app->output_message(5, "There are no events running right now.\n", false);
		}
		else $app->output_message(4, "Invalid account ID.\n");
	}
	else $app->output_message(3, "Skipping.. this strategy was applied recently.\n", false);
}
else $app->output_message(2, "Error: the api_key you supplied does not match any user_game.\n", false);
?>