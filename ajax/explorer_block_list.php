<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$game_id = (int) $_REQUEST['game_id'];
$game = new Game($app, $game_id);

$from_block_id = (int) $_REQUEST['from_block'];
$blocks_per_section = (int) $_REQUEST['blocks_per_section'];
$to_block_id = $from_block_id+$blocks_per_section-1;

echo $game->explorer_block_list($from_block_id, $to_block_id);
?>