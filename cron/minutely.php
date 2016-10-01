<?php
$script_start_time = microtime(true);
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	echo $app->start_regular_background_processes($_REQUEST['key']);
	
	if (empty($argv)) {
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round(60-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		
		echo '<script type="text/javascript">setTimeout("window.location=window.location;", '.(1000*$sec_until_refresh).');</script>'."\n";
		echo "Script completed in ".round($runtime_sec, 2)." seconds.<br/>\n";
		echo "Waiting $sec_until_refresh seconds to refresh...<br/>\n";
	}
}
else echo "Syntax is: minutely.php?key=<CRON_KEY_STRING>\n";
?>
