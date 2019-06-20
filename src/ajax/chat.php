<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

$output_obj = array();

if ($thisuser && $game) {
	if ($game->db_game['public_players'] == 1) {
		$user_id = intval($_REQUEST['user_id']);
		
		$to_user = new User($app, $user_id);
		
		if ($to_user) {
			if ($thisuser->user_in_game($game->db_game['game_id']) && $to_user->user_in_game($game->db_game['game_id'])) {
				$action = $_REQUEST['action'];
				
				if ($action == "send") {
					$message = $app->strong_strip_tags($_REQUEST['message']);
					
					if ($message != "") {
						$app->run_query("INSERT INTO user_messages SET game_id='".$game->db_game['game_id']."', from_user_id='".$thisuser->db_user['user_id']."', to_user_id='".$to_user->db_user['user_id']."', message=".$app->quote_escape($message).", send_time='".time()."';");
					}
				}
				
				$output_obj['username'] = "Player".$to_user->db_user['user_id'];
				$output_obj['content'] = "";

				$thread_messages = $app->run_query("SELECT * FROM user_messages WHERE game_id=".$game->db_game['game_id']." AND ((from_user_id=".$thisuser->db_user['user_id']." AND to_user_id=".$to_user->db_user['user_id'].") OR (from_user_id=".$to_user->db_user['user_id']." AND to_user_id='".$thisuser->db_user['user_id']."'));");
				
				while ($message = $thread_messages->fetch()) {
					$time_disp = $app->format_seconds(time()-$message['send_time']).' ago';
					if (time()-$message['send_time'] > 3600*24) $time_disp .= " (".date("n/j/Y", $message['send_time']).")";
					
					$output_obj['content'] .= '<div class="user_message_holder"><div title="'.$time_disp.'" class="';
					if ($message['from_user_id'] == $thisuser->db_user['user_id']) $output_obj['content'] .= "user_message_sent";
					else $output_obj['content'] .= 'user_message_received';
					$output_obj['content'] .= '">';
					$output_obj['content'] .= $message['message'].'</div></div>';
				}
				
				$app->run_query("UPDATE user_messages SET seen=1 WHERE game_id='".$game->db_game['game_id']."' AND to_user_id='".$thisuser->db_user['user_id']."' AND from_user_id='".$to_user->db_user['user_id']."';");
			}
		}
	}
}
echo json_encode($output_obj);
?>