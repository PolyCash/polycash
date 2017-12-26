<?php
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_target_time = 59;
$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['print_debug'])) $_REQUEST['print_debug'] = $cmd_vars['print_debug'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "loading_urls";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$pipe_config = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);
		$pipes = array();
		
		if (PHP_OS == "WINNT") $script_path_name = dirname(dirname(__FILE__));
		else $script_path_name = realpath(dirname(dirname(__FILE__)));
		
		for ($i=0; $i<16; $i++) {
			$cmd = $app->php_binary_location().' "'.$script_path_name.'/cron/load_cached_url_thread.php" key='.$GLOBALS['cron_key_string'].' process_index='.$i;
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