<?php
$host_not_required = true;
include('includes/connect.php');
include('includes/get_session.php');
include('lib/phpqrcode/qrlib.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if (!empty($_REQUEST['data']) && $_REQUEST['data'] == strip_tags($_REQUEST['data'])) {
	$render_data = urldecode($_REQUEST['data']);

	QRcode::png($render_data, false, 'Q', 6, 0);
}
?>