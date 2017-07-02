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
		$game->delete_reset_game($action);
		$game->blockchain->unset_first_required_block();
		
		$game->update_db_game();
		$until_block = $game->blockchain->last_block_id()+1;
		if (!empty($game->db_game['final_round'])) {
			$until_block = $game->db_game['game_starting_block'] + $game->db_game['round_length']*$game->db_game['final_round'];
			echo "until block: $until_block<br/>\n";
		}
		$game->ensure_events_until_block($until_block);
		$game->load_current_events();
	}
	
	echo "Great, the game has been ".$action."!\n";
}
else echo "Incorrect key.\n";
?>
