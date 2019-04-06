<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_db_game_by_id($game_id);

	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$game_def = new DailyCryptoMarketsGameDefinition($app);
		$events = $game_def->events_starting_between_rounds($game, 1, 12, 50, 281501);
		
		echo "<pre>".json_encode($events, JSON_PRETTY_PRINT)."</pre>\n";
	}
	else echo "Invalid game ID.\n";
}
else echo "You don't have permission to run this script.\n";
?>