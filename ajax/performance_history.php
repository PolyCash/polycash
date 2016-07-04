<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	$from_round_id = intval($_REQUEST['from_round_id']);
	$to_round_id = intval($_REQUEST['to_round_id']);
	
	echo performance_history($thisuser, $game, $from_round_id, $to_round_id);
}
?>