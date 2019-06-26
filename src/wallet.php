<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$error_code = false;
$message = "";

if (!isset($_REQUEST['action'])) $_REQUEST['action'] = "";

if ($_REQUEST['action'] == "unsubscribe") {
	include(AppSettings::srcPath().'/includes/html_start.php');
	
	$delivery = $app->run_query("SELECT * FROM async_email_deliveries WHERE delivery_key=:delivery_key;", [
		'delivery_key' => $_REQUEST['delivery_key']
	])->fetch();
	
	if ($delivery) {
		$app->run_query("UPDATE users u JOIN user_games ug ON u.user_id=ug.user_id SET ug.notification_preference='none' WHERE u.notification_email=:email;", [
			'email' => $delivery['to_email']
		]);
	}
	
	echo "<br/><p>&nbsp;&nbsp; You've been unsubscribed. You'll no longer receive email notifications about your accounts.</p>\n";
	
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}

if ($_REQUEST['action'] == "logout" && $thisuser) {
	$thisuser->log_out($session);
	
	$thisuser = FALSE;
	$message = "You have been logged out. ";
}

if (empty($thisuser) && !empty($_REQUEST['login_key'])) {
	$login_link_error = false;
	
	$login_link = $app->run_query("SELECT * FROM user_login_links WHERE access_key=:access_key;", [
		'access_key' => $_REQUEST['login_key']
	])->fetch();
	
	if ($login_link) {
		if (empty($login_link['time_clicked'])) {
			if ($login_link['time_created'] > time()-(60*15)) {
				if (empty($login_link['user_id'])) {
					$existing_user = $app->fetch_user_by_username($login_link['username']);
					
					if (!$existing_user) {
						$verify_code = $app->random_string(32);
						$salt = $app->random_string(16);
						
						$thisuser = $app->create_new_user($verify_code, $salt, $login_link['username'], "");
					}
					else {
						$login_link_error = true;
						$message = "Error: you followed an invalid login link. Please try again.";
					}
				}
				else {
					$db_user = $app->fetch_user_by_id($login_link['user_id']);
					
					if ($db_user) {
						$thisuser = new User($app, $db_user['user_id']);
					}
					else {
						$login_link_error = true;
						$message = "Error: invalid login link. Please try again.";
					}
				}
				
				if (!$login_link_error) {
					$app->run_query("UPDATE user_login_links SET time_clicked=:time_clicked WHERE login_link_id=:login_link_id;", [
						'time_clicked' => time(),
						'login_link_id' => $login_link['login_link_id']
					]);
					
					$redirect_url = false;
					$login_success = $thisuser->log_user_in($redirect_url, $viewer_id);
					
					if ($redirect_url) {
						header("Location: ".$redirect_url['url']);
						die();
					}
				}
			}
			else {
				$login_link_error = true;
				$message = "Error: this login link has already expired.";
			}
		}
		else {
			$login_link_error = true;
			$message = "Error: this login link has already been used.";
		}
	}
}

$game = false;

if ($thisuser) {
	if (!empty($_REQUEST['invite_key'])) {
		$invite_user_game = false;
		$invite_game = false;
		$success = $app->try_apply_invite_key($thisuser->db_user['user_id'], $_REQUEST['invite_key'], $invite_game, $invite_user_game);
		if ($success) {
			header("Location: /wallet/".$invite_game->db_game['url_identifier']);
			die();
		}
	}
	
	$uri_parts = explode("/", $uri);
	$url_identifier = $uri_parts[2];
	
	$requested_game = $app->fetch_game_by_identifier($url_identifier);
	
	if ($requested_game && (in_array($requested_game['game_status'], ['published','running','completed']) || $requested_game['creator_id'] == $thisuser->db_user['user_id'])) {
		$blockchain = new Blockchain($app, $requested_game['blockchain_id']);
		$game = new Game($blockchain, $requested_game['game_id']);
		
		if ($_REQUEST['action'] == "change_user_game") {
			$app->change_user_game($thisuser, $game, $_REQUEST['user_game_id']);
			
			header("Location: /wallet/".$game->db_game['url_identifier']."/");
			die();
		}
		
		$is_creator = false;
		if ($requested_game['creator_id'] == $thisuser->db_user['user_id']) $is_creator = true;
		
		$allow_public = false;
		if ($requested_game['giveaway_status'] == "public_free" || $requested_game['giveaway_status'] == "public_pay") $allow_public = true;
		
		$user_game = $thisuser->ensure_user_in_game($game, false);
		
		if ($user_game && $user_game['payment_required'] == 0) {
			if ($_REQUEST['action'] == "save_address") {
				$payout_address = $app->strong_strip_tags($_REQUEST['payout_address']);
				
				if ($payout_address != "") {
					$base_currency = $app->fetch_currency_by_id($game->blockchain->currency_id());
					$app->run_query("INSERT INTO external_addresses SET user_id=:user_id, currency_id=:currency_id, address=:address, time_created=:time_created;", [
						'user_id' => $thisuser->db_user['user_id'],
						'currency_id' => $base_currency['currency_id'],
						'address' => $payout_address,
						'time_created' => time()
					]);
					$address_id = $app->last_insert_id();
					
					$app->run_query("UPDATE user_games SET payout_address_id=:payout_address_id WHERE user_game_id=:user_game_id;", [
						'payout_address_id' => $address_id,
						'user_game_id' => $user_game['user_game_id']
					]);
					$user_game['payout_address_id'] = $address_id;
				}
			}
		}
		else if (!$user_game && ($requested_game['giveaway_status'] == "invite_free" || $requested_game['giveaway_status'] == "invite_pay")) {
			if ($requested_game['public_unclaimed_game_invitations'] == 1) {
				$invitation = $app->run_query("SELECT * FROM game_invitations WHERE game_id=:game_id AND used=0 AND used_user_id IS NULL ORDER BY invitation_id DESC LIMIT 1;", [
					'game_id' => $requested_game['game_id']
				])->fetch();
				
				if ($invitation) {
					$invite_user_game = false;
					$invite_game = false;
					$app->try_apply_invite_key($thisuser->db_user['user_id'], $invitation['invitation_key'], $invite_game, $invite_user_game);
					header("Location: /wallet/".$invite_game->db_game['url_identifier']);
					die();
				}
			}
			$pagetitle = "Join ".$requested_game['name'];
			$nav_tab_selected = "wallet";
			include(AppSettings::srcPath().'/includes/html_start.php');
			?>
			<div class="container-fluid">
				You need an invitation to join this game.
				<?php
				if ($requested_game['invitation_link'] != "") {
					echo " To receive an invitation please follow <a href=\"".$requested_game['invitation_link']."\">this link</a>.";
				}
				?>
			</div>
			<?php
			include(AppSettings::srcPath().'/includes/html_stop.php');
			die();
		}
		else if ($user_game['payment_required'] == 1) {
			$pagetitle = "Join ".$requested_game['name'];
			$nav_tab_selected = "wallet";
			include(AppSettings::srcPath().'/includes/html_start.php');
			?>
			<div class="container-fluid">
				<?php
				$invite_currency = $app->fetch_currency_by_id($requested_game['invite_currency']);
				
				if ($invite_currency) {
					if ($user_game['current_invoice_id'] > 0) {
						$invoice = $app->fetch_currency_invoice_by_id($user_game['current_invoice_id']);
					}
					else {
						$invoice = $app->new_currency_invoice($invite_currency, $invite_currency['currency_id'], $requested_game['invite_cost'], $thisuser, $user_game, 'join_buyin');
						
						$app->run_query("UPDATE user_games SET current_invoice_id=:current_invoice_id WHERE user_game_id=:user_game_id;", [
							'current_invoice_id' => $invoice['invoice_id'],
							'user_game_id' => $user_game['user_game_id']
						]);
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

							if (AppSettings::getParam('rsa_pub_key') != "" && AppSettings::getParam('rsa_keyholder_email') != "") {
								$invoice_exchange_rate = $app->historical_currency_conversion_rate($invoice['settle_price_id'], $invoice['pay_price_id']);

								$pay_currency = $app->fetch_currency_by_id($invoice['pay_currency_id']);
								$currency_address = $app->fetch_currency_address_by_id($invoice['currency_address_id']);

								$coins_per_currency = ($requested_game['giveaway_amount']/pow(10,$requested_game['decimal_places']))/$requested_game['invite_cost'];
								echo "This game has an initial exchange rate of ".$app->format_bignum($coins_per_currency)." ".$requested_game['coin_name_plural']." per ".$invite_currency['short_name'].". ";
								
								$buyin_disp = $app->format_bignum($requested_game['invite_cost']);
								echo "To join this game, you need to make a payment of ".$buyin_disp." ".$invite_currency['short_name'];
								if ($buyin_disp != '1') echo "s";
								
								$receive_disp = $app->format_bignum($requested_game['giveaway_amount']/pow(10,$requested_game['decimal_places']));
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
			include(AppSettings::srcPath().'/includes/html_stop.php');
			die();
		}
		else die("Error: this game has an invalid giveaway_status");
	}
	else {
		$pagetitle = AppSettings::getParam('site_name_short')." - My web wallet";
		$nav_tab_selected = "wallet";
		include(AppSettings::srcPath().'/includes/html_start.php');
		?>
		<div class="container-fluid">
			<div class="panel panel-default" style="margin-top: 15px;">
				<?php
				$my_games = $app->run_query("SELECT * FROM games g, user_games ug WHERE g.game_id=ug.game_id AND ug.user_id=:user_id AND (g.creator_id=:user_id OR g.game_status IN ('running','completed','published')) GROUP BY ug.game_id;", [
					'user_id' => $thisuser->db_user['user_id']
				]);
				
				if ($my_games->rowCount() > 0) {
					?>
					<div class="panel-heading">
						<div class="panel-title">Please select a game:</div>
					</div>
					<div class="panel-body">
						<?php
						while ($user_game = $my_games->fetch()) {
							echo "<a href=\"/wallet/".$user_game['url_identifier']."/\">".$user_game['name']."</a><br/>\n";
						}
						?>
					</div>
					<?php
				}
				else {
					?>
					<div class="panel-heading">
						<div class="panel-title">Please select a game.</div>
					</div>
					<div class="panel-body">
						You haven't joined any games yet.  <a href="/">Click here</a> to see a list of available games.
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
		include(AppSettings::srcPath().'/includes/html_stop.php');
		die();
	}
}

if ($thisuser && ($_REQUEST['action'] == "save_voting_strategy" || $_REQUEST['action'] == "save_voting_strategy_fees")) {
	$voting_strategy = $_REQUEST['voting_strategy'];
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	$aggregate_threshold = intval($_REQUEST['aggregate_threshold']);
	$api_url = $app->strong_strip_tags($_REQUEST['api_url']);
	if ($voting_strategy == "hit_url") $api_url = $app->strong_strip_tags($_REQUEST['hit_api_url']);
	$by_rank_csv = "";
	
	if ($voting_strategy_id > 0) {
		$user_strategy = $app->fetch_strategy_by_id($voting_strategy_id);
		
		if (!$user_strategy || $user_strategy['user_id'] != $thisuser->db_user['user_id']) die("Invalid strategy ID");
	}
	else {
		$app->run_query("INSERT INTO user_strategies SET user_id=:user_id, game_id=:game_id;", [
			'user_id' => $thisuser->db_user['user_id'],
			'game_id' => $game->db_game['game_id']
		]);
		$voting_strategy_id = $app->last_insert_id();
		
		$user_strategy = $app->fetch_strategy_by_id($voting_strategy_id);
	}
	
	if ($_REQUEST['action'] == "save_voting_strategy_fees") {
		$transaction_fee = floatval($_REQUEST['transaction_fee']);
		
		$app->run_query("UPDATE user_strategies SET transaction_fee=:transaction_fee WHERE strategy_id=:strategy_id;", [
			'transaction_fee' => $transaction_fee,
			'strategy_id' => $user_strategy['strategy_id']
		]);
		$user_strategy['transaction_fee'] = $transaction_fee;
		
		$error_code = 1;
		$message = "Great, your transaction fee has been updated!";
	}
	else {
		if (in_array($voting_strategy, ['manual', 'api', 'by_plan', 'by_entity','hit_url'])) {
			$update_strategy_params = [
				'voting_strategy' => $voting_strategy,
				'max_votesum_pct' => $max_votesum_pct,
				'min_votesum_pct' => $min_votesum_pct,
				'api_url' => $api_url,
				'strategy_id' => $user_strategy['strategy_id']
			];
			$update_strategy_q = "UPDATE user_strategies SET voting_strategy=:voting_strategy";
			if ($aggregate_threshold >= 0 && $aggregate_threshold <= 100) {
				$update_strategy_q .= ", aggregate_threshold=:aggregate_threshold";
				$update_strategy_params['aggregate_threshold'] = $aggregate_threshold;
			}
			
			$min_votesum_pct = intval($_REQUEST['min_votesum_pct']);
			$max_votesum_pct = intval($_REQUEST['max_votesum_pct']);
			if ($max_votesum_pct > 100) $max_votesum_pct = 100;
			if ($min_votesum_pct < 0) $min_votesum_pct = 0;
			if ($max_votesum_pct < $min_votesum_pct) $max_votesum_pct = $min_votesum_pct;
			
			$update_strategy_q .= ", max_votesum_pct=:max_votesum_pct, min_votesum_pct=:min_votesum_pct, api_url=:api_url WHERE strategy_id=:strategy_id;";
			$app->run_query($update_strategy_q, $update_strategy_params);
			
			$app->run_query("UPDATE user_games SET strategy_id=:strategy_id WHERE game_id=:game_id AND user_id=:user_id;", [
				'strategy_id' => $user_strategy['strategy_id'],
				'game_id' => $game->db_game['game_id'],
				'user_id' => $thisuser->db_user['user_id']
			]);
		}
		
		$entity_pct_sum = 0;
		$entity_pct_error = FALSE;
		
		$entities_by_game = $game->entities_by_game()->fetchAll();
		
		foreach ($entities_by_game as $entity) {
			$entity_pct = intval($_REQUEST['entity_pct_'.$entity['entity_id']]);
			$entity_pct_sum += $entity_pct;
		}
		
		if ($entity_pct_sum == 100) {
			$app->run_query("DELETE FROM user_strategy_entities WHERE strategy_id=:strategy_id;", [
				'strategy_id' => $user_strategy['strategy_id']
			]);
			
			foreach ($entities_by_game as $entity) {
				$entity_pct = intval($_REQUEST['entity_pct_'.$entity['entity_id']]);
				if ($entity_pct > 0) {
					$app->run_query("INSERT INTO user_strategy_entities SET strategy_id=:strategy_id, entity_id=:entity_id, pct_points=:pct_points;", [
						'strategy_id' => $user_strategy['strategy_id'],
						'entity_id' => $entity['entity_id'],
						'pct_points' => $entity_pct
					]);
				}
			}
		}
		else {
			if ($voting_strategy == "by_entity") {
				$error_code = 2;
				$message = "Error: the percentages that you entered did not add up to 100, your changes were discarded.";
			}
		}
		
		for ($block=1; $block<=$game->db_game['round_length']; $block++) {
			$strategy_block = $app->run_query("SELECT * FROM user_strategy_blocks WHERE strategy_id=:strategy_id AND block_within_round=:block_within_round;", [
				'strategy_id' => $user_strategy['strategy_id'],
				'block_within_round' => $block
			])->fetch();
			
			if ($_REQUEST['vote_on_block_'.$block] == "1") {
				if (!$strategy_block) {
					$app->run_query("INSERT INTO user_strategy_blocks SET strategy_id=:strategy_id, block_within_round=:block_within_round;", [
						'strategy_id' => $user_strategy['strategy_id'],
						'block_within_round' => $block
					]);
				}
			}
			else if ($strategy_block) {
				$app->run_query("DELETE FROM user_strategy_blocks WHERE strategy_block_id=:strategy_block_id;", [
					'strategy_block_id' => $strategy_block['strategy_block_id']
				]);
			}
		}
	}
}

if (empty($pagetitle)) {
	if ($game) $pagetitle = $game->db_game['name']." - Wallet";
	else $pagetitle = "Please log in";
}
$nav_tab_selected = "wallet";
include(AppSettings::srcPath().'/includes/html_start.php');

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
if (!empty($_REQUEST['initial_tab'])) $initial_tab = (int) $_REQUEST['initial_tab'];

if ($thisuser && $game) {
	$last_block_id = $game->last_block_id();
	$current_round = $game->block_to_round($last_block_id+1);
	$block_within_round = $game->block_id_to_round_index($last_block_id+1);
	$coins_per_vote = $app->coins_per_vote($game->db_game);
	
	$immature_balance = $thisuser->immature_balance($game, $user_game);
	$mature_balance = $thisuser->mature_balance($game, $user_game);
	
	list($user_votes, $votes_value) = $thisuser->user_current_votes($game, $last_block_id, $current_round, $user_game);
	$user_pending_bets = $game->user_pending_bets($user_game);
	$game_pending_bets = $game->pending_bets(true);
	list($vote_supply, $vote_supply_value) = $game->vote_supply($last_block_id, $current_round, $coins_per_vote, true);
	$account_value = $game->account_balance($user_game['account_id'])+$user_pending_bets;
	
	$blockchain_last_block_id = $game->blockchain->last_block_id();
	$blockchain_current_round = $game->block_to_round($blockchain_last_block_id+1);
	$blockchain_block_within_round = $game->block_id_to_round_index($blockchain_last_block_id+1);
	$blockchain_last_block = $game->blockchain->fetch_block_by_id($blockchain_last_block_id);
}
?>
<div class="container-fluid">
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
		$user_strategy = $game->fetch_user_strategy($user_game);
		
		$faucet_io = $game->check_faucet($user_game);
		
		$filter_arr['date'] = false;
		$event_ids = "";
		$new_event_js = $game->new_event_js(0, $thisuser, $filter_arr, $event_ids);
		?>
		<script type="text/javascript">
		//<![CDATA[
		var current_tab = 0;
		
		games.push(new Game(<?php
			echo $game->db_game['game_id'];
			echo ', false';
			echo ', false';
			echo ', "'.$game->mature_io_ids_csv($user_game).'"';
			echo ', "'.$game->db_game['payout_weight'].'"';
			echo ', '.$game->db_game['round_length'];
			echo ', '.$user_strategy['transaction_fee'];
			echo ', "'.$game->db_game['url_identifier'].'"';
			echo ', "'.$game->db_game['coin_name'].'"';
			echo ', "'.$game->db_game['coin_name_plural'].'"';
			echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
			echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
			echo ', "wallet", "'.$event_ids.'"';
			echo ', "'.$game->logo_image_url().'"';
			echo ', "'.$game->vote_effectiveness_function().'"';
			echo ', "'.$game->effectiveness_param1().'"';
			echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
			echo ', "'.$game->db_game['inflation'].'"';
			echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
			echo ', "'.$blockchain_last_block['time_mined'].'"';
			echo ', "'.$game->db_game['decimal_places'].'"';
			echo ', "'.$game->blockchain->db_blockchain['decimal_places'].'"';
			echo ', "'.$game->db_game['view_mode'].'"';
			echo ', '.$user_game['event_index'];
			echo ', false';
			echo ', "'.$game->db_game['default_betting_mode'].'"';
			echo ', true';
		?>));
		
		<?php
		$load_event_rounds = 1;
		
		$plan_start_round = $current_round;
		$plan_stop_round = $plan_start_round+$load_event_rounds-1;
		
		$from_block_id = ($plan_start_round-1)*$game->db_game['round_length']+1;
		$to_block_id = ($plan_stop_round-1)*$game->db_game['round_length']+1;
		
		$initial_load_events = $app->run_query("SELECT * FROM events e JOIN event_types t ON e.event_type_id=t.event_type_id WHERE e.game_id=:game_id AND e.event_starting_block >= :from_block_id AND e.event_starting_block <= :to_block_id ORDER BY e.event_id ASC;", [
			'game_id' => $game->db_game['game_id'],
			'from_block_id' => $from_block_id,
			'to_block_id' => $to_block_id
		]);
		$num_initial_load_events = $initial_load_events->rowCount();
		$i=0;
		
		while ($db_event = $initial_load_events->fetch()) {
			if ($i == 0) echo "games[0].all_events_start_index = ".$db_event['event_index'].";\n";
			else if ($i == $initial_load_events-1) echo "games[0].all_events_stop_index = ".$db_event['event_index'].";\n";
			
			echo "games[0].all_events[".$db_event['event_index']."] = new Event(games[0], ".$i.", ".$db_event['event_id'].", ".$db_event['event_index'].", ".$db_event['num_voting_options'].', "'.$db_event['vote_effectiveness_function'].'", "'.$db_event['effectiveness_param1'].'", '.$app->quote_escape($db_event['event_name']).", ".$db_event['event_starting_block'].", ".$db_event['event_final_block'].", ".$db_event['payout_rate'].");\n";
			echo "games[0].all_events_db_id_to_index[".$db_event['event_id']."] = ".$db_event['event_index'].";\n";
			
			$options_by_event = $app->fetch_options_by_event($db_event['event_id']);
			$j=0;
			while ($option = $options_by_event->fetch()) {
				$has_votingaddr = "true";
				echo "games[0].all_events[".$db_event['event_index']."].options.push(new option(games[0].all_events[".$db_event['event_index']."], ".$j.", ".$option['option_id'].", ".$option['option_index'].", ".$app->quote_escape($option['name']).", 0, $has_votingaddr));\n";
				$j++;
			}
			$i++;
		}
		
		echo $game->load_all_event_points_js(0, $user_strategy, $plan_start_round, $plan_stop_round);
		?>
		window.onload = function() {
			toggle_betting_mode('inflationary');
			loop_event();
			compose_vote_loop();
			<?php
			if (!$faucet_io) {
				if ($user_game['show_intro_message'] == 1) { ?>
					show_intro_message();
					<?php
					$app->run_query("UPDATE user_games SET show_intro_message=0 WHERE user_game_id=:user_game_id;", ['user_game_id' => $user_game['user_game_id']]);
				}
				if ($user_game['prompt_notification_preference'] == 1) { ?>
					$('#notification_modal').modal('show');
					<?php
					$app->run_query("UPDATE user_games SET prompt_notification_preference=0 WHERE user_game_id=:user_game_id;", ['user_game_id' => $user_game['user_game_id']]);
				}
			}
			?>
			render_tx_fee();
			reload_compose_vote();
			set_select_add_output();
			
			$(".datepicker").datepicker();
			<?php
			if ($_REQUEST['action'] == "start_bet") {
				echo "games[0].add_option_to_vote(".((int)$_REQUEST['event_index']).", ".((int)$_REQUEST['option_id']).");\n";
			}
			?>
			tab_clicked(<?php echo $initial_tab; ?>);
			
			set_plan_rightclicks();
			set_plan_round_sums();
			render_plan_rounds();
		};
		
		//]]>
		</script>
		
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">
					<?php
					echo $game->db_game['name'];
					if ($game->db_game['game_status'] == "paused" || $game->db_game['game_status'] == "unstarted") echo " (Paused)";
					else if ($game->db_game['game_status'] == "completed") echo " (Completed)";
					?>
				</div>
			</div>
			<div class="panel-body">
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
				<div style="overflow: hidden;">
					<div class="row">
						<div class="col-sm-2">Account&nbsp;value:</div>
						<div class="col-sm-3" style="text-align: right;" id="account_value"><?php
						echo $game->account_value_html($account_value, $user_game, $game_pending_bets, $vote_supply_value);
						?></div>
					</div>
				</div>
				<div id="wallet_text_stats">
					<?php
					echo $thisuser->wallet_text_stats($game, $blockchain_current_round, $blockchain_last_block_id, $blockchain_block_within_round, $mature_balance, $immature_balance, $user_votes, $votes_value, $user_pending_bets, $user_game);
					?>
				</div>
				<?php
				if ($game->db_game['buyin_policy'] != "none") { ?>
					<button class="btn btn-sm btn-success" style="margin-top: 8px;" onclick="manage_buyin('initiate');"><i class="fas fa-shopping-cart"></i> &nbsp; Buy more <?php echo $game->db_game['coin_name_plural']; ?></button>
					<?php
				}
				if ($game->db_game['sellout_policy'] == "on") { ?>
					<button class="btn btn-sm btn-info" style="margin-top: 8px;" onclick="manage_sellout('initiate');"><i class="fas fa-exchange-alt"></i> &nbsp; Sell your <?php echo $game->db_game['coin_name_plural']; ?></button>
					<?php
				}
				?>
				<button class="btn btn-sm btn-warning" style="margin-top: 8px;" onclick="apply_my_strategy();">Apply my strategy now</button>
				<button class="btn btn-sm btn-success" style="margin-top: 8px;" onclick="show_featured_strategies(); return false;">Change my strategy</button>
				
				<div id="apply_my_strategy_status" class="greentext"></div>
			</div>
		</div>
		
		<div id="tabcontent0" class="tabcontent">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">
						Play Now
						
						<div id="change_user_game">
							<select id="select_user_game" class="form-control input-sm" onchange="change_user_game();">
								<?php
								$user_games_by_game = $app->run_query("SELECT * FROM user_games WHERE user_id=:user_id AND game_id=:game_id;", [
									'user_id' => $thisuser->db_user['user_id'],
									'game_id' => $game->db_game['game_id']
								]);
								while ($db_user_game = $user_games_by_game->fetch()) {
									echo "<option ";
									if ($db_user_game['user_game_id'] == $user_game['user_game_id']) echo "selected=\"selected\" ";
									echo "value=\"".$db_user_game['user_game_id']."\">Account #".$db_user_game['account_id']." &nbsp;&nbsp; ".$app->format_bignum($db_user_game['account_value'])." ".$game->db_game['coin_abbreviation']."</option>\n";
								}
								?>
								<option value="new">Create a new account</option>
							</select>
						</div>
					</div>
				</div>
				<div class="panel-body">
					<?php
					if ($faucet_io) {
						echo '<p><button id="faucet_btn" class="btn btn-success" onclick="claim_from_faucet();"><i class="fas fa-hand-paper"></i> &nbsp; Claim '.$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].'</button></p>'."\n";
					}
					
					$game_status_explanation = $game->game_status_explanation($thisuser, $user_game);
					?>
					<div style="display: <?php if (false && $game->db_game['view_mode'] == "simple") echo "none"; else echo "block"; ?>; overflow: hidden;">
						<div id="game_status_explanation"<?php if ($game_status_explanation == "") echo ' style="display: none;"'; ?>><?php if ($game_status_explanation != "") echo $game_status_explanation; ?></div>
					</div>
					<?php
					if ($game->db_game['module'] == "CoinBattles") {
						$game->load_current_events();
						$event = $game->current_events[0];
						
						if (empty($event)) {
							echo "Chart canceled; there is no current event for this game.<br/>\n";
						}
						else {
							list($html, $js) = $game->module->currency_chart($game, $event->db_event['event_starting_block'], false);
							echo '<div style="margin-bottom: 15px;" id="game0_chart_html">'.$html."</div>\n";
							echo '<div id="game0_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
						}
					}
					?>
					
					<div class="row">
						<div class="col-md-6">
							<div style="overflow: auto; margin-bottom: 10px;">
								<div style="float: right;">
									<?php
									echo $game->event_filter_html();
									?>
								</div>
							</div>
							<div class="game_events game_events_long">
								<div id="game0_events" class="game_events_inner"></div>
							</div>
							
							<script type="text/javascript">
							<?php
							echo $new_event_js;
							?>
							</script>
						</div>
						<div class="col-md-6">
							<div id="betting_mode_inflationary" style="display: none;">
								<p style="float: right; clear: both;"><a href="" onclick="toggle_betting_mode('principal'); return false;">Switch to single betting mode</a></p>
								
								<p>
									<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">My Bets</a>
									&nbsp;&nbsp; <a href="" onclick="add_all_utxos_to_vote(); return false;">Add all coins</a>
									&nbsp;&nbsp; <a href="" onclick="remove_all_utxos_from_vote(); return false;">Remove all coins</a>
								</p>
								<p>
									To start a bet, click on your coins below.<br/>
								</p>
								
								<div id="select_input_buttons" class="input_buttons_holder"><?php
									echo $game->select_input_buttons($user_game);
								?></div>
								
								<div id="compose_vote" style="display: none;">
									<h3>Stake Now</h3>
									<div class="row bordered_row" style="border: 1px solid #bbb;">
										<div class="col-md-4 bordered_cell" id="compose_vote_inputs">
											<b>Inputs:</b><div style="display: none; margin-left: 20px;" id="input_amount_sum"></div><div style="display: inline-block; margin-left: 20px;" id="input_vote_sum"></div><br/>
											<p>
												How many <?php echo $game->db_game['coin_name_plural']; ?> do you want to spend?
												<input class="form-control input-sm" id="compose_burn_amount" placeholder="0" /><font id="max_burn_amount"></font>
											</p>
											<p id="compose_input_start_msg"></p>
										</div>
										<div class="col-md-8 bordered_cell" id="compose_vote_outputs">
											<b>Outputs:</b>
											<div id="display_tx_fee"></div>
											&nbsp;&nbsp; <a href="" onclick="add_all_options(); return false;">Add all options</a>
											&nbsp;&nbsp; <a href="" onclick="remove_all_outputs(); return false;">Remove all options</a>
											
											<select class="form-control" style="margin-top: 5px;" id="select_add_output" onchange="select_add_output_changed();"></select>
										</div>
									</div>
									<button class="btn btn-success" id="confirm_compose_vote_btn" style="margin-top: 5px; margin-left: 5px;" onclick="confirm_compose_vote();"><i class="fas fa-check-circle"></i> &nbsp; Confirm & Stake</button>
								</div>
								
								<div class="redtext" id="compose_vote_errors" style="margin-top: 10px;"></div>
								<div class="greentext" id="compose_vote_success" style="margin-top: 10px;"></div>
							</div>
							<div id="betting_mode_principal" style="display: none;">
								<p style="float: right;"><a href="" onclick="toggle_betting_mode('inflationary'); return false;">Switch to multiple betting mode</a></p>
								
								<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/my_bets/">My Bets</a>
								
								<form method="get" onsubmit="submit_principal_bet(); return false;" style="clear: both;">
									<div class="form-group">
										<label for="principal_amount">How much do you want to bet?</label>
										<div class="row">
											<div class="col-sm-6">
												<input class="form-control" type="text" id="principal_amount" name="principal_amount" style="text-align: right;" />
											</div>
											<div class="col-sm-6 form-control-static">
												<?php echo $game->db_game['coin_name_plural']; ?>
											</div>
										</div>
									</div>
									<div class="form-group">
										<label for="principal_option_id">Which option do you want to bet for?</label>
										<div class="row">
											<div class="col-sm-6">
												<select class="form-control" id="principal_option_id" name="principal_option_id"></select>
											</div>
										</div>
									</div>
									<div class="form-group">
										<label for="principal_fee">Transaction fee:</label>
										<div class="row">
											<div class="col-sm-6">
												<input class="form-control" type="text" id="principal_fee" name="principal_fee" style="text-align: right;" value="<?php echo rtrim($user_strategy['transaction_fee'], "0"); ?>" />
											</div>
											<div class="col-sm-6 form-control-static">
												<?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?>
											</div>
										</div>
									</div>
									<div class="form-group">
										<button class="btn btn-success" id="principal_bet_btn"><i class="fas fa-check-circle"></i> &nbsp; Confirm Bet</button>
										<div id="principal_bet_message" class="greentext" style="margin-top: 10px;"></div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php if ($game->db_game['public_players'] == 1) { ?>
		<div class="tabcontent" style="display: none;" id="tabcontent1">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">Play Now</div>
				</div>
				<div class="panel-body">
					<?php
					echo $game->render_game_players();
					?>
				</div>
			</div>
		</div>
		<?php } ?>
		<div id="tabcontent2" style="display: none;" class="tabcontent">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">Settings</div>
				</div>
				<div class="panel-body">
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
					<h3>Transaction Fees</h3>
					<form method="post" action="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">
						<input type="hidden" name="action" value="save_voting_strategy_fees" />
						<input type="hidden" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
						Pay fees on every transaction of:<br/>
						<div class="row">
							<div class="col-sm-4"><input class="form-control" name="transaction_fee" value="<?php echo $app->format_bignum($user_strategy['transaction_fee']); ?>" placeholder="0.0001" /></div>
							<div class="col-sm-4 form-control-static"><?php
							echo $game->blockchain->db_blockchain['coin_name_plural'];
							?></div>
						</div>
						<div class="row">
							<div class="col-sm-3">
								<button class="btn btn-primary" type="submit">Save</button>
							</div>
						</div>
					</form>
					<br/>
					
					<h3>Notifications</h3>
					<button class="btn btn-success" onclick="$('#notification_modal').modal('show');">Notification Settings</button>
					<br/>
					
					<h2>Choose your strategy</h2>
					<p>
						Select a staking strategy and your coins will automatically be staked even when you're not online.
					</p>
					<form method="post" action="/wallet/<?php echo $game->db_game['url_identifier']; ?>/">
						<input type="hidden" name="action" value="save_voting_strategy" />
						<input type="hidden" id="voting_strategy_id" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
						
						<div class="row bordered_row">
							<div class="col-md-2">
								<input type="radio" id="voting_strategy_manual" name="voting_strategy" value="manual"<?php if ($user_strategy['voting_strategy'] == "manual") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_manual">&nbsp;No&nbsp;auto-strategy</label>
							</div>
							<div class="col-md-10">
								<label class="plainlabel" for="voting_strategy_manual"> 
									I'll log in and vote in each round.
								</label>
							</div>
						</div>
						
						<div class="row bordered_row">
							<div class="col-md-2">
								<input type="radio" id="voting_strategy_api" name="voting_strategy" value="hit_url"<?php if ($user_strategy['voting_strategy'] == "hit_url") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_api">&nbsp;Hit URL</label>
							</div>
							<div class="col-md-10">
								<label class="plainlabel" for="hit_api_url">Hit this URL every minute</label>
								<input class="form-control" type="text" size="40" placeholder="http://" name="hit_api_url" id="hit_api_url" value="<?php echo $user_strategy['api_url']; ?>" />
							</div>
						</div>
						
						<div class="row bordered_row">
							<div class="col-md-2">
								<input type="radio" id="voting_strategy_api" name="voting_strategy" value="api"<?php if ($user_strategy['voting_strategy'] == "api") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_api">&nbsp;Vote&nbsp;by&nbsp;API</label>
							</div>
							<div class="col-md-10">
								<label class="plainlabel" for="voting_strategy_api">
									Hit a URL matching the PolyCash API format:
									<input class="form-control" type="text" size="40" placeholder="http://" name="api_url" id="api_url" value="<?php echo $user_strategy['api_url']; ?>" />
								</label><br/>
								<a href="/api/about/">API documentation</a><br/>
							</div>
						</div>
						
						<div class="row bordered_row">
							<div class="col-md-2">
								<input type="radio" id="voting_strategy_by_entity" name="voting_strategy" value="by_entity"<?php if ($user_strategy['voting_strategy'] == "by_entity") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_by_entity">&nbsp;Vote&nbsp;by&nbsp;option</label>
							</div>
							<div class="col-md-10">
								<label class="plainlabel" for="voting_strategy_by_entity"> 
									Vote for these options every time. The percentages you enter below must add up to 100.<br/>
									<?php /*<a href="" onclick="by_entity_reset_pct(); return false;">Set all to zero</a> <div style="margin-left: 15px; display: inline-block;" id="entity_pct_subtotal">&nbsp;</div>*/ ?>
								</label><br/>
								<?php
								$entities_by_game = $game->entities_by_game();
								$entity_i = 0;
								while ($entity = $entities_by_game->fetch()) {
									$pct_points = $app->run_query("SELECT * FROM user_strategy_entities WHERE strategy_id=:strategy_id AND entity_id=:entity_id;", [
										'strategy_id' => $user_strategy['strategy_id'],
										'entity_id' => $entity['entity_id']
									])->fetch()['pct_points'];
									
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
								<input type="radio" id="voting_strategy_by_plan" name="voting_strategy" value="by_plan"<?php if ($user_strategy['voting_strategy'] == "by_plan") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_by_plan">&nbsp;Plan&nbsp;my&nbsp;votes</label>
							</div>
							<div class="col-md-10">
								<button class="btn btn-success" onclick="show_planned_votes(); return false;">Edit my planned votes</button>
							</div>
						</div>
						
						<div class="row bordered_row">
							<div class="col-md-2">
								<input type="radio" id="voting_strategy_featured" name="voting_strategy" value="featured"<?php if ($user_strategy['voting_strategy'] == "featured") echo ' checked="checked"'; ?>><label class="plainlabel" for="voting_strategy_featured">&nbsp;Choose a strategy</label>
							</div>
							<div class="col-md-10">
								<button class="btn btn-success" onclick="show_featured_strategies(); return false;">Choose a strategy</button>
							</div>
						</div>
						
						<div class="row bordered_row">
							<div class="col-md-12">
								<br/><br/>
								<b>Settings</b><br/>
								These settings apply to "Plan my votes" and "Vote by option" options above.<br/>
								Wait until <input size="4" type="text" name="aggregate_threshold" id="aggregate_threshold" value="<?php echo $user_strategy['aggregate_threshold']; ?>" />% of my coins are available to vote. <br/>
								Only vote in these blocks of the round:<br/>
								
								<div style="border: 1px solid #bbb; padding: 10px; margin: 10px 0px; max-height: 200px; overflow-x: hidden; overflow-y: scroll;">
									<div class="row">
										<div class="col-md-2">
											<input type="checkbox" id="vote_on_block_all" onchange="vote_on_block_all_changed();" /><label class="plainlabel" for="vote_on_block_all">&nbsp;&nbsp;All</label>
										</div>
									</div>
									<div class="row">
										<?php
										for ($block=1; $block<=$game->db_game['round_length']; $block++) {
											echo '<div class="col-md-2">';
											echo '<input type="checkbox" name="vote_on_block_'.$block.'" id="vote_on_block_'.$block.'" value="1"';
											
											$strategy_block_q = "SELECT * FROM user_strategy_blocks WHERE strategy_id=:strategy_id AND block_within_round=:block_within_round;";
											$strategy_block_r = $app->run_query($strategy_block_q, [
												'strategy_id' => $user_strategy['strategy_id'],
												'block_within_round' => $block
											]);
											if ($strategy_block_r->rowCount() > 0) echo ' checked="checked"';
											
											echo '><label class="plainlabel" for="vote_on_block_'.$block.'">&nbsp;&nbsp;';
											echo $block."</label>";
											echo '</div>';
											if ($block%6 == 0) echo '</div><div class="row">';
										}
										?>
									</div>
								</div>
								
								Only vote for options which have between <input type="tel" size="4" value="<?php echo $user_strategy['min_votesum_pct']; ?>" name="min_votesum_pct" id="min_votesum_pct" />% and <input type="tel" size="4" value="<?php echo $user_strategy['max_votesum_pct']; ?>" name="max_votesum_pct" id="max_votesum_pct" />% of the current votes.<br/>
								<?php /*
								Maintain <input type="tel" size="6" id="min_coins_available" name="min_coins_available" value="<?php echo round($user_strategy['min_coins_available'], 2); ?>" /> EMP available at all times.  This number of coins will be reserved and won't be voted. */ ?>
							</div>
						</div>
						<br/>
						<button class="btn btn-primary" type="submit">Save my Strategy</button>
					</form>
					<br/>
				</div>
			</div>
		</div>
		<div id="tabcontent4" style="display: none;" class="tabcontent">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">Deposit or Withdraw</div>
				</div>
				<div class="panel-body">
					<?php
					if ($game->db_game['buyin_policy'] != "none") { ?>
						<p>
							You can buy <?php echo $game->db_game['coin_name_plural']; ?> by clicking below.  Once your payment is confirmed, <?php echo $game->db_game['coin_name_plural']; ?> will be added to your account based on the exchange rate at the time of confirmation.
						</p>
						<p>
							<button class="btn btn-success" onclick="manage_buyin('initiate');">Buy more <?php echo $game->db_game['coin_name_plural']; ?></button>
						</p>
						<?php
					}
					?>
					<p>
						You can also deposit <?php echo $game->db_game['coin_name_plural']; ?> directly to this account. Visit <a href="/accounts/?account_id=<?php echo $user_game['account_id']; ?>">My Accounts</a> to see a list of your addresses.
						<?php
						$game_account = $app->fetch_account_by_id($user_game['account_id']);
						
						if (!empty($game_account['current_address_id'])) {
							$default_address = $app->fetch_address_by_id($game_account['current_address_id']);
							echo "<br/>Or send ".$game->db_game['coin_name_plural']." to this address:<br/>\n";
							echo $default_address['address']."<br/>\n";
							echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$default_address['address'].'" />';
						}
						?>
					</p>
					<br/>
					
					<p>To withdraw <?php echo $game->db_game['coin_name_plural']; ?> please enter <?php echo $app->prepend_a_or_an($game->db_game['name']); ?> address below.</p>
					
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="withdraw_amount">Amount (<?php echo $game->db_game['coin_name_plural']; ?>):</label>
								<input class="form-control" type="tel" placeholder="0.000" id="withdraw_amount" style="text-align: right;" />
							</div>
							<div class="form-group">
								<label for="withdraw_fee">Fee (<?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?>):</label>
								<input class="form-control" type="tel" value="<?php echo $app->format_bignum($user_strategy['transaction_fee']); ?>" id="withdraw_fee" style="text-align: right;" />
							</div>
							<div class="form-group">
								<label for="withdraw_address">Address:</label>
								<input class="form-control" type="text" id="withdraw_address" />
							</div>
							<div class="form-group">
								<button class="btn btn-success" id="withdraw_btn" onclick="attempt_withdrawal();">Send <?php echo $game->db_game['coin_name_plural']; ?></button>
								<div id="withdraw_message" style="display: none; margin-top: 15px;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div class="tabcontent" style="display: none;" id="tabcontent5">
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">Invitations</div>
				</div>
				<div class="panel-body">
					<?php
					$perm_to_invite = $thisuser->user_can_invite_game($user_game);
					if ($perm_to_invite) {
						echo '<a class="btn btn-primary" href="" onclick="manage_game_invitations('.$game->db_game['game_id'].'); return false;">Invitations</a>';
					}
					else echo "Sorry, you don't have permission to send invitations for this game.";
					?>
				</div>
			</div>
		</div>
		
		<div class="modal fade" id="intro_message">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">New message from <?php echo AppSettings::getParam('site_name'); ?></h4>
					</div>
					<div class="modal-body">
						<p>
							Hi <?php echo $thisuser->db_user['username']; ?>, thanks for joining <?php echo $game->db_game['name']; ?>!
						</p>
						<p>
							It's recommended that you select an auto strategy so that your account will gain value while you sleep. You can change your auto strategy at any time by logging in and clicking the "Settings" tab to the left.
						</p>
						<p>
							<button class="btn btn-primary" onclick="$('#intro_message').modal('hide'); show_featured_strategies();">Choose an auto-strategy</button>
						</p>
					</div>
				</div>
			</div>
		</div>
		
		<div class="modal fade" id="notification_modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Notification Settings</h4>
					</div>
					<div class="modal-body">
						<div class="form-group">
							<label for="notification_preference">Would you like to receive notifications about the performance of your accounts?</label>
							<select class="form-control" id="notification_preference" name="notification_preference" onchange="notification_pref_changed();">
								<option <?php if ($user_game['notification_preference'] == "none") echo 'selected="selected" '; ?>value="none">No, don't notify me</option>
								<option <?php if ($user_game['notification_preference'] == "email") echo 'selected="selected" '; ?>value="email">Yes, send me email notifications</option>
							</select>
						</div>
						<div class="form-group">
							<input <?php if ($user_game['notification_preference'] == "none") echo 'style="display: none;" '; ?>class="form-control" type="text" name="notification_email" id="notification_email" placeholder="Enter your email address" value="<?php echo $thisuser->db_user['notification_email']; ?>" />
						</div>
						
						<button id="notification_save_btn" class="btn btn-primary" onclick="save_notification_preferences();"><i class="fas fa-check-circle"></i> &nbsp; Save Notification Settings</button>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="featured_strategies">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title">Please select a staking strategy</h4>
					</div>
					<div class="modal-body" id="featured_strategies_inner"></div>
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
		
		<div style="display: none;" class="modal fade" id="set_event_outcome_modal">
			<div class="modal-dialog">
				<div class="modal-content" id="set_event_outcome_modal_content"></div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="buyin_modal">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title"><?php echo $game->db_game['name']; ?>: Buy more <?php echo $game->db_game['coin_name_plural']; ?></h4>
					</div>
					<div class="modal-body">
						<div id="buyin_modal_content"></div>
						<div id="buyin_modal_details" style="margin-top: 10px;"></div>
						<div id="buyin_modal_invoices"></div>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="sellout_modal">
			<div class="modal-dialog modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title"><?php echo $game->db_game['name']; ?>: Sell your <?php echo $game->db_game['coin_name_plural']; ?></h4>
					</div>
					<div class="modal-body">
						<div id="sellout_modal_content"></div>
						<div id="sellout_modal_details" style="margin-top: 10px;"></div>
						<div id="sellout_modal_invoices"></div>
					</div>
				</div>
			</div>
		</div>
		
		<br/><br/>
		<?php
	}
	else {
		if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
		else {
			$uri = str_replace("?action=logout", "", $_SERVER['REQUEST_URI']);
			$redirect_url = $app->get_redirect_url($uri);
		}
		include(AppSettings::srcPath()."/includes/html_login.php");
	}
	?>
</div>
<?php

include(AppSettings::srcPath().'/includes/html_stop.php');
?>
