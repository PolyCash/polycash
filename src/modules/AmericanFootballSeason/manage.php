<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include(dirname(__FILE__)."/AmericanFootballSeasonManager.php");

$allowed_params = ['game_id', 'action'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);

	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$module = new AmericanFootballSeasonGameDefinition($app);
		$manager = new AmericanFootballSeasonManager($module, $app, $game);
		
		$action = $_REQUEST['action'];
		
		if ($action == "add_events") $manager->add_events(true);
		else if ($action == "set_outcomes") $manager->set_outcomes(true);
		else if ($action == "set_blocks") $manager->set_blocks(true);
		else if ($action == "fix_images") $manager->fix_images(true);
		else if ($action == "regular_actions") $module->regular_actions($game);
		else echo "Invalid action.\n";
	}
	else echo "Invalid game ID.\n";
}
else echo "You don't have permission to run this script.\n";
?>