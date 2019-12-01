<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);
$script_start_time = microtime(true);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['mode','game_identifier','blockchain_identifier','peer_id','key','remote_key'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$remote_url_base = "";
	$peer = false;
	if (!empty($_REQUEST['peer_id'])) {
		$peer = $app->fetch_peer_by_id((int)$_REQUEST['peer_id']);
		if (!$peer) die("Invalid peer_id supplied.");
		$remote_url_base = $peer['base_url'];
	}
	else {
		if (!empty($_REQUEST['remote_host'])) $remote_url_base = urldecode($_REQUEST['remote_host']);
		else die("Please supply: peer_id or remote_host");
	}
	
	if (empty($_REQUEST['mode']) || !in_array($_REQUEST['mode'], ['blockchain','game_ios','game_events'])) die("Please set mode to 'blockchain' or 'game_ios'");
	else $mode = $_REQUEST['mode'];
	
	$local_url = AppSettings::getParam('base_url')."/scripts/verify_api.php?mode=".$mode;
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
	
	if (!empty(AppSettings::getParam('operator_key')) && empty($_REQUEST['key'])) die("Please supply the right key string for this host");
	else $local_url .= "&key=".$_REQUEST['key'];
	
	if (empty($_REQUEST['remote_key'])) die("Please supply the right key string for the remote host");
	else $remote_url .= "&key=".$_REQUEST['remote_key'];
	
	echo $local_url."<br/>\n".$remote_url."<br/>\n";
	
	$obj1 = json_decode(file_get_contents($local_url));
	echo ". ";
	$app->flush_buffers();
	
	$obj2 = json_decode(file_get_contents($remote_url));
	echo ". ";
	$app->flush_buffers();
	
	if ($mode == "game_ios") {
		$loop_to = min(count($obj1), count($obj2));
		$any_error = false;
		
		for ($i=0; $i<$loop_to; $i++) {
			if ($i%1000 == 0) {
				echo ". ";
				$app->flush_buffers();
			}
			if ($obj1[$i] != $obj2[$i]) {
				echo "First error on line #$i<br/>\n";
				echo "<pre>".json_encode($obj1[$i])."</pre><pre>".json_encode($obj2[$i])."</pre>\n";
				if ($i > 0) $i=$loop_to;
				$any_error = true;
			}
		}
		
		if (!$any_error) echo "No errors found.\n";
	}
	else if ($mode == "game_events") {
		$loop_to = min(count($obj1), count($obj2));
		$error_count = 0;
		
		for ($i=0; $i<$loop_to; $i++) {
			if ($i%1000 == 0) {
				echo ". ";
				$app->flush_buffers();
			}
			if ($obj1[$i] != $obj2[$i]) {
				echo "Error on line #$i:\n";
				echo json_encode($obj1[$i], JSON_PRETTY_PRINT)."\n";
				echo json_encode($obj2[$i], JSON_PRETTY_PRINT)."\n";
				echo "<a href=\"/explorer/games/".$game_identifier."/events/".$obj1[$i]->event_index."\">".$obj1[$i]->event_name."</a> vs <a href=\"".$remote_url_base."/explorer/games/".$game_identifier."/events/".$obj2[$i]->event_index."\">".$obj2[$i]->event_name."</a><br/>\n";
				$error_count++;
			}
		}
		
		echo "Found $error_count errors.<br/>\n";
	}
	else if ($mode == "blockchain") {
		$loop_to = min(count($obj1), count($obj2));
		$any_error = false;
		
		for ($i=0; $i<$loop_to; $i++) {
			if ($i%1000 == 0) {
				echo ". ";
				$app->flush_buffers();
			}
			if ($obj1[$i] != $obj2[$i]) {
				echo "First error found<br/>\n";
				echo "<pre>".json_encode($obj1[$i])."</pre><pre>".json_encode($obj2[$i])."</pre>\n";
				if ($i > 0) $i = $loop_to;
				$any_error = true;
			}
		}
		
		if (!$any_error) echo "No errors found.\n";
	}
	else echo "You supplied an invalid mode.\n";
	
	echo "Script completed in ".round(microtime(true)-$script_start_time, 4)." seconds.\n";
}
else echo "Please supply the right key string.\n";
?>
