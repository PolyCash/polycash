<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	
	if (in_array($action, array('manage', 'generate', 'send'))) {
		$game_id = intval($_REQUEST['game_id']);
		$game = new Game($game_id);
		
		if ($game) {
			$perm_to_invite = $thisuser->user_can_invite_game($game->db_game);

			if ($perm_to_invite) {
				if ($action == "manage") {
					$q = "SELECT * FROM invitations i LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id='".$game->db_game['game_id']."' AND i.inviter_id='".$thisuser->db_user['user_id']."' ORDER BY invitation_id ASC;";
					$r = $GLOBALS['app']->run_query($q);
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
								echo '<a href="" onclick="send_invitation('.$game->db_game['game_id'].', '.$invitation['invitation_id'].', \'email\'); return false;">Send by email</a>&nbsp;&nbsp; ';
								echo '<a href="" onclick="send_invitation('.$game->db_game['game_id'].', '.$invitation['invitation_id'].', \'user\'); return false;">Send to user</a>';
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
					<button class="btn btn-success" onclick="generate_invitation(<?php echo $game->db_game['game_id']; ?>);">Generate an Invitation</button>
					<button class="btn btn-primary" onclick="switch_to_game(<?php echo $game->db_game['game_id']; ?>, 'fetch');">Game Settings</button>
					<?php
				}
				else if ($action == "send") {
					$send_to = urldecode($_REQUEST['send_to']);
					$invitation_id = intval($_REQUEST['invitation_id']);
					
					$q = "SELECT * FROM invitations WHERE invitation_id='".$invitation_id."' AND inviter_id='".$thisuser->db_user['user_id']."';";
					$r = $GLOBALS['app']->run_query($q);
					
					if (mysql_numrows($r) > 0) {
						$invitation = mysql_fetch_array($r);
						
						if ($invitation['game_id'] == $game->db_game['game_id'] && $invitation['used'] == 0) {
							$send_method = $_REQUEST['send_method'];
							
							if ($send_method == "email") {
								if ($invitation['sent_email_id'] == 0) {
									$email_id = $game->send_invitation_email($send_to, $invitation);
									
									$GLOBALS['app']->output_message(1, "Great, the invitation has been sent.", $invitation);
								}
								else $GLOBALS['app']->output_message(2, "Error: that invitation has already been sent or used.", $invitation);
							}
							else if ($send_method == "user") {
								$q = "SELECT * FROM users WHERE username='".mysql_real_escape_string($send_to)."';";
								$r = $GLOBALS['app']->run_query($q);
								
								if (mysql_numrows($r) > 0) {
									$send_to_user = mysql_fetch_array($r);
									
									$invite_game = false;
									$GLOBALS['app']->try_apply_invite_key($send_to_user['user_id'], $invitation['invitation_key'], $invite_game);
									
									if (strpos($send_to_user['notification_email'], '@')) {
										$email_id = $invite_game->send_invitation_email($send_to_user['notification_email'], $invitation);
									}
									
									$GLOBALS['app']->output_message(1, "Great, the invitation has been sent.", false);
								}
								else $GLOBALS['app']->output_message(2, "No one with that username was found.", false);
							}
							else $GLOBALS['app']->output_message(2, "Invalid URL", false);
						}
						else $GLOBALS['app']->output_message(2, "Error: that invitation has already been sent or used.", false);
					}
					else $GLOBALS['app']->output_message(2, "Error: you can't send that invitation.", false);
				}
				else {
					$invitation = false;
					$game->generate_invitation($thisuser->db_user['user_id'], $invitation, false);
					$GLOBALS['app']->output_message(1, "An invitation has been generated.", false);
				}
			}
			else $GLOBALS['app']->output_message(2, "Error: you don't have permission to generate invitations for this game.", false);
		}
		else $GLOBALS['app']->output_message(2, "Error: you don't have permission to generate invitations for this game.", false);
	}
	else $GLOBALS['app']->output_message(2, "Error: you specified an invalid action.", false);
}
?>