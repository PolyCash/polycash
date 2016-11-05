<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['blockchain_id'] = $cmd_vars['blockchain_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];

	$q = "SELECT * FROM addresses WHERE primary_blockchain_id='".$blockchain_id."';";
	$r = $app->run_query($q);
	echo "Resetting ".$r->rowCount()." addresses.<br/>\n";
	$reset_count = 0;
	while ($db_address = $r->fetch()) {
		$vote_identifier = $app->addr_text_to_vote_identifier($db_address['address']);
		$option_index = $app->vote_identifier_to_option_index($vote_identifier);
		$qq = "UPDATE addresses SET option_index='".$option_index."', vote_identifier='".$vote_identifier."' WHERE address_id='".$db_address['address_id']."';";
		$rr = $app->run_query($qq);
		$reset_count++;
		if ($reset_count%1000 == 0) echo $reset_count." \n";
	}
}
else echo "Incorrect key.\n";
?>