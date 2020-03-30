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
			
			if ($seconds_left > 0 && $seconds_left > (($db_running_game['every_event_bet_reminder_minutes']-5)*60) && $seconds_left <= $db_running_game['every_event_bet_reminder_minutes']*60) {
				$subject = $app->format_seconds(round($seconds_left/60)*60)." left to bet in ".$running_game->db_game['name']." ".$current_event->db_event['event_name'];
				
				$notify_users = $app->run_query("SELECT * FROM user_games ug JOIN users u ON ug.user_id=u.user_id WHERE ug.notification_preference='email' AND u.notification_email LIKE '%@%' AND ug.game_id=:game_id;", ['game_id' => $running_game->db_game['game_id']]);
				
				while ($notify_user = $notify_users->fetch()) {
					echo $notify_user['notification_email']."\n";
					
					$delivery_key = $app->random_string(16);
					
					$message_html = "<p>There's still time left to bet in ".$current_event->db_event['event_name']."<br/>\n";
					$message_html .= '<div style="display: block;">'.$event_html."</div><br/><br/>\n";
					$message_html .= 'To place a bet, follow <a href="'.AppSettings::getParam('base_url').'/wallet/'.$running_game->db_game['url_identifier'].'/">this link</a><br/><br/>';
					$message_html .= "To stop receiving these notifications please <a href=\"".AppSettings::getParam('base_url')."/wallet/".$running_game->db_game['url_identifier']."/?action=unsubscribe&delivery_key=".$delivery_key."\">click here to unsubscribe</a></p>\n";
					
					$app->mail_async($notify_user['notification_email'], AppSettings::getParam('site_name_short'), "no-reply@".AppSettings::getParam('site_domain'), $subject, $message_html, "", "", $delivery_key);
				}
			}
		}
	}
}
