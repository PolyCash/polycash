<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
$start_time = microtime(true);

$allowed_params = ['walletnotify', 'blockchain_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];

	$blockchain = new Blockchain($app, $blockchain_id);
	$blockchain->walletnotify($_REQUEST['walletnotify'], FALSE);

	echo "walletnotify completed: ".(microtime(true)-$start_time)." sec\n";
}
else echo "You need admin privileges to run this script.\n";
?>
