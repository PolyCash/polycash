<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$game_id = intval($_REQUEST['game_id']);

	$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game_id);
	
	if ($user_game) {
		if ($user_game['payment_required'] == 0) $status_code = 1;
		else $status_code = 2;
		
		$app->output_message($status_code, "", $user_game);
	}
	else $app->output_message(2, "", array('payment_required'=>1));
}
else $app->output_message(2, "Please log in", false);
?>
