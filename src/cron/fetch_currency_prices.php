<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$fetch_frequency_sec = AppSettings::getParam('currency_price_refresh_seconds');
	if (empty($fetch_frequecy_sec)) $fetch_frequecy_sec = 5*60;
	
	$last_price_refresh_at = $app->get_site_constant("last_price_refresh_at");
	
	if (empty($last_price_refresh_at) || $last_price_refresh_at < time()-$fetch_frequency_sec || !empty($_REQUEST['force'])) {
		$app->update_all_currency_prices();
		
		$app->set_site_constant("last_price_refresh_at", time());
		
		echo "Done fetching currency prices at ".round(microtime(true)-$script_start_time, 2)." seconds.\n";
	}
	else echo "Skipping.. currency prices are updated only every ".$fetch_frequency_sec." seconds.\n";
}
else echo "Error: incorrect key supplied in cron/fetch_currency_prices.php\n";
?>
