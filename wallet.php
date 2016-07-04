<?php
include('includes/connect.php');
include('includes/get_session.php');
$viewer_id = insert_pageview($thisuser);

$message = "";

if ($_REQUEST['do'] == "signup") {
	$first = mysql_real_escape_string(strip_tags(make_alphanumeric($_POST['first'], "")));
	$last = mysql_real_escape_string(strip_tags(make_alphanumeric($_POST['last'], "")));
	$email = mysql_real_escape_string(strip_tags($_POST['email']));
	
	$query = "SELECT * FROM users WHERE username='".$email."';";
	$result = run_query($query);
	
	$query = "SELECT * FROM users WHERE ip_address='".$_SERVER['REMOTE_ADDR']."';";
	$r = run_query($query);
	
	if (mysql_numrows($r) <= 20) {
		if (mysql_numrows($result) == 0) {
			$recaptcha_resp = recaptcha_check_answer($recaptcha_privatekey, $_SERVER["REMOTE_ADDR"], $_POST["g-recaptcha-response"]);
			
			if (!$recaptcha_resp) {
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
					
					$query = "INSERT INTO users SET first_name='".$first."', last_name='".$last."', username='".$email."', api_access_code='".mysql_real_escape_string(random_string(32))."', password='".$new_pass_hash."', ip_address='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."', time_created='".time()."', verify_code='".$verify_code."';";
					$result = run_query($query);
					$user_id = mysql_insert_id();
					
					$acode = 1;
					if ($_REQUEST['autogen_password'] == "1") {
						$message = "Your account has been created.  A new password was generated and emailed to <b>$email</b>.  Please check your inbox and then log in below. ";
					}
					else {
						$message = "Your account has been created.  Please log in below. ";
					}
					
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
					
					if ($_REQUEST['autogen_password'] == "1") {
						$email_message = "<p>You've created a new EmpireCoin web wallet for <b>".$email."</b>.</p>";
						$email_message .= "<p>A password was automatically generated for your user account.<p>";
						$email_message .= "<p>Your password is: ".$new_pass."</p>";
						$email_message .= "<p>If you'd like to set a new password, please visit: http://empireco.in/reset_password/</p>";
						$email_message .= "<p>Thanks for signing up!</p>";
						$email_message .= "<p>This message was sent to you by http://empireco.in</p>";
					}
					else {
						$email_message = "<p>A new EmpireCoin web wallet has been created for <b>".$email."</b>.</p>";
						$email_message .= "<p>Thanks for signing up!</p>";
						$email_message .= "<p>To log in any time please visit http://empireco.in/wallet/</p>";
						$email_message .= "<p>This message was sent to you by http://empireco.in</p>";
					}
					
					$email_id = mail_async($email, "EmpireCo.in", "no-reply@empireco.in", "New account created", $email_message, "", "");
				}
			}
		}
		else {
			$acode = 0;
			$message = "Sorry the email '$email' is already registered.";
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
		
		$query = "UPDATE users SET ip_address='".$_SERVER['REMOTE_ADDR']."' WHERE user_id='".$thisuser['user_id']."';";
		$result = run_query($query);
		
		$redirect_url_id = intval($_REQUEST['redirect_id']);
		
		$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$redirect_url = mysql_fetch_array($r);
			header("Location: ".$redirect_url['url']);
		}
		else header("Location: /wallet/");
		
		die();
	}
}
else if ($_REQUEST['do'] == "logout" && $thisuser) {
	$query = "UPDATE user_sessions SET logout_time='".time()."' WHERE session_id='".$session['session_id']."';";
	$r = run_query($query);
	session_regenerate_id();
	$thisuser = FALSE;
	$message = "You have been logged out. ";
}
else if ($thisuser && $_REQUEST['do'] == "save_voting_strategy") {
	$voting_strategy = $_REQUEST['voting_strategy'];
	$aggregate_threshold = intval($_REQUEST['aggregate_threshold']);
	$api_url = strip_tags(mysql_real_escape_string($_REQUEST['api_url']));
	$by_rank_csv = "";
	
	if (in_array($voting_strategy, array('manual', 'by_rank', 'by_nation', 'api'))) {
		for ($i=1; $i<=16; $i++) {
			if ($_REQUEST['by_rank_'.$i] == "1") $by_rank_csv .= $i.",";
		}
		if ($by_rank_csv != "") $by_rank_csv = substr($by_rank_csv, 0, strlen($by_rank_csv)-1);
		
		$q = "UPDATE users SET voting_strategy='".$voting_strategy."'";
		if ($aggregate_threshold >= 0 && $aggregate_threshold <= 100) {
			$q .= ", aggregate_threshold='".$aggregate_threshold."'";
			$thisuser['aggregate_threshold'] = $aggregate_threshold;
		}
		for ($block=1; $block<=9; $block++) {
			if ($_REQUEST['vote_on_block_'.$block] == "1") $vote_on_block = "1";
			else $vote_on_block = "0";
			$q .= ", vote_on_block_".$block."=".$vote_on_block;
			$thisuser['vote_on_block_'.$block] = $vote_on_block;
		}
		
		$nation_pct_sum = 0;
		$nation_pct_q = "";
		$nation_pct_error = FALSE;
		
		for ($nation_id=1; $nation_id<=16; $nation_id++) {
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
		$q .= " WHERE user_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		
		if ($nation_pct_error && $voting_strategy == "by_nation") {
			$q = "UPDATE users SET voting_strategy='".$thisuser['voting_strategy']."' WHERE user_id='".$thisuser['user_id']."';";
			$r = run_query($q);
			$voting_strategy = $thisuser['voting_strategy'];
			
			$acode = 0;
			$message = "Error: voting strategy couldn't be set to \"Vote by nation\", the percentages you entered didn't add up to 100%.";
		}
		
		$thisuser['voting_strategy'] = $voting_strategy;
		$thisuser['api_url'] = $api_url;
		$thisuser['by_rank_ranks'] = $by_rank_csv;
		$thisuser['max_votesum_pct'] = $max_votesum_pct;
		$thisuser['min_votesum_pct'] = $min_votesum_pct;
		$thisuser['min_coins_available'] = $min_coins_available;
	}
}

$pagetitle = "EmpireCoin - My web wallet";
$nav_tab_selected = "wallet";
include('includes/html_start.php');

$initial_tab = 0;
$account_value = account_coin_value($thisuser);
$immature_balance = immature_balance($thisuser);
$last_block_id = last_block_id($thisuser['currency_mode']);
$current_round = block_to_round($last_block_id+1);
$block_within_round = $last_block_id%get_site_constant('round_length')+1;
$mature_balance = $account_value - $immature_balance;
?>
<div class="container" style="max-width: 1000px; padding-top: 15px;">
	<?php
	if ($message != "") {
		echo "<font style=\"color: #";
		if ($acode == 0) echo "f00";
		else echo "0a0";
		echo "\">";
		echo $message;
		echo "</font>\n";
		echo "<br/><br/>\n";
	}
	
	if ($thisuser) { ?>
		<script type="text/javascript">
		var current_tab = false;
		var last_block_id = <?php echo $last_block_id; ?>;
		var last_transaction_id = <?php echo last_voting_transaction_id(); ?>;
		
		function tab_clicked(index_id) {
			if (current_tab !== false) {
				$('#tabcell'+current_tab).removeClass("tabcell_sel");
				$('#tabcontent'+current_tab).hide();
			}
			
			$('#tabcell'+index_id).addClass("tabcell_sel");
			$('#tabcontent'+index_id).show();
			
			current_tab = index_id;
		}
		
		function claim_coin_giveaway() {
			$('#giveaway_btn').html("Loading...");
			
			$.get("/ajax/coin_giveaway.php?do=claim", function(result) {
				$('#giveaway_btn').html("Claim 1,000 EmpireCoins");
				
				if (result == "1") alert("Great, 1,000 EmpireCoins have been added to your account!");
				else alert("Your free coins have already been claimed.");
				
				window.location = '/wallet/';
			});
		}
		
		function start_vote(nation_id) {
			$('#vote_confirm_'+nation_id).modal('toggle');
			$('#vote_details_'+nation_id).html($('#vote_details_general').html());
			$('#vote_amount_'+nation_id).focus();
		}
		
		function confirm_vote(nation_id) {
			$('#vote_confirm_btn_'+nation_id).html("Loading...");
			$.get("/ajax/place_vote.php?nation_id="+nation_id+"&amount="+encodeURIComponent($('#vote_amount_'+nation_id).val()), function(result) {
				$('#vote_confirm_btn_'+nation_id).html("Confirm Vote");
				var result_parts = result.split("=====");
				if (result_parts[0] == "0") {
					refresh_if_needed();
					$('#vote_confirm_'+nation_id).modal('hide');
					alert("Great, your vote has been submitted!");
				}
				else {
					$('#vote_error_'+nation_id).html(result_parts[1]);
					$('#vote_error_'+nation_id).slideDown('slow');
					setTimeout("$('#vote_error_"+nation_id+"').slideUp('fast');", 2500);
				}
			});
		}
		function rank_check_all_changed() {
			var set_checked = false;
			if ($('#rank_check_all').is(":checked")) set_checked = true;
			for (var i=1; i<=16; i++) {
				$('#by_rank_'+i).prop("checked", set_checked);
			}
		}
		function vote_on_block_all_changed() {
			var set_checked = false;
			if ($('#vote_on_block_all').is(":checked")) set_checked = true;
			for (var i=1; i<=9; i++) {
				$('#vote_on_block_'+i).prop("checked", set_checked);
			}
		}
		function by_nation_reset_pct() {
			for (var nation_id=1; nation_id<=16; nation_id++) {
				$('#nation_pct_'+nation_id).val("0");
			}
		}
		function loop_event() {
			var nation_pct_sum = 0;
			for (var i=1; i<=16; i++) {
				var temp_pct = parseInt($('#nation_pct_'+i).val());
				if (temp_pct && !$('#nation_pct_'+i).is(":focus") && temp_pct != $('#nation_pct_'+i).val()) {
					console.log("Setting nation_pct_"+i+" from "+$('#nation_pct_'+i).val()+" to "+temp_pct);
					$('#nation_pct_'+i).val(temp_pct);
				}
				if (temp_pct) nation_pct_sum += temp_pct;
			}
			if (nation_pct_sum <= 100 && nation_pct_sum >= 0) {
				$('#nation_pct_subtotal').html("<font class='greentext'>"+nation_pct_sum+"/100 allocated, "+(100-nation_pct_sum)+"% left</font>");
			}
			else {
				$('#nation_pct_subtotal').html("<font class='redtext'>"+nation_pct_sum+"/100 allocated</font>");
			}
			setTimeout("loop_event();", 1000);
		}
		
		var refresh_in_progress = false;
		function refresh_if_needed() {
			if (!refresh_in_progress) {
				refresh_in_progress = true;
				$.get("/ajax/check_new_activity.php?last_block_id="+last_block_id+"&last_transaction_id="+last_transaction_id+"&performance_history_sections="+performance_history_sections, function(result) {
					refresh_in_progress = false;
					var json_result = $.parseJSON(result);
					if (json_result['new_block'] == "1") {
						last_block_id = parseInt(json_result['last_block_id']);
						
						if (last_block_id%10 == 0) {
							$('#performance_history_0').html(json_result['performance_history']);
							$('#performance_history_0').hide();
							$('#performance_history_0').fadeIn('fast');
							
							performance_history_start_round = json_result['performance_history_start_round'];
							
							tab_clicked(2);
						}
					}
					if (json_result['new_block'] == "1" || json_result['new_transaction'] == "1") {
						last_transaction_id = parseInt(json_result['last_transaction_id']);
						$('#current_round_table').html(json_result['current_round_table']);
						$('#wallet_text_stats').html(json_result['wallet_text_stats']);
						$('#vote_details_general').html(json_result['vote_details_general']);
						
						$('#current_round_table').hide();
						$('#current_round_table').fadeIn('fast');
						
						$('#wallet_text_stats').hide();
						$('#wallet_text_stats').fadeIn('fast');
						
						var vote_nation_details = json_result['vote_nation_details'];
						for (var nation_id=1; nation_id<=16; nation_id++) {
							$('#vote_nation_details_'+nation_id).html(vote_nation_details[nation_id]);
						}
					}
				});
			}
			setTimeout("refresh_if_needed();", 2000);
		}
		$(document).ready(function() {
			loop_event();
			refresh_if_needed();
		});
		</script>
		<?php
		include("includes/wallet_status.php");
		?>
		<div class="row">
			<div class="col-xs-2 tabcell" id="tabcell0" onclick="tab_clicked(0);">Vote Now</div>
			<div class="col-xs-2 tabcell" id="tabcell1" onclick="tab_clicked(1);">Voting Strategy</div>
			<div class="col-xs-2 tabcell" id="tabcell2" onclick="tab_clicked(2);">Performance History</div>
			<div class="col-xs-2 tabcell" id="tabcell3" onclick="tab_clicked(3);">Deposit Coins</div>
			<div class="col-xs-2 tabcell" id="tabcell4" onclick="tab_clicked(4);">Withdraw Coins</div>
		</div>
		<div class="row">
			<div id="tabcontent0" style="display: none;" class="tabcontent">
				<div id="wallet_text_stats">
					<?php
					echo wallet_text_stats($thisuser, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance);
					?>
				</div>
				
				<br/>
				
				<div style="display: none;" id="vote_details_general">
					<?php echo vote_details_general($mature_balance); ?>
				</div>
				<?php
				$round_stats = round_voting_stats_all($current_round);
				$totalVoteSum = $round_stats[0];
				$maxVoteSum = $round_stats[1];
				$nation_id_to_rank = $round_stats[3];
				$round_stats = $round_stats[2];
				
				if (($last_block_id+1)%get_site_constant('round_length') == 0) {
					echo "The final block of round ".$current_round." is being mined. Voting is currently disabled.<br/>\n";
				}
				else {
					echo "To cast a vote please click on any of the nations below.<br/>\n";
					$nation_q = "SELECT * FROM nations ORDER BY vote_id ASC;";
					$nation_r = run_query($nation_q);
					$n_counter = 1;
					while ($nation = mysql_fetch_array($nation_r)) {
						$rank = $nation_id_to_rank[$nation['nation_id']]+1;
						$voting_sum = $round_stats[$nation_id_to_rank[$nation['nation_id']]]['voting_sum'];
						?>
						<div class="vote_nation_box" onclick="start_vote(<?php echo $nation['nation_id']; ?>);">
							<div class="vote_nation_flag <?php echo strtolower(str_replace(' ', '', $nation['name'])); ?>"></div>
							<div class="vote_nation_flag_label"><?php echo $n_counter.". ".$nation['name']; ?></div>
						</div>
						<div style="display: none;" class="modal fade" id="vote_confirm_<?php echo $nation['nation_id']; ?>">
							<div class="modal-dialog">
								<div class="modal-content">
									<div class="modal-body">
										<h2>Vote for <?php echo $nation['name']; ?></h2>
										<div id="vote_nation_details_<?php echo $nation['nation_id']; ?>">
											<?php
											echo vote_nation_details($nation, $rank, $voting_sum, $totalVoteSum);
											?>
										</div>
										<div id="vote_details_<?php echo $nation['nation_id']; ?>"></div>
										<br/>
										How many EmpireCoins do you want to vote?<br/>
										<div class="row">
											<div class="col-xs-4">
												Amount:
											</div>
											<div class="col-sm-4">
												<input type="text" class="responsive_input" placeholder="0.00" size="10" id="vote_amount_<?php echo $nation['nation_id']; ?>" />
											</div>
											<div class="col-sm-4">
												EmpireCoins
											</div>
										</div>
										<div class="redtext" id="vote_error_<?php echo $nation['nation_id']; ?>"></div>
									</div>
									<div class="modal-footer">
										<button class="btn btn-primary" id="vote_confirm_btn_<?php echo $nation['nation_id']; ?>" onclick="confirm_vote(<?php echo $nation['nation_id']; ?>);">Confirm Vote</button>
										<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
									</div>
								</div>
							</div>
						</div>
						<?php
						$n_counter++;
					}
				}
				
				echo "<br/>\n<div style=\"margin-top: 15px; border: 1px solid #aaa; padding: 10px; border-radius: 8px;\" id=\"current_round_table\">";
				
				echo current_round_table($current_round, $thisuser, true, true);
				
				echo "</div>";
				?>
			</div>
			<div id="tabcontent1" style="display: none;" class="tabcontent">
				<h2>Choose your voting strategy</h2>
				Instead of logging in every time you want to cast a vote, you can automate your voting behavior by choosing one of the automated voting strategies below. <br/><br/>
				<form method="post" action="/wallet/">
					<input type="hidden" name="do" value="save_voting_strategy" />
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_manual" name="voting_strategy" value="manual"<?php if ($thisuser['voting_strategy'] == "manual") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_manual">&nbsp;Vote&nbsp;manually</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_manual"> 
								I'll log in and vote in each round.
							</label>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_api" name="voting_strategy" value="api"<?php if ($thisuser['voting_strategy'] == "api") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_api">&nbsp;Vote&nbsp;by&nbsp;API</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_api">
								Hit a custom URL whenever I have coins available to determine my votes: <input type="text" size="40" placeholder="http://" name="api_url" id="api_url" value="<?php echo $thisuser['api_url']; ?>" />
							</label><br/>
							Your API access code is <?php echo $thisuser['api_access_code']; ?> <a href="/api/about/">API documentation</a><br/>
						</div>
					</div>
					<div class="row bordered_row">
						<div class="col-md-2">
							<input type="radio" id="voting_strategy_by_nation" name="voting_strategy" value="by_nation"<?php if ($thisuser['voting_strategy'] == "by_nation") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_nation">&nbsp;Vote&nbsp;by&nbsp;nation</label>
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
								echo '<input type="tel" size="4" name="nation_pct_'.$nation['nation_id'].'" id="nation_pct_'.$nation['nation_id'].'" placeholder="0" value="'.$thisuser['nation_pct_'.$nation['nation_id']].'" />';
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
							<input type="radio" id="voting_strategy_by_rank" name="voting_strategy" value="by_rank"<?php if ($thisuser['voting_strategy'] == "by_rank") echo ' checked'; ?>><label class="plainlabel" for="voting_strategy_by_rank">&nbsp;Vote&nbsp;by&nbsp;rank</label>
						</div>
						<div class="col-md-10">
							<label class="plainlabel" for="voting_strategy_by_rank">
								Split up my free balance and vote it across nations ranked:
							</label><br/>
							<input type="checkbox" id="rank_check_all" onchange="rank_check_all_changed();" /><label class="plainlabel" for="rank_check_all"> All</label><br/>
							<?php
							$by_rank_ranks = explode(",", $thisuser['by_rank_ranks']);
							
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
							Wait until <input size="4" type="text" name="aggregate_threshold" id="aggregate_threshold" value="<?php echo $thisuser['aggregate_threshold']; ?>" />% of my coins are available to vote. <br/>
							Only vote in these blocks of the round:<br/>
							<div class="row">
								<div class="col-md-2">
									<input type="checkbox" id="vote_on_block_all" onchange="vote_on_block_all_changed();" /><label class="plainlabel" for="vote_on_block_all"> All</label>
								</div>
								<?php
								for ($block=1; $block<=9; $block++) {
									echo '<div class="col-md-2">';
									echo '<input type="checkbox" name="vote_on_block_'.$block.'" id="vote_on_block_'.$block.'" value="1"';
									if ($thisuser['vote_on_block_'.$block] == 1) echo ' checked="checked"';
									echo '><label class="plainlabel" for="vote_on_block_'.$block.'"> ';
									echo $block.date("S", strtotime("1/".$block."/2015"))."</label>";
									echo '</div>';
									if ($block == 4) echo '</div><div class="row">';
								}
								?>
							</div>
							Only vote for nations which have between <input type="tel" size="4" value="<?php echo $thisuser['min_votesum_pct']; ?>" name="min_votesum_pct" id="min_votesum_pct" />% and <input type="tel" size="4" value="<?php echo $thisuser['max_votesum_pct']; ?>" name="max_votesum_pct" id="max_votesum_pct" />% of the current votes.<br/>
							Maintain <input type="tel" size="6" id="min_coins_available" name="min_coins_available" value="<?php echo round($thisuser['min_coins_available'], 3); ?>" /> EMP available at all times.  This number of coins will be reserved and won't be voted.
						</div>
					</div>
					<br/>
					<input class="btn btn-primary" type="submit" value="Save Voting Strategy" />
				</form>
			</div>
			<div id="tabcontent2" style="display: none;" class="tabcontent">
				<script type="text/javascript">
				var performance_history_sections = 1;
				var performance_history_start_round = <?php echo min(1, $current_round-12); ?>;
				var performance_history_loading = false;
				
				function show_more_performance_history() {
					if (!performance_history_loading) {
						performance_history_loading = true;
						performance_history_start_round -= 10;
						$('#performance_history').append('<div id="performance_history_'+performance_history_sections+'"></div>');
						$('#performance_history_'+performance_history_sections).html("Loading...");
						
						$.get("/ajax/performance_history.php?from_round_id="+performance_history_start_round+"&to_round_id="+(performance_history_start_round+10), function(result) {
							$('#performance_history_'+performance_history_sections).html(result);
							performance_history_sections++;
							performance_history_loading = false;
						});
					}
				}
				</script>
				<div id="performance_history">
					<div id="performance_history_0">
						<?php
						echo performance_history($thisuser, max(1, $current_round-11), $current_round-1);
						?>
					</div>
				</div>
				<center>
					<a href="" onclick="show_more_performance_history(); return false;">Show More</a>
				</center>
			</div>
			<div id="tabcontent3" style="display: none;" class="tabcontent">
				<?php
				$q = "SELECT * FROM webwallet_transactions WHERE currency_mode='beta' AND transaction_desc='giveaway' AND user_id='".$thisuser['user_id']."';";
				$r = run_query($q);
				if (mysql_numrows($r) == 0) {
					$initial_tab = 3;
					?>
					You're eligible for a one time coin giveaway of 1,000 EmpireCoins.<br/>
					<button class="btn btn-success" onclick="claim_coin_giveaway();" id="giveaway_btn">Claim 1,000 EmpireCoins</button>
					<?php
				}
				else { ?>
					We're currently in beta; this feature isn't available right now.
					<?php
				}
				?>
			</div>
			<div id="tabcontent4" style="display: none;" class="tabcontent">
				We're currently in beta; this feature isn't available right now.
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
		include("includes/loginbox.php");
	}
	?>
</div>
<?php

include('includes/html_stop.php');
?>