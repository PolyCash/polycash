<?php
include("../includes/connect.php");

$event_id = intval($_REQUEST['event_id']);
$event = new Event($app, false, $event_id);

echo "Initial coins in ".$event->db_event['name'].": ".$event->db_event['initial_coins']/pow(10,8).", inflation is ".(100*$event->db_event['exponential_inflation_rate'])."% ";
echo "(".(100*$event->db_event['exponential_inflation_rate']*(1-$event->db_event['exponential_inflation_minershare']))."% to stakeholders)<br/>\n";

for ($round_id=1; $round_id<100; $round_id++) {
	echo "Round #".$round_id." rewards are ".$app->format_bignum($app->pos_reward_in_round($event->db_event, $round_id)/pow(10,8))." coins to stakeholders, ".$app->format_bignum($event->db_event['round_length']*$app->pow_reward_in_round($event->db_event, $round_id)/pow(10,8))." coins to miners. Total coins: ".$app->format_bignum($app->ideal_coins_in_existence_after_round($event->db_event, $round_id)/pow(10,8))."<br/>\n";
}
?>