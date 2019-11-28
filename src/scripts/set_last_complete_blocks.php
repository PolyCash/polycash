<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$db_blockchains = $app->run_query("SELECT * FROM blockchains;");
	
	while ($db_blockchain = $db_blockchains->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$last_complete_block = $blockchain->last_complete_block_id();
		$blockchain->set_last_complete_block($last_complete_block);
		echo $blockchain->db_blockchain['blockchain_name'].": ".$last_complete_block."<br/>\n";
	}
}
else echo "Please run this script as admin.\n";
