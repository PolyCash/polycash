<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	
	$_REQUEST['event_id'] = $cmd_vars['event_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$event_id = intval($_REQUEST['event_id']);
	$event = new Event($app, false, $event_id);

	echo "Initial coins in ".$event->db_event['name'].": ".$event->db_event['initial_coins']/pow(10,8).", inflation is ".(100*$event->db_event['exponential_inflation_rate'])."% ";
	echo "(".(100*$event->db_event['exponential_inflation_rate']*(1-$event->db_event['exponential_inflation_minershare']))."% to stakeholders)<br/>\n";

	for ($round_id=1; $round_id<100; $round_id++) {
		echo "Round #".$round_id." rewards are ".$app->format_bignum($app->pos_reward_in_round($event->db_event, $round_id)/pow(10,8))." coins to stakeholders, ".$app->format_bignum($event->db_event['round_length']*$app->pow_reward_in_round($event->db_event, $round_id)/pow(10,8))." coins to miners. Total coins: ".$app->format_bignum($app->ideal_coins_in_existence_after_round($event->db_event, $round_id)/pow(10,8))."<br/>\n";
	}
}
else echo "Please supply the correct key.\n";
?>