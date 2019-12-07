<?php
ini_set('memory_limit', '4096M');
set_time_limit(0);
$script_start_time = microtime(true);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");
require_once(dirname(dirname(__FILE__))."/classes/PeerVerifier.php");

$allowed_params = ['mode','game_identifier','blockchain_identifier','peer_id','remote_key'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$mode = "";
	$game_identifier = "";
	$blockchain_identifier = "";
	$remote_host_url = "";
	$remote_key = "";
	$peer = false;
	$acceptable_modes = ['blockchain','game_ios','game_events'];
	
	if (empty($_REQUEST['mode']) || !in_array($_REQUEST['mode'], $acceptable_modes)) die("Please set mode to one of these: ".json_encode($acceptable_modes)."\n");
	else $mode = $_REQUEST['mode'];
	
	if ($mode == "blockchain") {
		if (empty($_REQUEST['blockchain_identifier'])) die("Please supply a blockchain_identifier\n");
		else $blockchain_identifier = $_REQUEST['blockchain_identifier'];
	}
	else {
		if (empty($_REQUEST['game_identifier'])) die("Please supply a valid game identifier\n");
		else $game_identifier = $_REQUEST['game_identifier'];
	}
	
	if (!empty($_REQUEST['peer_id'])) {
		$peer = $app->fetch_peer_by_id((int)$_REQUEST['peer_id']);
		if (!$peer) die("Invalid peer_id supplied.\n");
	}
	
	if (!$peer) {
		if (!empty($_REQUEST['remote_host'])) $remote_host_url = urldecode($_REQUEST['remote_host']);
		else die("Please supply: peer_id or remote_host");
	}
	
	if (empty($_REQUEST['remote_key'])) die("Please supply the right key string for the remote host");
	else $remote_key = $_REQUEST['remote_key'];
	
	$peer_verifier = new PeerVerifier($app, $_REQUEST['mode'], $game_identifier, $blockchain_identifier);
	$remote_url = $peer_verifier->remoteUrl($peer, $remote_host_url, $remote_key);
	
	$obj1 = json_decode(json_encode($peer_verifier->renderOutput()));
	echo ". ";
	$app->flush_buffers();
	
	$obj2 = json_decode(file_get_contents($remote_url));
	echo ". ";
	$app->flush_buffers();
	
	$peer_verifier->checkDisplayDifferences($obj1, $obj2);
	
	echo "Script completed in ".round(microtime(true)-$script_start_time, 4)." seconds.\n";
}
else echo "Please supply the right key string.\n";
?>
