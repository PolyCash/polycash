<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['account_id'] = $cmd_vars['account_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['account_id'])) {
		$game_id = (int) $_REQUEST['game_id'];
		$account_id = (int) $_REQUEST['account_id'];
		$fee = 0.00001;
		
		$game_q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$game_r = $app->run_query($game_q);
		
		if ($game_r->rowCount() == 1) {
			$db_game = $game_r->fetch();
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
			$total_to_destroy_ratio = 2;
			
			$account_q = "SELECT * FROM currency_accounts WHERE account_id='".$account_id."';";
			$account_r = $app->run_query($account_q);
			
			if ($account_r->rowCount() > 0) {
				$account = $account_r->fetch();
				
				$event_q = "SELECT * FROM events ev JOIN game_defined_options gdo ON ev.event_index=gdo.event_index WHERE ev.game_id='".$game->db_game['game_id']."' AND gdo.game_id='".$game->db_game['game_id']."' AND gdo.target_probability IS NOT NULL GROUP BY ev.event_id ORDER BY ev.event_index ASC;";
				$event_r = $app->run_query($event_q);
				$num_events = $event_r->rowCount();
				
				$coins_per_event = (float) $_REQUEST['coins_per_event'];
				if ($coins_per_event > 0) {
					$total_cost = $coins_per_event*$num_events*pow(10, $game->db_game['decimal_places']);
					
					echo "Betting ".$app->format_bignum($coins_per_event)." on each of ".$num_events." events.<br/>\n";
					
					$q = "SELECT *, SUM(gio.colored_amount) AS coins, SUM(gio.colored_amount)*(".($blockchain->last_block_id()+1)."-io.create_block_id) AS coin_blocks FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$account['account_id']."' GROUP BY gio.io_id ORDER BY coin_blocks ASC;";
					$r = $app->run_query($q);
					
					$io_amount_sum = 0;
					$game_amount_sum = 0;
					$io_ids = array();
					$keep_looping = true;
					
					while ($keep_looping && $io = $r->fetch()) {
						$game_amount_sum += $io['coins'];
						$io_amount_sum += $io['amount'];
						array_push($io_ids, $io['io_id']);
						
						if ($game_amount_sum >= $total_cost*$total_to_destroy_ratio) $keep_looping = false;
					}
					
					$io_nonfee_amount = $io_amount_sum-$fee_amount;
					$game_coins_per_coin = $game_amount_sum/$io_nonfee_amount;
					$max_io_spend = $total_to_destroy_ratio*$total_cost/$game_coins_per_coin;
					
					if ($max_io_spend <= $io_nonfee_amount) {
						$io_amounts = array();
						$io_destroy_amounts = array();
						$address_ids = array();
						$io_spent_sum = 0;
						
						while ($db_event = $event_r->fetch()) {
							$option_q = "SELECT op.*, gdo.target_probability FROM options op JOIN game_defined_options gdo ON op.event_option_index=gdo.option_index WHERE op.event_id='".$db_event['event_id']."' AND gdo.game_id='".$game->db_game['game_id']."' AND gdo.event_index='".$db_event['event_index']."' ORDER BY op.option_index ASC;";
							$option_r = $app->run_query($option_q);
							
							$address_error = false;
							$thisevent_io_amounts = array();
							$thisevent_io_destroy_amounts = array();
							$thisevent_address_ids = array();
							
							while ($option = $option_r->fetch()) {
								$addr_q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' AND a.option_index='".$option['option_index']."';";
								$addr_r = $app->run_query($addr_q);
								
								if ($addr_r->rowCount() > 0) {
									$address = $addr_r->fetch();
									
									$destroy_amount = floor($option['target_probability']*$coins_per_event*pow(10, $game->db_game['decimal_places']));
									$io_destroy_amount = floor($destroy_amount/$game_coins_per_coin);
									$io_amount = $io_destroy_amount*$total_to_destroy_ratio;
									
									array_push($thisevent_io_amounts, $io_amount);
									array_push($thisevent_io_destroy_amounts, $io_destroy_amount);
									array_push($thisevent_address_ids, $address['address_id']);
									
									echo "bet $destroy_amount on ".$option['name']."<br/>\n";
								}
								else {
									$address_error = true;
									echo "Skip ".$option['name'].".. no address.<br/>\n";
								}
							}
							
							if (!$address_error) {
								for ($i=0; $i<count($thisevent_io_amounts); $i++) {
									array_push($io_amounts, $thisevent_io_amounts[$i]);
									array_push($io_destroy_amounts, $thisevent_io_destroy_amounts[$i]);
									array_push($address_ids, $thisevent_address_ids[$i]);
									$io_spent_sum += $thisevent_io_amounts[$i];
								}
							}
							echo "<br/>\n";
						}
						
						$remainder_amount = $io_nonfee_amount-$io_spent_sum;
						if ($remainder_amount > 0) {
							array_push($io_amounts, $remainder_amount);
							array_push($io_destroy_amounts, 0);
							array_push($address_ids, $account['current_address_id']);
						}
						
						$transaction_id = $blockchain->create_transaction("transaction", $io_amounts, false, $io_ids, $address_ids, $io_destroy_amounts, $fee_amount);
						
						if ($transaction_id) {
							echo "Great, your transaction was submitted. <a href=\"/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$transaction_id."/\">View Transaction</a>";
						}
						else {
							echo "Error: failed to create the transaction.";
						}
					}
					else echo "There was an error with the game amount ($max_io_spend vs $io_nonfee_amount).";
				}
				else echo "Invalid coins_per_event.";
			}
			else echo "Invalid account ID.";
		}
		else echo "Please supply a valid game ID.";
	}
	else echo "Please supply a valid account ID.";
}
else echo "Incorrect key supplied.\n";
?>