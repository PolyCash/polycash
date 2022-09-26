<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$status_code = null;
$message = null;

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	if ($_REQUEST['action'] == "disable_game_peer") {
		$game_peer = $app->fetch_game_peer_by_id($_REQUEST['game_peer_id']);
		
		if ($game_peer && $db_game = $app->fetch_game_by_id($game_peer['game_id'])) {
			if ($db_game['creator_id'] == $thisuser->db_user['user_id']) {
				list($succeeded, $disable_error_message) = $app->disable_game_peer($game_peer);
				
				if ($succeeded) {
					$status_code = 1;
					$message = "Game peer was successfully disabled.";
				}
				else {
					$status_code = 6;
					$message = $disable_error_message;
				}
			}
			else {
				$status_code = 5;
				$message = "You don't have permission to manage peers for this game.";
			}
		}
		else {
			$status_code = 4;
			$message = "Please supply a valid peer id.";
		}
	}
	else {
		$status_code = 3;
		$message = "Please supply a valid action.";
	}
}
else {
	$status_code = 2;
	$message = "Please supply a valid synchronizer token.";
}

$app->output_message($status_code, $message);
