<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	if ($game) {
		$action = 'reset';
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "delete") $action = "delete";
		
		if (!empty($_REQUEST['block_id'])) {
			$block_id = (int) $_REQUEST['block_id'];
			$block_id = max(0, $block_id);
			
			if ($block_id <= $blockchain->last_block_id() && $block_id >= $game->db_game['game_starting_block']) {
				echo "Resetting game from block #".$block_id."<br/>\n";
				$game->delete_from_block($block_id);
			}
			else echo "Invalid block ID.<br/>\n";
		}
		else {
			$game->delete_reset_game($action);
			$game->blockchain->unset_first_required_block();
			
			$game->update_db_game();
			
			if ($game->blockchain->db_blockchain['only_game_id'] == $game->db_game['game_id']) {
				$game->start_game();
			}
		}
	}
	else echo "Failed to load game #".$game_id."<br/>\n";
	
	echo "Great, the game has been ".$action."!\n";
}
else echo "Incorrect key.\n";
?>
