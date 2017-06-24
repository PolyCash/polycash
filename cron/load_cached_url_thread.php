<?php
$script_target_time = 59;
set_time_limit($script_target_time);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['process_index'])) $_REQUEST['process_index'] = $cmd_vars['process_index'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$process_index = $_REQUEST['process_index'];
	
	$app->log_message("running cached url thread $process_index at ".time());
	
	$max_urls_per_execution = 200;
	
	$q = "SELECT * FROM cached_urls WHERE time_fetched IS NULL ORDER BY cached_url_id ASC LIMIT ".$max_urls_per_execution." OFFSET ".($process_index*$max_urls_per_execution).";";
	$r = $app->run_query($q);
	$keeplooping = true;
	
	while ($keeplooping && $cached_url = $r->fetch()) {
		$app->async_fetch_url($cached_url['url'], true);
		if (microtime(true)-$script_start_time > $script_target_time) $keeplooping = false;
	}
}
?>