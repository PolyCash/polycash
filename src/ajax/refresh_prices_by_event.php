<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$db_event = $app->fetch_event_by_id((int)$_REQUEST['event_id']);
			
			if ($db_event && $db_event['game_id'] == $game->db_game['game_id']) {
				$CryptoDuels = new CryptoDuelsGameDefinition($app);
				$successful = $CryptoDuels->refresh_prices_by_event($game, $db_event);
				
				if ($successful) $app->output_message(7, "Prices have been refreshed.", false);
				else $app->output_message(6, "There was an error refreshing the prices.", false);
			}
			else $app->output_message(5, "Invalid event ID.", false);
		}
		else $app->output_message(4, "You don't have permission to edit this game.", false);
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "Please log in.", false);
?>