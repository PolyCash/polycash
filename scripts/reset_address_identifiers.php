<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];

	$reset_addresses = $app->run_query("SELECT * FROM addresses WHERE primary_blockchain_id='".$blockchain_id."';");
	echo "Resetting ".$reset_addresses->rowCount()." addresses.<br/>\n";
	$reset_count = 0;
	
	while ($db_address = $reset_addresses->fetch()) {
		$vote_identifier = $app->addr_text_to_vote_identifier($db_address['address']);
		$option_index = $app->vote_identifier_to_option_index($vote_identifier);
		
		$app->run_query("UPDATE addresses SET option_index='".$option_index."', vote_identifier='".$vote_identifier."' WHERE address_id='".$db_address['address_id']."';");
		
		$reset_count++;
		if ($reset_count%1000 == 0) echo $reset_count." \n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>