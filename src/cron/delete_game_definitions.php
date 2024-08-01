<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug', 'game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$delete_game_definitions = $app->run_query("SELECT gd.game_definition_id FROM game_definitions gd JOIN games g ON gd.game_id=g.game_id WHERE gd.last_accessed_at<= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL g.keep_definitions_hours HOUR)) AND g.keep_definitions_hours IS NOT NULL ORDER BY gd.game_definition_id ASC LIMIT 1000;")->fetchAll();
	
	foreach ($delete_game_definitions as $delete_game_definition) {
		$app->print_debug("Deleting game definition #".$delete_game_definition['game_definition_id']);
		$app->run_query("DELETE FROM game_definitions WHERE game_definition_id=:game_definition_id;", [
			'game_definition_id' => $delete_game_definition['game_definition_id'],
		]);
		$app->print_debug("Deleted game definition #".$delete_game_definition['game_definition_id']." at ".round(microtime(true)-$script_start_time, 6));
	}
}
else echo "Please run this script as admin.\n";
