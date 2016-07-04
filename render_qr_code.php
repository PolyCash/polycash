<?php
include('includes/connect.php');
include('includes/get_session.php');
include('lib/phpqrcode/qrlib.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

QRcode::png($_REQUEST['data'], false, 'Q', 6, 0);
?>