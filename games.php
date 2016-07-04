<?php
include('includes/connect.php');
include('includes/get_session.php');
$viewer_id = insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Coin games";
$nav_tab_selected = "wallet";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px;">
	<?php
	if ($thisuser) {
		?>
		<br/>
		<script type="text/javascript">
		function new_match() {
			$('#new_match_modal').modal('toggle');
		}
		function confirm_new_match() {
			var match_type_id = $('#new_match_type').val();
			$.get("/ajax/manage_match.php?do=new&match_type_id="+match_type_id, function(result) {
				var result_json = JSON.parse(result);
				if (parseInt(result_json['result_code']) == 1) {
					window.location = "/games/?match_id="+result_json['match_id'];
				}
				else alert(result_json['error_message']);
			});
		}
		function join_match(match_id) {
			$.get("/ajax/manage_match.php?do=join&match_id="+match_id, function(result) {
				var result_json = JSON.parse(result);
				if (parseInt(result_json['result_code']) == 1) {
					window.location = "/games/?match_id="+match_id;
				}
				else alert(result_json['error_message']);
			});
		}
		function start_match(match_id) {
			$.get("/ajax/manage_match.php?do=start&match_id="+match_id, function(result) {
				var result_json = JSON.parse(result);
				if (parseInt(result_json['result_code']) == 1) {
					window.location = "/games/?match_id="+match_id;
				}
				else alert(result_json['error_message']);
			});
		}
		</script>
		<?php
		$match_id = intval($_REQUEST['match_id']);
		
		if ($match_id > 0) {
			$q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.match_id='".$match_id."';";
			$r = run_query($q);
			
			if (mysql_numrows($r) > 0) {
				$match = mysql_fetch_array($r);
				
				$my_membership = user_match_membership($thisuser['user_id'], $match['match_id']);
				
				if ($my_membership) {
					if ($match['num_joined'] < $match['num_players']) {
						$needed_players = $match['num_players'] - $match['num_joined'];
						echo $match['num_joined']."/".$match['num_players']." players have joined, waiting for ".$needed_players." player";
						if ($needed_players != 1) echo "s";
						echo ".";
					}
					else if ($match['num_joined'] == $match['num_players']) {
						?>
						<script type="text/javascript">
						var match_turns = -1;
						var match_refresh_in_progress = false;
						function match_refresh() {
							if (!match_refresh_in_progress) {
								match_refresh_in_progress = true;
								$.get("/ajax/check_match_activity.php?match_id=<?php echo $match['match_id']; ?>&turns="+match_turns, function(result) {
									alert(result);
									match_refresh_in_progress = false;
								});
							}
							setTimeout("match_refresh();", 500);
						}
						
						$(document).ready(function() {
							match_refresh();
						});
						</script>
						<div class="row">
							<div class="col-md-4">
								<div style="font-size: 11px; border: 1px solid #555; padding: 5px; margin-bottom: 8px;">
									<?php echo show_match_messages($match, $thisuser['user_id']); ?>
								</div>
							</div>
							<div class="col-md-8">
								<?php
								if ($match['status'] == "pending") {
									?>
									Great, this game is ready to begin!<br/>
									<button class="btn btn-success" onclick="start_match(<?php echo $match['match_id']; ?>);">Begin the game</button>
									<?php
								}
								else if ($match['status'] == "running") {
									$account_value = match_mature_balance($my_membership['membership_id']);
									echo "Hi player #".($my_membership['player_position']+1).", you have: <font class=\"greentext\">".number_format($account_value/pow(10,8), 2)." coins</font>.<br/>\n";
									
									$q = "SELECT * FROM match_memberships mem JOIN users u ON mem.user_id=u.user_id WHERE mem.match_id='".$match['match_id']."' ORDER BY player_position ASC;";
									$r = run_query($q);
									while ($player = mysql_fetch_array($r)) {
										$qq = "SELECT COUNT(*) FROM match_rounds WHERE match_id='".$match['match_id']."' AND winning_membership_id='".$player['membership_id']."';";
										$rr = run_query($qq);
										$player_wins = mysql_fetch_row($rr);
										$player_wins = $player_wins[0];
										
										echo "Player #".($player['player_position']+1).": ".$player_wins."<br/>\n";
									}
									
									echo "You're currently on round ".$match['current_round_number']." of ".$match['num_rounds']."<br/>\n";
									
									$q = "SELECT * FROM match_moves WHERE membership_id='".$my_membership['membership_id']."' AND round_number='".$match['current_round_number']."';";
									$r = run_query($q);
									if (mysql_numrows($r) > 0) {
										$my_move = mysql_fetch_array($r);
										echo "You put <font class=\"greentext\">".$my_move['amount']/pow(10,8)." coins</font> down on this round.<br/>\n";
										echo "Waiting on your opponent...";
									}
									else {
										?>
										Please enter an amount or use the sliders below, then submit your move for this round.<br/><br/>
										
										<div class="row">
											<div class="col-sm-4">
												<input class="form-control" id="match_move_amount" type="tel" size="6" placeholder="0.00" />
											</div>
										</div>
										
										<div id="match_slider" class="noUiSlider"></div>
										
										<button id="match_slider_label" class="btn btn-primary" onclick="submit_move();">Submit Move</button>
										
										<script type="text/javascript">
										var match_slider_changed = false;
										var match_text_amount = $('#match_move_amount').val();
										var move_amount = 0;
										
										function submit_move() {
											$.get("/ajax/manage_match.php?do=move&match_id=<?php echo $match['match_id']; ?>&amount="+move_amount, function(result) {
												var result_json = JSON.parse(result);
												if (parseInt(result_json['result_code']) == 1) {
													window.location = window.location;
												}
												else {
													alert(result_json['error_message']);
												}
											});
										}
										function match_loop() {
											if (match_text_amount != $('#match_move_amount').val()) {
												match_text_amount = $('#match_move_amount').val();
												
												if (match_text_amount != "") {
													var match_move_amount = parseFloat(match_text_amount);
													$('#match_slider').val(match_move_amount*100);
													refresh_match_slider(false);
												}
											}
											
											if (match_slider_changed) refresh_match_slider(true);
											
											match_slider_changed = false;
											setTimeout("match_loop();", 500);
										}
										function refresh_match_slider(update_text_input) {
											var match_move_amount = parseInt($('#match_slider').val())/100;
											move_amount = match_move_amount;
											$('#match_slider_label').html("Wager "+match_move_amount+" coins");
											if (update_text_input) $('#match_move_amount').val(match_move_amount);
										}
										$('#match_slider').noUiSlider({
											range: [0, 10000]
										   ,start: 5000, step: 1
										   ,handles: 1
										   ,connect: "lower"
										   ,serialization: {
												to: [ false, false ]
												,resolution: 1
											}
										   ,slide: function() {
											   match_slider_changed = true;
										   }
										});
										
										$(document).ready(function() {
											match_loop();
											refresh_match_slider(true);
										});
										</script>
										<?php
									}
									$current_player = match_current_player($match);
								}
								else if ($match['status'] == "finished") {
									
								}
								?>
							</div>
						</div>
						<?php
					}
					else {
						echo "Error, there are too many players in this game.";
					}
				}
				else {
					if ($match['num_joined'] < $match['num_players']) {
						echo "This is a ".$match['num_players']."-player game, but only ".$match['num_joined']." player";
						if ($match['num_joined'] == 1) echo " has";
						else echo "s have";
						echo " joined so far.<br/>\n";
						?>
						<button class="btn btn-success" onclick="join_match(<?php echo $match['match_id']; ?>);">Join this Match</button>
						<?php
					}
					else {
						echo "This game is already full.<br/>\n";
					}
				}
			}
			else echo "The match ID you submitted wasn't found.";
		}
		else {
			$my_matches_q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id JOIN match_memberships mm ON mm.match_id=m.match_id WHERE mm.user_id='".$thisuser['user_id']."';";
			$my_matches_r = run_query($my_matches_q);
			
			if (mysql_numrows($my_matches_r) > 0) {
				echo "<ul>";
				echo "<h1>My Games</h1>\n";
				while ($my_match = mysql_fetch_array($my_matches_r)) {
					echo "<li><a href=\"/games/?match_id=".$my_match['match_id']."\">#".$my_match['match_id'].", ".$my_match['name']." (".$my_match['num_joined']."/".$my_match['num_players']." players)</a></li>\n";
				}
				echo "</ul>\n";
			}
			
			$joinable_q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.num_joined < t.num_players AND NOT EXISTS (SELECT * FROM match_memberships mm WHERE mm.match_id=m.match_id AND mm.user_id='".$thisuser['user_id']."') ORDER BY t.name ASC, m.match_id ASC;";
			$joinable_r = run_query($joinable_q);
			if (mysql_numrows($joinable_r) > 0) {
				echo "<ul>";
				echo "<h1>Open Games</h1>";
				while ($joinable_match = mysql_fetch_array($joinable_r)) {
					echo "<li><a href=\"\" onclick=\"join_match(".$joinable_match['match_id']."); return false;\">#".$joinable_match['match_id'].", ".$joinable_match['name']." (".$joinable_match['num_joined']."/".$joinable_match['num_players']." players)</a></li>\n";
				}
				echo "</ul>";
			}
			else echo "There are no games available to join right now.<br/>\n";
			
			?>
			<br/>
			<button class="btn btn-success" onclick="new_match();">Start a new match</button>
			
			<div style="display: none;" class="modal fade" id="new_match_modal">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-body">
							Please select the a game type:<br/>
							<select id="new_match_type" class="form-control" required="required">
							<?php
							$q = "SELECT * FROM match_types ORDER BY name ASC;";
							$r = run_query($q);
							while ($match_type = mysql_fetch_array($r)) {
								echo "<option value=\"".$match_type['match_type_id']."\">".$match_type['num_players']."-player ".$match_type['name']."</option>\n";
							}
							?>
							</select>
						</div>
						<div class="modal-footer">
							<button class="btn btn-primary" id="new_match_confirm" onclick="confirm_new_match();">Create match</button>
							<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
	else {
		$redirect_url = get_redirect_url("/games/");
		$redirect_id = $redirect_url['redirect_url_id'];
		include("includes/loginbox.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>