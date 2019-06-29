<?php
require(AppSettings::srcPath()."/includes/connect.php");

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);

	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$game_def = new DailyCryptoMarketsGameDefinition($app);
		$game_def->regular_actions($game);
		
		echo "Done!\n";
	}
	else echo "Invalid game ID.\n";
}
else echo "You don't have permission to run this script.\n";
?>