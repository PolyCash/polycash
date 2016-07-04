<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if (!$game) {
	$game = new Game($GLOBALS['app']->get_site_constant('primary_game_id'));
}

$from_round_id = intval($_REQUEST['from_round_id']);

$last_block_id = $game->last_block_id();
$current_round = $game->block_to_round($last_block_id+1);

if ($from_round_id > 0 && $from_round_id < $current_round) {
	$rounds_complete = $game->rounds_complete_html($from_round_id, 20);
	echo json_encode($rounds_complete);
}
?>