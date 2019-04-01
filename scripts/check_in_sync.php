<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);
$script_start_time = microtime(true);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['mode','game_identifier','blockchain_identifier','issuer_id','key','remote_key'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$remote_url_base = "";
	$issuer = false;
	if (!empty($_REQUEST['issuer_id'])) {
		$issuer_id = (int)$_REQUEST['issuer_id'];
		$issuer_r = $app->run_query("SELECT * FROM card_issuers WHERE issuer_id='".$issuer_id."';");
		if ($issuer_r->rowCount() > 0) {
			$issuer = $issuer_r->fetch();
			$remote_url_base = $issuer['base_url'];
		}
		else die("Invalid issuer_id supplied.");
	}
	else {
		if (!empty($_REQUEST['remote_host'])) $remote_url_base = urldecode($_REQUEST['remote_host']);
		else die("Please supply: issuer_id or remote_host");
	}
	
	if (empty($_REQUEST['mode']) || !in_array($_REQUEST['mode'], ['blockchain','game_ios'])) die("Please set mode to 'blockchain' or 'game_ios'");
	else $mode = $_REQUEST['mode'];
	
	$local_url = $GLOBALS['base_url']."/scripts/verify_api.php?mode=".$mode;
	$remote_url = $remote_url_base."/scripts/verify_api.php?mode=".$mode;
	
	if ($mode == "blockchain") {
		if (empty($_REQUEST['blockchain_identifier'])) die("Please supply a blockchain_identifier");
		else {
			$blockchain_identifier = $_REQUEST['blockchain_identifier'];
			$local_url .= "&blockchain_identifier=".$blockchain_identifier;
			$remote_url .= "&blockchain_identifier=".$blockchain_identifier;
		}
	}
	else {
		if (empty($_REQUEST['game_identifier'])) die("Please supply a valid game identifier");
		else {
			$game_identifier = $_REQUEST['game_identifier'];
			$local_url .= "&game_identifier=".$game_identifier;
			$remote_url .= "&game_identifier=".$game_identifier;
		}
	}
	
	if (!empty($GLOBALS['cron_key_string']) && empty($_REQUEST['key'])) die("Please supply the right key string for this host");
	else $local_url .= "&key=".$_REQUEST['key'];
	
	if (empty($_REQUEST['remote_key'])) die("Please supply the right key string for the remote host");
	else $remote_url .= "&key=".$_REQUEST['remote_key'];
	
	echo $local_url."<br/>".$remote_url."<br/><br/>\n";
	
	$obj1 = json_decode(file_get_contents($local_url));
	$obj2 = json_decode(file_get_contents($remote_url));
	
	if ($mode == "game_ios") {
		$loop_to = min(count($obj1), count($obj2));
		for ($i=0; $i<$loop_to; $i++) {
			if ($obj1[$i] != $obj2[$i]) {
				echo "First error on game IO #".$obj1[$i][0]."<br/>\n";
				echo "<pre>".json_encode($obj1[$i])."</pre><pre>".json_encode($obj2[$i])."</pre>\n";
				$i=$loop_to;
			}
		}
	}
	else if ($mode == "blockchain") {
		$loop_to = min(count($obj1), count($obj2));
		for ($i=0; $i<$loop_to; $i++) {
			if ($obj1[$i] != $obj2[$i]) {
				echo "First error found<br/>\n";
				echo "<pre>".json_encode($obj1[$i])."</pre><pre>".json_encode($obj2[$i])."</pre>\n";
				$i = $loop_to;
			}
		}
	}
	else echo "You supplied an invalid mode.\n";
	
	echo "Script completed in ".round(microtime(true)-$script_start_time, 4)." seconds.\n";
}
else echo "Syntax is: main.php?key=<CRON_KEY_STRING>\n";
?>
