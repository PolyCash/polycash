<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$relevant_images = $app->run_query("SELECT * FROM images WHERE image_identifier IS NULL;")->fetchAll();
	echo "Setting image identifiers for ".count($relevant_images)." images.<br/>\n";
	
	foreach ($relevant_images as $db_image) {
		$image_fname = AppSettings::srcPath().$app->image_url($db_image);
		$fh = fopen($image_fname, 'r');
		$raw_image = fread($fh, filesize($image_fname));
		fclose($fh);
		
		$image_identifier = $app->image_identifier($raw_image);
		
		$image_info = getimagesize($image_fname);
		
		$app->run_query("UPDATE images SET image_identifier=:image_identifier, width=:width, height=:height WHERE image_id=:image_id;", [
			'image_identifier' => $image_identifier,
			'width' => $image_info[0],
			'height' => $image_info[1],
			'image_id' => $db_image['image_id']
		]);
	}
	echo "Done.\n";
}
else echo "Please supply the right key.\n";
?>