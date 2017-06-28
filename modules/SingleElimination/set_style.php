<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include_once(dirname(__FILE__)."/SingleEliminationGameDefinition.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$txt = "Algeria,47
Argentina,48
Australia,49
Belgium,50
Bosnia and Herzegovina,51
Brazil,4
Cameroon,52
Chile,53
Colombia,67
Costa Rica,68
Croatia,69
Ecuador,54
England,55
France,14
Germany,8
Ghana,56
Greece,57
Honduras,58
Iran,16
Italy,59
Ivory Coast,60
Japan,6
Mexico,9
Netherlands,61
Nigeria,11
Portugal,6
Russia,7
South Korea,62
Spain,63
Switzerland,64
United States,30
Uruguay,65";
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