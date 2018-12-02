<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id','block_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->fetch_db_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	if ($game) {
		if ($_REQUEST['block_id'] > 0) {
			$block_id = (int)$_REQUEST['block_id'];
			$game->reset_blocks_from_block($block_id);
			
			echo $game->db_game['name']." has been reset from block ".$block_id."\n";
		}
		else echo "Invalid block supplied.\n";
	}
	else echo "Failed to load game #".$game_id."<br/>\n";
}
else echo "You need admin privileges to run this script.\n";
?>