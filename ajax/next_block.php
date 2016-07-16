<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
	if ($game->db_game['game_type'] == "simulation" && $game->db_game['creator_id'] == $thisuser->db_user['user_id'] && $game->db_game['block_timing'] == "user_controlled") {
		$log_text = $game->new_block();
		$log_text = $game->apply_user_strategies();
		echo "1";
	}
	else echo "2";
}
?>