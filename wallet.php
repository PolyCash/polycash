<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$message = "";

if ($_REQUEST['do'] == "signup") {
	$first = mysql_real_escape_string(strip_tags(make_alphanumeric($_POST['first'], "")));
	$last = mysql_real_escape_string(strip_tags(make_alphanumeric($_POST['last'], "")));
	$email = mysql_real_escape_string(strip_tags($_POST['email']));
	
	$query = "SELECT * FROM users WHERE username='".$email."';";
	$result = run_query($query);
	
	$query = "SELECT * FROM users WHERE ip_address='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."';";
	$r = run_query($query);
	
	if (mysql_numrows($r) <= 20) {
		if (mysql_numrows($result) == 0) {
			if ($GLOBALS['signup_captcha_required']) {
				$recaptcha_resp = recaptcha_check_answer($GLOBALS['recaptcha_privatekey'], $_SERVER["REMOTE_ADDR"], $_POST["g-recaptcha-response"]);
			}
			if (!$recaptcha_resp && $GLOBALS['signup_captcha_required']) {
				$acode = 0;
				$message = "You entered the wrong CAPTCHA code. Please try again. ";
			}
			else {
				$pass_error = FALSE;
				
				if ($_REQUEST['autogen_password'] == "1") {
					$new_pass = random_string(10);
					$new_pass_hash = hash('sha256', $new_pass);
				}
				else {
					$new_pass = $_REQUEST['password'];
					if ($_REQUEST['password2'] != $new_pass) $pass_error = TRUE;
					$new_pass_hash = $new_pass;
				}
				
				if ($pass_error) {
					$acode = 0;
					$message = "The passwords that you entered did not match. Please try again. ";
				}
				else {
					$verify_code = random_string(32);
					
					$query = "INSERT INTO users SET game_id='".get_site_constant('primary_game_id')."', first_name='".$first."', last_name='".$last."', username='".$email."', notification_email='".$email."', api_access_code='".mysql_real_escape_string(random_string(32))."', password='".$new_pass_hash."', ip_address='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."', time_created='".time()."', verify_code='".$verify_code."';";
					$result = run_query($query);
					$user_id = mysql_insert_id();
					
					$acode = 1;
					if ($_REQUEST['autogen_password'] == "1") {
						$message = "Your account has been created.  A new password was generated and emailed to your inbox.  Please check your inbox and then log in below. ";
					}
					else {
						$message = "Your account has been created.  Please log in below. ";
					}
					
					if ($GLOBALS['pageview_tracking_enabled']) {
						$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$thisuser['user_id']."';";
						$r = run_query($q);
						if (mysql_numrows($r) == 0) {
							$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$thisuser['user_id']."';";
							$r = run_query($q);
						}
						$session_key = session_id();
						$expire_time = time()+3600*24;
						
						$query = "UPDATE users SET ip_address='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."' WHERE user_id='".$thisuser['user_id']."';";
						$result = run_query($query);
					}
					
					if ($_REQUEST['autogen_password'] == "1") {
						$email_message = "<p>You've created a new ".$GLOBALS['site_name_short']." web wallet for <b>".$email."</b>.</p>";
						$email_message .= "<p>A password was automatically generated for your user account.<p>";
						$email_message .= "<p>Your password is: ".$new_pass."</p>";
						$email_message .= "<p>If you'd like to set a new password, please visit: ".$GLOBALS['base_url']."/reset_password/</p>";
						$email_message .= "<p>Thanks for signing up!</p>";
						$email_message .= "<p>This message was sent to you by ".$GLOBALS['base_url']."</p>";
					}
					else {
						$email_message = "<p>A new ".$GLOBALS['site_name_short']." web wallet has been created for <b>".$email."</b>.</p>";
						$email_message .= "<p>Thanks for signing up!</p>";
						$email_message .= "<p>To log in any time please visit ".$GLOBALS['base_url']."/wallet/</p>";
						$email_message .= "<p>This message was sent to you by ".$GLOBALS['base_url']."</p>";
					}
					
					$email_id = mail_async($email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], "New account created", $email_message, "", "");
					
					if ($_REQUEST['invite_key'] != "") {
						try_apply_invite_key(get_site_constant('primary_game_id'), $user_id, $_REQUEST['invite_key']);
					}
					
					$q = "SELECT * FROM games WHERE creator_id IS NULL;";
					$r = run_query($q);
					while ($mandatory_game = mysql_fetch_array($r)) {
						ensure_user_in_game($user_id, $mandatory_game['game_id']);
					}
				}
			}
		}
		else {
			$acode = 0;
			$message = "Sorry the email address you entered is already registered.";
		}
	}
	else {
		$acode = 0;
		$message = "Sorry, there was an error creating your new account.";
	}
}
else if ($_REQUEST['do'] == "login") {
	$username = mysql_real_escape_string($_POST['username']);
	$password = mysql_real_escape_string($_POST['password']);
	
	$query = "SELECT * FROM users WHERE username='".$username."' AND password='".$password."';";
	$result = run_query($query);
	
	$message = "";
	$login_error = TRUE;
	
	if (mysql_numrows($result) == 0) {
		$message = "Incorrect username or password - try again.";
		$acode = 0;
	}
	else if (mysql_numrows($result) == 1) {
		$q = "SELECT * FROM users WHERE username='".$username."' AND password='".$password."' AND verified=1;";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$message = "Login successful - redirecting...";
			$login_error = FALSE;
		}
		else {
			$result2 = run_query($query);
			$user_tmp = mysql_fetch_array($result2);
			$message = "Your account is not yet verified.";
			$acode = 0;
		}
	} else {
		$message = "Sorry, a duplicate user account was found.";
		$acode = 0;
	}
	
	if ($login_error == FALSE) {		
		$thisuser = mysql_fetch_array($result);
		
		$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 0) {
			$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$thisuser['user_id']."';";
			$r = run_query($q);
		}
		$session_key = session_id();
		$expire_time = time()+3600*24;
		
		$query = "INSERT INTO user_sessions (user_id, session_key, login_time, logout_time, expire_time, ip_address) VALUES ('".$thisuser['user_id']."', '".$session_key."', '".time()."', 0, '".$expire_time."', '".$_SERVER['REMOTE_ADDR']."');";
		$result = run_query($query);
		
		$query = "UPDATE users SET logged_in=1, ip_address='".$_SERVER['REMOTE_ADDR']."' WHERE user_id='".$thisuser['user_id']."';";
		$result = run_query($query);
		
		$redirect_url_id = intval($_REQUEST['redirect_id']);
		
		$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
		$r = run_query($q);
		
		if ($_REQUEST['invite_key'] != "") {
			try_apply_invite_key($thisuser['game_id'], $thisuser['user_id'], $_REQUEST['invite_key']);
		}
		
		if (mysql_numrows($r) == 1) {
			$redirect_url = mysql_fetch_array($r);
			header("Location: ".$redirect_url['url']);
		}
		else header("Location: /wallet/");
		
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
else if ($thisuser && ($_REQUEST['do'] == "save_voting_strategy" || $_REQUEST['do'] == "save_voting_strategy_fees")) {
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
			
			$acode = 1;
			$message = "Great, your transaction fee has been updated!";
		}
		else {
			$acode = 0;
			$message = "Error: that fee amount is invalid, your changes were not saved.";
		}
	}
	else {
		if (in_array($voting_strategy, array('manual', 'by_rank', 'by_nation', 'api'))) {
			for ($i=1; $i<=$game['num_voting_options']; $i++) {
				if ($_REQUEST['by_rank_'.$i] == "1") $by_rank_csv .= $i.",";
			}
			if ($by_rank_csv != "") $by_rank_csv = substr($by_rank_csv, 0, strlen($by_rank_csv)-1);
			
			$q = "UPDATE user_strategies SET voting_strategy='".$voting_strategy."'";
			if ($aggregate_threshold >= 0 && $aggregate_threshold <= 100) {
				$q .= ", aggregate_threshold='".$aggregate_threshold."'";
			}
			for ($block=1; $block<=9; $block++) {
				if ($_REQUEST['vote_on_block_'.$block] == "1") $vote_on_block = "1";
				else $vote_on_block = "0";
				$q .= ", vote_on_block_".$block."=".$vote_on_block;
			}
			
			$nation_pct_sum = 0;
			$nation_pct_q = "";
			$nation_pct_error = FALSE;
			
			for ($nation_id=1; $nation_id<=$game['num_voting_options']; $nation_id++) {
				$nation_pct = intval($_REQUEST['nation_pct_'.$nation_id]);
				$nation_pct_q .= ", nation_pct_".$nation_id."=".$nation_pct;
				$nation_pct_sum += $nation_pct;
			}
			if ($nation_pct_sum == 100) $q .= $nation_pct_q;
			else $nation_pct_error = TRUE;
			
			$min_votesum_pct = intval($_REQUEST['min_votesum_pct']);
			$max_votesum_pct = intval($_REQUEST['max_votesum_pct']);
			if ($max_votesum_pct > 100) $max_votesum_pct = 100;
			if ($min_votesum_pct < 0) $min_votesum_pct = 0;
			if ($max_votesum_pct < $min_votesum_pct) $max_votesum_pct = $min_votesum_pct;
			
			$min_coins_available = round($_REQUEST['min_coins_available'], 3);
			
			$q .= ", min_coins_available='".$min_coins_available."', max_votesum_pct='".$max_votesum_pct."', min_votesum_pct='".$min_votesum_pct."', by_rank_ranks='".$by_rank_csv."', api_url='".$api_url."'";
			$q .= " WHERE strategy_id='".$user_strategy['strategy_id']."';";
			$r = run_query($q);
			
			if ($nation_pct_error && $voting_strategy == "by_nation") {
				$q = "UPDATE user_strategies SET voting_strategy='".$user_strategy['voting_strategy']."' WHERE strategy_id='".$user_strategy['strategy_id']."';";
				$r = run_query($q);
				$voting_strategy = $user_strategy['voting_strategy'];
				
				$acode = 0;
				$message = "Error: voting strategy couldn't be set to \"Vote by nation\", the percentages you entered didn't add up to 100%.";
			}
			
			$q = "UPDATE user_games SET strategy_id='".$user_strategy['strategy_id']."' WHERE game_id='".$thisuser['game_id']."' AND user_id='".$thisuser['user_id']."';";
			$r = run_query($q);
		}
	}
}

$pagetitle = $GLOBALS['site_name_short']." - My web wallet";
$nav_tab_selected = "wallet";
include('includes/html_start.php');

if ($thisuser) {
	$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game['game_id']."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$user_game = mysql_fetch_array($r);
		generate_user_addresses($user_game);
	}
	else {
		ensure_user_in_game($thisuser['user_id'], $game['game_id']);
		
		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser['user_id']."' AND ug.game_id='".$game['game_id']."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$user_game = mysql_fetch_array($r);
			generate_user_addresses($user_game);
		}
	}
}

$initial_tab = 0;
$account_value = account_coin_value($game, $thisuser);
$immature_balance = immature_balance($game, $thisuser);
$last_block_id = last_block_id($thisuser['game_id']);
$current_round = block_to_round($game, $last_block_id+1);
$block_within_round = block_id_to_round_index($game, $last_block_id+1);
$mature_balance = $account_value - $immature_balance;
?>
<div class="container" style="max-width: 1000px;">
	<?php
	if ($message != "") {
		echo "<font style=\"display: block; margin: 10px 0px; color: #";
		if ($acode == 0) echo "f00";
		else echo "0a0";
		echo "\">";
		echo $message;
		echo "</font>\n";
	}
	
	if ($thisuser) {
		$user_game = FALSE;
		$user_strategy = FALSE;
		
		$q = "SELECT * FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$thisuser['game_id']."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$user_game = mysql_fetch_array($r);
			
			$q = "SELECT * FROM user_strategies WHERE strategy_id='".$user_game['strategy_id']."';";
			$r = run_query($q);
			if (mysql_numrows($r) == 1) {
				$user_strategy = mysql_fetch_array($r);
			}
		}
		
		$round_stats = round_voting_stats_all($game, $current_round);
		$total_vote_sum = $round_stats[0];
		$max_vote_sum = $round_stats[1];
		$nation_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		?>
		<script type="text/javascript">
		var current_tab = 0;
		var last_block_id = <?php echo $last_block_id; ?>;
		var last_transaction_id = <?php echo last_transaction_id($thisuser['game_id']); ?>;
		var my_last_transaction_id = <?php
		$my_last_transaction_id = my_last_transaction_id($thisuser['user_id'], $thisuser['game_id']);
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
		var min_bet_round = parseInt(<?php
			$bet_round_range = bet_round_range($game);
			echo $bet_round_range[0];
		?>);
		
		var selected_nation_id = false;
		
		var initial_notification_pref = "<?php echo $thisuser['notification_preference']; ?>";
		var initial_notification_email = "<?php echo $thisuser['notification_email']; ?>";
		var started_checking_notification_settings = false;
		var initial_alias_pref = "<?php echo $thisuser['alias_preference']; ?>";
		var initial_alias = "<?php echo $thisuser['alias']; ?>";
		var started_checking_alias_settings = false;
		var performance_history_sections = 1;
		var performance_history_start_round = <?php echo max(1, $current_round-10); ?>;
		var performance_history_loading = false;
		
		var nation_has_votingaddr = [];
		for (var i=1; i<=16; i++) { nation_has_votingaddr[i] = false; }
		var votingaddr_count = 0;
		
		var user_logged_in = true;
		
		var refresh_page = "wallet";
		
		function load_nations() {
			nations.push(new nation(0, 'No Winner'));<?php
			$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
			$r = run_query($q);
			while ($nation = mysql_fetch_array($r)) {
				echo "\n\t\t\tnations.push(new nation(".$nation['nation_id'].", '".$nation['name']."'));";
				$votingaddr_id = user_address_id($game['game_id'], $thisuser['user_id'], $nation['nation_id']);
				if ($votingaddr_id !== false) {
					echo "\n\t\t\tnation_has_votingaddr[".$nation['nation_id']."] = true;";
					echo "\n\t\t\tvotingaddr_count++;";
				}
			}
		?>}
		
		<?php if ($game['losable_bets_enabled'] == 1) { ?>
		google.load("visualization", "1", {packages:["corechart"]});
		<?php } ?>
		
		$(document).ready(function() {
			load_nations();
			notification_pref_changed();
			alias_pref_changed();
			nation_selected(0);
			loop_event();
			game_loop_event();
			compose_vote_loop();
			<?php if ($game['losable_bets_enabled'] == 1) { ?>
			bet_loop();
			<?php } ?>
		});
		
		$(document).keypress(function (e) {
			if (e.which == 13) {
				var selected_nation_db_id = $('#rank2nation_id_'+selected_nation_id).val();
				
				if ($('#vote_amount_'+selected_nation_db_id).is(":focus")) {
					confirm_vote(selected_nation_db_id);
				}
			}
		});
		</script>
		
		<h1><?php
		if ($game['game_id'] != get_site_constant('primary_game_id')) echo $GLOBALS['site_name_short']." - ";
		echo $game['name'];
		if ($game['game_status'] == "paused" || $game['game_status'] == "unstarted") echo " (Paused)";
		?></h1>
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
		
		<div id="vote_popups"><?php	echo initialize_vote_nation_details($game, $nation_id2rank, $total_vote_sum, $thisuser['user_id']); ?></div>
		
		<div class="row">
			<div class="col-xs-2 tabcell" id="tabcell0" onclick="tab_clicked(0);">Play&nbsp;Now</div>
			<?php if ($game['losable_bets_enabled'] == 1) { ?>
			<div class="col-xs-2 tabcell" id="tabcell5" onclick="tab_clicked(4);">Gamble</div>
			<?php } ?>
			<div class="col-xs-2 tabcell" id="tabcell1" onclick="tab_clicked(1);">Settings</div>
			<div class="col-xs-2 tabcell" id="tabcell2" onclick="tab_clicked(2);">My&nbsp;Results</div>
			<div class="col-xs-2 tabcell" id="tabcell3" onclick="tab_clicked(3);">Deposit&nbsp;or&nbsp;Withdraw</div>
		</div>
		<div class="row">
			<div id="tabcontent0" class="tabcontent">
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
				
				<br/>To cast a vote please click on any of the empires below.<br/>
				<div id="vote_popups_disabled"<?php if ($block_within_round == $game['round_length']) echo ' style="display: none;"'; ?>>
					The final block of the round is being mined. Voting is currently disabled.
				</div>
				<div id="select_input_buttons"><?php
					echo select_input_buttons($thisuser['user_id'], $game);
				?></div>
				<div id="compose_vote" style="display: none;">
					<h2>Compose Your Voting Transaction</h2>
					<div class="row bordered_row" style="border: 1px solid #bbb;">
						<div class="col-md-6 bordered_cell" id="compose_vote_inputs">
							<b>Inputs:</b><div style="display: inline-block; margin-left: 20px;" id="input_amount_sum"></div><div style="display: inline-block; margin-left: 20px;" id="input_vote_sum"></div><br/>
							<div id="compose_input_start_msg">Add inputs by clicking on the coin blocks above.</div>
						</div>
						<div class="col-md-6 bordered_cell" id="compose_vote_outputs">
							<b>Outputs:</b><br/>
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
				if ($game['game_type'] == "simulation" && $thisuser['user_id'] == $game['creator_id']) {
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
			<div id="tabcontent1" style="display: none;" class="tabcontent">
				<h2>My Games</h2>
				<?php
				$q = "SELECT * FROM games g, user_games ug WHERE g.game_id=ug.game_id AND ug.user_id='".$thisuser['user_id']."';";
				$r = run_query($q);
				
				while ($user_game = mysql_fetch_array($r)) {
					?>
					<div class="row game_row">
						<div class="col-sm-6 game_cell">
							<?php
							echo '<a id="fetch_game_link_'.$user_game['game_id'].'" href="" onclick="switch_to_game('.$user_game['game_id'].', \'fetch\'); return false;"';
							if ($user_game['game_id'] == $game['game_id']) echo  ' class="selected_link"';
							echo '>';
							echo $user_game['name'];
							echo "</a>";
							?>
						</div>
					</div>
					<?php
				}
				?>
				<div class="row game_row">
					<div class="col-sm-6 game_cell">
						<a href="" onclick="switch_to_game(0, 'new'); return false;">Start a new Practice Game</a>
					</div>
				</div>
				
				<h2>Transaction Fees</h2>
				<form method="post" action="/wallet/">
					<input type="hidden" name="do" value="save_voting_strategy_fees" />
					<input type="hidden" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
					Pay fees on every transaction of:<br/>
					<div class="row">
						<div class="col-sm-4"><input class="form-control" name="transaction_fee" value="<?php echo $user_strategy['transaction_fee']/pow(10,8); ?>" placeholder="0.001" /></div>
						<div class="col-sm-4">coins</div>
					</div>
					<div class="row">
						<div class="col-sm-3">
							<input class="btn btn-primary" type="submit" value="Save" />
						</div>
					</div>
				</form>
				
				<h2>Notifications</h2>
				You can receive notifications whenever your coins are unlocked and ready to vote.<br/>
				<div class="row">
					<div class="col-sm-6">
						<select class="form-control" id="notification_preference" name="notification_preference" onfocus="notification_focused();" onchange="notification_pref_changed();">
							<option <?php if ($thisuser['notification_preference'] == "none") echo 'selected="selected" '; ?>value="none">Don't send me any notifications</option>
							<option <?php if ($thisuser['notification_preference'] == "email") echo 'selected="selected" '; ?>value="email">Send me an email notification when coins become available</option>
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
				
				<h2>Choose your voting strategy</h2>
				Instead of logging in every time you want to cast a vote, you can automate your voting behavior by choosing one of the automated voting strategies below. <br/><br/>
				<form method="post" action="/wallet/">
					<input type="hidden" name="do" value="save_voting_strategy" />
					<input type="hidden" name="voting_strategy_id" value="<?php echo $user_strategy['strategy_id']; ?>" />
					
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_manual" name="voting_strategy" value="manual"<?php if ($user_strategy['voting_strategy'] == "manual") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_manual">&nbsp;Vote&nbsp;manually</label>
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
								Hit a custom URL whenever I have coins available to determine my votes: <input type="text" size="40" placeholder="http://" name="api_url" id="api_url" value="<?php echo $user_strategy['api_url']; ?>" />
							</label><br/>
							Your API access code is <?php echo $thisuser['api_access_code']; ?> <a href="/api/about/">API documentation</a><br/>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_nation" name="voting_strategy" value="by_nation"<?php if ($user_strategy['voting_strategy'] == "by_nation") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_nation">&nbsp;Vote&nbsp;by&nbsp;nation</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_by_nation"> 
								Vote for these nations every time. The percentages you enter below must add up to 100.<br/>
								<a href="" onclick="by_nation_reset_pct(); return false;">Set all to zero</a> <div style="margin-left: 15px; display: inline-block;" id="nation_pct_subtotal">&nbsp;</div>
							</label><br/>
							<?php
							$q = "SELECT * FROM nations ORDER BY nation_id ASC;";
							$r = run_query($q);
							$nation_i = 0;
							while ($nation = mysql_fetch_array($r)) {
								if ($nation_i%4 == 0) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="tel" size="4" name="nation_pct_'.$nation['nation_id'].'" id="nation_pct_'.$nation['nation_id'].'" placeholder="0" value="'.$user_strategy['nation_pct_'.$nation['nation_id']].'" />';
								echo '<label class="plainlabel" for="nation_pct_'.$nation['nation_id'].'">% ';
								echo $nation['name']."</label>";
								echo '</div>';
								if ($nation_i%4 == 3) echo "</div>\n";
								$nation_i++;
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
								Split up my free balance and vote it across nations ranked:
							</label><br/>
							<input type="checkbox" id="rank_check_all" onchange="rank_check_all_changed();" /><label class="plainlabel" for="rank_check_all"> All</label><br/>
							<?php
							$by_rank_ranks = explode(",", $user_strategy['by_rank_ranks']);
							
							for ($rank=1; $rank<=16; $rank++) {
								if ($rank%4 == 1) echo '<div class="row">';
								echo '<div class="col-md-3">';
								echo '<input type="checkbox" name="by_rank_'.$rank.'" id="by_rank_'.$rank.'" value="1"';
								if (in_array($rank, $by_rank_ranks)) echo ' checked="checked"';
								echo '><label class="plainlabel" for="by_rank_'.$rank.'"> ';
								echo $rank.date("S", strtotime("1/".$rank."/2015"))."</label>";
								echo '</div>';
								if ($rank%4 == 0) echo "</div>\n";
							}
							?>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-12">
							<br/><br/>
							<b>Settings</b><br/>
							These settings apply to "Vote by nation" and "Vote by rank" options above.<br/>
							Wait until <input size="4" type="text" name="aggregate_threshold" id="aggregate_threshold" value="<?php echo $user_strategy['aggregate_threshold']; ?>" />% of my coins are available to vote. <br/>
							Only vote in these blocks of the round:<br/>
							<div class="row">
								<div class="col-md-2">
									<input type="checkbox" id="vote_on_block_all" onchange="vote_on_block_all_changed();" /><label class="plainlabel" for="vote_on_block_all"> All</label>
								</div>
								<?php
								for ($block=1; $block<=9; $block++) {
									echo '<div class="col-md-2">';
									echo '<input type="checkbox" name="vote_on_block_'.$block.'" id="vote_on_block_'.$block.'" value="1"';
									if ($user_strategy['vote_on_block_'.$block] == 1) echo ' checked="checked"';
									echo '><label class="plainlabel" for="vote_on_block_'.$block.'"> ';
									echo $block.date("S", strtotime("1/".$block."/2015"))."</label>";
									echo '</div>';
									if ($block == 4) echo '</div><div class="row">';
								}
								?>
							</div>
							Only vote for nations which have between <input type="tel" size="4" value="<?php echo $user_strategy['min_votesum_pct']; ?>" name="min_votesum_pct" id="min_votesum_pct" />% and <input type="tel" size="4" value="<?php echo $user_strategy['max_votesum_pct']; ?>" name="max_votesum_pct" id="max_votesum_pct" />% of the current votes.<br/>
							Maintain <input type="tel" size="6" id="min_coins_available" name="min_coins_available" value="<?php echo round($user_strategy['min_coins_available'], 2); ?>" /> EMP available at all times.  This number of coins will be reserved and won't be voted.
						</div>
					</div>
					<br/>
					<input class="btn btn-primary" type="submit" value="Save Voting Strategy" />
				</form>
			</div>
			<div id="tabcontent2" style="display: none;" class="tabcontent">
				<div id="performance_history">
					<div id="performance_history_0">
						<?php
						echo performance_history($thisuser, max(1, $current_round-10), $current_round-1);
						?>
					</div>
				</div>
				<center>
					<a href="" onclick="show_more_performance_history(); return false;">Show More</a>
				</center>
			</div>
			<div id="tabcontent3" style="display: none;" class="tabcontent">
				<h1>Deposit</h1>
				
				<div id="giveaway_div">
					<?php
					$giveaway_avail_msg = 'You\'re eligible for a one time coin giveaway of '.number_format($game['giveaway_amount']/pow(10,8)).' EmpireCoins.<br/>';
					$giveaway_avail_msg .= '<button class="btn btn-success" onclick="claim_coin_giveaway();" id="giveaway_btn">Claim '.number_format($game['giveaway_amount']/pow(10,8)).' EmpireCoins</button><br/>';
					
					if ($game['game_type'] == "simulation" && ($game['giveaway_status'] == "on" || $game['giveaway_status'] == "invite_only")) {
						if ($game['giveaway_status'] == "invite_only") {
							$q = "SELECT * FROM invitations WHERE used_user_id='".$thisuser['user_id']."' AND used_time=0 AND used=0;";
							$r = run_query($q);
							
							if (mysql_numrows($r) > 0) {
								$initial_tab = 3;
								echo $giveaway_avail_msg;
							}
						}
						else {
							$q = "SELECT * FROM webwallet_transactions t JOIN transaction_IOs io ON t.transaction_id=io.create_transaction_id WHERE io.game_id='".$game['game_id']."' AND io.user_id='".$thisuser['user_id']."' AND t.transaction_desc='giveaway';";
							$r = run_query($q);
							
							if (mysql_numrows($r) > 0) {}
							else {
								$initial_tab = 3;
								echo $giveaway_avail_msg;
							}
						}
					}
					?>
				</div>
				
				<?php
				$q = "SELECT * FROM addresses a LEFT JOIN nations n ON n.nation_id=a.nation_id WHERE a.game_id='".$thisuser['game_id']."' AND a.user_id='".$thisuser['user_id']."' ORDER BY a.nation_id IS NULL ASC, a.nation_id ASC;";
				$r = run_query($q);
				?>
				<b>You have <?php echo mysql_numrows($r); ?> addresses.</b><br/>
				<?php
				while ($address = mysql_fetch_array($r)) {
					?>
					<div class="row">
						<div class="col-sm-3">
							<?php if ($address['nation_id'] > 0) { ?>
							<img style="height: 12px; border: 1px solid rgba(0,0,0,0.5);" src="/img/flags/<?php echo $address['name']; ?>.jpg"> <?php echo $address['name']; ?>
							<?php } else { ?>
							Default Address
							<?php } ?>
						</div>
						<div class="col-sm-1">
							<a target="_blank" href="/explorer/addresses/<?php echo $address['address']; ?>">Explore</a>
						</div>
						<div class="col-sm-5">
							<input type="text" style="border: 0px; background-color: none; width: 100%; font-family: consolas" onclick="$(this).select();" value="<?php echo $address['address']; ?>" />
						</div>
					</div>
					<?php
				}
				?>
				
				<h1>Withdraw</h1>
				To withdraw coins, please enter an amount and an EmpireCoin address below then click "Withdraw"<br/>
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
							$q = "SELECT * FROM addresses a LEFT JOIN nations n ON n.nation_id=a.nation_id WHERE a.game_id='".$thisuser['game_id']."' AND a.user_id='".$thisuser['user_id']."' ORDER BY a.nation_id IS NULL ASC, a.nation_id ASC;";
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
			</div>
			<?php if ($game['losable_bets_enabled'] == 1) { ?>
				<div class="tabcontent" style="display: none;" id="tabcontent4">
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
					To place a bet, you'll burn your empirecoins by sending them to an unredeemable address. Once the outcome of the voting round is determined, the EmpireCoin protocol will check to see if you bet correctly and if so, new coins will be created and sent to your wallet.  These are pari-mutuel style bets in which your payout multiplier may continue changing until the betting period is over.  You can bet on the outcome of a round until the fifth block of the round.  Bets confirmed in the sixth block of a round or later are considered invalid and will be refunded back to the bettor, but with a 10% fee applied.  To place a bet, please select a round which you'd like to bet for and select one or more empires that you expect to win the round.
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
							<select class="form-control" id="bet_nation" onchange="add_bet_nation();">
								<option value="">-- Please Select --</option>
								<option value="0">No winner</option>
								<?php
								$q = "SELECT * FROM nations ORDER BY name ASC;";
								$r = run_query($q);
								while ($nation = mysql_fetch_array($r)) {
									echo "<option value=\"".$nation['nation_id']."\">".$nation['name']." wins</option>\n";
								}
								?>
							</select>
						</div>
						<div class="col-md-2">
							<a href="" onclick="add_all_bet_nations(); return false;">Add all</a>
						</div>
					</div>
					<div class="row">
						<div class="col-md-push-3 col-md-9" id="nation_bet_disp"></div>
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
			
		<div style="display: none;" class="modal fade" id="game_form">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
						<form onsubmit="save_game();">
							<div class="row" style="text-align: center;">
								<h1 id="game_form_name"></h1>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">
									Game status:
								</div>
								<div class="col-sm-6">
									<select class="form-control" id="game_form_game_status">
										<option value="unstarted">Not started</option>
										<option value="paused">Paused</option>
										<option value="running">Running</option>
									</select>
									<br/>
									<button class="btn btn-danger" onclick="switch_to_game(editing_game_id, 'delete'); return false;">Delete Game</button>
									<button class="btn btn-warning" onclick="switch_to_game(editing_game_id, 'reset'); return false;">Reset Game</button>
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
									<select class="form-control" type="text" id="game_form_payout_weight">
										<option value="coin">Coins staked</option>
										<option value="coin_block">Coins over time (coin blocks)</option>
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
									<select class="form-control" id="game_form_giveaway_status">
										<option value="on">Yes</option>
										<option value="off">No</option>
										<option value="invite_only">Invite Only</option>
									</select>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-6 form-control-static">Initial coins given to each player:</div>
								<div class="col-sm-3">
									<input class="form-control" style="text-align: right;" type="text" id="game_form_giveaway_amount" />
								</div>
								<div class="col-sm-3 form-control-static">
									coins
								</div>
							</div>
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
							
							<button id="save_game_btn" type="button" class="btn btn-success" onclick="save_game();">Save Game Settings</button>
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
							
							<button id="switch_game_btn" style="float: right;" type="button" class="btn btn-primary" onclick="switch_to_game(editing_game_id, 'switch');">Switch to this game</button>
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