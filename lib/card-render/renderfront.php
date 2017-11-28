<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
$res = $_REQUEST['res'];
if ($res != "low") $res = "high";

$card_id = (int) $_REQUEST['card_id'];

if ($card_id > 0) {
	$q = "SELECT * FROM cards c JOIN card_designs d ON c.design_id=d.design_id WHERE c.card_id='".$card_id."';";
	$r = $app->run_query($q);
	$card = $r->fetch();
	
	$q = "SELECT * FROM currencies WHERE currency_id='".$card['currency_id']."';";
	$r = $app->run_query($q);
	$currency = $r->fetch();
	
	if ($res == "low") {
		header("Content-type: image/jpeg");
		$im = imagecreatefrompng(dirname(dirname(dirname(__FILE__)))."/images/card_images/production/".$card['currency_id']."/".$card['fv_currency_id']."/front_".$card['amount'].".jpg") or die("failed");
	}
	else {
		header("Content-type: image/png");
		$im = imagecreatefrompng(dirname(dirname(dirname(__FILE__)))."/images/card_images/production/".$card['currency_id']."/".$card['fv_currency_id']."/front_".$card['amount'].".png") or die("failed");
	}
	
	$text_url = "http://".$_SERVER['SERVER_NAME']."/lib/card-render/rendertext.php?string=".$card['issuer_card_id'];
	if (!empty($card['text_color'])) $text_url .= "&color=".$card['text_color'];
	
	$png = file_get_contents($text_url);
	$text_fname = dirname(dirname(dirname(__FILE__)))."/downloads/images/".$card['issuer_card_id']."_text.png";
	$fh = fopen($text_fname, 'w');
	fwrite($fh, $png);
	fclose($fh);

	$text_im = imagecreatefrompng($text_fname);
	
	imagecopyresized($im, $text_im, 720, 1612, 0, 0, 260, 97, 260, 97);
	
	if ($_REQUEST['orient'] == "fat") {
		$rotated = imagerotate($im, 270, 0);
		if ($res == "low") imagejpeg($rotated);
		else imagepng($rotated);
		imagedestroy($rotated);
	}
	else {
		if ($res == "low") imagejpeg($im);
		else imagepng($im);
	}
	imagedestroy($im);
}
?>