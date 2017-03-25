<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");

$fname = "gamedef_base.txt";
$fh = fopen($fname, 'r');
$gamedef_txt = fread($fh, filesize($fname));

$game_def = json_decode($gamedef_txt);

$mock_chain_starting_block = $game_def->game_starting_block;
$mock_chain_last_block = 1173368;
$mock_chain_events_until_block = 1173378;

$defined_rounds = ceil(($mock_chain_events_until_block - $mock_chain_starting_block)/$game_def->round_length);

$events_per_round = array(0=>array('Bitcoin','Litecoin'), 1=>array('Bitcoin','Ethereum'));

$events = array();

for ($round = 1; $round<=$defined_rounds; $round++) {
	for ($e=0; $e<count($events_per_round); $e++) {
		$event = array(
			"event_starting_block" => $mock_chain_starting_block+$round*$game_def->round_length,
			"event_final_block" => $mock_chain_starting_block+($round+1)*$game_def->round_length-1,
			"event_payout_block" => $mock_chain_starting_block+($round+1)*$game_def->round_length-1,
			"event_name" => $events_per_round[$e][0]." vs ".$events_per_round[$e][1]." Round #".$round,
			"option_name" => "outcome",
			"option_name_plural" => "outcomes",
			"outcome_index" => null,
			"possible_outcomes" => array(
				0=>array("title" => $events_per_round[$e][0]),
				1=>array("title" => $events_per_round[$e][1])
			)
		);
		array_push($events, $event);
	}
}
$game_def->events = $events;
echo json_encode($game_def, JSON_PRETTY_PRINT);
?>