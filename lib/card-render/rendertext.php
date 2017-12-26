<?php
function digit2offset($digit) {
	$offsets = explode("\n", "0,0
1,64
2,119
3,187
4,247
5,308
6,372
7,434
8,496
9,558
10,622");
	$ans = -1;
	for ($i=0; $i<count($offsets); $i++) {
		$offsets[$i] = explode(",", $offsets[$i]);
	}
	for ($i=0; $i<count($offsets); $i++) {
		if ($digit == $offsets[$i][0]) {
			$ans = $offsets[$i][1];
		}
	}
	return $ans;
}

$string = $_REQUEST['string'];

header("Content-type: image/png");
$im = imagecreate(260, 97) or die("failed");

if (!empty($_REQUEST['color']) && $_REQUEST['color'] == "dark") $digits = imagecreatefrompng(dirname(dirname(dirname(__FILE__)))."/images/card_images/digits_dark.png");
else $digits = imagecreatefrompng(dirname(dirname(dirname(__FILE__)))."/images/card_images/digits.png");

$pos_offset = array();
$x = 0;
for ($i=0; $i<strlen($string); $i++) {
	$offset = digit2offset($string[$i]);
	$width = digit2offset($string[$i]+1) - $offset - 1;
	
	array_push($pos_offset, array($x, $offset, $width));
	$x = $x + $width;
}
$moveright = 260 - $x;
for ($i=0; $i<strlen($string); $i++) {
	imagecopyresized($im, $digits, $pos_offset[$i][0] + $moveright, 0, $pos_offset[$i][1], 0, $pos_offset[$i][2], 97, $pos_offset[$i][2], 97);
}

$background = imagecolorallocate($im, 0, 0, 0);
imagecolortransparent($im, $background);
imagealphablending($im, false);
imagesavealpha($im, true);
imagepng($im);
imagedestroy($im);
?>