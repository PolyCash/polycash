<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$from_block_height = (int) $_REQUEST['from_block_height'];
	$to_block_height = (int) $_REQUEST['to_block_height'];
	
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	for ($block_height=$from_block_height; $block_height<=$to_block_height; $block_height++) {
		$log_text = "";
		list($successful, $log_text) = $game->add_block($block_height);
		echo "$log_text<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>