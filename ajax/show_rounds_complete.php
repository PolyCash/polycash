<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if (!$game) {
	$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
}

$from_round_id = intval($_REQUEST['from_round_id']);

$last_block_id = last_block_id($game['game_id']);
$current_round = block_to_round($last_block_id+1);

if ($from_round_id > 0 && $from_round_id < $current_round) {
	$rounds_complete = rounds_complete_html($game, $from_round_id, 20);
	echo json_encode($rounds_complete);
}
?>