<?php
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$from_block_height = (int) $_REQUEST['from_block_height'];
	$to_block_height = (int) $_REQUEST['to_block_height'];
	
	$db_game = $app->fetch_db_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	for ($block_height=$from_block_height; $block_height<=$to_block_height; $block_height++) {
		$log_text = "";
		list($successful, $log_text, $bulk_to_block) = $game->add_block($block_height);
		if ($bulk_to_block) $block_height = $bulk_to_block;
		echo "$log_text<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>