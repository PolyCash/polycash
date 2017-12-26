<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include(dirname(dirname(__FILE__))."/phpqrcode/qrlib.php");

$card_id = (int) $_REQUEST['card_id']);

$q = "SELECT * FROM cards WHERE card_id='".$card_id."';";
$r = $app->run_query($q);

if ($r->rowCount() > 0) {
	$card = $r->fetch();
	QRcode::png("http://".$_SERVER['SERVER_NAME']."/check/".$card['name_id'], dirname(dirname(dirname(__FILE__)))."/downloads/images/".$card['name_id']."_qr.png", 'M', 4, 2);
}
?>