<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id', 'event_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['event_id'])) {
		$db_event = $app->fetch_event_by_id((int)$_REQUEST['event_id']);
		
		if ($db_event) {
			$db_game = $app->fetch_game_by_id($db_event['game_id']);
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$event = new Event($game, false, $db_event['event_id']);
			
			$last_block_id = $blockchain->last_block_id();
			
			$ref_time = microtime(true);
			$repeat_count = 50;
			for ($i=0; $i<$repeat_count; $i++) {
				$event->update_option_votes($last_block_id, false);
			}
			$benchmark_time = microtime(true)-$ref_time;
			
			echo "Completed in ".$app->format_bignum($benchmark_time)."\n";
		}
	}
	else if (!empty($_REQUEST['game_id'])) {
		$game_id = (int) $_REQUEST['game_id'];
		$db_game = $app->fetch_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$game->update_all_option_votes();
			echo "Done!\n";
		}
	}
	else echo "Please supply a game ID or an event ID.\n";
}
else echo "You need admin privileges to run this script.\n";
?>