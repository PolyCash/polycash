<?php
include("../includes/connect.php");

$db_game_id = intval($_REQUEST['game_id']);
$q = "SELECT * FROM games WHERE game_id='".$db_game_id."';";
$r = $app->run_query($q);
$db_game = $r->fetch();

echo "Initial coins in ".$db_game['name'].": ".$db_game['initial_coins']/pow(10,8).", inflation is ".(100*$db_game['exponential_inflation_rate'])."% ";
echo "(".(100*$db_game['exponential_inflation_rate']*(1-$db_game['exponential_inflation_minershare']))."% to stakeholders)<br/>\n";

for ($round_id=1; $round_id<100; $round_id++) {
	echo "Round #".$round_id." rewards are ".$app->format_bignum(pos_reward_in_round($db_game, $round_id)/pow(10,8))." coins to stakeholders, ".$app->format_bignum($db_game['round_length']*pow_reward_in_round($db_game, $round_id)/pow(10,8))." coins to miners. Total coins: ".$app->format_bignum(ideal_coins_in_existence_after_round($db_game, $round_id)/pow(10,8))."<br/>\n";
}
?>