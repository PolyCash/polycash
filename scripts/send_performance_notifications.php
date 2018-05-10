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
	
	$game_q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE g.game_status='running' GROUP BY g.game_id ORDER BY g.game_id ASC";
	$game_r = $app->run_query($game_q);
	
	while ($db_game = $game_r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$events = array();
		
		$event_q = "SELECT *, b.time_created AS block_time FROM events e JOIN blocks b ON e.event_payout_block=b.block_id WHERE b.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND e.game_id='".$game->db_game['game_id']."' AND b.time_created > ".(time()-3600*24)." ORDER BY e.event_index ASC;";
		$event_r = $app->run_query($event_q);
		
		while ($db_event = $event_r->fetch()) {
			array_push($events, $db_event);
		}
		
		$account_q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id LEFT JOIN user_strategies us ON ug.strategy_id=us.strategy_id LEFT JOIN featured_strategies fs ON us.featured_strategy_id=fs.featured_strategy_id WHERE ug.game_id='".$game->db_game['game_id']."' AND ug.notification_preference='email' AND u.notification_email LIKE '%@%' ORDER BY ug.user_id ASC;";
		$account_r = $app->run_query($account_q);
		
		while ($db_account = $account_r->fetch()) {
			$account_balance = $game->account_balance_at_block($db_account['account_id'], $game->blockchain->last_block_id(), true);
			
			$html = "<p>Account #".$db_account['account_id'];
			$html .= "&nbsp;&nbsp;&nbsp;Current balance: ".$app->format_bignum($account_balance/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."&nbsp;&nbsp;&nbsp;";
			
			if (!empty($db_account['strategy_name'])) $html .= " (".$db_account['strategy_name'].")";
			$html .= "</p><table cellspacing=\"0\" cellpadding=\"5\" border=\"1\">\n";
			
			for ($i=0; $i<count($events); $i++) {
				$bal1 = $game->account_balance_at_block($db_account['account_id'], $events[$i]['event_final_block'], false);
				$bal2 = $game->account_balance_at_block($db_account['account_id'], $events[$i]['event_final_block'], true);
				$bal_increase = $bal2-$bal1;
				
				if ($bal1 > 0) $pct_increase = 100*$bal_increase/$bal1;
				else $pct_increase = 0;
				
				$html .= "<tr>\n";
				$html .= "<td>".date("n/j/Y g:ia", $events[$i]['block_time'])."</td>\n";
				$html .= "<td><a href=\"".$GLOBALS['base_url']."/explorer/games/".$game->db_game['url_identifier']."/events/".$events[$i]['event_index']."\">".$events[$i]['event_name']."</a></td>\n";
				$html .= "<td>".$app->format_bignum($bal1/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."</td>";
				$html .= "<td>+".$app->format_bignum($bal_increase/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." (+".round($pct_increase,2)."%)</td>";
				$html .= "</tr>\n";
			}
			$html .= "</table><br/>\n";
			
			if (!array_key_exists($db_account['notification_email'], $html_by_email)) $html_by_email[$db_account['notification_email']] = "";
			$html_by_email[$db_account['notification_email']] .= $html;
		}
	}
	
	foreach ($html_by_email as $email=>$html) {
		$message_html = "<p>Your ".$GLOBALS['site_name_short']." accounts have changed in value over the past 24 hours. ";
		$message_html .= "Performances of your accounts are shown below. For more information, please <a href=\"".$GLOBALS['base_url']."/accounts/\">log in to your account</a>.</p>\n".$html;
		
		$app->mail_async($email, $GLOBALS['site_name_short'], "no-reply@".$GLOBALS['site_domain'], "Daily performances of your ".$GLOBALS['site_name_short']." accounts", $message_html, "", "");
	}
}
else echo "Incorrect key.";
?>