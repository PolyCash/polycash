<?php
include("../includes/connect.php");

$game_id = intval($_REQUEST['game_id']);
$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
$r = run_query($q);
$game = mysql_fetch_array($r);

echo "Initial coins in ".$game['name'].": ".$game['initial_coins']/pow(10,8).", inflation is ".(100*$game['exponential_inflation_rate'])."% ";
echo "(".(100*$game['exponential_inflation_rate']*(1-$game['exponential_inflation_minershare']))."% to stakeholders)<br/>\n";

for ($round_id=1; $round_id<100; $round_id++) {
	echo "Round #".$round_id." rewards are ".format_bignum(pos_reward_in_round($game, $round_id)/pow(10,8))." coins to stakeholders, ".format_bignum($game['round_length']*pow_reward_in_round($game, $round_id)/pow(10,8))." coins to miners. Total coins: ".format_bignum(ideal_coins_in_existence_after_round($game, $round_id)/pow(10,8))."<br/>\n";
}
?>