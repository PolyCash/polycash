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
	
	$table_header_html = '<tr><td>Stake</td><td>Payout</td><td>Odds</td><td>Effectiveness</td><td>Option</td><td>Event</td><td>Outcome</td></tr>';
	
	$game_q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE g.game_status='running' GROUP BY g.game_id ORDER BY g.game_id ASC";
	$game_r = $app->run_query($game_q);
	
	while ($db_game = $game_r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$coins_per_vote = $app->coins_per_vote($game->db_game);
		
		$bet_q = "SELECT gio.*, io.spend_transaction_id, ev.*, o.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS outcome_destroy_score, o.name AS option_name, gio.votes AS votes, o.votes AS option_votes, gio2.colored_amount AS payout_amount, u.notification_email FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN users u ON ug.user_id=u.user_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id JOIN options o ON gio.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id JOIN blocks b ON ev.event_payout_block=b.block_id LEFT JOIN transaction_game_ios gio2 ON gio.payout_io_id=gio2.game_io_id WHERE gio.game_id=".$game->db_game['game_id']." AND gio.is_coinbase=0 AND b.time_created > ".(time()-3600*24)." AND ug.notification_preference='email' AND u.notification_email LIKE '%@%' ORDER BY gio.game_io_id ASC;";
		$bet_r = $app->run_query($bet_q);
		
		while ($bet = $bet_r->fetch()) {
			$this_bet_html = "";
			$expected_payout = ($bet['effective_destroy_amount']*$bet['outcome_destroy_score']/$bet['option_effective_destroy_score'] + ($bet['sum_score']*$coins_per_vote*$bet['votes']/$bet['option_votes']))/pow(10,$game->db_game['decimal_places']);
			$my_stake = ($bet['destroy_amount'] + $bet[$game->db_game['payout_weight']."s_destroyed"]*$coins_per_vote)/pow(10,$game->db_game['decimal_places']);
			
			if ($my_stake > 0) $payout_multiplier = $expected_payout/$my_stake;
			else $payout_multiplier = 0;
			
			$net_stake += $my_stake;
			if (empty($bet['winning_option_id'])) $pending_stake += $my_stake;
			
			$this_bet_html .= '<tr>';
			
			$this_bet_html .= '<td><a href="'.$GLOBALS['base_url'].'/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$bet['io_id'].'/">';
			if ($game->db_game['inflation'] == "exponential") {
				$this_bet_html .= $app->format_bignum($my_stake)."&nbsp;".$game->db_game['coin_abbreviation'];
			}
			else {
				$this_bet_html .= $app->format_bignum($bet['votes']/pow(10,$game->db_game['decimal_places']))." votes";
			}
			$this_bet_html .= "</a></td>\n";
			
			$this_bet_html .= "<td>";
			$this_bet_html .= $app->format_bignum($expected_payout)."&nbsp;".$game->db_game['coin_abbreviation'];
			$this_bet_html .= "</td>\n";
			
			$this_bet_html .= "<td>x".$app->format_bignum($payout_multiplier)."</td>\n";
			
			$this_bet_html .= "<td>";
			$this_bet_html .= round($bet['effectiveness_factor']*100, 2)."%";
			$this_bet_html .= "</td>\n";
			
			$this_bet_html .= "<td>".$bet['option_name']."</td>";
			$this_bet_html .= "<td><a target=\"_blank\" href=\"".$GLOBALS['base_url']."/explorer/games/".$game->db_game['url_identifier']."/events/".$bet['event_index']."\">".$bet['event_name']."</a></td>\n";
			
			if (empty($bet['winning_option_id'])) {
				$outcome_txt = "Not Resolved";
			}
			else {
				if ($bet['winning_option_id'] == $bet['option_id']) {
					$outcome_txt = "Won";
					$delta = $expected_payout - $my_stake;
				}
				else {
					$outcome_txt = "Lost";
					$delta = (-1)*$my_stake;
				}
			}
			
			$this_bet_html .= '<td style="color:';
			if (empty($bet['winning_option_id'])) $this_bet_html .= '#000';
			else if ($delta >= 0) $this_bet_html .= "#0b0";
			else $this_bet_html .= "#f00";
			$this_bet_html .= '">';
			$this_bet_html .= $outcome_txt;
			
			if (!empty($bet['winning_option_id'])) {
				$this_bet_html .= " &nbsp;&nbsp; ";
				if ($delta >= 0) $this_bet_html .= "+";
				else $this_bet_html .= "-";
				$this_bet_html .= $app->format_bignum(abs($delta));
				$this_bet_html .= " ".$game->db_game['coin_abbreviation'];
			}
			$this_bet_html .= "</td>\n";
			
			$this_bet_html .= "</tr>\n";
			
			if (!array_key_exists($bet['notification_email'], $html_by_email)) $html_by_email[$bet['notification_email']] = "";
			$html_by_email[$bet['notification_email']] .= $this_bet_html;
		}
	}
	
	foreach ($html_by_email as $email=>$html) {
		$delivery_key = $app->random_string(16);
		
		$message_html = "<p>You have bets in ".$GLOBALS['site_name_short']." which were resolved in the past 24 hours. ";
		$message_html .= "For more information, please <a href=\"".$GLOBALS['base_url']."/accounts/\">log in to your account</a>.</p><p>To stop receiving these notifications please <a href=\"".$GLOBALS['base_url']."/wallet/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n<table>".$table_header_html.$html."</table>\n";
		
		$app->mail_async($email, $GLOBALS['site_name_short'], "no-reply@".$GLOBALS['site_domain'], "Your ".$GLOBALS['site_name_short']." bets have been paid out", $message_html, "", "", $delivery_key);
		echo "Sent to ".$email."<br/>\n";
	}
}
else echo "Incorrect key.";
?>