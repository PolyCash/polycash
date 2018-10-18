<?php
$host_not_required = TRUE;
include_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$html_by_email = array();
	$betcount_by_email = array();
	
	$table_header_html = '<tr><td>Stake</td><td>Payout</td><td>Odds</td><td>Effectiveness</td><td>Option</td><td>Event</td><td>Outcome</td></tr>';
	
	$game_q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE g.game_status='running' GROUP BY g.game_id ORDER BY g.game_id ASC";
	$game_r = $app->run_query($game_q);
	
	while ($db_game = $game_r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$coins_per_vote = $app->coins_per_vote($game->db_game);
		$last_block_id = $game->blockchain->last_block_id();
		$current_round = $game->block_to_round(1+$last_block_id);
		
		$net_delta = 0;
		$net_stake = 0;
		$pending_stake = 0;
		$num_wins = 0;
		$num_losses = 0;
		$num_unresolved = 0;
		$prev_user_game_id = false;
		$prev_notification_email = false;
		
		$bet_q = "SELECT gio.*, gio.destroy_amount AS destroy_amount, io.spend_transaction_id, io.spend_status, ev.*, et.vote_effectiveness_function, et.effectiveness_param1, o.effective_destroy_score AS option_effective_destroy_score, o.unconfirmed_effective_destroy_score, o.unconfirmed_votes, o.name AS option_name, ev.destroy_score AS sum_destroy_score, gio.votes AS votes, o.votes AS option_votes, gio2.colored_amount AS payout_amount, ug.user_game_id, u.notification_email FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN users u ON ug.user_id=u.user_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN options o ON gio.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id LEFT JOIN event_types et ON ev.event_type_id=et.event_type_id LEFT JOIN transaction_game_ios gio2 ON gio.payout_io_id=gio2.game_io_id JOIN blocks b ON ev.event_payout_block=b.block_id WHERE gio.game_id=".$game->db_game['game_id']." AND gio.is_coinbase=0 AND b.blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND b.time_created > ".(time()-3600*24)." AND ug.notification_preference='email' AND u.notification_email LIKE '%@%' ORDER BY ug.user_game_id ASC, ev.event_index ASC;";
		$bet_r = $app->run_query($bet_q);
		
		while ($bet = $bet_r->fetch()) {
			if (!array_key_exists($bet['notification_email'], $html_by_email)) {
				$html_by_email[$bet['notification_email']] = "";
				$betcount_by_email[$bet['notification_email']] = 0;
			}
			
			if ($bet['user_game_id'] != $prev_user_game_id) {
				if ($prev_user_game_id !== false) {
					$betcount_by_email[$prev_notification_email] = $num_wins+$num_losses+$num_unresolved;
					
					$bet_summary = "In <a href=\"".$GLOBALS['base_url']."/wallet/".$game->db_game['url_identifier']."\">".$game->db_game['name']."</a> ".lcfirst($app->bets_summary($game, $net_stake, $num_wins, $num_losses, $num_unresolved, $pending_stake, $net_delta));
					$html_by_email[$prev_notification_email] = "<p>".$bet_summary."</p><table>".$table_header_html.$html_by_email[$prev_notification_email]."</table>\n";
					
					$net_delta = 0;
					$net_stake = 0;
					$pending_stake = 0;
					$num_wins = 0;
					$num_losses = 0;
					$num_unresolved = 0;
				}
				
				$prev_user_game_id = $bet['user_game_id'];
				$prev_notification_email = $bet['notification_email'];
			}
			
			$this_bet_html = $app->render_bet($bet, $game, $coins_per_vote, $current_round, $net_delta, $net_stake, $pending_stake, $num_wins, $num_losses, $num_unresolved, 'td');
			
			$html_by_email[$bet['notification_email']] .= "<tr>".$this_bet_html."</tr>\n";
		}
	}
	
	$betcount_by_email[$prev_notification_email] = $num_wins+$num_losses+$num_unresolved;
	$bet_summary = "In <a href=\"".$GLOBALS['base_url']."/wallet/".$game->db_game['url_identifier']."\">".$game->db_game['name']."</a> ".lcfirst($app->bets_summary($game, $net_stake, $num_wins, $num_losses, $num_unresolved, $pending_stake, $net_delta));
	$html_by_email[$prev_notification_email] = "<p>".$bet_summary."</p><table>".$table_header_html.$html_by_email[$prev_notification_email]."</table>\n";
	
	foreach ($html_by_email as $email=>$html) {
		$delivery_key = $app->random_string(16);
	
		if ($betcount_by_email[$email] > 0) {
			$message_html = "<p>You have bets in ".$GLOBALS['site_name_short']." which were resolved in the past 24 hours.<br/>\nTo stop receiving these notifications please <a href=\"".$GLOBALS['base_url']."/wallet/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n".$html;
			
			$app->mail_async($email, $GLOBALS['site_name_short'], "no-reply@".$GLOBALS['site_domain'], "Your ".$GLOBALS['site_name_short']." bets have been paid out", $message_html, "", "", $delivery_key);
			
			echo "Sent to ".$email."<br/>\n";
		}
	}
}
else echo "Incorrect key.";
?>