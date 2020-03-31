<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$running_games = $app->fetch_running_games();

while ($db_running_game = $running_games->fetch()) {
	if ($db_running_game['every_event_bet_reminder_minutes'] > 0) {
		$blockchain = new Blockchain($app, $db_running_game['blockchain_id']);
		$last_block_id = $blockchain->last_block_id();
		$seconds_per_block = $blockchain->seconds_per_block('target');
		
		$running_game = new Game($blockchain, $db_running_game['game_id']);
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
			
			if ($seconds_left > 0 && $seconds_left <= $db_running_game['every_event_bet_reminder_minutes']*60) {
				$ref_user_game = false;
				$faucet_io = $running_game->check_faucet($ref_user_game);
				
				$subject = $app->format_seconds(round($seconds_left/60)*60)." left to bet in ".$running_game->db_game['name']." ".$current_event->db_event['event_name'];
				
				$notify_users = $app->run_query("SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ug.game_id=:game_id GROUP BY ug.user_id;", ['game_id' => $running_game->db_game['game_id']]);
				
				while ($notify_user = $notify_users->fetch()) {
					$sec_since_last_notification = time() - $notify_user['latest_event_reminder_time'];
					
					if ($sec_since_last_notification > 60*$db_running_game['every_event_bet_reminder_minutes']) {
						echo $notify_user['notification_email']."\n";
						
						list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available) = $running_game->user_faucet_info($notify_user['user_id'], $notify_user['game_id']);
						
						$faucet_txt = "";
						
						if ($eligible_for_faucet) {
							if ($faucet_io) {
								$faucet_txt .= "You're eligible to <a href=\"".AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier']."/\">claim ".$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$running_game->db_game['decimal_places'])).' '.$running_game->db_game['coin_name_plural']."</a> from the faucet.\n";
							}
							else $faucet_txt .= "There's no money in the faucet right now.\n";
						}
						else {
							if ($time_available) {
								$faucet_txt .= "You'll be eligible to claim ".$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$running_game->db_game['decimal_places'])).' '.$running_game->db_game['coin_name_plural']." from the faucet in ".$app->format_seconds($time_available-time()).".";
							}
							else $faucet_txt .= "You're not eligible to claim coins from this faucet.";
						}
						
						$delivery_key = $app->random_string(16);
						
						$message_html = "<p>There's still time left to bet in ".$current_event->db_event['event_name']."<br/>\n";
						$message_html .= '<div style="display: block;">'.$event_html."</div><br/><br/>\n";
						$message_html .= 'To place a bet, follow <a href="'.AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier'].'/">this link</a><br/><br/>';
						$message_html .= $faucet_txt."<br/><br/>\n";
						$message_html .= "To stop receiving these notifications please <a href=\"".AppSettings::getParam('base_url')."/wallet/".$running_game->db_game['url_identifier']."/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n";
						
						$app->mail_async($notify_user['notification_email'], AppSettings::getParam('site_name_short'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message_html, "", "", $delivery_key);
						
						$app->set_latest_event_reminder_time($notify_user['user_game_id'], time());
					}
				}
			}
		}
	}
}
