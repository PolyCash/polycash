<?php
$script_start_time = microtime(true);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (date("g:ia") == "12:30pm") {
		include(AppSettings::srcPath()."/scripts/send_performance_notifications.php");
	}
	
	$print_debug = @((bool) $_REQUEST['print_debug']);
	
	echo '<pre>';
	echo $app->start_regular_background_processes($print_debug);
	echo '</pre>';
	
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
else echo "Please supply the right key\n";
?>