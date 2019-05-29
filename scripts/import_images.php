<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if ($app->running_as_admin()) {
	$import_dirname = realpath(dirname(dirname(__FILE__)))."/images/imports/";
	
	if (is_dir($import_dirname)) {
		if ($import_dh = opendir($import_dirname)) {
			$general_entity_type = $app->check_set_entity_type("general entity");
			
			while (($import_image_name = readdir($import_dh)) !== false) {
				if (!in_array($import_image_name, [".", ".."])) {
					$fname_parts = explode(".", $import_image_name);
					$extension = $fname_parts[count($fname_parts)-1];
					$entity_name = substr($import_image_name, 0, strlen($import_image_name)-strlen($extension)-1);
					echo $import_image_name." ".$entity_name."<br/>\n";
					
					$image_fullname = $import_dirname.$import_image_name;
					
					if ($image_fh = fopen($image_fullname, 'r')) {
						$raw_image = fread($image_fh, filesize($image_fullname));
						fclose($image_fh);
						
						$entity = $app->check_set_entity($general_entity_type['entity_type_id'], $entity_name);
						$access_key = $app->random_string(20);
						
						$error_message = "";
						$db_image = $app->add_image($raw_image, $extension, $access_key, $error_message);
						
						if (!empty($error_message)) {
							echo $error_message;
							$app->flush_buffers();
						}
						
						if ($db_image) {
							$app->run_query("UPDATE entities SET default_image_id=".$db_image['image_id']." WHERE entity_id=".$entity['entity_id'].";");
							$app->run_query("UPDATE options SET image_id=".$db_image['image_id']." WHERE entity_id=".$entity['entity_id'].";");
						}
					}
					else echo "Failed to open ".$image_fullname."\n";
				}
			}
			echo "Done!\n";
		}
		else echo "Failed to open ".$import_dirname."\n";
	}
	else echo $import_dirname." is not a directory.\n";
}
else echo "Please run this script as an admin.\n";

?>