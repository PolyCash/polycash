<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

$expand_collapse = $_REQUEST['expand_collapse'];
if ($expand_collapse == "expand") $left_menu_open = 1;
else $left_menu_open = 0;

if (AppSettings::getParam('pageview_tracking_enabled')) {
	$viewer = $pageview_controller->get_viewer($viewer_id);
	
	$app->run_query("UPDATE viewers SET left_menu_open=:left_menu_open WHERE viewer_id=:viewer_id;", [
		'left_menu_open' => $left_menu_open,
		'viewer_id' => $viewer['viewer_id']
	]);
}
else if ($thisuser) {
	$app->run_query("UPDATE users SET left_menu_open=:left_menu_open WHERE user_id=:user_id;", [
		'left_menu_open' => $left_menu_open,
		'user_id' => $thisuser->db_user['user_id']
	]);
}

echo $left_menu_open;
?>