<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$denomination = $app->run_query("SELECT * FROM card_currency_denominations d JOIN currencies c ON d.currency_id=c.currency_id WHERE d.denomination_id=:denomination_id;", [
		'denomination_id' => $_REQUEST['denomination_id']
	])->fetch();

	if ($denomination) {
		$fv_currency = $app->fetch_currency_by_id($denomination['fv_currency_id']);
		
		$purity = (float) $_REQUEST['purity'];
		if (empty($_REQUEST['name'])) $name = "";
		else $name = strip_tags($_REQUEST['name']);
		if (empty($_REQUEST['title'])) $title = "";
		else $title = strip_tags($_REQUEST['title']);
		if (empty($_REQUEST['email'])) $email = "";
		else $email = strip_tags($_REQUEST['email']);
		if (empty($_REQUEST['pnum'])) $pnum = "";
		else $pnum = strip_tags($_REQUEST['pnum']);
		$secret_formatted = "0000-0000-0000-0000";

		$scale_factor = 5;

		if (!empty($_REQUEST['mode']) && $_REQUEST['mode'] == "image") {
			if ($_REQUEST['side'] == "front") {
				$front_fname = AppSettings::publicPath()."/images/card_images/preview/".$denomination['currency_id']."/".$denomination['fv_currency_id']."/front_".$denomination['denomination'].".png";
				
				if (is_file($front_fname)) {
					header("Content-type: image/jpg");
					$im = imagecreatefrompng($front_fname) or die("failed");
					list($width, $height) = getimagesize($front_fname);
					
					$resized_w = round(100*$scale_factor);
					$resized_h = round(175*$scale_factor);
					
					$im2 = imagecreate($resized_w,$resized_h);
					imagecopyresized($im2, $im, 0, 0, 0, 0, $resized_w, $resized_h, $width, $height);
					
					$im2 = imagerotate($im2, 90, 0);
					imagepng($im2);
					imagedestroy($im2);
					imagedestroy($im);
				}
				else echo "Error: $front_fname is missing.";
			}
			else {
				$back_fname = AppSettings::publicPath()."/images/card_images/designed_blank.png";
				if (is_file($back_fname)) {
					header("Content-type: image/jpg");
					
					// 1. Load the background image
					$im = imagecreatefrompng($back_fname) or die("failed");
					
					$black = imagecolorallocate($im, 0, 0, 0);
					$white = imagecolorallocate($im, 255, 255, 255);
					
					list($width, $height) = getimagesize($back_fname);
					
					// 2. Apply the watermark image
					if (!empty($fv_currency['default_design_image_id'])) {
						$card_image = $app->fetch_image_by_id($fv_currency['default_design_image_id']);
						
						$card_image_fname = AppSettings::publicPath()."/images/custom/".$card_image['image_id'].".".$card_image['extension'];
						$card_image_obj = imagecreatefrompng($card_image_fname) or die("Failed to open the watermark image");
						
						imagecopyresized($im, $card_image_obj, $card_image['px_from_left'], $card_image['px_from_top'], 0, 0, $card_image['width'], $card_image['height'], $card_image['width'], $card_image['height']) or die("Failed to resize the bg image");
					}
					
					// 3. Add in the QR code
					$qr_fname = AppSettings::publicPath()."/images/card_images/0001_qr.png";
					$im_qr = imagecreatefrompng($qr_fname);
					imagecopyresized($im, $im_qr, 290, 100, 0, 0, 440, 440, 132, 132);
					
					// 4. Add in all text
					$mainfont = AppSettings::publicPath()."/images/card_images/calibri.ttf";
					imagettftext($im, 35, 0, 1400, 960, $black, $mainfont, "Card ID:  0001");
					$value_txt = "Value:  ";
					if (empty($fv_currency['blockchain_id'])) $value_txt .= $fv_currency['symbol'];
					$value_txt .= $denomination['denomination'];
					if (!empty($fv_currency['blockchain_id'])) $value_txt .= " ".$fv_currency['abbreviation'];
					
					if ($purity != "unspecified") imagettftext($im, 35, 0, 730, 960, $black, $mainfont, "Fees: ".number_format(100-$purity, 2)."%");
					
					imagettftext($im, 35, 0, 40, 960, $black, $mainfont, $value_txt);
					if ($title != "") {
						imagettftext($im, 52, 0, 788, 168, $black, $mainfont, $name);
						imagettftext($im, 32, 0, 789, 239, $black, $mainfont, $title);
					}
					else {
						$intro_text = "This is a promise to pay from:";
						imagettftext($im, 32, 0, 790, 150, $black, $mainfont, $intro_text);
						imagettftext($im, 52, 0, 788, 230, $black, $mainfont, $name);
					}
					if ($pnum != "") {
						imagettftext($im, 27, 0, 790, 297, $black, $mainfont, "Phone:   ".$pnum);
					}
					else {
						imagettftext($im, 27, 0, 790, 297, $black, $mainfont, "For any questions or comments, please contact us by email.");
					}
					imagettftext($im, 27, 0, 790, 349, $black, $mainfont, "Email:     ".$email);
					
					$redeemable_sentence = "When active, this card is redeemable for ".$denomination['denomination']." ".ucwords($fv_currency['short_name_plural']).". ";
					
					imagettftext($im, 27, 0, 790, 450, $black, $mainfont, $redeemable_sentence);
					$nextline = "This is card #0001, minted ".date("M d, Y", time());
					
					$fee_amt = ((float)str_replace(",", "", $denomination['denomination']))*(100-$purity)/100;
					if ($fee_amt == 0) $nextline .= ".";
					else {
						$fee_amt_formatted = $fee_amt;
						if ($fee_amt_formatted >= 1000) $fee_amt_formatted = number_format($fee_amt_formatted);
						$fee_amt_formatted .= " ".$fv_currency['abbreviation'];
						
						$nextline .= " with ".$fee_amt_formatted." in fees.";
					}
					
					imagettftext($im, 27, 0, 790, 503, $black, $mainfont, $nextline);
					
					imagettftext($im, 27, 0, 315, 605, $black, $mainfont, "Use your phone to scan the QR code above, or visit ".AppSettings::getParam('base_url').".");
					imagettftext($im, 27, 0, 462, 659, $black, $mainfont, "Then scratch off the strip below to get your ".$fv_currency['short_name_plural'].".");
					
					imagettftext($im, 44, 0, 530, 805, $black, $mainfont, $secret_formatted);
					
					// 5. Add in appearance of scratch-off sticker
					$sticker_color = imagecolorallocate($im, 180, 180, 180);
					imagefilledrectangle($im, 220, 720, 1530, 855, $sticker_color);
					
					imagettftext($im, 46, 0, 450, 813, $black, $mainfont, "SCRATCH OFF GENTLY WITH COIN");
					
					// 6. Create the output-image
					$resized_w = round(175*$scale_factor);
					$resized_h = round(100*$scale_factor);
					
					$im2 = imagecreate($resized_w, $resized_h);
					imagecopyresized($im2, $im, 0, 0, 0, 0, $resized_w, $resized_h, $width, $height);
					
					imagepng($im2);
					imagedestroy($im2);
					imagedestroy($im);
				}
				else echo "Failed to load base image.";
			}
		}
		else {
			$img_url_vars = "&denomination_id=".((int)$_REQUEST['denomination_id'])."&purity=".((int)$_REQUEST['purity'])."&name=".urlencode(strip_tags($_REQUEST['name']))."&title=".urlencode(strip_tags($_REQUEST['title']))."&pnum=".urlencode(strip_tags($_REQUEST['pnum']))."&email=".urlencode(strip_tags($_REQUEST['email']));
			
			echo '<div class="row">';
			echo "<div class='col-sm-6'><img class=\"preview_card_image\" style=\"width: 100%;\" src=\"/ajax/card_preview.php?mode=image&side=front".$img_url_vars."\" /></div>\n";
			echo "<div class='col-sm-6'><img class=\"preview_card_image\" style=\"width: 100%;\" src=\"/ajax/card_preview.php?mode=image&side=back".$img_url_vars."\" /></div>\n";
			echo '</div>';
		}
	}
	else echo "Error: invalid denomination ID.";
}
else {
	$app->output_message(1, "Please log in", false);
}
?>