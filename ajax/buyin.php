<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
	$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$game->blockchain->currency_id()."';";
		$r = $app->run_query($q);
		$chain_currency = $r->fetch();
		
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "submit") {
			$invoice_id = (int) $_REQUEST['invoice_id'];
			$buyin_amount = floatval($_REQUEST['buyin_amount']);
			$color_amount = floatval($_REQUEST['color_amount']);
			$pay_amount = $buyin_amount+$color_amount;
			
			$q = "SELECT * FROM currency_invoices ci JOIN user_games ug ON ci.user_game_id=ug.user_game_id WHERE ci.invoice_id='".$invoice_id."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$invoice = $r->fetch();
				if ($invoice['user_id'] == $thisuser->db_user['user_id']) {
					$q = "UPDATE currency_invoices SET buyin_amount='".$buyin_amount."', color_amount='".$color_amount."', pay_amount='".$pay_amount."' WHERE invoice_id='".$invoice['invoice_id']."';";
					$r = $app->run_query($q);
				}
			}
			
			$app->output_message(1, "Great! ".ucfirst($game->db_game['coin_name_plural'])." will be credited to your account as soon as your BTC payment is confirmed.", false);
		}
		else {
			?>
			<div class="modal-header">
				<h4 class="modal-title"><?php echo $game->db_game['name']; ?>: Buy more <?php echo $game->db_game['coin_name_plural']; ?></h4>
			</div>
			<div class="modal-body">
				<?php
				$coins_in_existence = ($game->coins_in_existence(false)+$game->pending_bets())/pow(10, $game->db_game['decimal_places']);
				
				if ($game->db_game['buyin_policy'] == "for_sale") {
					$buyin_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
					$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id']);
					$pay_to_account = $game->check_set_blockchain_sale_account($thisuser, $buyin_currency);
				}
				else {
					$buyin_currency = $app->run_query("SELECT * FROM currencies WHERE blockchain_id='".$game->db_game['blockchain_id']."';")->fetch();
					$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
					$escrow_value = $game->escrow_value(false)/pow(10, $game->db_game['decimal_places']);
					$pay_to_account = $thisuser->fetch_currency_account($buyin_currency['currency_id']);
				}
				
				$buyin_blockchain = new Blockchain($app, $buyin_currency['blockchain_id']);
				
				if ($escrow_value > 0) {
					$exchange_rate = $coins_in_existence/$escrow_value;
				}
				else $exchange_rate = 0;
				?>
				<script type="text/javascript">
				function check_buyin_amount() {
					var exchange_rate = <?php echo $exchange_rate; ?>;
					var amount_in = parseFloat($('#buyin_amount').val());
					var color_amount = parseFloat($('#color_amount').val());
					var amount_out = amount_in*exchange_rate;
					
					$('#buyin_disp').show();
					$('#buyin_amount_disp').html(amount_in);
					$('#buyin_send_amount').html(format_coins(amount_in+color_amount));
					$('#buyin_receive_amount_disp').html(format_coins(amount_out));
					$('#buyin_pay_amount').val(format_coins(amount_in/exchange_rate));
					$('#exchange_rate').html(format_coins(exchange_rate));
					
					$.get("/ajax/buyin.php?action=submit&game_id=<?php echo $game->db_game['game_id']; ?>&invoice_id="+buyin_invoice_id+"&buyin_amount="+amount_in+"&color_amount="+color_amount, function(result) {});
				}
				</script>
				<?php
				echo '<div class="paragraph">';
				echo "Right now, there are ".$app->format_bignum($coins_in_existence)." ".$game->db_game['coin_name_plural']." in circulation";
				echo " and ".$app->format_bignum($escrow_value)." ".$buyin_currency['short_name_plural']." in escrow. ";
				echo "The exchange rate is currently ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$buyin_currency['short_name'].". ";
				echo '</div>';
				?>
				<div class="paragraph">
					<?php
					$buyin_limit = 0;
					if ($game->db_game['buyin_policy'] == "none") {
						echo "Sorry, buy-ins are not allowed in this game.";
					}
					else {
						if ($game->db_game['buyin_policy'] == "unlimited") {
							echo "You can buy in for as many coins as you want in this game. ";
						}
						else if ($game->db_game['buyin_policy'] == "game_cap") {
							echo "This game has a game-wide buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$game->blockchain->db_blockchain['coin_name_plural'].". ";
						}
						else if ($game->db_game['buyin_policy'] == "for_sale") {
							$game_sale_account = $game->check_set_game_sale_account($thisuser);
							$game_sale_amount = $game->account_balance($game_sale_account['account_id']);
							echo "There are ".$app->format_bignum($game_sale_amount/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." for sale. ";
						}
						else die("Invalid buy-in policy.");
						
						if ($pay_to_account) {
							$invoice_type = "buyin";
							if ($game->db_game['buyin_policy'] == "for_sale") $invoice_type = "sale_buyin";
							
							$invoice = $app->new_currency_invoice($pay_to_account, $pay_to_account['currency_id'], false, $thisuser, $user_game, $invoice_type);
							$invoice_address = $app->fetch_address_by_id($invoice['address_id']);
						}
						else {
							die("Failed to generate a deposit address.");
						}
						?>
						<script type="text/javascript">
						var buyin_invoice_id = <?php echo $invoice['invoice_id']; ?>;
						</script>
						
						</div><div class="paragraph">
						<?php
						if ($game->db_game['buyin_policy'] == "for_sale") {
							?>
							<p>
								How many <?php echo $buyin_currency['short_name_plural']; ?> do you want to spend?
							</p>
							<p>
								<div class="row">
									<div class="col-sm-12">
										<input type="text" class="form-control" id="buyin_amount">
									</div>
								</div>
							</p>
							<?php
						}
						else {
							?>
							<p>
								How many <?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?> do you want to spend?
							</p>
							<p>
								<div class="row">
									<div class="col-sm-12">
										<input type="text" class="form-control" id="buyin_amount">
									</div>
								</div>
							</p>
							<p>
								How many <?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?> do you want to color?
							</p>
							<p>
								<div class="row">
									<div class="col-sm-12">
										<input type="text" class="form-control" id="color_amount">
									</div>
								</div>
							</p>
							<?php
						}
						?>
						<button class="btn btn-primary" onclick="check_buyin_amount();">Check</button>
						
						<div style="display: none; margin: 10px 0px;" id="buyin_disp">
							For <div id="buyin_amount_disp" style="display: inline-block;"></div> <?php echo $buyin_currency['short_name_plural']; ?>, you'll receive approximately <div id="buyin_receive_amount_disp" style="display: inline-block;"></div> <?php echo $game->db_game['coin_name_plural']; ?>. Send <div id="buyin_send_amount" style="display: inline-block;"></div> <?php echo $buyin_currency['short_name_plural']; ?> to <a target="_blank" href="/explorer/blockchains/<?php echo $buyin_blockchain->db_blockchain['url_identifier']; ?>/addresses/<?php echo $invoice_address['address']; ?>"><?php echo $invoice_address['address']; ?></a>
							
							<p>
								<center><img style="margin: 10px;" src="/render_qr_code.php?data=<?php echo $invoice_address['address']; ?>" /></center>
							</p>
							<p>
								<?php echo ucfirst($game->db_game['coin_name_plural']); ?> will automatically be credited to your account when your payment is received.
							</p>
						</div>
						<?php
					}
					?>
				</div>
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
