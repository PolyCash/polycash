<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $db_game['game_id']);
$db_event = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_index=:event_index;", [
	'game_id' => $game->db_game['game_id'],
	'event_index' => $_REQUEST['event_index'],
])->fetch(PDO::FETCH_ASSOC);
$event = new Event($game, null, $db_event['event_id']);

switch ($action) {
	case 'load_seeds':
		$from_time = strtotime($event->db_event['event_final_time']);
		$to_time = strtotime($event->db_event['event_payout_time']) + $game->module->minutes_per_event_cohort*60;
		$seeds_response_raw = file_get_contents("http://opensourcebets.com/api/seeds/default?from_time=".$from_time."&to_time=".$to_time);
		$seeds_response = json_decode($seeds_response_raw, true);
		
		if (isset($seeds_response['seeds'])) {
			$app->output_message(1, null, ['sec_per_seed' => $seeds_response['sec_per_seed'], 'seeds' => $seeds_response['seeds']]);
		} else {
			$app->output_message(2, "Failed to fetch seeds.", null);
		}
		break;
}
