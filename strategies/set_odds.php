<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if ($thisuser) {
	$user_game_id = (int) $_REQUEST['user_game_id'];

	$q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id JOIN games g ON ug.game_id=g.game_id JOIN user_strategies s ON ug.strategy_id=s.strategy_id LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id WHERE ug.user_game_id='".$user_game_id."';";
	$r = $app->run_query($q);

	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		
		if ($thisuser->db_user['user_id'] == $user_game['user_id']) {
			$blockchain = new Blockchain($app, $user_game['blockchain_id']);
			$game = new Game($blockchain, $user_game['game_id']);
			
			$account_id = $user_game['account_id'];
			$fee = 0;
			
			$mining_block_id = $blockchain->last_block_id()+1;
			$round_id = $game->block_to_round($mining_block_id);
			$coins_per_vote = $app->coins_per_vote($game->db_game);
			$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
			
			$account_q = "SELECT * FROM currency_accounts WHERE account_id='".$account_id."';";
			$account_r = $app->run_query($account_q);
			
			if ($account_r->rowCount() > 0) {
				$account = $account_r->fetch();
				
				$event_q = "SELECT * FROM events ev JOIN game_defined_options gdo ON ev.event_index=gdo.event_index WHERE ev.game_id='".$game->db_game['game_id']."' AND gdo.game_id='".$game->db_game['game_id']."' AND gdo.target_probability IS NOT NULL";
				$event_q .= " AND ev.event_starting_block<=".$mining_block_id." AND ev.event_final_block>=".$mining_block_id;
				$event_q .= " GROUP BY ev.event_id ORDER BY ev.event_index ASC;";
				$event_r = $app->run_query($event_q);
				$num_events = $event_r->rowCount();
				
				$amount_mode = "per_event";
				if (!empty($_REQUEST['amount_mode']) && $_REQUEST['amount_mode'] == "inflation_only") $amount_mode = "inflation_only";
				
				if ($amount_mode == "per_event") $coins_per_event = (float) $_REQUEST['coins_per_event'];
				else {
					$user_votes = $thisuser->user_current_votes($game, $blockchain->last_block_id(), $round_id, $user_game);
					$votes_value = $user_votes*$coins_per_vote;
					$coins_per_event = ceil($votes_value/$num_events)/pow(10, $game->db_game['decimal_places']);
				}
				
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*$num_events*pow(10, $game->db_game['decimal_places']);
					
					echo "Betting ".$app->format_bignum($coins_per_event)." on each of ".$num_events." events.<br/>\n";
					
					$q = "SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount)*(".($blockchain->last_block_id()+1)."-io.create_block_id) AS coin_blocks, SUM(gio.colored_amount*(".$round_id."-gio.create_round_id)) AS coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' GROUP BY gio.io_id";
					//if ($game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] > 0) $q .= " HAVING(".$game->db_game['payout_weight']."s*".$coins_per_vote.") < ".$total_cost;
					$q .= " ORDER BY coins DESC;";
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
						if ($amount_mode != "inflation_only" && $game_amount_sum >= $burn_game_amount) $keep_looping = false;
					}
					
					if ($burn_game_amount < 0 || $burn_game_amount > $game_amount_sum) die("Failed to determine a valid burn amount (".$burn_game_amount." vs ".$game_amount_sum.").");
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					$game_coins_per_coin = $game_amount_sum/$io_nonfee_amount;
					
					echo $app->format_bignum($game_coins_per_coin)." ".$game->db_game['coin_name_plural']." per ".$blockchain->db_blockchain['coin_name'].".<br/>\n";
					
					$burn_address = $app->fetch_address_in_account($account['account_id'], 0);
					$burn_amount = ceil($burn_game_amount/$game_coins_per_coin);
					
					$remaining_io_amount = $io_nonfee_amount-$burn_amount;
					$io_amount_per_event = $remaining_io_amount/$num_events;
					
					echo "burn ".$app->format_bignum($burn_amount/pow(10, $blockchain->db_blockchain['decimal_places']))." ".$blockchain->db_blockchain['coin_name_plural']." (".$app->format_bignum($burn_game_amount/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." + ".$app->format_bignum($mandatory_bets/pow(10, $game->db_game['decimal_places']))." from inflation).<br/><br/>\n";
					
					$io_amounts = array($burn_amount);
					$address_ids = array($burn_address['address_id']);
					$io_spent_sum = $burn_amount;
					
					while ($db_event = $event_r->fetch()) {
						$option_q = "SELECT op.*, gdo.target_probability FROM options op JOIN game_defined_options gdo ON op.event_option_index=gdo.option_index WHERE op.event_id='".$db_event['event_id']."' AND gdo.game_id='".$game->db_game['game_id']."' AND gdo.event_index='".$db_event['event_index']."' ORDER BY op.option_index ASC;";
						$option_r = $app->run_query($option_q);
						
						$address_error = false;
						$thisevent_io_amounts = array();
						$thisevent_address_ids = array();
						$thisevent_io_sum = 0;
						
						while ($option = $option_r->fetch()) {
							$this_address = $app->fetch_address_in_account($account['account_id'], $option['option_index']);
							
							if ($this_address) {
								$io_amount = round($option['target_probability']*$io_amount_per_event);
								
								array_push($thisevent_io_amounts, $io_amount);
								array_push($thisevent_address_ids, $this_address['address_id']);
								$thisevent_io_sum += $io_amount;
								
								echo "bet $io_amount on ".$option['name']."<br/>\n";
							}
							else {
								$address_error = true;
								die("Cancelling transaction.. ".$option['name']." has no address.<br/>\n");
							}
						}
						echo $app->format_bignum($thisevent_io_sum/pow(10, $blockchain->db_blockchain['decimal_places']))." coins<br/>\n";
						
						if (!$address_error) {
							for ($i=0; $i<count($thisevent_io_amounts); $i++) {
								array_push($io_amounts, $thisevent_io_amounts[$i]);
								array_push($address_ids, $thisevent_address_ids[$i]);
								$io_spent_sum += $thisevent_io_amounts[$i];
							}
						}
						echo "<br/>\n";
					}
					$overshoot_amount = $io_spent_sum-$io_nonfee_amount;
					echo "overshoot: ".$app->format_bignum($overshoot_amount/pow(10, $blockchain->db_blockchain['decimal_places']))." coins<br/>\n";
					$io_amounts[count($io_amounts)-1] -= $overshoot_amount;
					
					$error_message = false;
					$transaction_id = $blockchain->create_transaction("transaction", $io_amounts, false, $io_ids, $address_ids, $fee_amount, $error_message);
					
					if ($transaction_id) {
						echo "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>";
					}
					else {
						echo "TX Error: ".$error_message;
					}
				}
				else echo "Invalid coins_per_event.";
			}
			else echo "Invalid account ID.";
		}
		else echo "You don't have permission to apply this strategy.\n";
	}
	else echo "Error: invalid user_game_id.\n";
}
else echo "You must be logged in to complete this action.\n";
?>