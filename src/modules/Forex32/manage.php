<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include(dirname(dirname(dirname(__FILE__)))."/modules/Forex32/Forex32Manager.php");

$allowed_params = ['game_id', 'action', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

$game_id = $_REQUEST['game_id'];
$db_game = $app->fetch_game_by_id($game_id);
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $game_id);

$module = new Forex32GameDefinition($app);
$manager = new Forex32Manager($module, $app, $game);

if (!empty($_REQUEST['force'])) $force=true;
else $force=false;

$print_debug = true;

if ($_REQUEST['action'] == "add_events") {
	$manager->add_events($skip_record_migration=false, $print_debug);
}
/*else if ($_REQUEST['action'] == "set_blocks") {
	$manager->custom_set_event_blocks($print_debug);
}
else if ($_REQUEST['action'] == "set_outcomes") {
	$manager->set_outcomes($print_debug);
}*/
else if ($_REQUEST['action'] == "regular_actions") {
	$manager->regular_actions($force, $print_debug);
}
else echo "Please specify an action.\n";
