<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
	$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$game->db_game['game_id']."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$game->db_game['invite_currency']."';";
		$r = $app->run_query($q);
		$invite_currency = $r->fetch();
		
		$btc_currency = $app->get_currency_by_abbreviation('btc');
		
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "submit") {
			$btc_amount = floatval($_REQUEST['btc_amount']);
			
			$q = "INSERT INTO game_buyins SET status='unpaid', pay_currency_id='".$btc_currency['currency_id']."', settle_currency_id='".$invite_currency['currency_id']."', currency_address_id='".$user_game['buyin_currency_address_id']."', user_id='".$thisuser->db_user['user_id']."', game_id='".$game->db_game['game_id']."', pay_amount='".$btc_amount."', time_created='".time()."', expire_time='".(time()+$GLOBALS['invoice_expiration_seconds'])."';";
			$r = $app->run_query($q);
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
				$pot_value = $game->pot_value();
				if ($pot_value > 0) {
					$exchange_rate = ($coins_in_existence/pow(10,8))/$pot_value;
				}
				else $exchange_rate = 0;
				
				$btc_exchange_rate = $app->currency_conversion_rate($invite_currency['currency_id'], $btc_currency['currency_id']);
				?>
				<script type="text/javascript">
				function check_buyin_amount() {
					var exchange_rate = <?php echo $exchange_rate; ?>;
					var btc_exchange_rate = <?php echo $btc_exchange_rate['conversion_rate']; ?>;
					var amount_in = parseFloat($('#buyin_amount').val());
					var amount_out = amount_in*exchange_rate;
					
					$('#buyin_disp').show();
					$('#buyin_amount_disp').html(amount_in);
					$('#buyin_receive_amount_disp').html(format_coins(amount_out));
					$('#buyin_send_amount_btc').html(format_coins(amount_in/btc_exchange_rate));
					$('#buyin_btc_pay_amount').val(format_coins(amount_in/btc_exchange_rate));
					$('#btc_exchange_rate').html(format_coins(btc_exchange_rate));
				}
				function submit_buyin() {
					var btc_amount = $('#buyin_btc_pay_amount').val();
					
					$.get("/ajax/buyin.php?action=submit&game_id=<?php echo $game->db_game['game_id']; ?>&btc_amount="+btc_amount, function(result) {
						var result_json = JSON.parse(result);
						alert(result_json['message']);
					});
				}
				</script>
				<?php
				echo '<div class="paragraph">';
				echo "Right now, there are ".$app->format_bignum($coins_in_existence/pow(10,8))." ".$game->db_game['coin_name_plural']." in circulation";
				echo " and ".$app->format_bignum($pot_value)." ".$invite_currency['short_name']."s in the pot. ";
				echo "The exchange rate is currently ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$invite_currency['short_name'].". ";
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
							echo "You've already made buy-ins totalling ".$app->format_bignum($user_buyin_limit['user_buyin_total'])." ".$invite_currency['short_name']."s.<br/>\n";
						}
						else echo "You haven't made any buy-ins yet.<br/>\n";
						
						if ($game->db_game['buyin_policy'] == "unlimited") {
							echo "You can buy in for as many coins as you want in this game. ";
						}
						else if ($game->db_game['buyin_policy'] == "per_user_cap") {
							echo "This game allows each player to buy in for up to ".$app->format_bignum($game->db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s. ";
						}
						else if ($game->db_game['buyin_policy'] == "game_cap") {
							echo "This game has a game-wide buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s. ";
						}
						else if ($game->db_game['buyin_policy'] == "game_and_user_cap") {
							echo "This game has a total buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s. ";
							echo "Until this limit is reached, each player can buy in for up to ".$app->format_bignum($game->db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s. ";
						}
						else die("Invalid buy-in policy.");
						
						if ($game->db_game['buyin_policy'] != "unlimited") {
							echo "</div><div class=\"paragraph\">Your remaining buy-in limit is ".$app->format_bignum($user_buyin_limit['user_buyin_limit'])." ".$invite_currency['short_name']."s. ";
						}
						
						if ($game->db_game['buyin_policy'] == "unlimited" || $user_buyin_limit['user_buyin_limit'] > 0) {
							if ($user_game['buyin_currency_address_id'] > 0) {
								$currency_address = $app->fetch_currency_address_by_id($user_game['buyin_currency_address_id']);
							}
							else {
								$currency_address_id = $app->new_currency_address(2, false);
								$currency_address = $app->fetch_currency_address_by_id($currency_address_id);
								$q = "UPDATE user_games SET buyin_currency_address_id='".$currency_address_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
								$r = $app->run_query($q);
							}
							?>
							</div><div class="paragraph">
							How much would you like to spend?<br/>
							<div class="row">
								<div class="col-sm-6">
									<input type="text" class="form-control" id="buyin_amount">
								</div>
								<div class="col-sm-6 form-control-static">
									<?php echo $invite_currency['short_name']."s"; ?>
								</div>
							</div>
							<button class="btn btn-primary" onclick="check_buyin_amount();">Check</button>
							
							<div style="display: none; margin: 10px 0px;" id="buyin_disp">
								<?php if ($invite_currency['currency_id'] != $btc_currency['currency_id']) { ?>
								The <?php echo $invite_currency['short_name']; ?> / BTC exchange rate is <div id="btc_exchange_rate" style="display: inline-block;"></div> <?php echo $invite_currency['short_name']; ?>s / BTC right now. 
								<?php } ?>
								For <div id="buyin_amount_disp" style="display: inline-block;"></div> <?php echo $invite_currency['short_name']."s"; ?>, you'll receive approximately <div id="buyin_receive_amount_disp" style="display: inline-block;"></div> <?php echo $game->db_game['coin_name_plural']; ?>. Send <div id="buyin_send_amount_btc" style="display: inline-block;"></div> BTC to <a target="_blank" href="https://blockchain.info/address/<?php echo $currency_address['pub_key']; ?>"><?php echo $currency_address['pub_key']; ?></a>
								<br/>
								<center><img style="margin: 10px;" src="/render_qr_code.php?data=<?php echo $currency_address['pub_key']; ?>" /></center>
								<br/>
								After making the payment please confirm the amount that you sent:<br/>
								<div class="row">
									<div class="col-sm-4">
										<input type="text" class="form-control" id="buyin_btc_pay_amount" />
									</div>
									<div class="col-sm-2 form-control-static">
										BTC
									</div>
									<div class="col-sm-6">
										<button class="btn btn-success" onclick="submit_buyin();">Confirm Payment</button>
									</div>
								</div>
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
