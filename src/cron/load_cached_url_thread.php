<?php
$script_target_time = 59;
set_time_limit($script_target_time);
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['print_debug', 'process_index'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_index = $_REQUEST['process_index'];
	
	$max_urls_per_execution = 200;
	
	$unprocessed_urls = $app->run_query("SELECT * FROM cached_urls WHERE time_fetched IS NULL ORDER BY cached_url_id ASC LIMIT ".$max_urls_per_execution." OFFSET ".($process_index*$max_urls_per_execution).";");
	$keeplooping = true;
	
	while ($keeplooping && $cached_url = $unprocessed_urls->fetch()) {
		$app->async_fetch_url($cached_url['url'], true);
		if (microtime(true)-$script_start_time > $script_target_time) $keeplooping = false;
	}
}
?>