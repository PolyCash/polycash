<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$txt = "Bitcoin,35
Bitcoin Cash,78
Litecoin,73
Ethereum,70
Ethereum Classic,71
Dash,72
Monero,74
NEM,75
NEO,79
Ripple,76";
	$lines = explode("\n", $txt);
	
	$general_entity_type = $app->check_set_entity_type("general entity");
	
	for ($i=0; $i<count($lines); $i++) {
		$vals = explode(",", $lines[$i]);
		$entity_name = trim($vals[0]);
		$image_id = trim($vals[1]);
		
		$entity = $app->check_set_entity($general_entity_type['entity_type_id'], $entity_name);
		$q = "UPDATE entities SET default_image_id='".$image_id."' WHERE entity_id='".$entity['entity_id']."';";
		$r = $app->run_query($q);
		echo "q: $q<br/>\n";
		
		$q = "UPDATE options SET image_id='".$image_id."' WHERE entity_id='".$entity['entity_id']."';";
		$r = $app->run_query($q);
		echo "q: $q<br/><br/>\n";
	}
}
?>