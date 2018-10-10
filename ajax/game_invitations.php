<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	
	if (in_array($action, array('manage', 'generate', 'send'))) {
		$game_id = intval($_REQUEST['game_id']);
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
		
		if ($game) {
			$perm_to_invite = $thisuser->user_can_invite_game($game->db_game);

			if ($perm_to_invite) {
				if ($action == "manage") {
					$q = "SELECT * FROM game_invitations i LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id='".$game->db_game['game_id']."' AND i.inviter_id='".$thisuser->db_user['user_id']."' ORDER BY invitation_id ASC;";
					$r = $app->run_query($q);
					echo 'You\'ve generated '.$r->rowCount().' invitations for this game.<br/>';
					while ($invitation = $r->fetch()) {
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
					<button class="btn btn-sm btn-success" onclick="generate_invitation(<?php echo $game->db_game['game_id']; ?>);">Generate an Invitation</button>
					<br/><br/>
					Or invite bulk users by uploading a CSV:<br/>
					<form id="invite_upload_form" action="/manage/<?php echo $game->db_game['url_identifier']; ?>/" method="post" enctype="multipart/form-data">
						<input type="hidden" name="last" value="invite_upload_csv" />
						<input type="hidden" name="next" value="events" />
						<input type="file" name="csv_file" class="btn btn-sm btn-warning" onchange="$('#invite_upload_form').submit();" />
					</form>
					<br/>
					<br/>
					<button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Close</button>
					<?php
				}
				else if ($action == "send") {
					$send_to = urldecode($_REQUEST['send_to']);
					$invitation_id = intval($_REQUEST['invitation_id']);
					
					$q = "SELECT * FROM game_invitations WHERE invitation_id='".$invitation_id."' AND inviter_id='".$thisuser->db_user['user_id']."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$invitation = $r->fetch();
						
						if ($invitation['game_id'] == $game->db_game['game_id'] && $invitation['used'] == 0) {
							$send_method = $_REQUEST['send_method'];
							
							if ($send_method == "email") {
								if ($invitation['sent_email_id'] == 0) {
									$email_id = $game->send_invitation_email($send_to, $invitation);
									
									$app->output_message(1, "Great, the invitation has been sent.", $invitation);
								}
								else $app->output_message(2, "Error: that invitation has already been sent or used.", $invitation);
							}
							else if ($send_method == "user") {
								$q = "SELECT * FROM users WHERE username=".$app->quote_escape($send_to).";";
								$r = $app->run_query($q);
								
								if ($r->rowCount() > 0) {
									$send_to_user = $r->fetch();
									
									$invite_game = false;
									$send_user_game = false;
									$app->try_apply_invite_key($send_to_user['user_id'], $invitation['invitation_key'], $invite_game, $send_user_game);
									$game->give_faucet_to_user($send_user_game);
									
									$app->output_message(1, "Great, the invitation has been sent.", false);
								}
								else $app->output_message(2, "No one with that username was found.", false);
							}
							else $app->output_message(2, "Invalid URL", false);
						}
						else $app->output_message(2, "Error: that invitation has already been sent or used.", false);
					}
					else $app->output_message(2, "Error: you can't send that invitation.", false);
				}
				else {
					$invitation = false;
					$game->generate_invitation($thisuser->db_user['user_id'], $invitation, false);
					$app->output_message(1, "An invitation has been generated.", false);
				}
			}
			else $app->output_message(2, "Error: you don't have permission to generate game_invitations for this game.", false);
		}
		else $app->output_message(2, "Error: you don't have permission to generate game_invitations for this game.", false);
	}
	else $app->output_message(2, "Error: you specified an invalid action.", false);
}
?>