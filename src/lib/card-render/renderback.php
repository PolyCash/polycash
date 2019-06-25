<?php
$host_not_required = true;
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/lib/phpqrcode/qrlib.php");

if (empty(AppSettings::getParam('cron_key_string')) || $_REQUEST['key'] == AppSettings::getParam('cron_key_string')) {
	$card_id = (int) $_REQUEST['card_id'];

	if ($card_id > 0) {
		$card = $app->run_query("SELECT * FROM cards WHERE card_id=:card_id;", ['card_id'=>$card_id])->fetch();
		$currency = $app->fetch_currency_by_id($card['currency_id']);
		$fv_currency = $app->fetch_currency_by_id($card['fv_currency_id']);
		
		$base_img_fname = "";
		if ($card['design_id'] > 0) {
			$card_design = $app->run_query("SELECT * FROM card_designs WHERE design_id=:design_id;", ['design_id'=>$card['design_id']])->fetch();
			$base_img_fname = AppSettings::srcPath()."/images/card_images/designed_blank.png";
		}
		else {
			$card_design = FALSE;
			$base_img_fname = "back_".$card['amount'].".png";
		}
		
		$qr_url = "http://".$_SERVER['SERVER_NAME']."/render_qr_code.php?data=".urlencode(AppSettings::getParam('base_url')."/redeem/".$card['peer_id']."/".$card['peer_card_id']);
		
		if (empty($card['secret'])) die("Failed to render: the card secret is not present.");
		else {
			header("Content-type: image/png");
			$im = imagecreatefrompng($base_img_fname) or die("failed");
			$im_qr = imagecreatefrompng($qr_url) or die("failed to load: ".$qr_url);
			$black = imagecolorallocate($im, 0, 0, 0);
			
			$ss = $card['secret'];
			$secret_formatted = substr($ss, 0, 4)." - ".substr($ss, 4, 4)." - ".substr($ss, 8, 4)." - ".substr($ss, 12, 4);
			
			if ($card_design) {
				if ($card_design['image_id'] > 0) {
					$design_image = $app->fetch_image_by_id($card_design['image_id']);
					
					$d_img_fname = AppSettings::srcPath()."/images/custom/".$design_image['image_id'].".".$design_image['extension'];
					$d_img = imagecreatefrompng($d_img_fname) or die("Failed to open the watermark image");
					
					imagecopyresized($im, $d_img, $design_image['px_from_left'], $design_image['px_from_top'], 0, 0, $design_image['width'], $design_image['height'], $design_image['width'], $design_image['height']) or die("Failed to resize the bg image");
				}
				
				$fee_amt = $card['amount']*(100-$card_design['purity'])/100;
				if ($fee_amt != 0) {
					$fee_amt_formatted = $fee_amt;
					if ($fee_amt_formatted >= 1000) $fee_amt_formatted = number_format($fee_amt_formatted);
					$fee_amt_formatted .= " ".$fv_currency['abbreviation'];
				}
				
				$formatted_amount = $card['amount'];
				if ($formatted_amount > 1000) $formatted_amount = number_format($formatted_amount);
				
				$mainfont = AppSettings::srcPath()."/images/card_images/calibri.ttf";
				imagettftext($im, 35, 0, 1400, 960, $black, $mainfont, "Card ID:  ".$card['peer_card_id']);
				$value_txt = "Value:  ".$formatted_amount." ".$fv_currency['abbreviation'];
				imagettftext($im, 35, 0, 40, 960, $black, $mainfont, $value_txt);
				
				if ($card_design['purity'] != "unspecified") imagettftext($im, 35, 0, 730, 960, $black, $mainfont, "Fees: ".number_format(100-$card['purity'], 2)."%");
				
				if ($card_design['display_title'] != "") {
					imagettftext($im, 52, 0, 788, 168, $black, $mainfont, $card_design['display_name']);
					imagettftext($im, 32, 0, 789, 239, $black, $mainfont, $card_design['display_title']);
				}
				else {
					$intro_text = "This is a promise to pay from:";
					
					imagettftext($im, 32, 0, 790, 150, $black, $mainfont, $intro_text);
					imagettftext($im, 52, 0, 788, 230, $black, $mainfont, $card_design['display_name']);
				}
				
				if ($card_design['display_pnum'] != "") {
					imagettftext($im, 27, 0, 790, 297, $black, $mainfont, "Phone:   ".$card_design['display_pnum']);
				}
				else {
					imagettftext($im, 27, 0, 790, 297, $black, $mainfont, "For any questions or comments, please contact us by email.");
				}
				
				imagettftext($im, 27, 0, 790, 349, $black, $mainfont, "Email:     ".$card_design['display_email']);
				
				$cname_disp = $fv_currency['name']."s";
				if (strlen($cname_disp) > 7) $cname_disp = $fv_currency['abbreviation'];
				
				$redeemable_sentence = "When active, this card is redeemable for ".$formatted_amount." ".$cname_disp.".";
				
				imagettftext($im, 27, 0, 790, 450, $black, $mainfont, $redeemable_sentence);
				$nextline = "This is card #".$card['peer_card_id'].", minted ".date("M d, Y", $card['mint_time']);
				
				if ($fee_amt > 0) $nextline .= " with ".$fee_amt_formatted." in fees";
				$nextline .= ".";
				
				imagettftext($im, 27, 0, 790, 503, $black, $mainfont, $nextline);
				
				imagettftext($im, 27, 0, 315, 605, $black, $mainfont, "Use your phone to scan the QR code above, or visit ".$card_design['redeem_url']);
				imagettftext($im, 27, 0, 462, 659, $black, $mainfont, "Then scratch off the strip below to get your ".$fv_currency['short_name_plural'].".");
				
				imagecopyresized($im, $im_qr, 290, 100, 0, 0, 440, 440, 174, 174);
				
				imagettftext($im, 44, 0, 530, 805, $black, $mainfont, $secret_formatted);
			}
			else {
				$arial_path = AppSettings::srcPath()."/images/card_images/arial.ttf";
				
				imagettftext($im, 32, 0, 882, 169, $black, $arial_path, $card['peer_card_id']);
				imagettftext($im, 32, 0, 1444, 670, $black, $arial_path, $card['peer_card_id']);
				imagettftext($im, 43, 0, 1578, 960, $black, $arial_path, $card['peer_card_id']);
				
				imagecopyresized($im, $im_qr, 655, 180, 0, 0, 440, 440, 132, 132);
				
				imagettftext($im, 44, 0, 530, 805, $black, $arial_path, $secret_formatted);
			}
			
			if ($_REQUEST['orient'] == "fat") {
				imagepng($im);
			}
			else {
				$rotated = imagerotate($im, 90, 0);
				imagepng($rotated);
				imagedestroy($rotated);
			}
			imagedestroy($im);
		}
	}
}
else echo "Error: incorrect key.";
?>