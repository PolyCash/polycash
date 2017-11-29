<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Redeem a Card";
$nav_tab_selected = "cards";
$nav_subtab_selected = "redeem";
$card = false;
include('includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if ($uri_parts[1] == "redeem") {
		$issuer_id = (int) $uri_parts[2];
		if (empty($uri_parts[3])) $card_id = false;
		else $card_id = (int) $uri_parts[3];
		
		if (!empty($card_id) && !empty($issuer_id)) {
			$q = "SELECT c.*, d.issuer_id FROM cards c JOIN card_designs d ON c.design_id=d.design_id WHERE c.issuer_card_id=".$app->quote_escape($card_id)." AND d.issuer_id='".$issuer_id."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$card = $r->fetch();
				
				$issuer = $app->get_issuer_by_id($card['issuer_id']);
				
				$printrequest_q = "SELECT * FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id WHERE d.design_id='".$card['design_id']."';";
				$printrequest_r = $app->run_query($printrequest_q);
				if ($printrequest_r->rowCount() > 0) $printrequest = $printrequest_r->fetch();
				
				$currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$card['currency_id']."';")->fetch();
				$fv_currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$card['fv_currency_id']."';")->fetch();
				?>
				<script type="text/javascript">
				var card_id = '<?php echo $card['card_id']; ?>';
				var issuer_id = '<?php echo $card['issuer_id']; ?>';
				
				$(document).ready(function() {
					update_page();
				});
				
				function update_page() {
					check_show_confirm_button();
					
					setTimeout("update_page();", 1000);
				}
				</script>
				
				<div id="step1">
					<div class="row">
						<div class="col-md-6 col-md-push-3">
							<div class="card_title_status">
								<?php
								if ($card['status'] == "assigned" || $card['status'] == "issued") {
									echo "This ".$app->format_bignum($card['amount'])." ".$currency['abbreviation']." card isn't activated yet. ";
								}
								else {
									echo "This card is worth ".$app->format_bignum($card['amount'])." ".$fv_currency['short_name_plural'].". ";
								}
								?>
							</div>
							<?php
							if ($card['status'] == "assigned") {
								echo "<p>Please instruct the card's owner to activate this card before you purchase it.<br/>";
								echo "After activating the card, refresh this page.</p>\n";
							}
							?>
							<div class="row">
								<div class="col-xs-4">
									<b>Issuer</b>
								</div>
								<div class="col-xs-8">
									<?php echo $issuer['issuer_name']; ?>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-4">
									<b>Card ID</b>
								</div>
								<div class="col-xs-8">
									#<?php echo $card['issuer_card_id']; ?>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-4">
									<b>Fees</b>
								</div>
								<div class="col-xs-8" style="color: #<?php
									$card_converted_amount = 0;//get_card_btc_amount($card);
									$fees = $app->get_card_fees($card);
									
									if ($fees > 0) echo "f00"; else echo "0a0";
									?>;"><?php
									echo $app->format_bignum($fees);
									
									echo " (".number_format(100-$card['purity'])."%)";
									?>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-4">
									<b>Minted</b>
								</div>
								<div class="col-xs-8">
									<?php echo $app->format_seconds(time()-$card['mint_time']); ?> ago
								</div>
							</div>
							<?php
							/*if ($card['currency_fv'] == 0) { ?>
								<div class="row">
									<div class="col-xs-4">
										<b>Exchange Rate</b>
									</div>
									<div class="col-xs-5">
										<font style="color: #0a0; font-size: inherit;">
										$<?php
										if ($usd_per_coin > 0.01) {
											echo number_format($usd_per_coin, 2);
										}
										else {
											echo number_format($usd_per_coin, 6);
										}
										?>
										</font> / <?php echo $card['currency_abbrev']; ?>
									</div>
								</div>
							<?php }*/ ?>
							<div class="row">
								<div class="col-xs-4">
									<b>Value</b>
								</div>
								<div class="col-xs-8">
									<font style="color: #0a0;"><?php echo $app->format_bignum($card['amount'] - $fees)." ".$fv_currency['short_name_plural']; ?></font>
									<?php
									if ($card['currency_id'] != $card['fv_currency_id']) echo ' &rarr; '.$app->format_bignum($card_converted_amount)." ".$currency['short_name_plural'];
									?>
								</div>
							</div>
							<?php
							if ($card['status'] == "sold") {
								?>
								<script type="text/javascript">
								$(document).ready(function() {
									$("#redeem_code").mask("9999-9999-9999-9999");
								});
								</script>
								
								<div class="row">
									<div class="col-sm-12">
										<button class="btn btn-success btn-block" style="margin-top: 10px;" onclick="redeem_toggle();">Redeem Now</button>
									</div>
								</div>
								
								<div style="display: none; margin-top: 15px;" id="enter_redeem_code">
									<div class="form-group">
										<label for="gc_redeem_digits">Please scratch off your card, then enter the 16 digit code here:</label>
										
										<div class="row">
											<div class="col-md-8">
												<input class="form-control" type="tel" size="20" maxlength="19" class="code_enter" id="redeem_code" />
											</div>
											<div class="col-md-4">
												<div class="btn btn-success" id="confirm_button" onclick="check_the_code();">Redeem</div>
											</div>
										</div>
									</div>
								</div>
								<?php
							}
							else if ($card['status'] == "canceled") {
								echo "This card has been canceled.";
							}
							else if ($card['status'] == "issued" || $card['status'] == "printed") {
								echo "This card hasn't been released yet.";
							}
							else if ($card['status'] == "redeemed") {
								?>
								<script type="text/javascript">
								var card_id = '<?php echo $card['card_id']; ?>';
								</script>
								<br/>
								<p>
									To access the money on this card, please log in with it.
								</p>
								<p>
									<a class="btn btn-success" href="" onclick="$('#card_login').toggle('fast'); return false;">Log In</a>
								</p>
								<?php
								$ask4nameid = FALSE;
								$login_title = "Please log in:";
								$card_login_card_id = "'".$card['issuer_card_id']."'";
								$card_login_issuer_id = $card['issuer_id'];
								include('includes/html_card_login.php');
							}
							
							if (!empty($thisuser) && !empty($printrequest) && $thisuser->db_user['user_id'] == $printrequest['user_id']) {
								if ($card['status'] == "sold") {
									$allowed_statuses = array('canceled','printed');
								}
								else if ($card['status'] == "printed" || $card['status'] == "issued") {
									$allowed_statuses = array('canceled', 'sold');
								}
								else $allowed_statuses = array();
								
								if (count($allowed_statuses) > 0) {
									?>
									<br/>
									<p>
										<form method="get" action="/account/">
											<input type="hidden" name="action" value="manage_cards" />
											<input type="hidden" name="action2" value="change_card_status" />
											<input type="hidden" name="card_id" value="<?php echo $card['card_id']; ?>" />
											<div class="form-group">
												<label for="to_status">Change card status:</label>
												<select class="form-control" name="to_status" onchange="this.form.submit();">
													<option value="">-- Please Select --</option>
													<?php
													foreach ($allowed_statuses as $allowed_status) {
														echo '<option value="'.$allowed_status.'">'.ucwords($allowed_status).'</option>'."\n";
													}
													?>
												</select>
											</div>
										</form>
									</p>
									<?php
								}
							}
							?>
							<div id="messages" style="margin-top: 15px; display: block;"></div>
						</div>
					</div>
				</div>
				<div id="redeem_options" class="modal fade" style="display: none;">
					<?php include("includes/html_redeem_card.php"); ?>
				</div>
				<?php
			}
		}
	}
	
	if (empty($card)) {
		?>
		<div class="row">
			<div class="col-sm-6 col-sm-push-3 text-center">
				<h1>Redeem your gift card</h2>
				<p>
					Do you have a gift card to redeem?<br/>
					Please enter card details below to find your card.
				</p>
				<form action="/redeem/" method="get" onsubmit="search_card_id(); return false;">
					<div class="form-group">
						<label for="card_id_search">Which website issued your card?</label>
						<select class="form-control" name="card_issuer_id" id="card_issuer_id">
							<option value="">-- Please Select --</option>
							<?php
							$q = "SELECT * FROM card_issuers ORDER BY issuer_name ASC;";
							$r = $app->run_query($q);
							while ($db_issuer = $r->fetch()) {
								echo "<option value=\"".$db_issuer['issuer_id']."\">".$db_issuer['issuer_name']."</option>\n";
							}
							?>
						</select>
					</div>
					<div class="form-group">
						<label for="card_id_search">Please enter the 4-digit ID number from your card:</label>
						<input class="form-control" type="tel" size="8" value="" placeholder="0000" id="card_id_search" name="card_id_search" /> 
					</div>
					<input class="btn btn-primary" type="submit" value="Find my card" onclick="search_card_id(); return false;" />
				</form>
			</div>
		</div>
		<?php
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>