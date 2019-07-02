<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$pagetitle = "Redeem a Card";
$nav_tab_selected = "cards";
$nav_subtab_selected = "redeem";
$card = false;

if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
else $redirect_url = false;

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<input type="hidden" id="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
	<?php
	if ($uri_parts[1] == "redeem") {
		$peer_id = (int) $uri_parts[2];
		if (empty($uri_parts[3])) $card_id = false;
		else $card_id = (int) $uri_parts[3];
		
		if (!empty($card_id) && !empty($peer_id)) {
			$card = $app->fetch_card_by_peer_and_id($peer_id, $card_id);
			
			if ($card) {
				$peer = $app->fetch_peer_by_id($card['peer_id']);
				
				$printrequest = $app->run_query("SELECT * FROM card_printrequests pr JOIN card_designs d ON pr.design_id=d.design_id WHERE d.design_id=:design_id;", [
					'design_id' => $card['design_id']
				])->fetch();
				
				$currency = $app->fetch_currency_by_id($card['currency_id']);
				$fv_currency = $app->fetch_currency_by_id($card['fv_currency_id']);
				?>
				<script type="text/javascript">
				thisPageManager.card_id = '<?php echo $card['peer_card_id']; ?>';
				thisPageManager.peer_id = '<?php echo $card['peer_id']; ?>';
				
				window.onload = function() {
					$("#redeem_code").mask("9999-9999-9999-9999");
				};
				</script>
				
				<input type="hidden" id="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
				
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
									<b>peer</b>
								</div>
								<div class="col-xs-8">
									<?php echo $peer['peer_name']; ?>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-4">
									<b>Card ID</b>
								</div>
								<div class="col-xs-8">
									#<?php echo $card['peer_card_id']; ?>
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
								<div class="row">
									<div class="col-sm-12">
										<button class="btn btn-success btn-block" style="margin-top: 10px;" onclick="thisPageManager.redeem_toggle();">Redeem Now</button>
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
												<div class="btn btn-success" id="redeem_card_confirm_btn" onclick="thisPageManager.check_the_code();">Redeem</div>
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
							else if ($card['status'] == "redeemed" || $card['status'] == "claimed") {
								?>
								<script type="text/javascript">
								thisPageManager.card_id = '<?php echo $card['peer_card_id']; ?>';
								thisPageManager.peer_id = '<?php echo $card['peer_id']; ?>';
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
								$card_login_card_id = "'".$card['peer_card_id']."'";
								$card_login_peer_id = $card['peer_id'];
								include(AppSettings::srcPath().'/includes/html_card_login.php');
							}
							
							if (!empty($thisuser) && !empty($printrequest) && $thisuser->db_user['user_id'] == $printrequest['user_id']) {
								if ($card['status'] == "sold") {
									$allowed_statuses = array('canceled','printed');
								}
								else if ($card['status'] == "printed" || $card['status'] == "issued") {
									$allowed_statuses = array('canceled', 'sold');
								}
								else $allowed_statuses = [];
								
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
					<?php include(AppSettings::srcPath()."/includes/html_redeem_card.php"); ?>
				</div>
				<?php
			}
		}
	}
	
	if (empty($card)) {
		?>
		<div class="row">
			<div class="col-sm-6 col-sm-push-3 text-center">
				<h1>Log in via card</h2>
				<p>
					Please enter card details below to find your card.<br/>
					Or <a href="/wallet/<?php if ($redirect_url) echo '?redirect_key='.$redirect_url['redirect_key']; ?>">log in with a user account</a>
				</p>
				<form action="/redeem/" method="get" onsubmit="thisPageManager.search_card_id(); return false;">
					<div class="form-group">
						<label for="card_id_search">Which website issued your card?</label>
						<select class="form-control" name="peer_id" id="peer_id">
							<option value="">-- Please Select --</option>
							<?php
							$db_peers = $app->run_query("SELECT * FROM peers WHERE visible=1 ORDER BY peer_name ASC;");
							while ($db_peer = $db_peers->fetch()) {
								echo "<option value=\"".$db_peer['peer_id']."\">".$db_peer['peer_name']."</option>\n";
							}
							?>
						</select>
					</div>
					<div class="form-group">
						<label for="card_id_search">Please enter the 4-digit ID number from your card:</label>
						<input class="form-control" type="tel" size="8" value="" placeholder="0000" id="card_id_search" name="card_id_search" /> 
					</div>
					<input class="btn btn-primary" type="submit" value="Find my card" onclick="thisPageManager.search_card_id(); return false;" />
				</form>
			</div>
		</div>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>