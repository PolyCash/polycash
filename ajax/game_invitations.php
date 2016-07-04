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
			if ($thisuser['user_id'] == $game['creator_id']) {
				if ($action == "manage") {
					$q = "SELECT * FROM invitations i JOIN transactions t ON i.giveaway_transaction_id=t.transaction_id LEFT JOIN users u ON i.used_user_id=u.user_id LEFT JOIN async_email_deliveries d ON i.sent_email_id=d.delivery_id WHERE i.game_id='".$game['game_id']."';";
					$r = run_query($q);
					echo 'This game has '.mysql_numrows($r).' invitations.<br/>';
					while ($invitation = mysql_fetch_array($r)) {
						echo '<div class="row">';
						echo '<div class="col-md-4"><a href="/wallet/'.$game['url_identifier'].'/?invite_key='.$invitation['invitation_key'].'">'.$invitation['invitation_key'].'</a></div>';
						echo '<div class="col-md-4" style="font-size: 12px;">'.format_bignum($invitation['amount']/pow(10,8))." coins, ";
						if ($invitation['used_user_id'] > 0) echo 'claimed by '.$invitation['username'];
						else echo 'unclaimed';
						echo '</div>';
						echo '<div class="col-md-4" style="font-size: 12px;">';
						
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
					
					$q = "SELECT * FROM invitations WHERE invitation_id='".$invitation_id."';";
					$r = run_query($q);
					
					if (mysql_numrows($r) > 0) {
						$invitation = mysql_fetch_array($r);
						
						if ($invitation['game_id'] == $game['game_id'] && $invitation['used'] == 0 && $invitation['sent_email_id'] == 0) {
							$blocks_per_hour = 3600/$game['seconds_per_block'];
							$round_reward = ($game['pos_reward']+$game['pow_reward']*$game['round_length'])/pow(10,8);
							$rounds_per_hour = 3600/($game['seconds_per_block']*$game['round_length']);
							$coins_per_hour = $round_reward*$rounds_per_hour;
							$seconds_per_round = $game['seconds_per_block']*$game['round_length'];
							$miner_pct = 100*($game['pow_reward']*$game['round_length'])/($round_reward*pow(10,8));
							
							$subject = "You've been invited to join ".$game['name'];
							$message = "To accept this invitation, please follow <a href=\"".$GLOBALS['base_url']."/wallet/".$game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>. ";
							//$message .= "In this game, ".format_bignum($round_reward)." coins will be given out in each ".rtrim(format_seconds($seconds_per_round), 's')." voting round. ";
							
							$email_id = mail_async($to_email, $GLOBALS['site_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "");
							
							$q = "UPDATE invitations SET sent_email_id='".$email_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
							$r = run_query($q);
						}
					}
				}
				else {
					$invitation = false;
					generate_invitation($game, $thisuser['user_id'], $invitation, false);
					output_message(1, "An invitation has been generated.", $invitation);
				}
			}
		}
	}
}
?>