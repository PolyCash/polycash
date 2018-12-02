<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$script_start_time = microtime(true);

$allowed_params = ['force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$minute = date("i", time());
	$fetch_frequency_minutes = 30;
	
	if ($minute%$fetch_frequency_minutes == 0 || $_REQUEST['force'] == 1) {
		$btc_currency = $app->get_currency_by_abbreviation('btc');
		$latest_btc_price = $app->latest_currency_price($btc_currency['currency_id']);
		
		if (!isset($GLOBALS['currency_price_refresh_seconds'])) die('Error: please add something like $GLOBALS[\'currency_price_refresh_seconds\'] = 60; to your config file.');
		
		if ($latest_btc_price['time_added'] < time()-$GLOBALS['currency_price_refresh_seconds']) {
			$app->update_all_currency_prices();
		}

		echo "Done fetching currency prices at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
	}
	else echo "Skipping.. currency prices are updated only every ".$fetch_frequency_minutes." minutes.";
}
else echo "Error: incorrect key supplied in cron/fetch_currency_prices.php\n";
?>
