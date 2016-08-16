<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (!empty($GLOBALS['homepage_fname'])) include("pages/".$GLOBALS['homepage_fname']);
else include("pages/default.php");
?>