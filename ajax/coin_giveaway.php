<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM games WHERE game_id='".$thisuser['game_id']."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	$invitation = false;
	$success = try_apply_giveaway($game, $thisuser, $invitation);
	if ($success) echo "1";
	else echo "0";
}
?>