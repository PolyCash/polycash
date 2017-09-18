<?php
include('includes/connect.php');
include('includes/get_session.php');

if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$error_code = false;
$message = "";

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = "";

if ($_REQUEST['action'] == "logout" && $thisuser) {
	$q = "UPDATE user_sessions SET logout_time='".time()."' WHERE session_id='".$session['session_id']."';";
	$r = $app->run_query($q);
	
	$q = "UPDATE users SET logged_in=0 WHERE user_id='".$thisuser->db_user['user_id']."';";
	$r = $app->run_query($q);
	
	session_regenerate_id();
	
	$thisuser = FALSE;
	$message = "You have been logged out. ";
}

$game = false;

if ($thisuser) {
	if (!empty($_REQUEST['invite_key'])) {
		$invite_game = false;
		$success = $app->try_apply_invite_key($thisuser->db_user['user_id'], $_REQUEST['invite_key'], $invite_game);
		if ($success) {
			header("Location: /wallet/".$invite_game['url_identifier']);
			die();
		}
	}
	
	$uri_parts = explode("/", $uri);
	$url_identifier = $uri_parts[2];
	
	$q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($url_identifier)." AND (game_status IN ('published','running','completed') OR creator_id='".$thisuser->db_user['user_id']."');";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$requested_game = $r->fetch();
		
		$blockchain = new Blockchain($app, $requested_game['blockchain_id']);
		$game = new Game($blockchain, $requested_game['game_id']);
		
		if ($_REQUEST['action'] == "change_user_game") {
			$user_game_id = $_REQUEST['user_game_id'];
			
			if ($user_game_id == "new") {
				$select_user_game = $thisuser->ensure_user_in_game($game, true);
				$thisuser->set_selected_user_game($game, $select_user_game['user_game_id']);
			}
			else {
				$user_game_id = (int) $user_game_id;
				
				$q = "SELECT * FROM user_games WHERE user_game_id='".$user_game_id."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$select_user_game = $r->fetch();
					
					if ($select_user_game['user_id'] == $thisuser->db_user['user_id']) {
						$thisuser->set_selected_user_game($game, $select_user_game['user_game_id']);
					}
				}
			}
			header("Location: /wallet/".$game->db_game['url_identifier']."/");
			die();
		}
		
		$is_creator = false;
		if ($requested_game['creator_id'] == $thisuser->db_user['user_id']) $is_creator = true;
		
		$allow_public = false;
		if ($requested_game['giveaway_status'] == "public_free" || $requested_game['giveaway_status'] == "public_pay") $allow_public = true;
		
		$q = "SELECT * FROM games g JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND g.game_id='".$requested_game['game_id']."' ORDER BY ug.selected DESC;";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$user_game = $r->fetch();
		}
		else if ($is_creator || $allow_public) {
			$user_game = $thisuser->ensure_user_in_game($game, true);
		}
		
		if ($user_game && $user_game['payment_required'] == 0) {
			if ($_REQUEST['action'] == "save_address") {
				$payout_address = $app->strong_strip_tags($_REQUEST['payout_address']);
				
				if ($payout_address != "") {
					$base_currency = $app->fetch_currency_by_id($game->blockchain->currency_id());
					$qq = "INSERT INTO external_addresses SET user_id='".$thisuser->db_user['user_id']."', currency_id=".$base_currency['currency_id'].", address=".$app->quote_escape($payout_address).", time_created='".time()."';";
					$rr = $app->run_query($qq);
					$address_id = $app->last_insert_id();
					
					$qq = "UPDATE user_games SET payout_address_id='".$address_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
					$rr = $app->run_query($qq);
					$user_game['payout_address_id'] = $address_id;
				}
			}
			
			/*if ($user_game['payout_address_id'] > 0) {}
			else if ($requested_game['giveaway_status'] == "invite_pay" || $requested_game['giveaway_status'] == "public_pay" || $game->escrow_value(false) > 0) {
				$pagetitle = "Join ".$requested_game['name'];
				$nav_tab_selected = "wallet";
				include('includes/html_start.php');
				?>
				<script type="text/javascript">
				$(document).ready(function() {
					$('#payout_address').focus();
				});
				</script>
				<div class="container" style="max-width: 1000px; padding-top: 10px;">
					<form action="/wallet/<?php echo $requested_game['url_identifier']; ?>/" method="post">
						<input type="hidden" name="action" value="save_address" />
						Please specify a <?php echo $game->blockchain->db_blockchain['coin_name']; ?> address where your winnings should be sent:<br/>
						<div class="row" style="margin-top: 10px;">
							<div class="col-md-8">
								<input class="form-control" id="payout_address" name="payout_address" />
							</div>
						</div>
						<input type="submit" class="btn btn-primary" value="Save Address" style="margin-top: 10px;" />
					</form>
				</div>
				<?php
				include('includes/html_stop.php');
				die();
			}*/
		}
		else if (!$user_game && ($requested_game['giveaway_status'] == "invite_free" || $requested_game['giveaway_status'] == "invite_pay")) {
			if ($requested_game['public_unclaimed_game_invitations'] == 1) {
				$q = "SELECT * FROM game_invitations WHERE game_id='".$requested_game['game_id']."' AND used=0 AND used_user_id IS NULL ORDER BY invitation_id DESC LIMIT 1;";
				$r = $app->run_query($q);
				if ($r->rowCount() > 0) {
					$invitation = $r->fetch();
					$invite_game = false;
					$app->try_apply_invite_key($thisuser->db_user['user_id'], $invitation['invitation_key'], $invite_game);
					header("Location: /wallet/".$invite_game->db_game['url_identifier']);
					die();
				}
			}
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
		else if ($user_game['payment_required'] == 1) {
			$pagetitle = "Join ".$requested_game['name'];
			$nav_tab_selected = "wallet";
			include('includes/html_start.php');
			?>
			<div class="container" style="max-width: 1000px; padding-top: 10px;">
				<?php
				$invite_currency = $app->fetch_currency_by_id($requested_game['invite_currency']);
				
				if ($invite_currency) {
					if ($user_game['current_invoice_id'] > 0) {
						$invoice = $app->fetch_currency_invoice_by_id($user_game['current_invoice_id']);
					}
					else {
						$invoice = $app->new_currency_invoice($invite_currency, $requested_game['invite_cost'], $thisuser, $user_game, 'join_buyin');
						
						$q = "UPDATE user_games SET current_invoice_id='".$invoice['invoice_id']."' WHERE user_game_id='".$user_game['user_game_id']."';";
						$r = $app->run_query($q);
					}
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
							if ($thisuser->db_user['user_id'] == $requested_game['creator_id'] && $requested_game['game_status'] == "editable") {
								$blockchain = new Blockchain($app, $requested_game['blockchain_id']);
								$requested_game_obj = new Game($blockchain, $requested_game['game_id']);
								
								echo "You created this game, you can edit it <a href=\"/wallet/".$requested_game['url_identifier']."\">here</a>.<br/>\n";
							}

							if ($GLOBALS['rsa_pub_key'] != "" && $GLOBALS['rsa_keyholder_email'] != "") {
								$q = "SELECT * FROM currency_prices WHERE price_id='".$invoice['pay_price_id']."';";
								$r = $app->run_query($q);
								$invoice_exchange_rate = $app->historical_currency_conversion_rate($invoice['settle_price_id'], $invoice['pay_price_id']);

								$pay_currency = $app->fetch_currency_by_id($invoice['pay_currency_id']);
								$currency_address = $app->fetch_currency_address_by_id($invoice['currency_address_id']);

								$coins_per_currency = ($requested_game['giveaway_amount']/pow(10,8))/$requested_game['invite_cost'];
								echo "This game has an initial exchange rate of ".$app->format_bignum($coins_per_currency)." ".$requested_game['coin_name_plural']." per ".$invite_currency['short_name'].". ";
								
								$buyin_disp = $app->format_bignum($requested_game['invite_cost']);
								echo "To join this game, you need to make a payment of ".$buyin_disp." ".$invite_currency['short_name'];
								if ($buyin_disp != '1') echo "s";
								
								$receive_disp = $app->format_bignum($requested_game['giveaway_amount']/pow(10,8));
								echo " in exchange for ".$receive_disp." ";
								if ($receive_disp == '1') echo $requested_game['coin_name'];
								else echo $requested_game['coin_name_plural'];
								echo ".<br/>\n";
								
								echo "<br/>\n";
								
								echo "To join, send ".$app->decimal_to_float($invoice['pay_amount'])." ".$pay_currency['abbreviation']." to ";
								echo "<a target=\"_blank\" href=\"https://blockchain.info/address/".$currency_address['pub_key']."\">".$currency_address['pub_key']."</a><br/>\n";
								echo '<center><img style="margin: 10px;" src="/render_qr_code.php?data='.$currency_address['pub_key'].'" /></center>';
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
								echo $app->game_info_table($requested_game);
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
			$q = "SELECT * FROM games g, user_games ug WHERE g.game_id=ug.game_id AND ug.user_id='".$thisuser->db_user['user_id']."' AND (g.creator_id='".$thisuser->db_user['user_id']."' OR g.game_status IN ('running','completed','published')) GROUP BY ug.game_id;";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				echo "Please select a game.<br/>\n";
				while ($user_game = $r->fetch()) {
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

	$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' AND ug.game_id='".$game->db_game['game_id']."' ORDER BY ug.selected DESC;";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		$thisuser->generate_user_addresses($game, $user_game);
	}
	else {
		$user_game = $thisuser->ensure_user_in_game($game, true);
		
		$thisuser->generate_user_addresses($game, $user_game);
	}
}

if ($thisuser && ($_REQUEST['action'] == "save_voting_strategy" || $_REQUEST['action'] == "save_voting_strategy_fees")) {
	$voting_strategy = $_REQUEST['voting_strategy'];
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	$aggregate_threshold = intval($_REQUEST['aggregate_threshold']);
	$api_url = $app->quote_escape($app->strong_strip_tags($_REQUEST['api_url']));
	$by_rank_csv = "";
	
	if ($voting_strategy_id > 0) {
		$q = "SELECT * FROM user_strategies WHERE user_id='".$thisuser->db_user['user_id']."' AND strategy_id='".$voting_strategy_id."';";
		$r = $app->run_query($q);
		if ($r->rowCount() == 1) {
			$user_strategy = $r->fetch();
		}
		else die("Invalid strategy ID");
	}
	else {
		$q = "INSERT INTO user_strategies SET user_id='".$thisuser->db_user['user_id']."', game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		$voting_strategy_id = $app->last_insert_id();
		
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$voting_strategy_id."';";
		$r = $app->run_query($q);
		$user_strategy = $r->fetch();
	}
	if ($_REQUEST['action'] == "save_voting_strategy_fees") {
		$transaction_fee = floatval($_REQUEST['transaction_fee']);
		if ($transaction_fee == floor($transaction_fee*pow(10,8))/pow(10,8)) {
			$transaction_fee = $transaction_fee*pow(10,8);
			$q = "UPDATE user_strategies SET transaction_fee='".$transaction_fee."' WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$r = $app->run_query($q);
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
		if (in_array($voting_strategy, array('manual', 'api', 'by_plan', 'by_entity'))) {
			/*for ($i=1; $i<=$game->db_game['num_voting_options']; $i++) {
				if ($_REQUEST['by_rank_'.$i] == "1") $by_rank_csv .= $i.",";
			}
			if ($by_rank_csv != "") $by_rank_csv = substr($by_rank_csv, 0, strlen($by_rank_csv)-1);*/
			
			$q = "UPDATE user_strategies SET voting_strategy='".$voting_strategy."'";
			if ($aggregate_threshold >= 0 && $aggregate_threshold <= 100) {
				$q .= ", aggregate_threshold='".$aggregate_threshold."'";
			}
			
			$min_votesum_pct = intval($_REQUEST['min_votesum_pct']);
			$max_votesum_pct = intval($_REQUEST['max_votesum_pct']);
			if ($max_votesum_pct > 100) $max_votesum_pct = 100;
			if ($min_votesum_pct < 0) $min_votesum_pct = 0;
			if ($max_votesum_pct < $min_votesum_pct) $max_votesum_pct = $min_votesum_pct;
			
			$q .= ", max_votesum_pct='".$max_votesum_pct."', min_votesum_pct='".$min_votesum_pct."', api_url=".$api_url;
			$q .= " WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$r = $app->run_query($q);
			
			$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE game_id='".$game->db_game['game_id']."' AND user_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
		}
		
		$entity_pct_sum = 0;
		$entity_pct_error = FALSE;
		
		$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$game->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
		$rr = $app->run_query($qq);
		while ($entity = $rr->fetch()) {
			$entity_pct = intval($_REQUEST['entity_pct_'.$entity['entity_id']]);
			$entity_pct_sum += $entity_pct;
		}
		
		if ($entity_pct_sum == 100) {
			$qq = "DELETE FROM user_strategy_entities WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$rr = $app->run_query($qq);
			
			$qq = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$game->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
			$rr = $app->run_query($qq);
			
			while ($entity = $rr->fetch()) {
				$entity_pct = intval($_REQUEST['entity_pct_'.$entity['entity_id']]);
				if ($entity_pct > 0) {
					$qqq = "INSERT INTO user_strategy_entities SET strategy_id='".$user_strategy['strategy_id']."', entity_id='".$entity['entity_id']."', pct_points='".$entity_pct."';";
					$rrr = $app->run_query($qqq);
				}
			}
		}
		else {
			if ($voting_strategy == "by_entity") {
				$error_code = 2;
				$message = "Error: the percentages that you entered did not add up to 100, your changes were discarded.";
			}
		}
		
		for ($block=1; $block<$game->db_game['round_length']; $block++) {
			$strategy_block = false;
			$q = "SELECT * FROM user_strategy_blocks WHERE strategy_id='".$user_strategy['strategy_id']."' AND block_within_round='".$block."';";
			$r = $app->run_query($q);
			if ($r->rowCount() > 0) $strategy_block = $r->fetch();
			
			if ($_REQUEST['vote_on_block_'.$block] == "1") {
				if (!$strategy_block) {
					$q = "INSERT INTO user_strategy_blocks SET strategy_id='".$user_strategy['strategy_id']."', block_within_round='".$block."';";
					$r = $app->run_query($q);
				}
			}
			else if ($strategy_block) {
				$q = "DELETE FROM user_strategy_blocks WHERE strategy_block_id='".$strategy_block['strategy_block_id']."';";
				$r = $app->run_query($q);
			}
		}
	}
}

if (empty($pagetitle)) {
	if ($game) $pagetitle = $game->db_game['name']." - Wallet";
	else $pagetitle = "Please log in";
}
$nav_tab_selected = "wallet";
include('includes/html_start.php');

if ($_REQUEST['action'] == "signup" && $error_code == 1) { ?>
	<script type="text/javascript">
	$(document).ready(function() {
		$('#login_username').val('<?php echo $username; ;?>');
		$('#login_password').focus();
	});
	</script>
	<?php
}

$initial_tab = 0;
if ($thisuser && $game) {
	$account_value = $thisuser->account_coin_value($game, $user_game);
	$immature_balance = $thisuser->immature_balance($game, $user_game);
	$mature_balance = $thisuser->mature_balance($game, $user_game);
	
	$blockchain_last_block_id = $game->blockchain->last_block_id();
	$blockchain_current_round = $game->block_to_round($blockchain_last_block_id+1);
	$blockchain_block_within_round = $game->block_id_to_round_index($blockchain_last_block_id+1);
	$blockchain_last_block = $game->blockchain->fetch_block_by_id($blockchain_last_block_id);
	
	$last_block_id = $game->last_block_id();
	$current_round = $game->block_to_round($last_block_id+1);
	$block_within_round = $game->block_id_to_round_index($last_block_id+1);
}
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
	
	if ($thisuser) {
		$user_game = FALSE;
		$user_strategy = FALSE;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."' ORDER BY selected DESC;";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$user_game = $r->fetch();
			
			$user_strategy = $game->fetch_user_strategy($user_game);
		}
		else {
			die("Error: you're not in this game.");
		}
		
		$my_last_transaction_id = $thisuser->my_last_transaction_id($game->db_game['game_id']);
		$performance_history_rounds_per_section = 10;
		?>
		<script type="text/javascript">
		//<![CDATA[
		var current_tab = 0;
		var initial_notification_pref = "<?php echo $user_game['notification_preference']; ?>";
		var initial_notification_email = "<?php echo $thisuser->db_user['notification_email']; ?>";
		var started_checking_notification_settings = false;
		var initial_alias_pref = "<?php echo $thisuser->db_user['alias_preference']; ?>";
		var initial_alias = "<?php echo $thisuser->db_user['alias']; ?>";
		var started_checking_alias_settings = false;
		var performance_history_sections = 1;
		var performance_history_rounds_per_section = <?php echo $performance_history_rounds_per_section; ?>;
		var performance_history_from_round = <?php echo max(1, $current_round-$performance_history_rounds_per_section-1); ?>;
		var performance_history_initial_load_round = <?php echo $current_round-1; ?>;
		var performance_history_loading = false;
		
		var user_logged_in = true;
		
		var games = new Array();
		games.push(new Game(<?php
			echo $game->db_game['game_id'];
			echo ', false';
			echo ', false, ';
			if ($my_last_transaction_id) echo $my_last_transaction_id;
			else echo 'false';
			echo ', "'.$game->mature_io_ids_csv($user_game).'"';
			echo ', "'.$game->db_game['payout_weight'].'"';
			echo ', '.$game->db_game['round_length'];
			echo ', '.$user_strategy['transaction_fee'];
			echo ', "'.$game->db_game['url_identifier'].'"';
			echo ', "'.$game->db_game['coin_name'].'"';
			echo ', "'.$game->db_game['coin_name_plural'].'"';
			echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
			echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
			echo ', "wallet", "'.$game->event_ids().'"';
			echo ', "'.$game->logo_image_url().'"';
			echo ', "'.$game->vote_effectiveness_function().'"';
			echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
			echo ', "'.$game->db_game['inflation'].'"';
			echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
			echo ', "'.$blockchain_last_block['time_mined'].'"';
		?>));
		
		games[0].game_loop_event();
		
		<?php
		$load_event_rounds = 20;
		
		$plan_start_round = $current_round;
		$plan_stop_round = $plan_start_round+$load_event_rounds-1;
		
		$from_block_id = ($plan_start_round-1)*$game->db_game['round_length']+1;
		$to_block_id = ($plan_stop_round-1)*$game->db_game['round_length']+1;
		
		$game->load_current_events();
		
		$q = "SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id='".$game->db_game['game_id']."' AND e.event_starting_block >= ".$from_block_id." AND e.event_starting_block <= ".$to_block_id." ORDER BY e.event_id ASC;";
		$r = $app->run_query($q);
		$initial_load_events = $r->rowCount();
		$i=0;
		if ($initial_load_events > 0) {
			while ($db_event = $r->fetch()) {
				if ($i == 0) echo "games[0].all_events_start_index = ".$db_event['event_index'].";\n";
				else if ($i == $initial_load_events-1) echo "games[0].all_events_stop_index = ".$db_event['event_index'].";\n";
				
				echo "games[0].all_events[".$db_event['event_index']."] = new Event(games[0], ".$db_event['event_index'].", ".$db_event['event_id'].", ".$db_event['num_voting_options'].', "'.$db_event['vote_effectiveness_function'].'");'."\n";
				echo "games[0].all_events_db_id_to_index[".$db_event['event_id']."] = ".$db_event['event_index'].";\n";
				
				$option_q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY event_option_index ASC;";
				$option_r = $app->run_query($option_q);
				$j=0;
				while ($option = $option_r->fetch()) {
					$has_votingaddr = "false";
					$votingaddr_id = $thisuser->user_address_id($game, $option['option_index'], false, $user_game['account_id']);
					if ($votingaddr_id !== false) $has_votingaddr = "true";
					
					echo "games[0].all_events[".$db_event['event_index']."].options.push(new option(games[0].all_events[".$db_event['event_index']."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", '".str_replace("'", "", $option['name'])."', 0, $has_votingaddr));\n";
					$j++;
				}
				$i++;
			}
		}
		
		echo $game->load_all_event_points_js(0, $user_strategy, $plan_start_round, $plan_stop_round);
		?>
		
		$(document).ready(function() {
			loop_event();
			compose_vote_loop();
			<?php
			if ($game->db_game['game_status'] == 'unstarted') { ?>
				switch_to_game(<?php echo $game->db_game['game_id']; ?>, 'fetch');
				<?php
			}
			if ($user_game['show_planned_votes'] == 1) { ?>
				show_intro_message();
				<?php
				$qq = "UPDATE user_games SET show_planned_votes=0 WHERE user_game_id='".$user_game['user_game_id']."';";
				$rr = $app->run_query($qq);
			}
			?>
		});
		
		//]]>
		</script>
		
		<h1><?php
		echo $game->db_game['name'];
		if ($game->db_game['game_status'] == "paused" || $game->db_game['game_status'] == "unstarted") echo " (Paused)";
		else if ($game->db_game['game_status'] == "completed") echo " (Completed)";
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
		
		/*if ($game->db_game['inflation'] == "exponential") {
			echo '<div class="row"><div class="col-sm-2">Vote&nbsp;conversion&nbsp;rate:</div><div style="text-align: right;" class="col-sm-3"><font class="greentext">';
			echo $app->format_bignum($app->votes_per_coin($game->db_game)).'</font> votes &rarr; <font class="greentext">1</font> '.$game->db_game['coin_name'].'</div></div>';
		}*/
		?>
		<div id="wallet_text_stats">
			<?php
			echo $thisuser->wallet_text_stats($game, $blockchain_current_round, $blockchain_last_block_id, $blockchain_block_within_round, $mature_balance, $immature_balance, $user_game);
			?>
		</div>
		<br/>
		
		<div class="row">
			<div class="col-xs-2 tabcell" id="tabcell0" onclick="tab_clicked(0);">Play&nbsp;Now</div>
			<div class="col-xs-2 tabcell" id="tabcell1" onclick="tab_clicked(1);">Players</div>
			<div class="col-xs-2 tabcell" id="tabcell2" onclick="tab_clicked(2);">Strategy</div>
			<div class="col-xs-2 tabcell" id="tabcell3" onclick="tab_clicked(3);">Results</div>
			<div class="col-xs-2 tabcell" id="tabcell4" onclick="tab_clicked(4);">Deposit&nbsp;or&nbsp;Withdraw</div>
			<div class="col-xs-2 tabcell" id="tabcell5" onclick="tab_clicked(5);">My&nbsp;Games</div>
		</div>
		<div class="row">
			<div id="tabcontent0" class="tabcontent">
				<?php
				if ($game->db_game['buyin_policy'] != "none") { ?>
					<button style="float: right;" class="btn btn-success" onclick="initiate_buyin();">Buy more <?php echo $game->db_game['coin_name_plural']; ?></button>
					<?php
				}
				
				$game_status_explanation = $game->game_status_explanation($thisuser, $user_game);
				?>
				<div style="display: block; overflow: hidden;">
					<div id="game_status_explanation"<?php if ($game_status_explanation == "") echo ' style="display: none;"'; ?>><?php if ($game_status_explanation != "") echo $game_status_explanation; ?></div>
					
					<div id="change_user_game">
						<select id="select_user_game" class="form-control" onchange="change_user_game();">
							<?php
							$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
							$r = $app->run_query($q);
							while ($db_user_game = $r->fetch()) {
								echo "<option ";
								if ($db_user_game['user_game_id'] == $user_game['user_game_id']) echo "selected=\"selected\" ";
								echo "value=\"".$db_user_game['user_game_id']."\">Account #".$db_user_game['account_id']." &nbsp;&nbsp; ".$app->format_bignum($db_user_game['account_value'])." ".$game->db_game['coin_abbreviation']."</option>\n";
							}
							?>
							<option value="new">Create a new account</option>
						</select>
					</div>
				</div>
				
				<div id="game0_events" class="game_events"></div>
				
				<script type="text/javascript" id="game0_new_event_js">
				<?php
				echo $game->new_event_js(0, $thisuser);
				?>
				</script>
				<div id="vote_popups_disabled"<?php if ($block_within_round != $game->db_game['round_length']) echo ' style="display: none;"'; ?>>
					The final block of the round is being mined. Voting is currently disabled.
				</div>
				<div id="select_input_buttons"><?php
					echo $game->select_input_buttons($user_game);
				?></div>
				
				<div class="redtext" id="compose_vote_errors" style="margin-top: 5px;"></div>
				<div class="greentext" id="compose_vote_success" style="margin-top: 5px;"></div>
				
				<div id="compose_vote" style="display: none;">
					<h2>Vote Now</h2>
					<div class="row bordered_row" style="border: 1px solid #bbb;">
						<div class="col-md-6 bordered_cell" id="compose_vote_inputs">
							<b>Inputs:</b><div style="display: none; margin-left: 20px;" id="input_amount_sum"></div><div style="display: inline-block; margin-left: 20px;" id="input_vote_sum"></div><br/>
							<div id="compose_input_start_msg">Add inputs by clicking on the votes above.</div>
						</div>
						<div class="col-md-6 bordered_cell" id="compose_vote_outputs">
							<b>Outputs:</b><div id="display_tx_fee"></div><br/>
							<select class="form-control" id="select_add_output" onchange="select_add_output_changed();"></select>
						</div>
					</div>
					<button class="btn btn-success" id="confirm_compose_vote_btn" style="margin-top: 5px; margin-left: 5px;" onclick="confirm_compose_vote();">Confirm & Stake</button>
				</div>
			</div>
			
			<div class="tabcontent" style="display: none;" id="tabcontent1">
				<?php
				echo $game->render_game_players();
				?>
			</div>
			
			<div id="tabcontent2" style="display: none;" class="tabcontent">
				<?php
				if ($user_game['payout_address_id'] > 0) {
					$payout_address = $app->fetch_external_address_by_id($user_game['payout_address_id']);
					echo "Payout address: ".$payout_address['address'];
				}
				else {
					echo "You haven't specified a payout address for this game.";
				}
				?>
				<br/>
				<h2>Transaction Fees</h2>
				<form method="post" action="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">
					<input type="hidden" name="action" value="save_voting_strategy_fees" />
					<input type="hidden" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
					Pay fees on every transaction of:<br/>
					<div class="row">
						<div class="col-sm-4"><input class="form-control" name="transaction_fee" value="<?php echo $app->format_bignum($user_strategy['transaction_fee']/pow(10,8)); ?>" placeholder="0.001" /></div>
						<div class="col-sm-4 form-control-static"><?php
						echo $game->blockchain->db_blockchain['coin_name_plural'];
						?></div>
					</div>
					<div class="row">
						<div class="col-sm-3">
							<input class="btn btn-primary" type="submit" value="Save" />
						</div>
					</div>
				</form>
				<br/>
				
				<h2>Notifications</h2>
				Would you like to receive notifications whenever a new round begins?<br/>
				<div class="row">
					<div class="col-sm-6">
						<select class="form-control" id="notification_preference" name="notification_preference" onfocus="notification_focused();" onchange="notification_pref_changed();">
							<option <?php if ($user_game['notification_preference'] == "none") echo 'selected="selected" '; ?>value="none">No, don't notify me</option>
							<option <?php if ($user_game['notification_preference'] == "email") echo 'selected="selected" '; ?>value="email">Yes, email me whenever a new round starts</option>
						</select>
					</div>
					<div class="col-sm-6">
						<input style="display: none;" class="form-control" type="text" name="notification_email" id="notification_email" onfocus="notification_focused();" placeholder="Enter your email address" value="<?php echo $thisuser->db_user['notification_email']; ?>" />
					</div>
				</div>
				<button style="display: none;" id="notification_save_btn" class="btn btn-primary" onclick="save_notification_preferences();">Save Notification Settings</button>
				<br/>
				
				<h2>Choose your voting strategy</h2>
				Please set up a voting strategy so that your votes can be cast even when you're not online to vote.<br/><br/>
				<form method="post" action="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">
					<input type="hidden" name="action" value="save_voting_strategy" />
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
								Hit a custom URL whenever I have <?php echo $game->db_game['coin_name_plural']; ?> available to determine my votes: <input type="text" size="40" placeholder="http://" name="api_url" id="api_url" value="<?php echo $user_strategy['api_url']; ?>" />
							</label><br/>
							Your API access code is <?php echo $user_game['api_access_code']; ?><br/>
							<a href="/api/about/">API documentation</a><br/>
						</div>
					</div>
					
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_entity" name="voting_strategy" value="by_entity"<?php if ($user_strategy['voting_strategy'] == "by_entity") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_entity">&nbsp;Vote&nbsp;by&nbsp;option</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_by_entity"> 
								Vote for these options every time. The percentages you enter below must add up to 100.<br/>
								<?php /*<a href="" onclick="by_entity_reset_pct(); return false;">Set all to zero</a> <div style="margin-left: 15px; display: inline-block;" id="entity_pct_subtotal">&nbsp;</div>*/ ?>
							</label><br/>
							<?php
							$q = "SELECT * FROM options op JOIN events e ON op.event_id=e.event_id JOIN entities en ON op.entity_id=en.entity_id WHERE e.game_id='".$game->db_game['game_id']."' GROUP BY en.entity_id ORDER BY en.entity_id ASC;";
							$r = $app->run_query($q);
							$entity_i = 0;
							while ($entity = $r->fetch()) {
								$qq = "SELECT * FROM user_strategy_entities WHERE strategy_id='".$user_strategy['strategy_id']."' AND entity_id='".$entity['entity_id']."';";
								$rr = $app->run_query($qq);
								if ($rr->rowCount() > 0) {
									$pct_points = $rr->fetch()['pct_points'];
								}
								else $pct_points = "";
								
								if ($entity_i%4 == 0) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="tel" size="4" name="entity_pct_'.$entity['entity_id'].'" id="entity_pct_'.$entity_i.'" placeholder="0" value="'.$pct_points.'" />';
								echo '<label class="plainlabel" for="entity_pct_'.$entity_i.'">% ';
								echo $entity['entity_name']."</label>";
								echo '</div>';
								if ($entity_i%4 == 3) echo "</div>\n";
								$entity_i++;
							}
							if ($entity_i%4 != 0) echo "</div>\n";
							?>
						</div>
					</div>
					<?php /*
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
							
							for ($rank=1; $rank<=$game->db_game['num_voting_options']; $rank++) {
								if ($rank%4 == 1) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="checkbox" name="by_rank_'.$rank.'" id="by_rank_'.$rank.'" value="1"';
								if (in_array($rank, $by_rank_ranks)) echo ' checked="checked"';
								echo '><label class="plainlabel" for="by_rank_'.$rank.'"> '.$app->to_ranktext($rank)."</label>";
								echo '</div>';
								if ($rank%4 == 0 || $rank == $game->db_game['num_voting_options']) echo "</div>\n";
							}
							?>
						</div>
					</div>
					*/ ?>
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
							These settings apply to "Plan my votes" and "Vote by rank" options above.<br/>
							Wait until <input size="4" type="text" name="aggregate_threshold" id="aggregate_threshold" value="<?php echo $user_strategy['aggregate_threshold']; ?>" />% of my coins are available to vote. <br/>
							Only vote in these blocks of the round:<br/>
							<div class="row">
								<div class="col-md-2">
									<input type="checkbox" id="vote_on_block_all" onchange="vote_on_block_all_changed();" /><label class="plainlabel" for="vote_on_block_all">&nbsp;&nbsp;All</label>
								</div>
							</div>
							<div class="row">
								<?php
								for ($block=1; $block<$game->db_game['round_length']; $block++) {
									echo '<div class="col-md-2">';
									echo '<input type="checkbox" name="vote_on_block_'.$block.'" id="vote_on_block_'.$block.'" value="1"';
									
									$strategy_block_q = "SELECT * FROM user_strategy_blocks WHERE strategy_id='".$user_strategy['strategy_id']."' AND block_within_round='".$block."';";
									$strategy_block_r = $app->run_query($strategy_block_q);
									if ($strategy_block_r->rowCount() > 0) echo ' checked="checked"';
									
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
				<br/>
				<?php /*
				<h2>Privacy Settings</h2>
				You can make your gameplay public by choosing an alias below.<br/>
				<div class="row">
					<div class="col-sm-6">
						<select class="form-control" id="alias_preference" name="alias_preference" onfocus="alias_focused();" onchange="alias_pref_changed();">
							<option <?php if ($thisuser->db_user['alias_preference'] == "private") echo 'selected="selected" '; ?>value="private">Keep my identity private</option>
							<option <?php if ($thisuser->db_user['alias_preference'] == "public") echo 'selected="selected" '; ?>value="public">Let me choose a public alias</option>
						</select>
					</div>
					<div class="col-sm-6">
						<input style="display: none;" class="form-control" type="text" name="alias" id="alias" onfocus="alias_focused();" placeholder="Please enter an alias" value="<?php echo $thisuser->db_user['alias']; ?>" />
					</div>
				</div>
				<button style="display: none;" id="alias_save_btn" class="btn btn-primary" onclick="save_alias_preferences();">Save Privacy Settings</button>
				<br/>
				*/ ?>
			</div>
			<div id="tabcontent3" style="display: none;" class="tabcontent">
				<p>Results for all events are shown below.  Did you want to <a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">see results for your bets only</a>?</p>
				<div id="performance_history">
					<div id="performance_history_new">
					</div>
					<div id="performance_history_0">
						<?php
						echo $thisuser->performance_history($game, max(1, $current_round-$performance_history_rounds_per_section-1), $current_round-1);
						?>
					</div>
				</div>
				<center>
					<a href="" onclick="show_more_performance_history(); return false;">Show More</a>
				</center>
			</div>
			<div id="tabcontent4" style="display: none;" class="tabcontent">
				<h1>Deposit</h1>
				<?php
				if ($game->db_game['buyin_policy'] != "none") { ?>
					<p>
					You can buy more <?php echo $game->db_game['coin_name_plural']; ?> by sending <?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?> to your deposit address.  Once your <?php echo $game->blockchain->db_blockchain['coin_name']; ?> payment is confirmed, <?php echo $game->db_game['coin_name_plural']; ?> will be added to your account based on the <?php echo $game->blockchain->db_blockchain['coin_name']; ?> / <?php echo $game->db_game['coin_name']; ?> exchange rate at the time of confirmation.
					</p>
					<p>
					<button class="btn btn-success" onclick="initiate_buyin();">Buy more <?php echo $game->db_game['coin_name_plural']; ?></button>
					</p>
					<?php
				}
				else {
					echo "<p>You cannot buy directly in to this game. Instead, please purchase ".$game->db_game['coin_name_plural']." on an exchange and then send them to one of your addresses listed below.</p>\n";
				}
				?>
				
				<h1>Withdraw</h1>
				To withdraw coins please enter <?php echo $app->prepend_a_or_an($game->db_game['name']); ?> address below.<br/>
				<div class="row">
					<div class="col-md-3 form-control-static">
						Amount:
					</div>
					<div class="col-md-3">
						<input class="form-control" type="tel" placeholder="0.000" id="withdraw_amount" style="text-align: right;" />
					</div>
					<div class="col-md-3 form-control-static">
						<?php echo $game->db_game['coin_name_plural']; ?>
					</div>
				</div>
				<div class="row">
					<div class="col-md-3 form-control-static">
						Fee:
					</div>
					<div class="col-md-3">
						<input class="form-control" type="tel" value="<?php echo $user_strategy['transaction_fee']/pow(10,8); ?>" id="withdraw_fee" style="text-align: right;" />
					</div>
					<div class="col-md-3 form-control-static">
						<?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?>
					</div>
				</div>
				<div class="row">
					<div class="col-md-3 form-control-static">
						Address:
					</div>
					<div class="col-md-5">
						<input class="form-control" type="text" id="withdraw_address" />
					</div>
				</div>
				<div class="row">
					<div class="col-md-3 form-control-static">
						Vote remainder towards:
					</div>
					<div class="col-md-5">
						<select class="form-control" id="withdraw_remainder_address_id">
							<option value="random">Random</option>
							<?php
							$option_index_range = $game->option_index_range();
							
							for ($option_index=$option_index_range[0]; $option_index<=$option_index_range[1]; $option_index++) {
								$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.option_index='".$option_index."';";
								$rr = $app->run_query($qq);
								
								if ($rr->rowCount() > 0) {
									$address = $rr->fetch();
									echo "<option value=\"".$address['address_id']."\">";
									if ($address['option_index'] == "") echo "None";
									else echo "Voting option #".$address['option_index'];
									echo "</option>\n";
								}
							}
							?>
						</select>
					</div>
				</div>
				<div class="row">
					<div class="col-md-push-3 col-md-5">
						<button class="btn btn-success" id="withdraw_btn" onclick="attempt_withdrawal();">Withdraw</button>
						<div id="withdraw_message" style="display: none; margin-top: 15px;"></div>
					</div>
				</div>
				
				<h1>My <?php echo $game->db_game['name']; ?> addresses</h1>
				<?php
				$option_index_range = $game->option_index_range();
				
				$addr_id_csv = "";
				for ($option_index=$option_index_range[0]; $option_index<=$option_index_range[1]; $option_index++) {
					$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.option_index='".$option_index."';";
					$rr = $app->run_query($qq);
					
					if ($rr->rowCount() > 0) {
						$address = $rr->fetch();
						?>
						<div class="row">
							<div class="col-sm-3">
								<?php
								if ($address['option_index'] != "") echo "Voting option #".$address['option_index'];
								else echo "Default Address";
								?>
							</div>
							<div class="col-sm-4">
								<input type="text" class="address_cell" onclick="$(this).select();" value="<?php echo $address['address']; ?>" />
							</div>
							<div class="col-sm-2">
								<?php
								$color_bal = $game->address_balance_at_block($address, $game->blockchain->last_block_id());
								echo '<a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/addresses/'.$address['address'].'">'.$app->format_bignum($color_bal/pow(10,8))." ".$game->db_game['coin_name_plural'].'</a>';
								?>
							</div>
							<div class="col-sm-2">
								<?php
								$chain_bal = $game->blockchain->address_balance_at_block($address, $game->blockchain->last_block_id());
								echo '<a target="_blank" href="/explorer/blockchains/'.$game->blockchain->db_blockchain['url_identifier'].'/addresses/'.$address['address'].'">'.$app->format_bignum($chain_bal/pow(10,8))." ".$game->blockchain->db_blockchain['coin_name_plural'].'</a>';
								?>
							</div>
						</div>
						<?php
						$addr_id_csv .= $address['address_id'].",";
					}
				}
				if ($addr_id_csv != "") {
					$addr_id_csv = substr($addr_id_csv, 0, strlen($addr_id_csv)-1);
					
					$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$user_game['account_id']."' AND a.address_id NOT IN (".$addr_id_csv.") ORDER BY option_index ASC;";
					$rr = $app->run_query($qq);
					
					if ($rr->rowCount() > 0) {
						echo "<br/>\n";
						while ($address = $rr->fetch()) {
							?>
							<div class="row">
								<div class="col-sm-3">
									<?php
									if ($address['option_index'] != "") echo "Voting option #".$address['option_index'];
									else echo "Default Address";
									?>
								</div>
								<div class="col-sm-4">
									<input type="text" class="address_cell" onclick="$(this).select();" value="<?php echo $address['address']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php
									$color_bal = $game->address_balance_at_block($address, $game->blockchain->last_block_id());
									echo '<a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/addresses/'.$address['address'].'">'.$app->format_bignum($color_bal/pow(10,8))." ".$game->db_game['coin_name_plural'].'</a>';
									?>
								</div>
								<div class="col-sm-2">
									<?php
									$chain_bal = $game->blockchain->address_balance_at_block($address, $game->blockchain->last_block_id());
									echo '<a target="_blank" href="/explorer/blockchains/'.$game->blockchain->db_blockchain['url_identifier'].'/addresses/'.$address['address'].'">'.$app->format_bignum($chain_bal/pow(10,8))." ".$game->blockchain->db_blockchain['coin_name_plural'].'</a>';
									?>
								</div>
							</div>
							<?php
						}
					}
				}
				?>
			</div>
			
			<div class="tabcontent" style="display: none;" id="tabcontent5">
				<h4>My Games</h4>
				<?php
				$game_id_csv = "";
				$q = "SELECT * FROM games WHERE creator_id='".$thisuser->db_user['user_id']."' ORDER BY game_id ASC;";
				$r = $app->run_query($q);
				while ($user_game = $r->fetch()) {
					$game_id_csv .= $user_game['game_id'].",";
					echo $app->game_admin_row($thisuser, $user_game, $game->db_game['game_id']);
				}
				if ($game_id_csv != "") $game_id_csv = substr($game_id_csv, 0, strlen($game_id_csv)-1);
				
				$q = "SELECT * FROM games g LEFT JOIN user_games ug ON g.game_id=ug.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."'";
				if ($game_id_csv != "") $q .= " AND g.game_id NOT IN (".$game_id_csv.")";
				$q .= " GROUP BY g.game_id ORDER BY g.game_id ASC;";
				$r = $app->run_query($q);
				while ($user_game = $r->fetch()) {
					echo $app->game_admin_row($thisuser, $user_game, $game->db_game['game_id']);
				}
				
				$new_game_perm = $thisuser->new_game_permission();
				
				if ($new_game_perm) { ?>
					<br/>
					<button class="btn btn-primary" onclick="switch_to_game(0, 'new'); return false;">Create a new Game</button>
					<?php
				}
				?>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="intro_message">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">New message from <?php echo $GLOBALS['site_name']; ?></h4>
					</div>
					<div class="modal-body">
						<p>
							Hi <?php echo $thisuser->db_user['username']; ?>, thanks for joining <?php echo $game->db_game['name']; ?>! 
							<?php if ($game->db_game['final_round'] > 0) echo 'This game lasts for '.$game->db_game['final_round'].' voting rounds.  ';
							if ($game->db_game['inflation'] == "exponential") {
							}
							else {
								?>
								At the end of each round, the supply of <?php echo $game->db_game['coin_name_plural']; ?> 
								<?php
								if ($game->db_game['inflation'] == "fixed_exponential") echo 'inflates by '.(100*$game->db_game['exponential_inflation_rate']).'%';
								else echo 'increases by '.$app->format_bignum($game->db_game['pos_reward']/pow(10,8));
								echo ". ";
							}
							?>
							After each round, a winner is declared and new <?php echo $game->db_game['coin_name_plural']; ?> are created and given to everyone who voted for the winner.
						</p>
						<p>
							To do well in this game, be sure to vote in each round. Click below to set your voting strategy. You can change your voting strategy at any time by clicking on the "Strategy" tab.<br/>
						</p>
						<p>
							<button class="btn btn-primary" onclick="$('#intro_message').modal('hide'); show_planned_votes();">Continue</button>
						</p>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="planned_votes">
			<div class="modal-dialog" style="width: 80%; max-width: 1000px;">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">My Planned Votes</h4>
					</div>
					<div class="modal-body">
						<p>
							Set your planned votes by clicking on the options below.  You can vote on more than one option in each round. Keep clicking on an option to increase its votes.  Or right click to remove all votes from an option.  Your planned votes are confidential and cannot be seen by other players.
						</p>
						
						<button id="scramble_plan_btn" class="btn btn-warning" onclick="scramble_strategy(<?php echo $user_strategy['strategy_id']; ?>); return false;">Randomize my Votes</button>
						
						<font style="margin-left: 25px;">Load rounds: </font><input type="text" size="5" id="select_from_round" value="<?php echo $game->round_to_display_round($plan_start_round); ?>" /> to <input type="text" size="5" id="select_to_round" value="<?php echo $game->round_to_display_round($plan_stop_round); ?>" /> <button class="btn btn-default btn-sm" onclick="load_plan_rounds(); return false;">Go</button>
						
						<br/>
						<div id="plan_rows" style="margin: 10px 0px; max-height: 350px; overflow-y: scroll; border: 1px solid #bbb; padding: 0px 10px;">
							<?php
							echo $game->plan_options_html($plan_start_round, $plan_stop_round, $user_strategy);
							?>
						</div>
						
						<button id="save_plan_btn" class="btn btn-success" onclick="save_plan_allocations(); return false;">Save Changes</button>
						<button style="float: right;" type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						
						<div id="plan_rows_js"></div>
						
						<input type="hidden" id="from_round" name="from_round" value="<?php echo $game->round_to_display_round($plan_start_round); ?>" />
						<input type="hidden" id="to_round" name="to_round" value="<?php echo $game->round_to_display_round($plan_stop_round); ?>" />
					</div>
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
									Runs on Blockchain:
								</div>
								<div class="col-sm-6">
									<select id="game_form_blockchain_id" class="form-control">
										<option value="">-- Please Select --</option>
										<?php
										$q = "SELECT * FROM blockchains ORDER BY blockchain_name ASC;";
										$r = $app->run_query($q);
										while ($db_blockchain = $r->fetch()) {
											echo "<option value=\"".$db_blockchain['blockchain_id']."\">".$db_blockchain['blockchain_name']."</option>\n";
										}
										?>
									</select>
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
								<div class="col-sm-6 form-control-static">Game starts on block:</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" style="text-align: right;" id="game_form_game_starting_block" />
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
								<div class="col-sm-6 form-control-static">Escrow address:</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_escrow_address" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Genesis transaction:</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_genesis_tx_hash" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Coins created by genesis tx:</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_genesis_amount" style="text-align: right;" />
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
								<div class="col-sm-6 form-control-static">Vote effectiveness:</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_default_vote_effectiveness_function">
										<option value="constant">Votes count equally through the round</option>
										<option value="linear_decrease">Linearly decreasing vote effectiveness</option>
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
								<div class="col-sm-6 form-control-static">Buy-in policy:</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_buyin_policy" onchange="game_form_buyin_policy_changed();">
										<option value="none">No additional buy-ins</option>
										<option value="unlimited">Unlimited buy-ins</option>
										<option value="game_cap">Buy-in cap for the whole game</option>
									</select>
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
										<option value="fixed_exponential">Fixed Exponential</option>
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
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Event rule:</div>
								<div class="col-sm-6">
									<select id="game_form_event_rule" class="form-control" onchange="game_form_event_rule_changed();">
										<option value="single_event_series">Single, repeating event</option>
										<option value="entity_type_option_group">One event for each item in a group</option>
										<option value="all_pairs">Head to head between all options</option>
									</select>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Voting options:</div>
								<div class="col-sm-6">
									<select id="game_form_option_group_id" class="form-control">
										<?php
										$q = "SELECT * FROM option_groups ORDER BY description ASC;";
										$r = $app->run_query($q);
										while ($option_group = $r->fetch()) {
											echo '<option value="'.$option_group['group_id'].'">'.$option_group['description']."</option>\n";
										}
										?>
									</select>
								</div>
							</div>
							<div id="game_form_event_rule_entity_type_option_group">
								<div class="row">
									<div class="col-sm-6 form-control-static">Events per round:</div>
									<div class="col-sm-6">
										<input class="form-control" type="text" id="game_form_events_per_round" style="text-align: right" />
									</div>
								</div>
								<div class="row">
									<div class="col-sm-6 form-control-static">One event for each of these:</div>
									<div class="col-sm-6">
										<select id="game_form_event_entity_type_id" class="form-control">
											<?php
											$q = "SELECT * FROM entity_types ORDER BY entity_name ASC;";
											$r = $app->run_query($q);
											while ($entity_type = $r->fetch()) {
												echo '<option value="'.$entity_type['entity_type_id'].'">'.$entity_type['entity_name']."</option>\n";
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Each event is called:</div>
								<div class="col-sm-6">
									<input class="form-control" type="text" id="game_form_event_type_name" />
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Voting cap:</div>
								<div class="col-sm-3">
									<input class="form-control" type="text" style="text-align: right;" id="game_form_default_max_voting_fraction" />
								</div>
								<div class="col-sm-3 form-control-static">%</div>
							</div>
							
							<div style="height: 10px;"></div>
							<button style="float: right;" type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							
							<button id="save_game_btn" type="button" class="btn btn-success" onclick="save_game('save');">Save Settings</button>
							
							<button id="publish_game_btn" type="button" class="btn btn-primary" onclick="save_game('publish');">Save &amp; Publish</button>
							
							<?php /*<button id="game_invitations_game_btn" type="button" class="btn btn-info" data-dismiss="modal" onclick="manage_game_invitations(editing_game_id);">Invite People</button> */ ?>
						</form>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="set_event_outcome_modal">
			<div class="modal-dialog">
				<div class="modal-content" id="set_event_outcome_modal_content">
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="buyin_modal">
			<div class="modal-dialog">
				<div class="modal-content" id="buyin_modal_content">
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
