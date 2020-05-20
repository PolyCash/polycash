<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

function finish_reading_bet_rows($linear_or_binary, $app, $game, &$bet, &$prev_user_game_id, &$betcount_by_user_game, &$num_wins, &$num_losses, &$num_unresolved, &$ref_user_game, &$prev_account_id, &$unresolved_net_delta, &$prev_last_notified_account_value, &$html_by_user_game, &$table_header_html, &$net_delta, &$net_stake, &$pending_stake, &$resolved_fees_paid, &$num_refunded) {
	if ($prev_user_game_id !== false) {
		$betcount_by_user_game[$prev_user_game_id] = $num_wins+$num_losses+$num_unresolved;
		$ref_user_game = ['account_id'=>$prev_account_id];
		
		$account_value = $game->account_balance($prev_account_id)+$game->user_pending_bets($ref_user_game);
		$bet_summary = "";
		
		if ($linear_or_binary == "binary") {
			$bet_summary = "In ".$game->db_game['name']." you placed ".$app->bets_summary($game, $net_stake, $num_wins, $num_losses, $num_unresolved, $num_refunded, $pending_stake, $net_delta, $resolved_fees_paid).".<br/>\n";
			$bet_summary .= "Your account is now worth <a href=\"".AppSettings::getParam('base_url')."/wallet/".$game->db_game['url_identifier']."/?action=change_user_game&user_game_id=".$prev_user_game_id."\">".$app->format_bignum($account_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."</a>";
			$estimated_account_value = $account_value/pow(10, $game->db_game['decimal_places']);
		}
		else {
			if ($unresolved_net_delta != 0) {
				$bet_summary .= "You're ";
				if ($unresolved_net_delta >= 0) $bet_summary .= 'up <font style="color: #0a0;">';
				else $bet_summary .= 'down <font style="color: #f00;">';
				$bet_summary .= $app->format_bignum(abs($unresolved_net_delta)).' '.$game->db_game['coin_name_plural'];
				$bet_summary .= '</font> on your outstanding positions.<br/>';
			}
			
			$estimated_account_value = $unresolved_net_delta+($account_value/pow(10, $game->db_game['decimal_places']));
			
			$bet_summary .= "Your account is now worth <a href=\"".AppSettings::getParam('base_url')."/explorer/games/".$game->db_game['url_identifier']."/my_bets/?user_game_id=".$prev_user_game_id."\">".$app->format_bignum($estimated_account_value)." ".$game->db_game['coin_name_plural']."</a>";
		}
		
		if ($prev_last_notified_account_value !== false) {
			$delta_since_last_notification = $estimated_account_value-$prev_last_notified_account_value;
			$bet_summary .= ".<br/>You're";
			if ($delta_since_last_notification >= 0) $bet_summary .= ' up <font style="color: #0a0;">';
			else $bet_summary .= ' down <font style="color: #f00;">';
			$bet_summary .= $app->format_bignum(abs($delta_since_last_notification)).' '.$game->db_game['coin_name_plural'];
			$bet_summary .= '</font> since yesterday.';
		}
		$app->set_last_account_notified_value($prev_account_id, $estimated_account_value);
		
		$html_by_user_game[$prev_user_game_id] = "<p>".$bet_summary."</p><table>".$table_header_html.$html_by_user_game[$prev_user_game_id]."</table>\n";
		
		$net_delta = 0;
		$net_stake = 0;
		$pending_stake = 0;
		$resolved_fees_paid = 0;
		$num_wins = 0;
		$num_losses = 0;
		$num_unresolved = 0;
		$num_refunded = 0;
		$unresolved_net_delta = 0;
	}
	
	$prev_user_game_id = $bet['user_game_id'];
	$prev_account_id = $bet['account_id'];
	$prev_last_notified_account_value = !empty($bet['last_notified_account_value']) ? $bet['last_notified_account_value'] : false;
}

if ($app->running_as_admin()) {
	// First render binary bets
	$html_by_user_game = [];
	$betcount_by_user_game = [];
	$notification_email_by_user_game = [];
	
	$binary_table_header_html = '<tr><td>Stake</td><td>Payout</td><td>Odds</td><td>Effectiveness</td><td>Option</td><td>Event</td><td>Outcome</td></tr>';
	
	$running_games = $app->run_query("SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE g.game_status='running' GROUP BY g.game_id ORDER BY g.game_id ASC");
	
	while ($db_game = $running_games->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$coins_per_vote = $app->coins_per_vote($game->db_game);
		$last_block_id = $game->blockchain->last_block_id();
		$current_round = $game->block_to_round(1+$last_block_id);
		
		$net_delta = 0;
		$net_stake = 0;
		$pending_stake = 0;
		$resolved_fees_paid = 0;
		$num_wins = 0;
		$num_losses = 0;
		$num_unresolved = 0;
		$num_refunded = 0;
		$prev_user_game_id = false;
		$prev_account_id = false;
		$prev_last_notified_account_value = false;
		
		$bet_params = [
			'game_id' => $game->db_game['game_id'],
			'blockchain_id' => $game->blockchain->db_blockchain['blockchain_id'],
			'ref_time' => (time()-3600*24)
		];
		$bet_q = "SELECT gio.game_io_id, gio.colored_amount, gio.option_id, gio.is_coinbase, gio.is_resolved, gio.game_out_index, gio.contract_parts, t.tx_hash, ca.account_id, ca.last_notified_account_value, p.ref_block_id, p.ref_round_id, p.ref_coin_blocks, p.ref_coin_rounds, p.effectiveness_factor, p.effective_destroy_amount, p.destroy_amount, p.".$game->db_game['payout_weight']."s_destroyed, p.game_io_id AS parent_game_io_id, p.contract_parts AS total_contract_parts, io.spend_transaction_id, io.spend_status, ev.*, o.effective_destroy_score AS option_effective_destroy_score, o.unconfirmed_effective_destroy_score, o.unconfirmed_votes, o.name AS option_name, ev.destroy_score AS sum_destroy_score, p.votes, o.votes AS option_votes, ug.user_game_id, u.notification_email FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN users u ON ug.user_id=u.user_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transactions t ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id LEFT JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN options o ON gio.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id JOIN blocks b ON ev.event_payout_block=b.block_id WHERE gio.game_id=:game_id AND gio.is_coinbase=1 AND gio.resolved_before_spent=1 AND b.blockchain_id=:blockchain_id AND b.time_created > :ref_time AND ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ev.payout_rule='binary' ORDER BY ug.user_game_id ASC, ev.event_index ASC;";
		$bets = $app->run_query($bet_q, $bet_params)->fetchAll();
		
		for ($bet_i=0; $bet_i<count($bets); $bet_i++) {
			$bet = $bets[$bet_i];
			
			if (!array_key_exists($bet['user_game_id'], $html_by_user_game)) {
				$html_by_user_game[$bet['user_game_id']] = "";
				$betcount_by_user_game[$bet['user_game_id']] = 0;
				$notification_email_by_user_game[$bet['user_game_id']] = $bet['notification_email'];
			}
			
			if ($bet['user_game_id'] != $prev_user_game_id) {
				finish_reading_bet_rows("binary", $app, $game, $bet, $prev_user_game_id, $betcount_by_user_game, $num_wins, $num_losses, $num_unresolved, $ref_user_game, $prev_account_id, $unresolved_net_delta, $prev_last_notified_account_value, $html_by_user_game, $binary_table_header_html, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_refunded);
			}
			
			$this_bet_html = $app->render_binary_bet($bet, $game, $coins_per_vote, $current_round, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_wins, $num_losses, $num_unresolved, $num_refunded, 'td', $last_block_id);
			$this_bet_html  = str_replace('<font class="greentext">', '<font style="color: #0a0;">', $this_bet_html);
			$this_bet_html  = str_replace('<font class="redtext">', '<font style="color: #f00;">', $this_bet_html);
			
			$html_by_user_game[$bet['user_game_id']] .= "<tr>".$this_bet_html."</tr>\n";
		}
		
		finish_reading_bet_rows("binary", $app, $game, $bet, $prev_user_game_id, $betcount_by_user_game, $num_wins, $num_losses, $num_unresolved, $ref_user_game, $prev_account_id, $unresolved_net_delta, $prev_last_notified_account_value, $html_by_user_game, $binary_table_header_html, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_refunded);
	}
	
	foreach ($html_by_user_game as $user_game_id=>$html) {
		$delivery_key = $app->random_string(16);
	
		if ($betcount_by_user_game[$user_game_id] > 0) {
			$email = $notification_email_by_user_game[$user_game_id];
			
			$message_html = "<p>You have bets in ".AppSettings::getParam('site_name_short')." which were resolved in the past 24 hours.<br/>\nTo stop receiving these notifications please <a href=\"".AppSettings::getParam('base_url')."/wallet/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n".$html;
			
			$app->mail_async($email, AppSettings::getParam('site_name_short'), "no-reply@".AppSettings::getParam('site_domain'), "Your bets have been paid out", $message_html, "", "", $delivery_key);
			
			echo "Sent binary notifications to ".$email."<br/>\n";
		}
	}
	
	// Now render linear bets (synthetic / tracked assets)
	$html_by_user_game = [];
	$betcount_by_user_game = [];
	$notification_email_by_user_game = [];
	
	$linear_table_header_html = '<tr><td>Amt Paid</td><td>Option Purchased</td><td>Range</td><td>Position Purchased</td><td>Asset Performance</td><td>Position Performance</td></tr>';
	
	$running_games = $app->run_query("SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE g.game_status='running' GROUP BY g.game_id ORDER BY g.game_id ASC");
	
	while ($db_game = $running_games->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$coins_per_vote = $app->coins_per_vote($game->db_game);
		$last_block_id = $game->blockchain->last_block_id();
		$current_round = $game->block_to_round(1+$last_block_id);
		
		$net_delta = 0;
		$net_stake = 0;
		$pending_stake = 0;
		$resolved_fees_paid = 0;
		$num_wins = 0;
		$num_losses = 0;
		$num_unresolved = 0;
		$num_refunded = 0;
		$unresolved_net_delta = 0;
		$prev_user_game_id = false;
		$prev_account_id = false;
		$prev_last_notified_account_value = false;
		
		$linear_bet_params = [
			'game_id' => $game->db_game['game_id']
		];
		$linear_bet_q = "SELECT gio.game_io_id, gio.colored_amount, gio.option_id, gio.is_coinbase, gio.is_resolved, gio.game_out_index, gio.contract_parts, t.tx_hash, ca.account_id, ca.last_notified_account_value, p.ref_block_id, p.ref_round_id, p.ref_coin_blocks, p.ref_coin_rounds, p.effectiveness_factor, p.effective_destroy_amount, p.destroy_amount, p.".$game->db_game['payout_weight']."s_destroyed, p.game_io_id AS parent_game_io_id, p.contract_parts AS total_contract_parts, io.spend_transaction_id, io.spend_status, ev.*, o.entity_id, o.effective_destroy_score AS option_effective_destroy_score, o.unconfirmed_effective_destroy_score, o.unconfirmed_votes, o.name AS option_name, o.event_option_index, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, p.votes, o.votes AS option_votes, ug.user_game_id, u.notification_email FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id JOIN currency_accounts ca ON ak.account_id=ca.account_id JOIN user_games ug ON ug.account_id=ca.account_id JOIN users u ON ug.user_id=u.user_id JOIN transaction_ios io ON a.address_id=io.address_id JOIN transactions t ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id LEFT JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN options o ON gio.option_id=o.option_id JOIN events ev ON o.event_id=ev.event_id WHERE gio.game_id=:game_id AND gio.is_coinbase=1 AND gio.resolved_before_spent=1 AND ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ev.payout_rule='linear' AND ev.event_starting_block <= ".$last_block_id." AND ev.event_payout_block >= ".$last_block_id." ORDER BY ug.user_game_id ASC, ev.event_index ASC;";
		$linear_bets = $app->run_query($linear_bet_q, $linear_bet_params)->fetchAll();
		
		for ($bet_i=0; $bet_i<count($linear_bets); $bet_i++) {
			$bet = $linear_bets[$bet_i];
			
			if (!array_key_exists($bet['user_game_id'], $html_by_user_game)) {
				$html_by_user_game[$bet['user_game_id']] = "";
				$betcount_by_user_game[$bet['user_game_id']] = 0;
				$notification_email_by_user_game[$bet['user_game_id']] = $bet['notification_email'];
			}
			
			if ($bet['user_game_id'] != $prev_user_game_id) {
				finish_reading_bet_rows("linear", $app, $game, $bet, $prev_user_game_id, $betcount_by_user_game, $num_wins, $num_losses, $num_unresolved, $ref_user_game, $prev_account_id, $unresolved_net_delta, $prev_last_notified_account_value, $html_by_user_game, $linear_table_header_html, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_refunded);
			}
			
			$ref_html = "";
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $bet_net_delta, $payout_fees) = $game->get_payout_info($bet, $coins_per_vote, $last_block_id, $ref_html);
			
			$this_bet_html = $app->render_linear_bet('td', $bet, $game, $inflation_stake, $effective_paid, $current_leverage, $equivalent_contracts, $borrow_delta, $track_pay_price, $bought_price_usd, $fair_io_value, $bet_net_delta, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_wins, $num_losses, $num_unresolved, $num_refunded, $unresolved_net_delta);
			$this_bet_html  = str_replace('<font class="greentext">', '<font style="color: #0a0;">', $this_bet_html);
			$this_bet_html  = str_replace('<font class="redtext">', '<font style="color: #f00;">', $this_bet_html);
			
			$html_by_user_game[$bet['user_game_id']] .= "<tr>".$this_bet_html."</tr>\n";
		}
		
		if ($prev_user_game_id) {
			finish_reading_bet_rows("linear", $app, $game, $bet, $prev_user_game_id, $betcount_by_user_game, $num_wins, $num_losses, $num_unresolved, $ref_user_game, $prev_account_id, $unresolved_net_delta, $prev_last_notified_account_value, $html_by_user_game, $linear_table_header_html, $net_delta, $net_stake, $pending_stake, $resolved_fees_paid, $num_refunded);
		}
	}
	
	foreach ($html_by_user_game as $user_game_id=>$html) {
		$delivery_key = $app->random_string(16);
	
		if ($betcount_by_user_game[$user_game_id] > 0) {
			$email = $notification_email_by_user_game[$user_game_id];
			
			$message_html = "<p>You have outstanding positions in ".AppSettings::getParam('site_name_short')." games.<br/>\nTo stop receiving these notifications please <a href=\"".AppSettings::getParam('base_url')."/wallet/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n".$html;
			
			$app->mail_async($email, AppSettings::getParam('site_name_short'), "no-reply@".AppSettings::getParam('site_domain'), "Your positions have changed in value", $message_html, "", "", $delivery_key);
			
			echo "Sent linear notifications to ".$email."<br/>\n";
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>