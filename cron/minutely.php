<?php
$script_start_time = microtime(true);
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$app->log_message("minutely.php is running");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$last_script_run_time = (int) $app->get_site_constant("last_script_run_time");
	if ($last_script_run_time < time()-(60*5) && $GLOBALS['process_lock_method'] == "db") {
		$app->set_site_constant("loading_blocks", 0);
		$app->set_site_constant("loading_games", 0);
		$app->set_site_constant("main_loop_running", 0);
	}
	
	echo $app->start_regular_background_processes($_REQUEST['key']);
	
	if (empty($argv)) {
		$runtime_sec = microtime(true)-$script_start_time;
		$sec_until_refresh = round(60-$runtime_sec);
		if ($sec_until_refresh < 0) $sec_until_refresh = 0;
		?>
		Script completed in <?php echo round($runtime_sec, 2); ?> seconds.<br/>
		Waiting <?php echo $sec_until_refresh; ?> seconds to refresh...<br/>
		<div id="display_time"></div>
		
		<script type="text/javascript">
		var load_time = new Date().getTime();
		function refresh_display_time() {
			var time_since_load = new Date().getTime()-load_time;
			console.log(time_since_load);
			setTimeout(function() {refresh_display_time();}, 1000);
			document.getElementById("display_time").innerHTML = (time_since_load/1000)+" seconds elapsed";
		}
		setTimeout(function() {window.location=window.location;}, <?php echo 1000*$sec_until_refresh; ?>);
		refresh_display_time();
		</script>
		<?php
	}
}
else echo "Syntax is: minutely.php?key=<CRON_KEY_STRING>\n";
?>
