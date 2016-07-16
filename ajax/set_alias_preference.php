<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

die('This functionality is currently disabled.');
/*
if ($thisuser) {
	$preference = $_REQUEST['preference'];
	if ($preference != "public") $preference = "private";
	
	$alias = $_REQUEST['alias'];
	
	$q = "UPDATE users SET alias_preference='".$preference."', alias=".$app->quote_escape(strip_tags($alias))." WHERE user_id='".$thisuser->db_user['user_id']."';";
	$r = $app->run_query($q);
	
	echo "Your notification settings have been saved.";
}
else echo "First, please log in.";*/
?>