<?php
$host_not_required = true;
include(AppSettings::srcPath().'/includes/connect.php');
include(AppSettings::srcPath().'/includes/get_session.php');
include('lib/phpqrcode/qrlib.php');

if (!empty($_REQUEST['data']) && $_REQUEST['data'] == strip_tags($_REQUEST['data'])) {
	$render_data = urldecode($_REQUEST['data']);

	QRcode::png($render_data, false, 'Q', 6, 0);
}
?>