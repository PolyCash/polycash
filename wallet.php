<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$error_code = false;
$message = "";

if ($_REQUEST['do'] == "signup") {
	$username = $_POST['username'];
	$notification_email = $_POST['notification_email'];
	safe_email($username);
	safe_email($notification_email);
	
	if ($GLOBALS['pageview_tracking_enabled']) {
		$ip_match_q = "SELECT * FROM users WHERE ip_address='".$_SERVER['REMOTE_ADDR']."';";
		$ip_match_r = run_query($ip_match_q);
		
		if (mysql_numrows($ip_match_r) <= 20) {}
		else {
			$error_code = 2;
			$message = "Sorry, there was an error creating your new account.";
		}
	}
	
	if (!$error_code) {
		$user_q = "SELECT * FROM users WHERE username='".$username."';";
		$user_r = run_query($user_q);
		
		if (mysql_numrows($user_r) == 0) {
			if ($notification_email == "" || mysql_numrows(run_query("SELECT * FROM users WHERE notification_email='".$notification_email."';")) == 0) {
				if ($GLOBALS['signup_captcha_required']) {
					$recaptcha_resp = recaptcha_check_answer($GLOBALS['recaptcha_privatekey'], $_SERVER["REMOTE_ADDR"], $_POST["g-recaptcha-response"]);
				}
				if ($GLOBALS['signup_captcha_required'] && !$recaptcha_resp) {
					$error_code = 2;
					$message = "You entered the wrong CAPTCHA code. Please try again. ";
				}
				else {
					$pass_error = FALSE;
					
					if ($_REQUEST['autogen_password'] == "1") {
						$new_pass = random_string(12);
						$new_pass_hash = hash('sha256', $new_pass);
					}
					else {
						$new_pass = mysql_real_escape_string($_REQUEST['password']);
						if ($_REQUEST['password2'] != $new_pass) $pass_error = TRUE;
						$new_pass_hash = $new_pass;
					}
					
					if ($pass_error) {
						$error_code = 2;
						$message = "The passwords that you entered did not match. Please try again. ";
					}
					else {
						$verify_code = random_string(32);
						
						$q = "INSERT INTO users SET game_id='".get_site_constant('primary_game_id')."', username='".$username."', notification_email='".$notification_email."', api_access_code='".mysql_real_escape_string(random_string(32))."', password='".$new_pass_hash."'";
						if ($GLOBALS['pageview_tracking_enabled']) {
							$q .= ", ip_address='".$_SERVER['REMOTE_ADDR']."'";
						}
						if ($GLOBALS['new_games_per_user'] != "unlimited") {
							$q .= ", authorized_games='".$GLOBALS['new_games_per_user']."'";
						}
						$bitcoin_address = $_REQUEST['bitcoin_address'];
						safe_text($bitcoin_address);
						
						if ($bitcoin_address != "") {
							$qq = "INSERT INTO external_addresses SET user_id='".$user_id."', currency_id=2, address='".$bitcoin_address."', time_created='".time()."';";
							$rr = run_query($qq);
							$address_id = mysql_insert_id();
							$q .= ", bitcoin_address_id='".$address_id."'";
						}
						
						$q .= ", time_created='".time()."', verify_code='".$verify_code."';";
						$r = run_query($q);
						$user_id = mysql_insert_id();
						
						
						$q = "SELECT * FROM users WHERE user_id='".$user_id."';";
						$r = run_query($q);
						$thisuser = mysql_fetch_array($r);
						
						$session_key = session_id();
						$expire_time = time()+3600*24;
						
						$error_code = 1;
						if ($_REQUEST['autogen_password'] == "1") {
							$message = "Your account was with this password: <b>".$new_pass."</b>. Please save this password now; it will not be displayed again.";
						}
						else {
							$message = "Your account has been created.  Please enter your password below to log in.";
						}
						
						if ($GLOBALS['pageview_tracking_enabled']) {
							$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$thisuser['user_id']."';";
							$r = run_query($q);
							if (mysql_numrows($r) == 0) {
								$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$thisuser['user_id']."';";
								$r = run_query($q);
							}
							
							$q = "UPDATE users SET ip_address='".$_SERVER['REMOTE_ADDR']."' WHERE user_id='".$thisuser['user_id']."';";
							$r = run_query($q);
						}
						
						// Send an email if the username includes
						if ($GLOBALS['outbound_email_enabled'] && strpos($notification_email, '@')) {
							$email_message = "<p>A new ".$GLOBALS['site_name_short']." web wallet has been created for <b>".$username."</b>.</p>";
							$email_message .= "<p>Thanks for signing up!</p>";
							$email_message .= "<p>To log in any time please visit ".$GLOBALS['base_url']."/wallet/</p>";
							$email_message .= "<p>This message was sent to you by ".$GLOBALS['base_url']."</p>";
							
							$email_id = mail_async($notification_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], "New account created", $email_message, "", "");
						}
						
						$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
						$r = run_query($q);
						
						if (mysql_numrows($r) == 1) {
							$primary_game = mysql_fetch_array($r);
							ensure_user_in_game($thisuser, $primary_game['game_id']);

							if ($primary_game['giveaway_status'] == "public_free") {
								$giveaway = new_game_giveaway($primary_game, $user_id, 'initial_purchase', false);
							}
						}
						
						$redirect_url = false;
						
						if ($GLOBALS['pageview_tracking_enabled']) log_user_in($thisuser, $redirect_url, $viewer_id);
						else log_user_in($thisuser, $redirect_url);
						
						if ($_REQUEST['invite_key'] != "") {
							$invite_game = false;
							$success = try_apply_invite_key($thisuser['user_id'], $_REQUEST['invite_key'], $invite_game);
							if ($success) {
								header("Location: /wallet/".$invite_game['url_identifier']);
								die();
							}
						}
						
						$redir_game = fetch_game_from_url();
						if ($redir_game) {
							$header_loc = "/wallet/".$redir_game['url_identifier']."/";
						}
						else $header_loc = "/wallet/";
						
						header("Location: ".$header_loc);
						die();
					}
				}
			}
			else {
				$error_code = 2;
				$message = "Sorry, someone has already registered that email address.";
			}
		}
		else {
			$error_code = 2;
			$message = "Sorry, someone has already registered that alias.";
		}
	}
	else {
		$error_code = 2;
		$message = "Sorry, there was an error creating your new account.";
	}
}
else if ($_REQUEST['do'] == "login") {
	$username = $_POST['username'];
	safe_email($username);
	$password = mysql_real_escape_string($_POST['password']);
	
	$q = "SELECT * FROM users WHERE username='".$username."' AND password='".$password."';";
	$r = run_query($q);
	
	if (mysql_numrows($r) == 0) {
		$message = "Incorrect username or password, please try again.";
		$error_code = 2;
	}
	else if (mysql_numrows($r) == 1) {
		$thisuser = mysql_fetch_array($r);
		$message = "You have been logged in, redirecting...";
		$error_code = 1;
	}
	else {
		$message = "System error, a duplicate user account was found.";
		$error_code = 2;
	}
	
	if ($error_code == 1) {
		$redirect_url = false;
		
		if ($GLOBALS['pageview_tracking_enabled']) log_user_in($thisuser, $redirect_url, $viewer_id);
		else log_user_in($thisuser, $redirect_url);
		
		if ($_REQUEST['invite_key'] != "") {
			$invite_game = false;
			$success = try_apply_invite_key($thisuser['user_id'], $_REQUEST['invite_key'], $invite_game);
			if ($success) {
				header("Location: /wallet/".$invite_game['url_identifier']);
				die();
			}
		}
		if ($redirect_url) {
			header("Location: ".$redirect_url['url']);
		}
		else {
			$redir_game = fetch_game_from_url();
			if ($redir_game) {
				$header_loc = "/wallet/".$redir_game['url_identifier']."/";
			}
			else $header_loc = "/wallet/";
			
			header("Location: ".$header_loc);
		}
		die();
	}
}
else if ($_REQUEST['do'] == "logout" && $thisuser) {
	$q = "UPDATE user_sessions SET logout_time='".time()."' WHERE session_id='".$session['session_id']."';";
	$r = run_query($q);
	
	$q = "UPDATE users SET logged_in=0 WHERE user_id='".$thisuser['user_id']."';";
	$r = run_query($q);
	
	session_regenerate_id();
	
	$thisuser = FALSE;
	$message = "You have been logged out. ";
}

$game = false;

if ($thisuser) {
	if ($_REQUEST['invite_key'] != "") {
		$invite_game = false;
		$success = try_apply_invite_key($thisuser['user_id'], $_REQUEST['invite_key'], $invite_game);
		if ($success) {
			header("Location: /wallet/".$invite_game['url_identifier']);
			die();
		}
	}
	
	$uri_parts = explode("/", $uri);
	$url_identifier = $uri_parts[2];
	
	$q = "SELECT * FROM games WHERE url_identifier='".mysql_real_escape_string($url_identifier)."' AND (game_status IN ('published','running','completed') OR creator_id='".$thisuser['user_id']."');";
	$r = run_query($q);

	if (mysql_numrows($r) > 0) {
		$requested_game = mysql_fetch_array($r);
		
		$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND g.game_id='".$requested_game['game_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$game = mysql_fetch_array($r);
		}
		else if ($requested_game['giveaway_status'] == "public_free" || $requested_game['giveaway_status'] == "public_pay") {
			ensure_user_in_game($thisuser, $requested_game['game_id']);
			$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND g.game_id='".$requested_game['game_id']."';";
			$r = run_query($q);
			$game = mysql_fetch_array($r);
		}
		
		if ($game && $game['payment_required'] == 0) {
			if ($_REQUEST['do'] == "save_address") {
				$bitcoin_address = $_REQUEST['bitcoin_address'];
				safe_text($bitcoin_address);
				
				if ($bitcoin_address != "") {
					$qq = "INSERT INTO external_addresses SET user_id='".$thisuser['user_id']."', currency_id=2, address='".$bitcoin_address."', time_created='".time()."';";
					$rr = run_query($qq);
					$address_id = mysql_insert_id();
					
					$qq = "UPDATE user_games SET bitcoin_address_id='".$address_id."' WHERE user_game_id='".$game['user_game_id']."';";
					$rr = run_query($qq);
					$game['bitcoin_address_id'] = $address_id;
				}
			}
			
			if ($game['bitcoin_address_id'] > 0) {}
			else if ($requested_game['giveaway_status'] == "invite_pay" || $requested_game['giveaway_status'] == "public_pay") {
				$pagetitle = "Join ".$requested_game['name'];
				$nav_tab_selected = "wallet";
				include('includes/html_start.php');
				?>
				<script type="text/javascript">
				$(document).ready(function() {
					$('#bitcoin_address').focus();
				});
				</script>
				<div class="container" style="max-width: 1000px; padding-top: 10px;">
					<form action="/wallet/<?php echo $requested_game['url_identifier']; ?>/" method="post">
						<input type="hidden" name="do" value="save_address" />
						This is a paid game; please specify a Bitcoin address where your winnings should be sent:<br/>
						<div class="row">
							<div class="col-md-8">
								<input class="form-control" id="bitcoin_address" name="bitcoin_address" />
							</div>
						</div>
						<input type="submit" class="btn btn-primary" value="Save Address" />
					</form>
				</div>
				<?php
				include('includes/html_stop.php');
				die();
			}
		}
		else if (!$game && ($requested_game['giveaway_status'] == "invite_free" || $requested_game['giveaway_status'] == "invite_pay")) {
			$pagetitle = "Join ".$requested_game['name'];
			$nav_tab_selected = "wallet";
			include('includes/html_start.php');
			?>
			<div class="container" style="max-width: 1000px; padding-top: 10px;">
				You need an invitation to join this game.
				<?php
				if ($requested_game['invitation_link'] != "") {
					echo " To receive an invitation please follow <a href=\"".$requested_game['invitation_link']."\">this link</a>.";
				}
				?>
			</div>
			<?php
			include('includes/html_stop.php');
			die();
		}
		else if ($game['payment_required'] == 1) {
			$pagetitle = "Join ".$requested_game['name'];
			$nav_tab_selected = "wallet";
			include('includes/html_start.php');
			?>
			<div class="container" style="max-width: 1000px; padding-top: 10px;">
				<?php
				$invite_currency = false;
				$q = "SELECT * FROM currencies WHERE currency_id='".$requested_game['invite_currency']."';";
				$r = run_query($q);

				if (mysql_numrows($r) > 0) {
					$invite_currency = mysql_fetch_array($r);

					$invoice = new_currency_invoice($invite_currency['currency_id'], $requested_game['invite_cost'], $thisuser['user_id'], $requested_game['game_id']);
					?>
					<script type="text/javascript">
					var game_id = '<?php echo $requested_game['game_id']; ?>';

					$(document).ready(function() {
						game_payment_loop_event();
					});
					function game_payment_loop_event() {
						var check_url = "/ajax/check_game_payment.php?game_id="+game_id+"&invoice_id=<?php echo $invoice['invoice_id']; ?>";
						$.ajax({
							url: check_url,
							success: function(result) {
								var result_json = JSON.parse(result);
								if (result_json['payment_required'] == 0) {
									setTimeout("window.location = window.location", 200);
								}
								setTimeout("game_payment_loop_event()", 1000);
							},
							error: function(XMLHttpRequest, textStatus, errorThrown) {
								setTimeout("game_payment_loop_event()", 1000);
							}
						});
					}
					</script>
					
					<h1><?php echo $requested_game['name']; ?></h1>
					
					<div class="row">
						<div class="col-md-7">
							<?php
							if ($thisuser['user_id'] == $requested_game['creator_id'] && $requested_game['game_status'] == "editable") {
								$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
								$r = run_query($q);
								$primary_game = mysql_fetch_array($r);

								echo "You created this game, you can edit it <a href=\"/wallet/".$primary_game['url_identifier']."\">here</a>.<br/>\n";
							}

							if ($GLOBALS['rsa_pub_key'] != "" && $GLOBALS['rsa_keyholder_email'] != "") {
								$q = "SELECT * FROM currency_prices WHERE price_id='".$invoice['pay_price_id']."';";
								$r = run_query($q);
								$invoice_exchange_rate = historical_currency_conversion_rate($invoice['settle_price_id'], $invoice['pay_price_id']);

								$q = "SELECT * FROM currencies WHERE currency_id='".$invoice['pay_currency_id']."';";
								$r = run_query($q);
								$pay_currency = mysql_fetch_array($r);

								$q = "SELECT * FROM currencies WHERE currency_id='".$invoice['settle_currency_id']."';";
								$r = run_query($q);
								$settle_currency = mysql_fetch_array($r);

								$q = "SELECT * FROM invoice_addresses WHERE invoice_address_id='".$invoice['invoice_address_id']."';";
								$r = run_query($q);
								$invoice_address = mysql_fetch_array($r);

								$coins_per_currency = ($requested_game['giveaway_amount']/pow(10,8))/$requested_game['invite_cost'];
								echo "This game has an initial exchange rate of ".format_bignum($coins_per_currency)." ".$requested_game['coin_name_plural']." per ".$invite_currency['short_name'].". ";
								
								$buyin_disp = format_bignum($requested_game['invite_cost']);
								echo "To join this game, you need to make a payment of ".$buyin_disp." ".$invite_currency['short_name'];
								if ($buyin_disp != '1') echo "s";
								
								$receive_disp = format_bignum($requested_game['giveaway_amount']/pow(10,8));
								echo " in exchange for ".$receive_disp." ";
								if ($receive_disp == '1') echo $requested_game['coin_name'];
								else echo $requested_game['coin_name_plural'];
								echo ".<br/>\n";
								
								if ($pay_currency['currency_id'] != $settle_currency['currency_id']) {
									echo "<br/>The exchange rate is currently ".$invoice_exchange_rate." ".$settle_currency['short_name']."s per ".$pay_currency['short_name'].". ";
								}
								echo "<br/>";
								if ($invite_currency['abbreviation'] == "btc") echo "To join, send ".decimal_to_float($invoice['pay_amount'])." to ";
								else echo "Make the ".decimal_to_float($requested_game['invite_cost'])." ".$invite_currency['short_name']." payment in Bitcoins by sending ".decimal_to_float($invoice['pay_amount'])." BTC to ";
								echo "<a target=\"_blank\" href=\"https://blockchain.info/address/".$invoice_address['pub_key']."\">".$invoice_address['pub_key']."</a><br/>\n";
								echo '<center><img style="margin: 10px;" src="/render_qr_code.php?data='.$invoice_address['pub_key'].'" /></center>';
								echo 'You will automatically be redirected when the Bitcoins are received.';
							}
							else {
								echo "Sorry, this site is not configured to accept bitcoin payments. Please contact the site administrator to rectify this problem.";
							}
							?>
						</div>
						<div class="col-md-5">
							<div style="border: 1px solid #ccc; padding: 10px;">
								<?php
								echo game_info_table($requested_game);
								?>
							</div>
						</div>
					</div>
					<?php
				}
				else echo "Error: an invalid buy-in currency was specified for this game.";
				?>
			</div>
			<?php
			include('includes/html_stop.php');
			die();
		}
		else die("Error: this game has an invalid giveaway_status");
	}
	else {
		$pagetitle = $GLOBALS['site_name_short']." - My web wallet";
		$nav_tab_selected = "wallet";
		include('includes/html_start.php');
		?>
		<div class="container" style="max-width: 1000px;"><br/>
			<?php
			$q = "SELECT * FROM games g, user_games ug WHERE g.game_id=ug.game_id AND ug.user_id='".$thisuser['user_id']."' AND (g.creator_id='".$thisuser['user_id']."' OR g.game_status IN ('running','completed','published'));";
			$r = run_query($q);
			
			if (mysql_numrows($r) > 0) {
				echo "Please select a game.<br/>\n";
				while ($user_game = mysql_fetch_array($r)) {
					echo "<a href=\"/wallet/".$user_game['url_identifier']."/\">".$user_game['name']."</a><br/>\n";
				}
			}
			else {
				echo "You haven't joined any games yet.  <a href=\"/\">Click here</a> to see a list of available games.<br/>\n";
			}
			?>
		</div>
		<?php
		include('includes/html_stop.php');
		die();
	}

	$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game['game_id']."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$user_game = mysql_fetch_array($r);
		generate_user_addresses($user_game);
	}
	else {
		ensure_user_in_game($thisuser, $game['game_id']);
		
		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game['game_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$user_game = mysql_fetch_array($r);
			generate_user_addresses($user_game);
		}
	}
}

if ($thisuser && ($_REQUEST['do'] == "save_voting_strategy" || $_REQUEST['do'] == "save_voting_strategy_fees")) {
	$voting_strategy = $_REQUEST['voting_strategy'];
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	$aggregate_threshold = intval($_REQUEST['aggregate_threshold']);
	$api_url = strip_tags(mysql_real_escape_string($_REQUEST['api_url']));
	$by_rank_csv = "";
	
	if ($voting_strategy_id > 0) {
		$q = "SELECT * FROM user_strategies WHERE user_id='".$thisuser['user_id']."' AND strategy_id='".$voting_strategy_id."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$user_strategy = mysql_fetch_array($r);
		}
		else die("Invalid strategy ID");
	}
	else {
		$q = "INSERT INTO user_strategies SET user_id='".$thisuser['user_id']."', game_id='".$game['game_id']."';";
		$r = run_query($q);
		$voting_strategy_id = mysql_insert_id();
		
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$voting_strategy_id."';";
		$r = run_query($q);
		$user_strategy = mysql_fetch_array($r);
	}
	if ($_REQUEST['do'] == "save_voting_strategy_fees") {
		$transaction_fee = floatval($_REQUEST['transaction_fee']);
		if ($transaction_fee == floor($transaction_fee*pow(10,8))/pow(10,8)) {
			$transaction_fee = $transaction_fee*pow(10,8);
			$q = "UPDATE user_strategies SET transaction_fee='".$transaction_fee."' WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$r = run_query($q);
			$user_strategy['transaction_fee'] = $transaction_fee;
			
			$error_code = 1;
			$message = "Great, your transaction fee has been updated!";
		}
		else {
			$error_code = 2;
			$message = "Error: that fee amount is invalid, your changes were not saved.";
		}
	}
	else {
		if (in_array($voting_strategy, array('manual', 'by_rank', 'by_option', 'api', 'by_plan'))) {
			for ($i=1; $i<=$game['num_voting_options']; $i++) {
				if ($_REQUEST['by_rank_'.$i] == "1") $by_rank_csv .= $i.",";
			}
			if ($by_rank_csv != "") $by_rank_csv = substr($by_rank_csv, 0, strlen($by_rank_csv)-1);
			
			$q = "UPDATE user_strategies SET voting_strategy='".$voting_strategy."'";
			if ($aggregate_threshold >= 0 && $aggregate_threshold <= 100) {
				$q .= ", aggregate_threshold='".$aggregate_threshold."'";
			}
			
			$option_pct_sum = 0;
			$option_pct_q = "";
			$option_pct_error = FALSE;
			
			for ($option_id=1; $option_id<=$game['num_voting_options']; $option_id++) {
				$option_pct = intval($_REQUEST['option_pct_'.$option_id]);
				$option_pct_q .= ", option_pct_".$option_id."=".$option_pct;
				$option_pct_sum += $option_pct;
			}
			if ($option_pct_sum == 100) $q .= $option_pct_q;
			else $option_pct_error = TRUE;
			
			$min_votesum_pct = intval($_REQUEST['min_votesum_pct']);
			$max_votesum_pct = intval($_REQUEST['max_votesum_pct']);
			if ($max_votesum_pct > 100) $max_votesum_pct = 100;
			if ($min_votesum_pct < 0) $min_votesum_pct = 0;
			if ($max_votesum_pct < $min_votesum_pct) $max_votesum_pct = $min_votesum_pct;
			
			$min_coins_available = round($_REQUEST['min_coins_available'], 3);
			
			$q .= ", min_coins_available='".$min_coins_available."', max_votesum_pct='".$max_votesum_pct."', min_votesum_pct='".$min_votesum_pct."', by_rank_ranks='".$by_rank_csv."', api_url='".$api_url."'";
			$q .= " WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$r = run_query($q);
			
			if ($option_pct_error && $voting_strategy == "by_option") {
				$q = "UPDATE user_strategies SET voting_strategy='".$user_strategy['voting_strategy']."' WHERE strategy_id='".$user_strategy['strategy_id']."';";
				$r = run_query($q);
				$voting_strategy = $user_strategy['voting_strategy'];
				
				$error_code = 2;
				$message = "Error: voting strategy couldn't be set to \"Vote by option\", the percentages you entered didn't add up to 100%.";
			}
			
			$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE game_id='".$game['game_id']."' AND user_id='".$thisuser['user_id']."';";
			$r = run_query($q);
		}
		
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		save_plan_allocations($user_strategy, $from_round, $to_round);
		
		for ($block=1; $block<$game['round_length']; $block++) {
			$strategy_block = false;
			$q = "SELECT * FROM user_strategy_blocks WHERE strategy_id='".$user_strategy['strategy_id']."' AND block_within_round='".$block."';";
			$r = run_query($q);
			if (mysql_numrows($r) > 0) $strategy_block = mysql_fetch_array($r);
			
			if ($_REQUEST['vote_on_block_'.$block] == "1") {
				if (!$strategy_block) {
					$q = "INSERT INTO user_strategy_blocks SET strategy_id='".$user_strategy['strategy_id']."', block_within_round='".$block."';";
					$r = run_query($q);
				}
			}
			else if ($strategy_block) {
				$q = "DELETE FROM user_strategy_blocks WHERE strategy_block_id='".$strategy_block['strategy_block_id']."';";
				$r = run_query($q);
			}
		}
	}
}

if (!$pagetitle) {
	if ($game) $pagetitle = $game['name']." - Wallet";
	else $pagetitle = "Please log in";
}
$nav_tab_selected = "wallet";
include('includes/html_start.php');

if ($_REQUEST['do'] == "signup" && $error_code == 1) { ?>
	<script type="text/javascript">
	$(document).ready(function() {
		$('#login_username').val('<?php echo $username; ;?>');
		$('#login_password').focus();
	});
	</script>
	<?php
}

$initial_tab = 0;
$account_value = account_coin_value($game, $thisuser);
$immature_balance = immature_balance($game, $thisuser);
$last_block_id = last_block_id($game['game_id']);
$current_round = block_to_round($game, $last_block_id+1);
$block_within_round = block_id_to_round_index($game, $last_block_id+1);
$mature_balance = mature_balance($game, $thisuser);
?>
<div class="container" style="max-width: 1000px;">
	<?php
	if ($message != "") {
		echo '<font style="display: block; margin: 10px 0px;" class="';
		if ($error_code == 1) echo "greentext";
		else echo "redtext";
		echo '">';
		echo $message;
		echo "</font>\n";
	}
	
	if ($game['giveaway_status'] == "invite_free" || $game['giveaway_status'] == "public_free") {
		$qq = "SELECT * FROM game_giveaways WHERE game_id='".$game['game_id']."' AND user_id='".$thisuser['user_id']."' AND type='initial_purchase';";
		$rr = run_query($qq);
		
		if (mysql_numrows($rr) == 0) {
			$giveaway = new_game_giveaway($game, $thisuser['user_id'], 'initial_purchase', false);
		}
	}
	
	if ($thisuser) {
		$user_game = FALSE;
		$user_strategy = FALSE;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game['game_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$user_game = mysql_fetch_array($r);
			
			$q = "SELECT * FROM user_strategies WHERE strategy_id='".$user_game['strategy_id']."';";
			$r = run_query($q);
			
			if (mysql_numrows($r) > 0) {
				$user_strategy = mysql_fetch_array($r);
			}
			else {
				$q = "SELECT * FROM user_strategies WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game['game_id']."';";
				$r = run_query($q);
				
				if (mysql_numrows($r) > 0) {
					$user_strategy = mysql_fetch_array($r);
					$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE user_game_id='".$user_game['user_game_id']."';";
					$r = run_query($q);
				}
				else {
					$q = "DELETE FROM user_games WHERE user_game_id='".$user_game['user_game_id']."';";
					$r = run_query($q);
					die("No strategy!");
				}
			}
		}
		else {
			die("Error: you're not in this game.");
		}
		
		$round_stats = round_voting_stats_all($game, $current_round);
		$total_vote_sum = $round_stats[0];
		$max_vote_sum = $round_stats[1];
		$option_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		?>
		<script type="text/javascript">
		//<![CDATA[
		var current_tab = 0;
		var last_block_id = <?php echo $last_block_id; ?>;
		var last_transaction_id = <?php echo last_transaction_id($game['game_id']); ?>;
		var my_last_transaction_id = <?php
		$my_last_transaction_id = my_last_transaction_id($thisuser['user_id'], $game['game_id']);
			if ($my_last_transaction_id) echo $my_last_transaction_id;
			else echo 'false';
		?>;
		var mature_io_ids_csv = '<?php echo mature_io_ids_csv($thisuser['user_id'], $game); ?>';
		var refresh_in_progress = false;
		var last_refresh_time = 0;
		var payout_weight = '<?php echo $game['payout_weight']; ?>';
		var game_round_length = <?php echo $game['round_length']; ?>;
		var game_loop_index = 1;
		var last_game_loop_index_applied = -1;
		var min_bet_round = <?php
			$bet_round_range = bet_round_range($game);
			echo $bet_round_range[0];
		?>;
		var fee_amount = <?php echo $user_strategy['transaction_fee']; ?>;
		var game_id = <?php echo $game['game_id']; ?>;
		var game_url_identifier = '<?php echo $game['url_identifier']; ?>';
		var coin_name = '<?php echo $game['coin_name']; ?>';
		var coin_name_plural = '<?php echo $game['coin_name_plural']; ?>';
		var num_voting_options = <?php echo $game['num_voting_options']; ?>;
		
		var selected_option_id = false;
		
		var initial_notification_pref = "<?php echo $thisuser['notification_preference']; ?>";
		var initial_notification_email = "<?php echo $thisuser['notification_email']; ?>";
		var started_checking_notification_settings = false;
		var initial_alias_pref = "<?php echo $thisuser['alias_preference']; ?>";
		var initial_alias = "<?php echo $thisuser['alias']; ?>";
		var started_checking_alias_settings = false;
		var performance_history_sections = 1;
		var performance_history_start_round = <?php echo max(1, $current_round-10); ?>;
		var performance_history_loading = false;
		
		var user_logged_in = true;
		
		var refresh_page = "wallet";
		
		var option_has_votingaddr = [];
		var votingaddr_count = 0;
		
		function load_options() {
			options.push(new option(0, false, 'No Winner'));
			<?php
			$q = "SELECT * FROM game_voting_options WHERE game_id='".$game['game_id']."' ORDER BY option_id ASC;";
			$r = run_query($q);
			$option_index = 0;
			while ($option = mysql_fetch_array($r)) {
				echo "\n\t\t\toptions.push(new option(".$option_index.", ".$option['option_id'].", '".$option['name']."'));";
				$votingaddr_id = user_address_id($game['game_id'], $thisuser['user_id'], $option['option_id']);
				if ($votingaddr_id !== false) {
					echo "\n\t\t\toption_has_votingaddr[".$option['option_id']."] = true;";
					echo "\n\t\t\tvotingaddr_count++;";
				}
				$option_index++;
			}
		?>}
		
		<?php if ($game['losable_bets_enabled'] == 1) { ?>
		google.load("visualization", "1", {packages:["corechart"]});
		<?php } ?>
		
		$(document).ready(function() {
			render_tx_fee();
			load_options();
			load_plan_option_events();
			notification_pref_changed();
			alias_pref_changed();
			option_selected(0);
			reload_compose_vote();
			
			$('.datepicker').datepicker();

			loop_event();
			game_loop_event();
			compose_vote_loop();
			
			<?php
			if ($game['losable_bets_enabled'] == 1) { ?>
				bet_loop();
				<?php
			}
			if ($game['game_status'] == 'unstarted') { ?>
				switch_to_game(<?php echo $game['game_id']; ?>, 'fetch');
				<?php
			}
			if ($user_game['show_planned_votes'] == 1) { ?>
				show_intro_message();
				<?php
				$qq = "UPDATE user_games SET show_planned_votes=0 WHERE user_game_id='".$user_game['user_game_id']."';";
				$rr = run_query($qq);
			}
			?>
		});
		
		$(document).keypress(function (e) {
			if (e.which == 13) {
				var selected_option_db_id = $('#rank2option_id_'+selected_option_id).val();
				
				if ($('#vote_amount_'+selected_option_db_id).is(":focus")) {
					confirm_vote(selected_option_db_id);
				}
			}
		});
		//]]>
		</script>
		
		<h1><?php
		echo $game['name'];
		if ($game['game_status'] == "paused" || $game['game_status'] == "unstarted") echo " (Paused)";
		else if ($game['game_status'] == "completed") echo " (Completed)";
		?></h1>
		
		<div style="display: none;" class="modal fade" id="game_invitations">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Game Invitations</h4>
					</div>
					<div class="modal-body" id="game_invitations_inner">
					</div>
				</div>
			</div>
		</div>
		
		<?php
		include("includes/wallet_status.php");
		?>
		<div id="wallet_text_stats">
			<?php
			echo wallet_text_stats($thisuser, $game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance);
			?>
		</div>
		<br/>
		<div style="display: none;" id="vote_details_general">
			<?php echo vote_details_general($mature_balance); ?>
		</div>
		
		<div id="vote_popups"><?php	echo initialize_vote_option_details($game, $option_id2rank, $total_vote_sum, $thisuser['user_id']); ?></div>
		
		<div class="row">
			<div class="col-xs-2 tabcell" id="tabcell0" onclick="tab_clicked(0);">Play&nbsp;Now</div>
			<?php if ($game['losable_bets_enabled'] == 1) { ?>
			<div class="col-xs-2 tabcell" id="tabcell6" onclick="tab_clicked(6);">Gamble</div>
			<?php } ?>
			<div class="col-xs-2 tabcell" id="tabcell1" onclick="tab_clicked(1);">Players</div>
			<div class="col-xs-2 tabcell" id="tabcell2" onclick="tab_clicked(2);">Strategy</div>
			<div class="col-xs-2 tabcell" id="tabcell3" onclick="tab_clicked(3);">Results</div>
			<div class="col-xs-2 tabcell" id="tabcell4" onclick="tab_clicked(4);">Deposit&nbsp;or&nbsp;Withdraw</div>
			<div class="col-xs-2 tabcell" id="tabcell5" onclick="tab_clicked(5);">My&nbsp;Games</div>
		</div>
		<div class="row">
			<div id="tabcontent0" class="tabcontent">
				<?php
				$game_status_explanation = game_status_explanation($game);
				?>
				<div id="game_status_explanation"<?php if ($game_status_explanation == "") echo ' style="display: none;"'; ?>><?php if ($game_status_explanation != "") echo $game_status_explanation; ?></div>

				<?php
				if ($game['buyin_policy'] != "none") { ?>
					<button style="float: right;" class="btn btn-success" onclick="initiate_buyin();">Buy more <?php echo $game['coin_name_plural']; ?></button>
					<?php
				}
				?>
				
				<div class="row">
					<div class="col-md-6">
						<h2>Current votes</h2>
						<div id="my_current_votes">
							<?php
							echo my_votes_table($game, $current_round, $thisuser);
							?>
						</div>
					</div>
				</div>
				
				<div id="vote_popups_disabled"<?php if ($block_within_round != $game['round_length']) echo ' style="display: none;"'; ?>>
					The final block of the round is being mined. Voting is currently disabled.
				</div>
				<div id="select_input_buttons"><?php
					echo select_input_buttons($thisuser['user_id'], $game);
				?></div>
				<div id="compose_vote" style="display: none;">
					<h2>Vote Now</h2>
					<div class="row bordered_row" style="border: 1px solid #bbb;">
						<div class="col-md-6 bordered_cell" id="compose_vote_inputs">
							<b>Inputs:</b><div style="display: inline-block; margin-left: 20px;" id="input_amount_sum"></div><div style="display: inline-block; margin-left: 20px;" id="input_vote_sum"></div><br/>
							<div id="compose_input_start_msg">Add inputs by clicking on the votes above.</div>
						</div>
						<div class="col-md-6 bordered_cell" id="compose_vote_outputs">
							<b>Outputs:</b><div id="display_tx_fee"></div><br/>
							<div id="compose_output_start_msg">Add outputs by clicking on the empires below.</div>
						</div>
					</div>
					<div class="redtext" id="compose_vote_errors" style="margin-top: 5px;"></div>
					<div class="greentext" id="compose_vote_success" style="margin-top: 5px;"></div>
					<button class="btn btn-primary" id="confirm_compose_vote_btn" style="margin-top: 5px; margin-left: 5px;" onclick="confirm_compose_vote();">Submit Voting Transaction</button>
				</div>
				<div id="current_round_table">
					<?php
					echo current_round_table($game, $current_round, $thisuser, true);
					?>
				</div>
				<?php
				if (FALSE && $game['game_type'] == "simulation" && $thisuser['user_id'] == $game['creator_id']) {
					if ($game['block_timing'] == "user_controlled") $toggle_text = "Switch to automatic block timing";
					else $toggle_text = "Switch to user-controlled block timing";
					?>
					<div style="margin-top: 10px; overflow: hidden;">
						<button class="btn btn-primary" onclick="toggle_block_timing();" id="toggle_timing_btn"><?php echo $toggle_text; ?></button>
						<?php if ($game['block_timing'] == "user_controlled") { ?>
						<button style="float: right;" class="btn btn-success" onclick="next_block();" id="next_block_btn">Next Block</button>
						<?php } ?>
					</div>
					<?php
				}
				?>
			</div>
			
			<div class="tabcontent" style="display: none;" id="tabcontent1">
				<?php
				$q = "SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.game_id='".$game['game_id']."';";
				$r = run_query($q);
				echo "<h3>".mysql_numrows($r)." players</h3>\n";
				
				while ($temp_user_game = mysql_fetch_array($r)) {
					echo '<div class="row">';
					echo '<div class="col-sm-4"><a href="" onclick="openChatWindow('.$temp_user_game['user_id'].'); return false;">'.$temp_user_game['username'].'</a></div>';
					
					$networth_disp = format_bignum(account_coin_value($game, $temp_user_game)/pow(10,8));
					echo '<div class="col-sm-4">'.$networth_disp.' ';
					if ($networth_disp == '1') echo $game['coin_name'];
					else echo $game['coin_name_plural'];
					echo '</div>';
					
					echo '</div>';
				}
				?>
			</div>
			
			<div id="tabcontent2" style="display: none;" class="tabcontent">
				<?php
				if ($user_game['bitcoin_address_id'] > 0) {
					$qq = "SELECT * FROM external_addresses WHERE address_id='".$user_game['bitcoin_address_id']."';";
					$rr = run_query($qq);
					$payout_address = mysql_fetch_array($rr);
					echo "Payout address: ".$payout_address['address'];
				}
				else {
					echo "You haven't specified a payout address for this game.";
				}
				?>
				<br/>
				<h2>Transaction Fees</h2>
				<form method="post" action="/wallet/<?php echo $game['url_identifier']; ?>/">
					<input type="hidden" name="do" value="save_voting_strategy_fees" />
					<input type="hidden" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
					Pay fees on every transaction of:<br/>
					<div class="row">
						<div class="col-sm-4"><input class="form-control" name="transaction_fee" value="<?php echo format_bignum($user_strategy['transaction_fee']/pow(10,8)); ?>" placeholder="0.001" /></div>
						<div class="col-sm-4 form-control-static"><?php echo $game['coin_name_plural']; ?></div>
					</div>
					<div class="row">
						<div class="col-sm-3">
							<input class="btn btn-primary" type="submit" value="Save" />
						</div>
					</div>
				</form>
				
				<h2>Choose your voting strategy</h2>
				Please set up a voting strategy so that your votes can be cast even when you're not online to vote.<br/><br/>
				<form method="post" action="/wallet/<?php echo $game['url_identifier']; ?>/">
					<input type="hidden" name="do" value="save_voting_strategy" />
					<input type="hidden" id="voting_strategy_id" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
					
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_manual" name="voting_strategy" value="manual"<?php if ($user_strategy['voting_strategy'] == "manual") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_manual">&nbsp;No&nbsp;auto-strategy</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_manual"> 
								I'll log in and vote in each round.
							</label>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_api" name="voting_strategy" value="api"<?php if ($user_strategy['voting_strategy'] == "api") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_api">&nbsp;Vote&nbsp;by&nbsp;API</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_api">
								Hit a custom URL whenever I have <?php echo $game['coin_name_plural']; ?> available to determine my votes: <input type="text" size="40" placeholder="http://" name="api_url" id="api_url" value="<?php echo $user_strategy['api_url']; ?>" />
							</label><br/>
							Your API access code is <?php echo $thisuser['api_access_code']; ?> <a href="/api/about/">API documentation</a><br/>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_option" name="voting_strategy" value="by_option"<?php if ($user_strategy['voting_strategy'] == "by_option") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_option">&nbsp;Vote&nbsp;by&nbsp;option</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_by_option"> 
								Vote for these options every time. The percentages you enter below must add up to 100.<br/>
								<a href="" onclick="by_option_reset_pct(); return false;">Set all to zero</a> <div style="margin-left: 15px; display: inline-block;" id="option_pct_subtotal">&nbsp;</div>
							</label><br/>
							<?php
							$q = "SELECT * FROM game_voting_options WHERE game_id='".$game['game_id']."' ORDER BY option_id ASC;";
							$r = run_query($q);
							$option_i = 0;
							while ($option = mysql_fetch_array($r)) {
								if ($option_i%4 == 0) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="tel" size="4" name="option_pct_'.$option['option_id'].'" id="option_pct_'.$option['option_id'].'" placeholder="0" value="'.$user_strategy['option_pct_'.$option['option_id']].'" />';
								echo '<label class="plainlabel" for="option_pct_'.$option['option_id'].'">% ';
								echo $option['name']."</label>";
								echo '</div>';
								if ($option_i%4 == 3) echo "</div>\n";
								$option_i++;
							}
							?>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_rank" name="voting_strategy" value="by_rank"<?php if ($user_strategy['voting_strategy'] == "by_rank") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_rank">&nbsp;Vote&nbsp;by&nbsp;rank</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_by_rank">
								Split up my free balance and vote it across options ranked:
							</label><br/>
							<input type="checkbox" id="rank_check_all" onchange="rank_check_all_changed();" /><label class="plainlabel" for="rank_check_all"> All</label><br/>
							<?php
							$by_rank_ranks = explode(",", $user_strategy['by_rank_ranks']);
							
							for ($rank=1; $rank<=16; $rank++) {
								if ($rank%4 == 1) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="checkbox" name="by_rank_'.$rank.'" id="by_rank_'.$rank.'" value="1"';
								if (in_array($rank, $by_rank_ranks)) echo ' checked="checked"';
								echo '><label class="plainlabel" for="by_rank_'.$rank.'"> '.to_ranktext($rank)."</label>";
								echo '</div>';
								if ($rank%4 == 0) echo "</div>\n";
							}
							?>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_plan" name="voting_strategy" value="by_plan"<?php if ($user_strategy['voting_strategy'] == "by_plan") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_plan">&nbsp;Plan&nbsp;my&nbsp;votes</label>
						</div>
						<div class="col-md-10">
							<button class="btn btn-success" onclick="show_planned_votes(); return false;">Edit my planned votes</button>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-12">
							<br/><br/>
							<b>Settings</b><br/>
							These settings apply to "Vote by option" and "Vote by rank" options above.<br/>
							Wait until <input size="4" type="text" name="aggregate_threshold" id="aggregate_threshold" value="<?php echo $user_strategy['aggregate_threshold']; ?>" />% of my coins are available to vote. <br/>
							Only vote in these blocks of the round:<br/>
							<div class="row">
								<div class="col-md-2">
									<input type="checkbox" id="vote_on_block_all" onchange="vote_on_block_all_changed();" /><label class="plainlabel" for="vote_on_block_all">&nbsp;&nbsp;All</label>
								</div>
							</div>
							<div class="row">
								<?php
								for ($block=1; $block<$game['round_length']; $block++) {
									echo '<div class="col-md-2">';
									echo '<input type="checkbox" name="vote_on_block_'.$block.'" id="vote_on_block_'.$block.'" value="1"';
									
									$strategy_block_q = "SELECT * FROM user_strategy_blocks WHERE strategy_id='".$user_strategy['strategy_id']."' AND block_within_round='".$block."';";
									$strategy_block_r = run_query($strategy_block_q);
									if (mysql_numrows($strategy_block_r) > 0) echo ' checked="checked"';
									
									echo '><label class="plainlabel" for="vote_on_block_'.$block.'">&nbsp;&nbsp;';
									echo $block."</label>";
									echo '</div>';
									if ($block%6 == 0) echo '</div><div class="row">';
								}
								?>
							</div>
							Only vote for options which have between <input type="tel" size="4" value="<?php echo $user_strategy['min_votesum_pct']; ?>" name="min_votesum_pct" id="min_votesum_pct" />% and <input type="tel" size="4" value="<?php echo $user_strategy['max_votesum_pct']; ?>" name="max_votesum_pct" id="max_votesum_pct" />% of the current votes.<br/>
							<?php /*
							Maintain <input type="tel" size="6" id="min_coins_available" name="min_coins_available" value="<?php echo round($user_strategy['min_coins_available'], 2); ?>" /> EMP available at all times.  This number of coins will be reserved and won't be voted. */ ?>
						</div>
					</div>
					<br/>
					<input class="btn btn-primary" type="submit" value="Save Voting Strategy" />
				</form>
				
				<?php /*
				<h2>Notifications</h2>
				You can receive notifications whenever your <?php echo $game['coin_name_plural']; ?> are unlocked and ready to vote.<br/>
				<div class="row">
					<div class="col-sm-6">
						<select class="form-control" id="notification_preference" name="notification_preference" onfocus="notification_focused();" onchange="notification_pref_changed();">
							<option <?php if ($thisuser['notification_preference'] == "none") echo 'selected="selected" '; ?>value="none">Don't send me any notifications</option>
							<option <?php if ($thisuser['notification_preference'] == "email") echo 'selected="selected" '; ?>value="email">Send me an email notification when <?php echo $game['coin_name_plural']; ?> become available</option>
						</select>
					</div>
					<div class="col-sm-6">
						<input style="display: none;" class="form-control" type="text" name="notification_email" id="notification_email" onfocus="notification_focused();" placeholder="Enter your email address" value="<?php echo $thisuser['notification_email']; ?>" />
					</div>
				</div>
				<button style="display: none;" id="notification_save_btn" class="btn btn-primary" onclick="save_notification_preferences();">Save Notification Settings</button>
				<br/>
				
				<h2>Privacy Settings</h2>
				You can make your gameplay public by choosing an alias below.<br/>
				<div class="row">
					<div class="col-sm-6">
						<select class="form-control" id="alias_preference" name="alias_preference" onfocus="alias_focused();" onchange="alias_pref_changed();">
							<option <?php if ($thisuser['alias_preference'] == "private") echo 'selected="selected" '; ?>value="private">Keep my identity private</option>
							<option <?php if ($thisuser['alias_preference'] == "public") echo 'selected="selected" '; ?>value="public">Let me choose a public alias</option>
						</select>
					</div>
					<div class="col-sm-6">
						<input style="display: none;" class="form-control" type="text" name="alias" id="alias" onfocus="alias_focused();" placeholder="Please enter an alias" value="<?php echo $thisuser['alias']; ?>" />
					</div>
				</div>
				<button style="display: none;" id="alias_save_btn" class="btn btn-primary" onclick="save_alias_preferences();">Save Privacy Settings</button>
				<br/>
				*/ ?>
			</div>
			<div id="tabcontent3" style="display: none;" class="tabcontent">
				<div id="performance_history">
					<div id="performance_history_0">
						<?php
						echo performance_history($thisuser, $game, max(1, $current_round-10), $current_round-1);
						?>
					</div>
				</div>
				<center>
					<a href="" onclick="show_more_performance_history(); return false;">Show More</a>
				</center>
			</div>
			<div id="tabcontent4" style="display: none;" class="tabcontent">
				<div id="giveaway_div">
					<?php
					$giveaway_avail_msg = 'You\'re eligible for a one time coin giveaway of '.number_format($game['giveaway_amount']/pow(10,8)).' '.$game['coin_name_plural'].'.<br/>';
					$giveaway_avail_msg .= '<button class="btn btn-success" onclick="claim_coin_giveaway();" id="giveaway_btn">Claim '.number_format($game['giveaway_amount']/pow(10,8)).' '.$game['coin_name_plural'].'</button><br/><br/>';
					
					$giveaway_available = check_giveaway_available($game, $thisuser);
					
					if ($giveaway_available) {
						$initial_tab = 4;
						echo $giveaway_avail_msg;
					}
					?>
				</div>
				
				<h1>Withdraw</h1>
				To withdraw coins please enter <?php echo prepend_a_or_an($game['name']); ?> address below.<br/>
				<div class="row">
					<div class="col-md-3">
						Amount:
					</div>
					<div class="col-md-3">
						<input class="form-control" type="tel" placeholder="0.000" id="withdraw_amount" style="text-align: right;" />
					</div>
				</div>
				<div class="row">
					<div class="col-md-3">
						Address:
					</div>
					<div class="col-md-5">
						<input class="form-control" type="text" id="withdraw_address" />
					</div>
				</div>
				<div class="row">
					<div class="col-md-3">
						Vote remainder towards:
					</div>
					<div class="col-md-5">
						<select class="form-control" id="withdraw_remainder_address_id">
							<option value="random">Random</option>
							<?php
							$q = "SELECT * FROM addresses a LEFT JOIN game_voting_options vo ON vo.option_id=a.option_id WHERE vo.game_id='".$game['game_id']."' AND a.user_id='".$thisuser['user_id']."' GROUP BY a.option_id ORDER BY vo.option_id IS NULL ASC, vo.option_id ASC;";
							$r = run_query($q);
							while ($address = mysql_fetch_array($r)) {
								if ($address['name'] == "") $address['name'] = "None";
								echo "<option value=\"".$address['address_id']."\">".$address['name']."</option>\n";
							}
							?>
						</select>
					</div>
				</div>
				<div class="row">
					<div class="col-md-push-3 col-md-2">
						<button class="btn btn-success" id="withdraw_btn" onclick="attempt_withdrawal();">Withdraw</button>
					</div>
				</div>
				
				<h1>Deposit</h1>
				<?php
				$q = "SELECT * FROM addresses a LEFT JOIN game_voting_options gvo ON gvo.option_id=a.option_id WHERE a.game_id='".$game['game_id']."' AND a.user_id='".$thisuser['user_id']."' ORDER BY a.option_id IS NULL DESC, a.option_id ASC;";
				$r = run_query($q);
				?>
				<b>You have <?php echo mysql_numrows($r); ?> addresses.</b><br/>
				<?php
				while ($address = mysql_fetch_array($r)) {
					?>
					<div class="row">
						<div class="col-sm-3">
							<?php
							if ($address['option_id'] > 0) {
								echo option_flag(false, $address['name']);
								echo $address['name'];
							}
							else {
								echo "Default Address";
							}
							?>
						</div>
						<div class="col-sm-1">
							<a target="_blank" href="/explorer/<?php echo $game['url_identifier']; ?>/addresses/<?php echo $address['address']; ?>">Explore</a>
						</div>
						<div class="col-sm-5">
							<input type="text" style="border: 0px; background-color: none; width: 100%; font-family: consolas" onclick="$(this).select();" value="<?php echo $address['address']; ?>" />
						</div>
					</div>
					<?php
				}
				?>
			</div>
			
			<div class="tabcontent" style="display: none;" id="tabcontent5">
				<h4>My Games</h4>
				<?php
				$q = "SELECT *, g.game_id AS game_id FROM games g LEFT JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser['user_id']."' OR g.creator_id='".$thisuser['user_id']."' ORDER BY g.game_id ASC;";
				$r = run_query($q);
				
				while ($user_game = mysql_fetch_array($r)) {
					?>
					<div class="row game_row<?php
					if ($user_game['game_id'] == $game['game_id']) echo  ' boldtext';
					?>">
						<div class="col-sm-1 game_cell">
							<?php echo ucwords($user_game['game_status']); ?>
						</div>
						<div class="col-sm-5 game_cell">
							<a target="_blank" href="/wallet/<?php echo $user_game['url_identifier']; ?>/"><?php echo $user_game['name']; ?></a>
						</div>
						<div class="col-sm-3 game_cell">
							<?php
							echo '<a id="fetch_game_link_'.$user_game['game_id'].'" href="" onclick="switch_to_game('.$user_game['game_id'].', \'fetch\'); return false;">Settings</a>';
							?>
						</div>
						<div class="col-sm-3 game_cell">
							<?php
							$perm_to_invite = user_can_invite_game($user_game, $thisuser['user_id']);
							if ($perm_to_invite) {
								?>
								<a href="" onclick="manage_game_invitations(<?php echo $user_game['game_id']; ?>); return false;">Invitations</a>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
				
				$new_game_perm = new_game_permission($thisuser);
				
				if ($new_game_perm) { ?>
					<br/>
					<button class="btn btn-primary" onclick="switch_to_game(0, 'new'); return false;">Start a new Private Game</button>
					<?php
				}
				?>
			</div>
			
			<?php if ($game['losable_bets_enabled'] == 1) { ?>
				<div class="tabcontent" style="display: none;" id="tabcontent6">
					<div id="my_bets">
						<?php
						echo my_bets($game, $thisuser);
						?>
					</div>
					<h2>Place a Bet</h1>
					<p>
					In the "Play Now" tab you can win coins for free from the coins inflation.  But winnings are small compared to the amount of coins you're staking.  In this tab, you can place traditional-style bets where you'll lose all of your money if you bet on the wrong empire, but you'll win a large amount if you're correct.  These bets are conducted through a decentralized protocol, which means there's no house taking an edge or charging a fee when you bet.
					</p>
					<p>
					To place a bet, you'll burn your <?php echo $game['coin_name_plural']; ?> by sending them to an unredeemable address. Once the outcome of the voting round is determined, the <?php echo $game['name']; ?> protocol will check to see if you bet correctly and if so, new coins will be created and sent to your wallet.  These are pari-mutuel style bets in which your payout multiplier may continue changing until the betting period is over.  You can bet on the outcome of a round until the fifth block of the round.  Bets confirmed in the sixth block of a round or later are considered invalid and will be refunded back to the bettor, but with a 10% fee applied.  To place a bet, please select a round which you'd like to bet for and select one or more empires that you expect to win the round.
					</p>
					<div class="row">
						<div class="col-md-3">
							Select a round:
						</div>
						<div class="col-md-6">
							<div id="select_bet_round">
								<?php
								echo select_bet_round($game, $current_round);
								?>
							</div>
						</div>
					</div>
					<div class="row" id="bet_charts" style="display: none;">
						<div class="col-md-4">
							<div id="round_odds_chart" style="height: 320px;"></div>
						</div>
						<div class="col-md-8" id="round_odds_stats" style="min-height: 320px; padding-top: 8px;"></div>
					</div>
					<div class="row">
						<div class="col-md-3">
							Amount to bet:
						</div>
						<div class="col-md-6">
							<input class="form-control" type="tel" placeholder="0.000" id="bet_amount" style="text-align: right;" />
						</div>
						<div class="col-md-2">
							coins
						</div>
					</div>
					<div class="row">
						<div class="col-md-3">
							Add an outcome:
						</div>
						<div class="col-md-6">
							<select class="form-control" id="bet_option" onchange="add_bet_option();">
								<option value="">-- Please Select --</option>
								<option value="0">No winner</option>
								<?php
								$q = "SELECT * FROM options ORDER BY name ASC;";
								$r = run_query($q);
								while ($option = mysql_fetch_array($r)) {
									echo "<option value=\"".$option['option_id']."\">".$option['name']." wins</option>\n";
								}
								?>
							</select>
						</div>
						<div class="col-md-2">
							<a href="" onclick="add_all_bet_options(); return false;">Add all</a>
						</div>
					</div>
					<div class="row">
						<div class="col-md-push-3 col-md-9" id="option_bet_disp"></div>
					</div>
					<div class="row">
						<div class="col-md-push-3 col-md-6">
							<button class="btn btn-primary" onclick="place_bet();" id="bet_confirm_btn">Place Bet</button>
						</div>
					</div>
					<br/>
				</div>
			<?php } ?>
		</div>
		
		<div style="display: none;" class="modal fade" id="intro_message">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">New message from <?php echo $GLOBALS['coin_brand_name']; ?></h4>
					</div>
					<div class="modal-body">
						<p>
							Hi <?php echo $thisuser['username']; ?>, thanks for joining <?php echo $game['name']; ?>! 
							This game lasts for <?php echo $game['final_round']; ?> voting rounds.  
							At the end of each round, the supply of <?php echo $game['coin_name_plural']; ?> 
							<?php
							if ($game['inflation'] == "exponential") echo 'inflates by '.(100*$game['exponential_inflation_rate']).'%';
							else echo 'increases by '.format_coins($game['pos_reward']/pow(10,8));
							?>. These new <?php echo $game['coin_name_plural']; ?> are split up and given to everyone who voted for the winner.
						</p>
						<p>
							To make sure you don't miss out on votes, a random voting strategy has been applied to your account.  These random votes will only be applied at the end of the voting round.  You can still cast your votes manually by voting before the end of the round.
						</p>
						<p>
							You are currently on a planned voting strategy but several other strategy types are available.  You can change your voting strategy at any time by clicking on the "Strategy" tab.  Please click below to check the random votes that you have been assigned.<br/>
						</p>
						<p>
							<button class="btn btn-primary" onclick="$('#intro_message').modal('hide'); show_planned_votes();">Continue</button>
						</p>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="planned_votes">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">My Planned Votes</h4>
					</div>
					<div class="modal-body">
						<?php
						if ($game['final_round'] > 0) {
							$plan_start_round = 1;
							$plan_stop_round = $game['final_round'];
						}
						else {
							$plan_start_round = $current_round;
							$plan_stop_round = $plan_start_round+10;
						}
						?>
						
						<button id="scramble_plan_btn" class="btn btn-warning" onclick="scramble_strategy(<?php echo $user_strategy['strategy_id']; ?>); return false;">Randomize my Votes</button>
						
						<font style="margin-left: 25px;">Load rounds: </font><input type="text" size="4" id="select_from_round" value="<?php echo $plan_start_round; ?>" /> to <input type="text" size="4" id="select_to_round" value="<?php echo $plan_stop_round; ?>" /> <button class="btn btn-default btn-sm" onclick="load_plan_rounds(); return false;">Go</button>
						
						<br/>
						<div id="plan_rows" style="margin: 10px 0px; max-height: 450px; overflow-y: scroll; border: 1px solid #bbb; padding: 0px 10px;">
							<?php
							echo plan_options_html($game, $plan_start_round, $plan_stop_round);
							?>
						</div>
						
						<button id="save_plan_btn" class="btn btn-success" onclick="save_plan_allocations(); return false;">Save Changes</button>
						<button style="float: right;" type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						
						<div id="plan_rows_js"></div>
						
						<input type="hidden" id="from_round" name="from_round" value="<?php echo $plan_start_round; ?>" />
						<input type="hidden" id="to_round" name="to_round" value="<?php echo ($plan_stop_round); ?>" />
						
						<script type="text/javascript">
						var plan_option_max_points = 5;
						var plan_option_increment = 1;
						var plan_options = new Array();
						var plan_option_row_sums = new Array();
						var round_id2row_id = new Array();
						
						$(document).ready(function() {
							initialize_plan_options(<?php echo $plan_start_round; ?>, <?php echo $plan_stop_round; ?>);
							<?php
							$q = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$plan_start_round." AND round_id <= ".$plan_stop_round.";";
							$r = run_query($q);
							while ($allocation = mysql_fetch_array($r)) {
								echo "load_plan_option(".$allocation['round_id'].", option_id2option_index[".$allocation['option_id']."], ".$allocation['points'].");\n";
							}
							?>
						});
						</script>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="buyin_modal">
			<div class="modal-dialog">
				<div class="modal-content" id="buyin_modal_content">
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="game_form">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="game_form_name_disp"></h4>
					</div>
					<div class="modal-body">
						<form onsubmit="save_game();">
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Game title:
								</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_name" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Each coin is called a(n):
								</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_coin_name" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Coins (plural) are called:
								</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_coin_name_plural" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Currency abbreviation:
								</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_coin_abbreviation" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Game status:
								</div>
								<div class="col-sm-6">
									<div id="game_form_game_status" class="form-control-static"></div>
									
									<button id="start_game_btn" class="btn btn-info" style="display: none;" onclick="switch_to_game(editing_game_id, 'running'); return false;">Start Game</button>
									<button id="pause_game_btn" class="btn btn-info" style="display: none;" onclick="switch_to_game(editing_game_id, 'paused'); return false;">Pause Game</button>

									<button id="delete_game_btn" class="btn btn-danger" style="display: none;" onclick="switch_to_game(editing_game_id, 'delete'); return false;">Delete Game</button>
									<button id="reset_game_btn" class="btn btn-warning" style="display: none;" onclick="switch_to_game(editing_game_id, 'reset'); return false;">Reset Game</button>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Game ends?
								</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_has_final_round" onchange="game_form_final_round_changed();">
										<option value="0">No</option>
										<option value="1">Yes</option>
									</select>
								</div>
							</div>
							<div id="game_form_final_round_disp">
								<div class="row">
									<div class="col-sm-6 form-control-static">
										Number of rounds in the game:
									</div>
									<div class="col-sm-6">
										<input type="text" class="form-control" id="game_form_final_round" placeholder="0" />
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Start the game:
								</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_start_condition" onchange="game_form_start_condition_changed();">
										<option value="fixed_time">At a particular time</option>
										<option value="players_joined">When enough people join</option>
									</select>
								</div>
							</div>
							<div id="game_form_start_condition_fixed_time">
								<div class="row">
									<div class="col-sm-6 form-control-static">
										When should the game start?
									</div>
									<div class="col-sm-3">
										<input type="text" class="form-control datepicker" id="game_form_start_date" />
									</div>
									<div class="col-sm-3">
										<select class="form-control" id="game_form_start_time">
											<?php
											for ($hour=0; $hour<=23; $hour++) {
												$am_pm = "am";
												if ($hour > 12) {
													$am_pm = "pm";
												}
												if ($hour == 11) $am_pm = "noon";
												else if ($hour == 23) $am_pm = "midnight";
												echo '<option value="'.$hour.'">'.(($hour%12)+1).':00 '.$am_pm.'</option>'."\n";
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div id="game_form_start_condition_players_joined">
								<div class="row">
									<div class="col-sm-6 form-control-static">
										Start when how many players have joined?
									</div>
									<div class="col-sm-3">
										<input type="text" class="form-control" id="game_form_start_condition_players" style="text-align: right;" />
									</div>
									<div class="col-sm-3 form-control-static">
										players
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Blocks per round:
								</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_round_length" />
								</div>
								<div class="col-sm-3 form-control-static">
									blocks
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Seconds per block:</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_seconds_per_block" />
								</div>
								<div class="col-sm-3 form-control-static">
									seconds
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Definition of a vote:</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_payout_weight">
										<option value="coin">Coins staked</option>
										<option value="coin_block">Coins over time. 1 vote per block</option>
										<option value="coin_round">Coins over time. 1 vote per round</option>
									</select>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Transaction lock time:</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_maturity" />
								</div>
								<div class="col-sm-3 form-control-static">
									blocks
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Give out coins to each player?</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_giveaway_status" onchange="game_form_giveaway_status_changed();">
										<option value="public_free">Free coins for everyone</option>
										<option value="invite_free">Free coins with invite</option>
										<option value="public_pay">Pay to join, no invitation required</option>
										<option value="invite_pay">Pay to accept an invitation</option>
									</select>
								</div>
							</div>
							<div id="game_form_giveaway_status_pay">
								<div class="row">
									<div class="col-sm-6 form-control-static">Cost per invitation:</div>
									<div class="col-sm-3">
										<input type="text" class="form-control" id="game_form_invite_cost" style="text-align: right" />
									</div>
									<div class="col-sm-3">
										<select class="form-control" id="game_form_invite_currency">
											<?php
											$q = "SELECT * FROM currencies ORDER BY currency_id ASC;";
											$r = run_query($q);
											while ($currency = mysql_fetch_array($r)) {
												echo '<option value="'.$currency['currency_id'].'">'.ucwords($currency['short_name']).'s</option>'."\n";
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<?php /*<div class="row">
								<div class="col-sm-6 form-control-static">Number of players:</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_num_players_status">
										<option value="unlimited">Unlimited</option>
										<option value="capped">Capped at some number</option>
										<option value="exactly">Exactly some number</option>
									</select>
								</div>
							</div> */ ?>
							<div class="row">
								<div class="col-sm-6 form-control-static">Coins given out per invitation:</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_giveaway_amount" />
								</div>
								<div class="col-sm-3 form-control-static">
									coins
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Buy-in policy:</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_buyin_policy" onchange="game_form_buyin_policy_changed();">
										<option value="none">No additional buy-ins</option>
										<option value="unlimited">Unlimited buy-ins</option>
										<option value="per_user_cap">Limit buy-ins per user</option>
										<option value="game_cap">Buy-in cap for the whole game</option>
										<option value="game_and_user_cap">Game-wide cap &amp; user cap</option>
									</select>
								</div>
							</div>
							<div id="game_form_per_user_buyin_cap_disp">
								<div class="row">
									<div class="col-sm-6 form-control-static">Buy-in limit per user:</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_per_user_buyin_cap" />
									</div>
									<div class="col-sm-3 form-control-static">
										invite currency units
									</div>
								</div>
							</div>
							<div id="game_form_game_buyin_cap_disp">
								<div class="row">
									<div class="col-sm-6 form-control-static">Game-wide buy-in cap:</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_game_buyin_cap" />
									</div>
									<div class="col-sm-3 form-control-static">
										invite currency units
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Inflation:</div>
								<div class="col-sm-3">
									<select id="game_form_inflation" class="form-control" onchange="game_form_inflation_changed();">
										<option value="linear">Linear</option>
										<option value="exponential">Exponential</option>
									</select>
								</div>
							</div>
							<div id="game_form_inflation_exponential">
								<div class="row">
									<div class="col-sm-6 form-control-static">Inflation per round:</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_exponential_inflation_rate" />
									</div>
									<div class="col-sm-3 form-control-static">
										%
									</div>
								</div>
								<div class="row">
									<div class="col-sm-6 form-control-static">Percentage given to miners</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_exponential_inflation_minershare" />
									</div>
									<div class="col-sm-3 form-control-static">
										%
									</div>
								</div>
							</div>
							<div id="game_form_inflation_linear">
								<div class="row">
									<div class="col-sm-6 form-control-static">Voting payout reward:</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_pos_reward" />
									</div>
									<div class="col-sm-3 form-control-static">
										coins
									</div>
								</div>
								<div class="row">
									<div class="col-sm-6 form-control-static">Reward for mining a block:</div>
									<div class="col-sm-3">
										<input class="form-control" style="text-align: right;" type="text" id="game_form_pow_reward" />
									</div>
									<div class="col-sm-3 form-control-static">
										coins
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Winning percentage limit:</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_max_voting_fraction" />
								</div>
								<div class="col-sm-3 form-control-static">
									%
								</div>
							</div>
							
							<div style="height: 10px;"></div>
							<button style="float: right;" type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							
							<button id="save_game_btn" type="button" class="btn btn-success" onclick="save_game('save');">Save Settings</button>
							
							<button id="publish_game_btn" type="button" class="btn btn-primary" onclick="save_game('publish');">Save &amp; Publish</button>
							
							<button id="invitations_game_btn" type="button" class="btn btn-info" data-dismiss="modal" onclick="manage_game_invitations(editing_game_id);">Invite People</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		
		<br/><br/>
		
		<script type="text/javascript">
		$(document).ready(function() {
			tab_clicked(<?php echo $initial_tab; ?>);
		});
		</script>
		<?php
	}
	else {
		include("includes/html_login.php");
	}
	?>
</div>
<?php

include('includes/html_stop.php');
?>