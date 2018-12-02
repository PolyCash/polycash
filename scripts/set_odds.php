<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

$allowed_params = ['game_id', 'account_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['account_id'])) {
		$game_id = (int) $_REQUEST['game_id'];
		$account_id = (int) $_REQUEST['account_id'];
		$fee = 0.00001;
		
		$db_game = $app->fetch_db_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
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
				
				$coins_per_event = (float) $_REQUEST['coins_per_event'];
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*$num_events*pow(10, $game->db_game['decimal_places']);
					
					echo "Betting ".$app->format_bignum($coins_per_event)." on each of ".$num_events." events.<br/>\n";
					
					$q = "SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount)*(".($blockchain->last_block_id()+1)."-io.create_block_id) AS coin_blocks, SUM(gio.colored_amount*(".$round_id."-gio.create_round_id)) AS coin_rounds FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' GROUP BY gio.io_id";
					if ($game->db_game['inflation'] == "exponential" && $game->db_game['exponential_inflation_rate'] > 0) $q .= " HAVING(".$game->db_game['payout_weight']."s*".$coins_per_vote.") < ".$total_cost;
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
						$keep_looping = false; // Always use a single IO as input, else cancel this
					}
					$burn_game_amount = $total_cost-$mandatory_bets;
					
					if ($burn_game_amount < 0 || $burn_game_amount > $game_amount_sum) die("Failed to determine a valid burn amount (".$burn_game_amount.").");
					
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
						echo "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>\n";
					}
					else {
						echo "TX Error: ".$error_message."\n";
					}
				}
				else echo "Invalid coins_per_event.\n";
			}
			else echo "Invalid account ID.\n";
		}
		else echo "Please supply a valid game ID.\n";
	}
	else echo "Please supply a valid account ID.\n";
}
else echo "Incorrect key supplied.\n";
?>