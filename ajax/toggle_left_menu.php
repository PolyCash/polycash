<?php
include("../includes/connect.php");
include("../includes/get_session.php");

$expand_collapse = $_REQUEST['expand_collapse'];
if ($expand_collapse == "expand") $left_menu_open = 1;
else $left_menu_open = 0;

if ($GLOBALS['pageview_tracking_enabled']) {
	$viewer_id = $pageview_controller->insert_pageview($thisuser);
	$viewer = $pageview_controller->get_viewer($viewer_id);
	
	$q = "UPDATE viewers SET left_menu_open='".$left_menu_open."' WHERE viewer_id='".$viewer['viewer_id']."';";
	$r = $app->run_query($q);
}
else if ($thisuser) {
	$q = "UPDATE users SET left_menu_open='".$left_menu_open."' WHERE user_id='".$thisuser->db_user['user_id']."';";
	$r = $app->run_query($q);
}

echo $left_menu_open;
?>