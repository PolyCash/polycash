<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$running_games = $app->fetch_running_games();

while ($db_running_game = $running_games->fetch()) {
	$blockchain = new Blockchain($app, $db_running_game['blockchain_id']);
	$running_game = new Game($blockchain, $db_running_game['game_id']);
	$last_block_id = $blockchain->last_block_id();
	$current_round = $running_game->block_to_round($last_block_id+1);
	$seconds_per_block = $blockchain->seconds_per_block('target');
	$coins_per_vote = $app->coins_per_vote($running_game->db_game);
	
	if (!empty($running_game->db_game['auto_stake_featured_strategy_id']) && $running_game->last_block_id() == $blockchain->last_block_id()) {
		$notify_users = $app->run_query("SELECT ug.*, u.notification_email FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ug.game_id=:game_id;", ['game_id' => $running_game->db_game['game_id']])->fetchAll();
		
		echo "Checking ".count($notify_users)." users\n";
		
		foreach ($notify_users as $user_game) {
			$notify_user = new User($app, $user_game['user_id']);
			
			if ($user_game['account_id'] > 0 && (empty($user_game['latest_speedup_reminder_time']) || $user_game['latest_speedup_reminder_time'] < time() - (3600*24*2.5))) {
				$balance = $running_game->account_balance($user_game['account_id']);
				
				if ($balance > 0) {
					list($user_votes, $votes_value) = $notify_user->user_current_votes($running_game, $last_block_id, $current_round, $user_game);
					
					if ($running_game->db_game['payout_weight'] == "coin_round") $votes_per_coin_per_round = 1;
					else if ($running_game->db_game['payout_weight'] == "coin_block") $votes_per_coin_per_round = $running_game->db_game['round_length'];
					else throw new Exception("This feature is disabled for deprecated payout_weight modes.");
					
					$gain_per_round = $votes_per_coin_per_round*$balance*$coins_per_vote;
					$seconds_per_round = $seconds_per_block*$running_game->db_game['round_length'];
					$rounds_per_day = 3600*24/$seconds_per_round;
					$gain_per_day = $gain_per_round*$rounds_per_day;
					
					$speedup_pct = $votes_value/$balance*100;
					
					if ($speedup_pct > 0.1) {
						$subject = "Earn ".$running_game->db_game['coin_name_plural']." ".round($speedup_pct, 3)."% faster by staking your ".$app->format_bignum($votes_value/pow(10, $running_game->db_game['decimal_places']))." in unrealized ".$running_game->db_game['coin_name_plural'];
						
						$auto_stake_key = $app->random_string(24);
						
						$message = $app->render_view('speedup_email', [
							'game' => $running_game,
							'balance' => $balance,
							'votes_value' => $votes_value,
							'speedup_pct' => $speedup_pct,
							'gain_per_day' => $gain_per_day,
							'wallet_link' => AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier'].'/?action=change_user_game&user_game_id='.$user_game['user_game_id'],
							'account_id' => $user_game['account_id'],
							'auto_stake_link' => AppSettings::getParam('base_url').'/auto_stake/?account_id='.$user_game['account_id']."&stake_key=".$auto_stake_key,
						]);
						
						echo $user_game['notification_email'].", #".$user_game['account_id'].", bal:".$balance/pow(10, $running_game->db_game['decimal_places']).", unrealized:".$votes_value/pow(10, $running_game->db_game['decimal_places']).", gain per day:".$gain_per_day/pow(10, $running_game->db_game['decimal_places']).", speedup:".$speedup_pct."%\n";
						
						$delivery_key = $app->random_string(16);
						
						$app->mail_async($user_game['notification_email'], AppSettings::getParam('site_name'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message, "", "", $delivery_key);
						
						$app->run_query("UPDATE user_games SET latest_speedup_reminder_time=".time().", auto_stake_key='".$auto_stake_key."', auto_stake_tx_hash=NULL WHERE user_game_id=:user_game_id;", [
							'user_game_id' => $user_game['user_game_id']
						]);
					}
				}
			}
		}
	}
	
	if ($db_running_game['every_event_bet_reminder_minutes'] > 0) {
		$running_game->load_current_events();
		
		echo $db_running_game['name'].": ".$db_running_game['every_event_bet_reminder_minutes']." minutes, ".count($running_game->current_events)." current events\n";
		
		foreach ($running_game->current_events as $current_event) {
			$blocks_left = $current_event->db_event['event_final_block']-$last_block_id;
			$seconds_left = $seconds_per_block*$blocks_left;
			
			$event_html = $current_event->event_html(null, false, true, 0, 0);
			$event_html = str_replace("background-image: url('/images", "background-repeat: no-repeat; background-size: cover; background-position: center center; border-radius: 50%; background-image: url('".AppSettings::getParam('base_url')."/images", $event_html);
			$event_html = str_replace('class="vote_option_box_container"', 'style="position: relative; display: inline-block; margin-right: 20px;"', $event_html);
			$event_html = str_replace('href="/', 'href="'.AppSettings::getParam('base_url').'/', $event_html);
			$event_html = str_replace(' style="font-size: 88%"', '', $event_html);
			
			if ($current_event->db_event['event_index']%6 == 0 && $seconds_left > 0 && $seconds_left <= $db_running_game['every_event_bet_reminder_minutes']*60) {
				$ref_user_game = false;
				$faucet_io = $running_game->check_faucet($ref_user_game);
				
				$subject = $app->format_seconds(round($seconds_left/60)*60)." left to bet in ".$running_game->db_game['name']." ".$current_event->db_event['event_name'];
				
				$notify_users = $app->run_query("SELECT ug.*, u.notification_email FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ug.game_id=:game_id GROUP BY ug.user_id;", ['game_id' => $running_game->db_game['game_id']]);
				
				while ($notify_user = $notify_users->fetch()) {
					$sec_since_last_notification = time() - $notify_user['latest_event_reminder_time'];
					
					if ($sec_since_last_notification > 60*$db_running_game['every_event_bet_reminder_minutes']) {
						echo $notify_user['notification_email']."\n";
						
						list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available) = $running_game->user_faucet_info($notify_user['user_id'], $notify_user['game_id']);
						
						$faucet_txt = "";
						
						if ($eligible_for_faucet) {
							if ($faucet_io) {
								$faucet_txt .= "You're eligible to <a href=\"".AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier']."/\">claim ".$running_game->display_coins($faucet_io['colored_amount_sum'])."</a> from the faucet.\n";
							}
							else $faucet_txt .= "There's no money in the faucet right now.\n";
						}
						else {
							if ($time_available) {
								$faucet_txt .= "You'll be eligible to claim ".$running_game->display_coins($faucet_io['colored_amount_sum'])." from the faucet in ".$app->format_seconds($time_available-time()).".";
							}
							else $faucet_txt .= "You're not eligible to claim coins from this faucet.";
						}
						
						$delivery_key = $app->random_string(16);
						
						$message_html = "<p>There's still time left to bet in ".$current_event->db_event['event_name']."<br/>\n";
						$message_html .= '<div style="display: block;">'.$event_html."</div><br/><br/>\n";
						$message_html .= 'To place a bet, follow <a href="'.AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier'].'/">this link</a><br/><br/>';
						$message_html .= $faucet_txt."<br/><br/>\n";
						$message_html .= "To stop receiving these notifications please <a href=\"".AppSettings::getParam('base_url')."/wallet/".$running_game->db_game['url_identifier']."/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n";
						
						$app->mail_async($notify_user['notification_email'], AppSettings::getParam('site_name'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message_html, "", "", $delivery_key);
						
						$app->set_latest_event_reminder_time($notify_user['user_game_id'], time());
					}
				}
			}
		}
	}
}
