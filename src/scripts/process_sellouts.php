<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if (!empty($_REQUEST['block_id'])) {
			$block_id = (int) $_REQUEST['block_id'];
			$game->process_sellouts_in_block($block_id);
		}
		else {
			for ($block_id=$game->db_game['game_starting_block']; $block_id<=$game->blockchain->last_block_id(); $block_id++) {
				$game->process_sellouts_in_block($block_id);
			}
		}
	}
}
else echo "You need admin privileges to run this script.\n";
?>