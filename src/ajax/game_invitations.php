<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$action = $_REQUEST['action'];
	
	if (in_array($action, array('manage', 'generate', 'send'))) {
		$game_id = intval($_REQUEST['game_id']);
		$db_game = $app->fetch_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $game_id);
			
			if ($game) {
				$perm_to_invite = $thisuser->user_can_invite_game($game->db_game);

				if ($perm_to_invite) {
					if ($action == "manage") {
						?>
						<div class="modal-body">
							<?php
							$my_invitations = $app->run_query("SELECT * FROM game_invitations i LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id=:game_id AND i.inviter_id=:inviter_id ORDER BY invitation_id ASC;", [
								'game_id' => $game->db_game['game_id'],
								'inviter_id' => $thisuser->db_user['user_id']
							])->fetchAll();
							
							echo "You've generated ".count($my_invitations).' invitations for this game.<br/>';
							
							foreach ($my_invitations as $invitation) {
								echo '<div class="row">';
								echo '<div class="col-sm-6">';
								if ($invitation['used_user_id'] > 0) echo 'Claimed by '.$invitation['username'];
								else echo 'Unclaimed';
								echo '</div>';
								echo '<div class="col-sm-6">';
								
								if ($invitation['sent_email_id'] == 0) {
									if ($invitation['used_user_id'] == 0) {
										echo '<a href="" onclick="thisPageManager.send_invitation('.$game->db_game['game_id'].', '.$invitation['invitation_id'].', \'email\'); return false;">Send by email</a>&nbsp;&nbsp; ';
										echo '<a href="" onclick="thisPageManager.send_invitation('.$game->db_game['game_id'].', '.$invitation['invitation_id'].', \'user\'); return false;">Send to user</a>';
									}
								}
								else {
									echo "Sent to ".$invitation['to_email'];
								}
								
								echo '</div>';
								echo "</div>\n";
							}
							?>
							<button class="btn btn-sm btn-success" onclick="thisPageManager.generate_invitation(<?php echo $game->db_game['game_id']; ?>);"><i class="fas fa-plus-circle"></i> &nbsp; Generate an Invitation</button>
							<br/><br/>
							Or invite bulk users by uploading a CSV:<br/>
							<form id="invite_upload_form" action="/manage/<?php echo $game->db_game['url_identifier']; ?>/" method="post" enctype="multipart/form-data">
								<input type="hidden" name="last" value="invite_upload_csv" />
								<input type="hidden" name="next" value="events" />
								<input type="file" name="csv_file" class="btn btn-sm btn-primary" onchange="$('#invite_upload_form').submit();" />
								<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
							</form>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-sm btn-warning" data-dismiss="modal"><i class="fas fa-times"></i> &nbsp; Close</button>
						</div>
						<?php
					}
					else if ($action == "send") {
						$send_to = strip_tags(urldecode($_REQUEST['send_to']));
						$invitation_id = intval($_REQUEST['invitation_id']);
						
						$invitation = $app->run_query("SELECT * FROM game_invitations WHERE invitation_id=:invitation_id AND inviter_id=:inviter_id;", [
							'invitation_id' => $invitation_id,
							'inviter_id' => $thisuser->db_user['user_id']
						])->fetch();
						
						if ($invitation) {
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
									$send_to_user = $app->fetch_user_by_username($send_to);
									
									if ($send_to_user) {
										$invite_game = false;
										$send_user_game = false;
										$app->try_apply_invite_key($send_to_user['user_id'], $invitation['invitation_key'], $invite_game, $send_user_game);
										$game->claim_max_from_faucet($send_user_game);
										
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
		else $app->output_message(2, "Error: you supplied an invalid game ID.", false);
	}
	else $app->output_message(2, "Error: you specified an invalid action.", false);
}
?>