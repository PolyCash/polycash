<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id', 'block_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (empty($_REQUEST['blockchain_id'])) die("Please specify a blockchain_id.\n");
	else $blockchain_id = (int) $_REQUEST['blockchain_id'];
	
	$blockchain = new Blockchain($app, $blockchain_id);
	
	if (!empty($_REQUEST['block_id'])) $from_block_id = (int) $_REQUEST['block_id'];
	else $from_block_id = false;
	
	echo $blockchain->sync_initial($from_block_id);
	echo '<br/><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/blocks/">See Blocks</a>';
}
else {
	echo "You need admin privileges to run this script.\n";
}
?>
