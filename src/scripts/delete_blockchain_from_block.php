<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_id', 'block_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$block_id = (int) $_REQUEST['block_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	$blockchain->delete_blocks_from_height($block_id);
	
	echo "Done\n";
}
else echo "Please run this script as administrator\n";
