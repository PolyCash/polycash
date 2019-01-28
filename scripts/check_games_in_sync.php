<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);
$script_start_time = microtime(true);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id','mode','blockchain_identifier'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$mode = $_REQUEST['mode'];
	$url1 = "http://poly.cash/scripts/verify_api.php?game_id=34&mode=".$mode."&blockchain_identifier=".$_REQUEST['blockchain_identifier']."&key=bPdJ4GibZjDajf3q";
	$url2 = "http://45.56.85.67/scripts/verify_api.php?game_id=54&mode=".$mode."&blockchain_identifier=".$_REQUEST['blockchain_identifier']."&key=slingshot88";
	
	echo $url1."<br/>".$url2."<br/><br/>\n";
	
	$obj1 = json_decode(file_get_contents($url1));
	$obj2 = json_decode(file_get_contents($url2));
	
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
