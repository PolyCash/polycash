<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$script_start_time = microtime(true);

if ($argv) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (!empty($_REQUEST['key']) && $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$btc_currency = $app->get_currency_by_abbreviation('btc');
	$latest_btc_price = $app->latest_currency_price($btc_currency['currency_id']);
	
	if (!isset($GLOBALS['currency_price_refresh_seconds'])) die('Error: please add something like $GLOBALS[\'currency_price_refresh_seconds\'] = 60; to your config file.');
	
	if ($latest_btc_price['time_added'] < time()-$GLOBALS['currency_price_refresh_seconds']) {
		$app->update_all_currency_prices();
	}

	echo "Done fetching currency prices at ".round(microtime(true)-$script_start_time, 2)." seconds.<br/>\n";
}
else echo "Error: incorrect key supplied in cron/fetch_currency_prices.php\n";
?>
