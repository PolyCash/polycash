<?php
ini_set('memory_limit', '4096M');
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/classes/PeerVerifier.php");

$allowed_params = ['mode','game_identifier','blockchain_identifier'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$peer_verifier = new PeerVerifier($app, $_REQUEST['mode'], $_REQUEST['game_identifier'], $_REQUEST['blockchain_identifier']);
	echo json_encode($peer_verifier->renderOutput(), JSON_PRETTY_PRINT);
}
else echo "Please supply the right key string\n";
?>
