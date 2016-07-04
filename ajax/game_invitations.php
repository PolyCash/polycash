<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	
	if (in_array($action, array('manage', 'generate', 'send'))) {
		$game_id = intval($_REQUEST['game_id']);
		$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$game = mysql_fetch_array($r);

			$perm_to_invite = user_can_invite_game($game, $thisuser['user_id']);

			if ($perm_to_invite) {
				if ($action == "manage") {
					$q = "SELECT * FROM invitations i LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id='".$game['game_id']."' AND i.inviter_id='".$thisuser['user_id']."' ORDER BY invitation_id ASC;";
					$r = run_query($q);
					echo 'You\'ve generated '.mysql_numrows($r).' invitations for this game.<br/>';
					while ($invitation = mysql_fetch_array($r)) {
						echo '<div class="row">';
						echo '<div class="col-sm-6">';
						if ($invitation['used_user_id'] > 0) echo 'Claimed by '.$invitation['username'];
						else echo 'Unclaimed';
						echo '</div>';
						echo '<div class="col-sm-6">';
						
						if ($invitation['sent_email_id'] == 0) {
							if ($invitation['used_user_id'] == 0) {
								echo '<a href="" onclick="send_invitation('.$game['game_id'].', '.$invitation['invitation_id'].', \'email\'); return false;">Send by email</a>&nbsp;&nbsp; ';
								echo '<a href="" onclick="send_invitation('.$game['game_id'].', '.$invitation['invitation_id'].', \'user\'); return false;">Send to user</a>';
							}
						}
						else {
							echo "Sent to ".$invitation['to_email'];
						}
						
						echo '</div>';
						echo "</div>\n";
					}
					?>
					<br/>
					<button style="float: right;" type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button class="btn btn-success" onclick="generate_invitation(<?php echo $game['game_id']; ?>);">Generate an Invitation</button>
					<button class="btn btn-primary" onclick="switch_to_game(<?php echo $game['game_id']; ?>, 'fetch');">Game Settings</button>
					<?php
				}
				else if ($action == "send") {
					$send_to = urldecode($_REQUEST['send_to']);
					$invitation_id = intval($_REQUEST['invitation_id']);
					
					$q = "SELECT * FROM invitations WHERE invitation_id='".$invitation_id."' AND inviter_id='".$thisuser['user_id']."';";
					$r = run_query($q);
					
					if (mysql_numrows($r) > 0) {
						$invitation = mysql_fetch_array($r);
						
						if ($invitation['game_id'] == $game['game_id'] && $invitation['used'] == 0) {
							$send_method = $_REQUEST['send_method'];
							
							if ($send_method == "email") {
								if ($invitation['sent_email_id'] == 0) {
									$email_id = send_invitation_email($game, $send_to, $invitation);
									
									output_message(1, "Great, the invitation has been sent.", $invitation);
								}
								else output_message(2, "Error: that invitation has already been sent or used.", $invitation);
							}
							else if ($send_method == "user") {
								$q = "SELECT * FROM users WHERE username='".mysql_real_escape_string($send_to)."';";
								$r = run_query($q);
								
								if (mysql_numrows($r) > 0) {
									$send_to_user = mysql_fetch_array($r);
									
									$invite_game = false;
									try_apply_invite_key($send_to_user['user_id'], $invitation['invitation_key'], $invite_game);
									
									if (strpos($send_to_user['username'], '@')) {
										$email_id = send_invitation_email($game, $send_to, $invitation);
									}
									
									output_message(1, "Great, the invitation has been sent.", false);
								}
								else output_message(2, "No one with that username was found.", false);
							}
							else output_message(2, "Invalid URL", false);
						}
						else output_message(2, "Error: that invitation has already been sent or used.", false);
					}
					else output_message(2, "Error: you can't send that invitation.", false);
				}
				else {
					$invitation = false;
					generate_invitation($game, $thisuser['user_id'], $invitation, false);
					output_message(1, "An invitation has been generated.", false);
				}
			}
			else output_message(2, "Error: you don't have permission to generate invitations for this game.", false);
		}
		else output_message(2, "Error: you don't have permission to generate invitations for this game.", false);
	}
	else output_message(2, "Error: you specified an invalid action.", false);
}
?>