<?php
include("../includes/connect.php");
include("../includes/get_session.php");

$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".(int)$_REQUEST['game_id']."';")->fetch();
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, (int)$_REQUEST['game_id']);

$from_round_id = (int)$_REQUEST['from_round_id'];

$last_block_id = $game->blockchain->last_block_id();
$current_round = $game->block_to_round($last_block_id+1);

if ($from_round_id > 0 && $from_round_id < $current_round) {
	$rounds_complete = $game->rounds_complete_html($from_round_id, 20);
	echo json_encode($rounds_complete);
}
else {
	$output_obj[0] = 0;
	$output_obj[1] = "";
	echo json_encode($output_obj);
}
?>