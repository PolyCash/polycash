<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$from_block_id = (int) $_REQUEST['from_block'];
$blocks_per_section = (int) $_REQUEST['blocks_per_section'];
$to_block_id = $from_block_id+$blocks_per_section-1;

if (empty($_REQUEST['game_id'])) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$blockchain = new Blockchain($app, $blockchain_id);
	$ref_game = false;
	echo $blockchain->explorer_block_list($from_block_id, $to_block_id, $ref_game);
}
else {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);
	
	echo $game->explorer_block_list($from_block_id, $to_block_id);
}
?>