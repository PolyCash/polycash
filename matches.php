<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$pagetitle = "EmpireCoin - Coin games";
$nav_tab_selected = "wallet";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px;">
	<br/>
	<?php
	if ($thisuser) {
		$match_id = intval($_REQUEST['match_id']);
		
		if ($match_id > 0) {
			$q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.match_id='".$match_id."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() > 0) {
				$match = $r->fetch();
				
				$my_membership = user_match_membership($thisuser->db_user['user_id'], $match['match_id']);
				
				if ($my_membership) {
					?>
					<script type="text/javascript">
					var match_refresh_in_progress = false;
					var last_move_number = <?php echo $match['last_move_number']; ?>;
					var last_message_id = <?php echo last_match_message($match['match_id']); ?>;
					var current_round_number = <?php echo $match['current_round_number']; ?>;
					
					var match_slider_changed = false;
					var match_text_amount = $('#match_move_amount').val();
					var move_amount = 0;
					var account_value = <?php echo match_mature_balance($my_membership['membership_id']); ?>;
					
					$(document).ready(function() {
						load_match_slider(account_value/Math.pow(10,8));
						match_loop();
						refresh_match_slider(true);
						match_refresh_loop(<?php echo $match['match_id']; ?>);
					});
					</script>
					<div style="display: none;" class="modal fade" id="last_round_result_modal">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-body">
									<div id="last_round_result"></div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-4">
							<div style="font-size: 11px; border: 1px solid #555; padding: 5px; margin-bottom: 8px;" id="match_messages">
								<?php echo show_match_messages($match, $thisuser->db_user['user_id'], false); ?>
							</div>
						</div>
						<div class="col-md-8" id="match_body_container">
							<?php
							if ($match['num_joined'] < $match['num_players']) {
								$needed_players = $match['num_players'] - $match['num_joined'];
								echo $match['num_joined']."/".$match['num_players']." players have joined, waiting for ".$needed_players." player";
								if ($needed_players != 1) echo "s";
								echo ".";
							}
							else if ($match['num_joined'] == $match['num_players']) {
								echo match_body($match, $my_membership, $thisuser);
							}
							else {
								echo "Error, there are too many players in this game.";
							}
							?>
						</div>
					</div>
					<?php
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
			$my_matches_q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id JOIN match_memberships mm ON mm.match_id=m.match_id WHERE mm.user_id='".$thisuser->db_user['user_id']."';";
			$my_matches_r = $app->run_query($my_matches_q);
			
			if ($my_matches_r->rowCount() > 0) {
				echo "<ul>";
				echo "<h1>My Games</h1>\n";
				while ($my_match = $my_matches_r->fetch()) {
					echo "<li><a href=\"/games/?match_id=".$my_match['match_id']."\">#".$my_match['match_id'].", ".$my_match['name']." (".$my_match['num_joined']."/".$my_match['num_players']." players)</a></li>\n";
				}
				echo "</ul>\n";
			}
			
			$joinable_q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.num_joined < t.num_players AND NOT EXISTS (SELECT * FROM match_memberships mm WHERE mm.match_id=m.match_id AND mm.user_id='".$thisuser->db_user['user_id']."') ORDER BY t.name ASC, m.match_id ASC;";
			$joinable_r = $app->run_query($joinable_q);
			if ($joinable_r->rowCount() > 0) {
				echo "<ul>";
				echo "<h1>Open Games</h1>";
				while ($joinable_match = $joinable_r->fetch()) {
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
							$r = $app->run_query($q);
							while ($match_type = $r->fetch()) {
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
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>