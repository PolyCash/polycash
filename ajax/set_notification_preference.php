<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$preference = $_REQUEST['preference'];
	if ($preference != "email") $preference = "none";
	
	$email = $_REQUEST['email'];
	
	$q = "UPDATE users SET notification_preference='".$preference."', notification_email='".mysql_real_escape_string(strip_tags($email))."' WHERE user_id='".$thisuser['user_id']."';";
	$r = run_query($q);
	
	echo "Your notification settings have been saved.";
}
else echo "First, please log in.";
?>