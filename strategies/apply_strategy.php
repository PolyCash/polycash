<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	if ($thisuser->db_user['user_id'] == $user_game['user_id']) {
		$blockchain = new Blockchain($app, $user_game['blockchain_id']);
		$game = new Game($blockchain, $user_game['game_id']);
		$mining_block_id = $blockchain->last_block_id()+1;
		$round_id = $game->block_to_round($mining_block_id);
		
		$log_text = "";
		$api_response = false;
		$game->apply_user_strategy($log_text, $user_game, $mining_block_id, $round_id, $api_response, true);
		$game->update_option_votes();
		
		$message = "Your strategy has been applied.";
		if ($api_response) $message = $api_response->message;
		
		$app->output_message(1, $message, false);
	}
	else $app->output_message(2, "You don't have permission to apply this strategy.\n", false);
}
else $app->output_message(3, "Invalid game ID or you're not logged in.\n", false);
?>