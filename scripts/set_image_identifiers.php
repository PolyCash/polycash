<?php
$host_not_required = TRUE;
include_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$image_r = $app->run_query("SELECT * FROM images WHERE image_identifier IS NULL;");
	echo "Setting image identifiers for ".$image_r->rowCount()." images.<br/>\n";
	
	while ($db_image = $image_r->fetch()) {
		$image_fname = dirname(dirname(__FILE__)).$app->image_url($db_image);
		$fh = fopen($image_fname, 'r');
		$raw_image = fread($fh, filesize($image_fname));
		fclose($fh);
		
		$image_identifier = $app->image_identifier($raw_image);
		
		$image_info = getimagesize($image_fname);
		
		$app->run_query("UPDATE images SET image_identifier=".$app->quote_escape($image_identifier).", width='".$image_info[0]."', height='".$image_info[1]."' WHERE image_id=".$db_image['image_id'].";");
	}
	echo "Done.\n";
}
else echo "Please supply the right key.\n";
?>