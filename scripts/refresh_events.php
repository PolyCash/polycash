<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['event_id'] = $cmd_vars['event_id'];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['event_id'])) {
		$event_id = (int) $_REQUEST['event_id'];
		$event_q = "SELECT * FROM games g JOIN events e ON e.game_id=g.game_id WHERE e.event_id='".$event_id."';";
		$event_r = $app->run_query($event_q);
		
		if ($event_r->rowCount() > 0) {
			$db_event = $event_r->fetch();
			
			$blockchain = new Blockchain($app, $db_event['blockchain_id']);
			$game = new Game($blockchain, $db_event['game_id']);
			$event = new Event($game, false, $db_event['event_id']);
			
			$last_block_id = $blockchain->last_block_id();
			
			$event->update_option_votes($last_block_id, false);
			
			echo "Done!\n";
		}
	}
	else if (!empty($_REQUEST['game_id'])) {
		$game_id = (int) $_REQUEST['game_id'];
		
		$game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';");
		
		if ($game_r->rowCount() > 0) {
			$db_game = $game_r->fetch();
			
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$game->update_all_option_votes();
			echo "Done!\n";
		}
	}
}
?>