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
					$q = "SELECT * FROM invitations i LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id='".$game['game_id']."' AND i.inviter_id='".$thisuser['user_id']."';";
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
								echo '<a href="" onclick="send_invitation('.$game['game_id'].', '.$invitation['invitation_id'].'); return false;">Send</a>';
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
					$to_email = urldecode($_REQUEST['to_email']);
					$invitation_id = intval($_REQUEST['invitation_id']);
					
					$q = "SELECT * FROM invitations WHERE invitation_id='".$invitation_id."' AND inviter_id='".$thisuser['user_id']."';";
					$r = run_query($q);
					
					if (mysql_numrows($r) > 0) {
						$invitation = mysql_fetch_array($r);
						
						if ($invitation['game_id'] == $game['game_id'] && $invitation['used'] == 0 && $invitation['sent_email_id'] == 0) {
							$blocks_per_hour = 3600/$game['seconds_per_block'];
							$round_reward = ($game['pos_reward']+$game['pow_reward']*$game['round_length'])/pow(10,8);
							$rounds_per_hour = 3600/($game['seconds_per_block']*$game['round_length']);
							$coins_per_hour = $round_reward*$rounds_per_hour;
							$seconds_per_round = $game['seconds_per_block']*$game['round_length'];
							
							if ($game['inflation'] == "linear") $miner_pct = 100*($game['pow_reward']*$game['round_length'])/($round_reward*pow(10,8));
							else $miner_pct = 100*$game['exponential_inflation_minershare'];

							$invite_currency = false;
							if ($game['invite_currency'] > 0) {
								$q = "SELECT * FROM currencies WHERE currency_id='".$game['invite_currency']."';";
								$r = run_query($q);
								$invite_currency = mysql_fetch_array($r);
							}

							$subject = "You've been invited to join ".$game['name'];
							if ($game['giveaway_status'] == "invite_pay" || $game['giveaway_status'] == "public_pay") {
								$subject .= ". Join by paying ".format_bignum($game['invite_cost'])." ".$invite_currency['short_name']."s for ".format_bignum($game['giveaway_amount']/pow(10,8))." ".$game['coin_name_plural'].".";
							}
							else {
								$subject .= ". Get ".format_bignum($game['giveaway_amount']/pow(10,8))." ".$game['coin_name_plural']." for free by accepting this invitation.";
							}
							$message .= "<p>";
							if ($game['inflation'] == "linear") $message .= $game['name']." is a cryptocurrency which generates ".$coins_per_hour." ".$game['coin_name_plural']." per hour. ";
							else $message .= $game['name']." is a cryptocurrency with ".($game['exponential_inflation_rate']*100)."% inflation every ".format_seconds($seconds_per_round).". ";
							$message .= $miner_pct."% is given to miners for securing the network and the remaining ".(100-$miner_pct)."% is given to players for casting winning votes. ";
							if ($game['final_round'] > 0) {
								$game_total_seconds = $seconds_per_round*$game['final_round'];
								$message .= "Once this game starts, it will last for ".format_seconds($game_total_seconds)." (".$game['final_round']." rounds). ";
								$message .= "At the end, all ".$invite_currency['short_name']."s that have been paid in will be divided up and given out to all players in proportion to players' final balances.";
							}
							$message .= "</p>";

							$message .= "<p>In this game, you can vote for one of ".$game['num_voting_options']." empires every ".format_seconds($seconds_per_round).".  Team up with other players and cast your votes strategically to win coins and destroy your competitors.</p>";
							$message .= game_info_table($game);
							$message .= "<p>To start playing, accept your invitation by following <a href=\"".$GLOBALS['base_url']."/wallet/".$game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";
							$message .= "<p>This message was sent to you by ".$GLOBALS['site_name']."</p>";

							$email_id = mail_async($to_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
							
							$q = "UPDATE invitations SET sent_email_id='".$email_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
							$r = run_query($q);

							output_message(1, "Great, the invitation has been sent.", $invitation);
						}
						else output_message(2, "Error: that invitation has already been sent or used.", $invitation);
					}
					else output_message(2, "Error: you can't send that invitation.", $invitation);
				}
				else {
					$invitation = false;
					generate_invitation($game, $thisuser['user_id'], $invitation, false);
					output_message(1, "An invitation has been generated.", false);
				}
			}
			else output_message(2, "Error: you don't have permission to generate invitations for this game.", $invitation);
		}
		else output_message(2, "Error: you don't have permission to generate invitations for this game.", $invitation);
	}
	else output_message(2, "Error: you specified an invalid action.", $invitation);
}
?>