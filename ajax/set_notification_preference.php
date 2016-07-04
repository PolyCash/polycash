<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

die('This functionality is currently disabled.');
/*
if ($thisuser) {
	$preference = $_REQUEST['preference'];
	if ($preference != "email") $preference = "none";
	
	$email = $_REQUEST['email'];
	
	$q = "UPDATE users SET notification_preference='".$preference."', notification_email=".$app->quote_escape(strip_tags($email))." WHERE user_id='".$thisuser->db_user['user_id']."';";
	$r = $app->run_query($q);
	
	echo "Your notification settings have been saved.";
}
else echo "First, please log in.";*/
?>