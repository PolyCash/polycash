<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser && $game) {
	$invitation = false;
	$success = try_capture_giveaway($game, $thisuser, $invitation);
	if ($success) echo "1";
	else echo "0";
}
?>