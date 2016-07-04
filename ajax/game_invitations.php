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
								$subject .= ". Join by paying ".number_format($game['invite_cost'])." ".$invite_currency['short_name']."s for ".format_bignum($game['giveaway_amount']/pow(10,8))." coins.";
							}
							else {
								$subject .= ". Get ".format_bignum($game['giveaway_amount']/pow(10,8))." coins for free by accepting this invitation.";
							}
							$message .= "<p>";
							if ($game['inflation'] == "linear") $message .= $game['name']." is a cryptocurrency which generates ".$coins_per_hour." coins per hour. ";
							else $message .= $game['name']." is a cryptocurrency with ".($game['exponential_inflation_rate']*100)."% inflation every ".format_seconds($seconds_per_round).". ";
							$message .= $miner_pct."% is given to miners for securing the network and the remaining ".(100-$miner_pct)."% is given to players for casting winning votes. ";
							if ($game['final_round'] > 0) {
								$game_total_seconds = $seconds_per_round*$game['final_round'];
								$message .= "Once this game starts, it will last for ".format_seconds($game_total_seconds)." (".$game['final_round']." rounds). ";
								$message .= "At the end, all ".$invite_currency['short_name']."s that have been paid in will be divided up and given out to all players in proportion to players' final balances.";
							}
							$message .= "</p>";

							$message .= "<p>In this game, you can vote for one of ".$game['num_voting_options']." empires every ".format_seconds($seconds_per_round).".  Team up with other players and cast your votes strategically to win coins and destroy your competitors.</p>";

							$game_url = $GLOBALS['base_url']."/".$game['url_identifier'];
							$message .= "<p><table>";
							$message .= "<tr><td>Game name:</td><td>".$game['name']."</td></tr>\n";
							$message .= "<tr><td>Game URL:</td><td><a href=\"".$game_url."\">".$game_url."</a></td></tr>\n";
							$message .= "<tr><td>Cost to join:</td><td>";
							if ($game['giveaway_status'] == "invite_pay" || $game['giveaway_status'] == "public_pay") $message .= number_format($game['invite_cost'])." ".$invite_currency['short_name']."s";
							else $message .= "Free";
							$message .= "</td></tr>\n";
							$message .= "<tr><td>Length of game:</td><td>";
							if ($game['final_round'] > 0) $message .= $game['final_round']." rounds (".format_seconds($game_total_seconds).")";
							$message .= "</td></tr>\n";
							$message .= "<tr><td>Inflation:</td><td>".ucwords($game['inflation'])."</td></tr>\n";
							$message .= "<tr><td>Inflation Rate:</td><td>";
							if ($game['inflation'] == "linear") $message .= format_bignum($round_reward)." coins per round (".format_bignum($game['pos_reward']/pow(10,8))." to voters, ".format_bignum($game['pow_reward']*$game['round_length']/pow(10,8))." to miners)";
							else $message .= 100*$game['exponential_inflation_rate']."% per round (".(100 - 100*$game['exponential_inflation_minershare'])."% to voters, ".(100*$game['exponential_inflation_minershare'])." to miners)";
							$message .= "</td></tr>\n";
							$message .= "<tr><td>Blocks per round:</td><td>".$game['round_length']."</td></tr>\n";
							$message .= "<tr><td>Block target time:</td><td>".format_seconds($game['seconds_per_block'])."</td></tr>\n";
							$message .= "<tr><td>Coins locked after transaction for:&nbsp;&nbsp;</td><td>".$game['maturity']." blocks</td></tr>\n";
							$message .= "</table></p>\n";

							$message .= "<p>To start playing, accept your invitation by following <a href=\"".$GLOBALS['base_url']."/wallet/".$game['url_identifier']."/?invite_key=".$invitation['invitation_key']."\">this link</a>.</p>";

							$message .= "<p>This message was sent to you by ".$GLOBALS['site_name']."</p>";

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