<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);

if ($db_game) {
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	$db_event = $game->fetch_event_by_index($_REQUEST['event_index']);
	$event = new Event($game, null, $db_event['event_id']);
	$options = $app->fetch_options_by_event($event->db_event['event_id'])->fetchAll();
	$season_events_by_option = [];
	
	foreach ($options as &$option) {
		$past_events[$option['option_id']] = $game->fetch_events_by_entity_and_season($option['entity_id'], $event->db_event['season_index'], $event->db_event['event_starting_block']);
		$past_score_sum = 0;
		$past_wins = 0;
		foreach ($past_events[$option['option_id']] as $past_event_id => $past_event) {
			if ($past_event['winning_entity_id'] == $option['entity_id']) $past_wins++;
			foreach ($past_event['options'] as $past_option) {
				if ($past_option['entity_id'] == $option['entity_id']) $past_score_sum += $past_option['option_block_score'];
			}
		}
		$option['past_wins'] = $past_wins;
		$option['past_average_score'] = $past_score_sum/count($past_events[$option['option_id']]);
	}
	
	$event_past_avg = round(array_sum(array_column($options, 'past_average_score'))/count($options), 8);
	$event_score_boost = round(($game->db_game['target_option_block_score']-$event_past_avg)/2, 8);
	
	$app->output_message(1, "", [
		'renderedContent' => $app->render_view('event_details', [
			'game' => $game,
			'event' => $event,
			'options' => $options,
			'past_events' => $past_events,
			'event_past_avg' => $event_past_avg,
			'event_score_boost' => $event_score_boost,
		])
	]);
}
