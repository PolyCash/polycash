<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

$db_game = $app->fetch_game_by_id((int)$_REQUEST['game_id']);
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, (int)$_REQUEST['game_id']);

$from_event_index = (int) $_REQUEST['from_event_index'];
$to_event_index = (int) $_REQUEST['to_event_index'];

if ($from_event_index <= $to_event_index) {
	$event_outcomes_html = $game->event_outcomes_html($from_event_index, $to_event_index, $thisuser);
	echo json_encode($event_outcomes_html);
}
else {
	$output_obj[0] = 0;
	$output_obj[1] = "";
	echo json_encode($output_obj);
}
?>