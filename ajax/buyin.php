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
			//$q = "INSERT INTO game_buyins SET status='unpaid', pay_currency_id='".$chain_currency['currency_id']."', settle_currency_id='".$chain_currency['currency_id']."', currency_address_id='".$user_game['buyin_currency_address_id']."', user_id='".$thisuser->db_user['user_id']."', game_id='".$game->db_game['game_id']."', pay_amount='".$btc_amount."', time_created='".time()."', expire_time='".(time()+$GLOBALS['invoice_expiration_seconds'])."';";
			//$r = $app->run_query($q);
			$app->output_message(1, "Great! ".ucfirst($game->db_game['coin_name_plural'])." will be credited to your account as soon as your BTC payment is confirmed.", false);
		}
		else {
			?>
			<div class="modal-header">
				<h4 class="modal-title"><?php echo $game->db_game['name']; ?>: Buy more <?php echo $game->db_game['coin_name_plural']; ?></h4>
			</div>
			<div class="modal-body">
				<?php
				$coins_in_existence = $game->coins_in_existence(false);
				$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
				$escrow_value = $game->blockchain->address_balance_at_block($escrow_address, $game->blockchain->last_block_id());
				
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
				}
				function submit_buyin() {
					var buyin_amount = $('#buyin_amount').val();
					var color_amount = $('#color_amount').val();
					
					$.get("/ajax/buyin.php?action=submit&game_id=<?php echo $game->db_game['game_id']; ?>&invoice_id="+buyin_invoice_id+"&buyin_amount="+buyin_amount+"&color_amount="+color_amount, function(result) {
						var result_json = JSON.parse(result);
						alert(result_json['message']);
					});
				}
				</script>
				<?php
				echo '<div class="paragraph">';
				echo "Right now, there are ".$app->format_bignum($coins_in_existence/pow(10,8))." ".$game->db_game['coin_name_plural']." in circulation";
				echo " and ".$app->format_bignum($escrow_value/pow(10,8))." ".$chain_currency['short_name']."s in escrow. ";
				echo "The exchange rate is currently ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$chain_currency['short_name'].". ";
				echo '</div>';
				?>
				<div class="paragraph">
					<?php
					$buyin_limit = 0;
					if ($game->db_game['buyin_policy'] == "none") {
						echo "Sorry, buy-ins are not allowed in this game.";
					}
					else {
						$user_buyin_limit = $thisuser->user_buyin_limit($game);
						
						if ($user_buyin_limit['user_buyin_total'] > 0) {
							echo "You've already made buy-ins totalling ".$app->format_bignum($user_buyin_limit['user_buyin_total'])." ".$chain_currency['short_name']."s.<br/>\n";
						}
						else echo "You haven't made any buy-ins yet.<br/>\n";
						
						if ($game->db_game['buyin_policy'] == "unlimited") {
							echo "You can buy in for as many coins as you want in this game. ";
						}
						else if ($game->db_game['buyin_policy'] == "per_user_cap") {
							echo "This game allows each player to buy in for up to ".$app->format_bignum($game->db_game['per_user_buyin_cap'])." ".$chain_currency['short_name']."s. ";
						}
						else if ($game->db_game['buyin_policy'] == "game_cap") {
							echo "This game has a game-wide buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$chain_currency['short_name']."s. ";
						}
						else if ($game->db_game['buyin_policy'] == "game_and_user_cap") {
							echo "This game has a total buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$chain_currency['short_name']."s. ";
							echo "Until this limit is reached, each player can buy in for up to ".$app->format_bignum($game->db_game['per_user_buyin_cap'])." ".$chain_currency['short_name']."s. ";
						}
						else die("Invalid buy-in policy.");
						
						if ($game->db_game['buyin_policy'] != "unlimited") {
							echo "</div><div class=\"paragraph\">Your remaining buy-in limit is ".$app->format_bignum($user_buyin_limit['user_buyin_limit'])." ".$chain_currency['short_name']."s. ";
						}
						
						if ($game->db_game['buyin_policy'] == "unlimited" || $user_buyin_limit['user_buyin_limit'] > 0) {
							$currency_account = $thisuser->fetch_currency_account($chain_currency['currency_id']);
							
							if ($currency_account) {
								$invoice = $app->new_currency_invoice($chain_currency, false, $thisuser, $user_game, 'buyin');
								$invoice_addr_q = "SELECT * FROM addresses WHERE address_id='".$invoice['address_id']."';";
								$invoice_address = $app->run_query($invoice_addr_q)->fetch();
							}
							else {
								die("Failed to generate a deposit address.");
							}
							?>
							<script type="text/javascript">
							var buyin_invoice_id = <?php echo $invoice['invoice_id']; ?>;
							</script>
							
							</div><div class="paragraph">
							<p>
								How many <?php echo $chain_currency['short_name_plural']; ?> do you want to spend?
							</p>
							<p>
								<div class="row">
									<div class="col-sm-12">
										<input type="text" class="form-control" id="buyin_amount">
									</div>
								</div>
							</p>
							<p>
								How many <?php echo $chain_currency['short_name_plural']; ?> do you want to color?
							</p>
							<p>
								<div class="row">
									<div class="col-sm-12">
										<input type="text" class="form-control" id="color_amount">
									</div>
								</div>
							</p>
							<button class="btn btn-primary" onclick="check_buyin_amount();">Check</button>
							
							<div style="display: none; margin: 10px 0px;" id="buyin_disp">
								For <div id="buyin_amount_disp" style="display: inline-block;"></div> <?php echo $chain_currency['short_name']."s"; ?>, you'll receive approximately <div id="buyin_receive_amount_disp" style="display: inline-block;"></div> <?php echo $game->db_game['coin_name_plural']; ?>. Send <div id="buyin_send_amount" style="display: inline-block;"></div> <?php echo $chain_currency['abbreviation']; ?> to <a target="_blank" href="/explorer/blockchains/<?php echo $game->blockchain->db_blockchain['url_identifier']; ?>/address/<?php echo $invoice_address['address']; ?>"><?php echo $invoice_address['address']; ?></a>
								
								<p>
									<center><img style="margin: 10px;" src="/render_qr_code.php?data=<?php echo $invoice_address['address']; ?>" /></center>
								</p>
								<p>
									After making the payment please click below to request <?php echo $game->db_game['coin_name_plural']; ?>:
								</p>
								<p>
									<button class="btn btn-success" onclick="submit_buyin();">Request <?php echo ucwords($game->db_game['coin_name_plural']); ?></button>
								</p>
							</div>
							<?php
						}
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
