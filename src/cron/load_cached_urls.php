<?php
set_time_limit(0);
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 59;
$script_start_time = microtime(true);

$allowed_params = ['print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "load_cached_urls";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$pipe_config = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		$pipes = [];
		
		$script_path_name = AppSettings::srcPath();
		
		for ($i=0; $i<16; $i++) {
			$cmd = $app->php_binary_location().' "'.$script_path_name.'/cron/load_cached_url_thread.php" key='.AppSettings::getParam('cron_key_string').' process_index='.$i;
			if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
			else $cmd .= " 2>&1 >/dev/null";
			$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
			if (!is_resource($block_loading_process)) $html .= "Failed to start process ".$i."<br/>\n";
			sleep(0.1);
		}
	}
	else echo "Already loading URLs.\n";
}
else echo "Please supply the correct key.\n";
?>