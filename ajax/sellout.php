<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
	$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id JOIN user_strategies us ON ug.strategy_id=us.strategy_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$game->blockchain->currency_id()."';";
		$r = $app->run_query($q);
		$chain_currency = $r->fetch();
		
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "confirm") {
			if ($game->db_game['sellout_policy'] == "on") {
				$invoice_id = (int) $_REQUEST['invoice_id'];
				$sellout_amount = ceil(floatval($_REQUEST['sellout_amount'])*pow(10, $game->db_game['decimal_places']));
				$tx_fee = ceil($user_game['transaction_fee']*pow(10, $game->db_game['decimal_places']));
				$receive_address = $_REQUEST['address'];
				
				$q = "SELECT * FROM currency_invoices ci JOIN user_games ug ON ci.user_game_id=ug.user_game_id WHERE ci.invoice_id='".$invoice_id."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() == 1) {
					$invoice = $r->fetch();
					
					if ($invoice['user_id'] == $thisuser->db_user['user_id']) {
						$coins_in_existence = ($game->coins_in_existence(false)+$game->pending_bets())/pow(10, $game->db_game['decimal_places']);
						$sellout_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
						$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id']);
						//$game_sale_account = $game->check_set_game_sale_account($thisuser);
						$sellout_blockchain = new Blockchain($app, $sellout_currency['blockchain_id']);
						
						$db_receive_address = $sellout_blockchain->create_or_fetch_address($receive_address, true, false, false, true, false, false);
						
						if ($escrow_value > 0) {
							$exchange_rate = $coins_in_existence/$escrow_value;
						}
						else $exchange_rate = 0;
						
						$cost_game_coins = $sellout_amount+$tx_fee;
						//$pay_amount = max(0, $sellout_amount/$exchange_rate - $tx_fee);
						
						$q = "UPDATE currency_invoices SET receive_address_id='".$db_receive_address['address_id']."', buyin_amount='".$sellout_amount/pow(10, $game->db_game['decimal_places'])."', fee_amount='".$user_game['transaction_fee']."' WHERE invoice_id='".$invoice['invoice_id']."';";
						$r = $app->run_query($q);
						
						$q = "SELECT *, SUM(gio.colored_amount) AS coins FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.is_resolved=1 AND io.spend_status IN ('unspent','unconfirmed') AND k.account_id='".$user_game['account_id']."' GROUP BY gio.io_id ORDER BY coins ASC;";
						$r = $app->run_query($q);
						
						$io_amount_sum = 0;
						$game_amount_sum = 0;
						$ios = array();
						$io_ids = array();
						$keep_looping = true;
						
						while ($keep_looping && $io = $r->fetch()) {
							$game_amount_sum += $io['coins'];
							$io_amount_sum += $io['amount'];
							array_push($io_ids, $io['io_id']);
							array_push($ios, $io);
							
							if ($game_amount_sum >= $cost_game_coins) $keep_looping = false;
						}
						
						$io_nonfee_amount = $io_amount_sum-$tx_fee;
						$game_coins_per_coin = $game_amount_sum/$io_nonfee_amount;
						
						$send_chain_amount = ceil($sellout_amount/$game_coins_per_coin);
						$amounts = array($send_chain_amount);
						$address_ids = array($invoice['address_id']);
						
						if ($io_nonfee_amount > $send_chain_amount) {
							$remainder_amount = $io_nonfee_amount-$send_chain_amount;
							array_push($amounts, $remainder_amount);
							array_push($address_ids, $ios[0]['address_id']);
						}
						
						$transaction_id = $game->blockchain->create_transaction("transaction", $amounts, false, $io_ids, $address_ids, $tx_fee);
						
						if ($transaction_id) {
							$app->output_message(1, "Great! ".ucfirst($game->db_game['coin_name_plural'])." will be credited to your account as soon as your BTC payment is confirmed.", false);
						}
						else {
							$app->output_message(2, "Error: failed to create the transaction.", false);
						}
					}
					else $app->output_message(3, "Error: you don't have permission to update this invoice.", false);
				}
				else $app->output_message(4, "Error: invalid invoice ID.", false);
			}
			else $app->output_message(5, "Error: sellouts are disabled for this game.", false);
		}
		else {
			?>
			<div class="modal-header">
				<h4 class="modal-title"><?php echo $game->db_game['name']; ?>: Sell your <?php echo $game->db_game['coin_name_plural']; ?></h4>
			</div>
			<div class="modal-body">
				<?php
				$coins_in_existence = $game->coins_in_existence(false)/pow(10, $game->db_game['decimal_places']);
				
				if ($game->db_game['sellout_policy'] == "on") {
					$sellout_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
					$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id']);
					$game_sale_account = $game->check_set_game_sale_account($thisuser);
				
					$sellout_blockchain = new Blockchain($app, $sellout_currency['blockchain_id']);
					
					if ($escrow_value > 0) {
						$exchange_rate = $coins_in_existence/$escrow_value;
					}
					else $exchange_rate = 0;
					?>
					<script type="text/javascript">
					function check_sellout_amount() {
						var exchange_rate = <?php echo $exchange_rate; ?>;
						var amount_in = parseFloat($('#sellout_amount').val());
						var amount_out = Math.max(0, amount_in/exchange_rate-games[0].fee_amount);
						
						$('#sellout_disp').show();
						$('#sellout_amount_disp').html(amount_in);
						$('#sellout_send_amount').html(format_coins(amount_in));
						$('#sellout_receive_amount_disp').html(format_coins(amount_out));
						$('#sellout_tx_fee').html(games[0].fee_amount);
						
						$.get("/ajax/sellout.php?action=submit&game_id=<?php echo $game->db_game['game_id']; ?>&invoice_id="+sellout_invoice_id+"&sellout_amount="+amount_in, function(result) {});
					}
					</script>
					<?php
					echo '<div class="paragraph">';
					echo "Right now, there are ".$app->format_bignum($coins_in_existence)." ".$game->db_game['coin_name_plural']." in circulation";
					echo " and ".$app->format_bignum($escrow_value)." ".$sellout_currency['short_name_plural']." in escrow. ";
					echo "The exchange rate is currently ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$sellout_currency['short_name'].". ";
					echo '</div>';
					?>
					<div class="paragraph">
						<?php
						$blockchain_sale_account = $game->check_set_blockchain_sale_account($thisuser, $sellout_currency);
						$blockchain_sale_amount = $sellout_blockchain->account_balance($blockchain_sale_account['account_id']);
						$game_equivalent_amount = ($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))*$exchange_rate;
						echo "There are ".$app->format_bignum($blockchain_sale_amount/pow(10, $sellout_blockchain->db_blockchain['decimal_places']))." ".$sellout_blockchain->db_blockchain['coin_name_plural']." for sale (".$app->format_bignum($game_equivalent_amount)." ".$game->db_game['coin_name_plural']."). ";
						
						if ($game_sale_account) {
							$invoice_type = "sellout";
							$invoice = $app->new_currency_invoice($game_sale_account, $sellout_currency['currency_id'], false, $thisuser, $user_game, $invoice_type);
							$invoice_addr_q = "SELECT * FROM addresses WHERE address_id='".$invoice['address_id']."';";
							$invoice_address = $app->run_query($invoice_addr_q)->fetch();
						}
						else {
							die("Failed to generate a deposit address.");
						}
						?>
						<script type="text/javascript">
						var sellout_invoice_id = <?php echo $invoice['invoice_id']; ?>;
						</script>
						
					</div>
					<div class="paragraph">
						<p>
							How many <?php echo $game->db_game['coin_name_plural']; ?> do you want to change?
						</p>
						<p>
							<div class="row">
								<div class="col-sm-12">
									<input type="text" class="form-control" id="sellout_amount" />
								</div>
							</div>
						</p>
						
						<button class="btn btn-primary" onclick="check_sellout_amount();">Check</button>
						
						<div style="display: none; margin: 10px 0px;" id="sellout_disp">
							<p>
								<div style="display: inline-block" id="sellout_tx_fee"></div> <?php echo $sellout_currency['short_name']; ?> tx fee
							</p>
							<p>
								<div id="sellout_amount_disp" style="display: inline-block;"></div> <?php echo $game->db_game['coin_name_plural']; ?> will get you approximately <div id="sellout_receive_amount_disp" style="display: inline-block;"></div> <?php echo $sellout_currency['short_name_plural']; ?>.
							</p>
							
							<div class="form-group">
								<label for="sellout_blockchain_address">What address should your <?php echo $sellout_currency['short_name_plural']; ?> be sent to?</label>
								<input type="text" class="form-control" id="sellout_blockchain_address" />
							</div>
							<p>
								<button class="btn btn-success" onclick="confirm_sellout();">Sell <?php echo $game->db_game['coin_name_plural']; ?></button>
							</p>
						</div>
					</div>
					<?php
				}
				else echo "Sellouts are not enabled for this game.";
				?>
			</div>
			<?php
		}
	}
}
else { ?>
	<div class="modal-body" style="overflow: hidden;">
		Error: it looks like you're not logged into this game.
	</div>
	<?php
}
?>
