<?php
include("../includes/connect.php");

$game_id = intval($_REQUEST['game_id']);
$game = new Game($app, $game_id);

echo "Initial coins in ".$game->db_game['name'].": ".$game->db_game['initial_coins']/pow(10,8).", inflation is ".(100*$game->db_game['exponential_inflation_rate'])."% ";
echo "(".(100*$game->db_game['exponential_inflation_rate']*(1-$game->db_game['exponential_inflation_minershare']))."% to stakeholders)<br/>\n";

for ($round_id=1; $round_id<100; $round_id++) {
	echo "Round #".$round_id." rewards are ".$app->format_bignum($app->pos_reward_in_round($game->db_game, $round_id)/pow(10,8))." coins to stakeholders, ".$app->format_bignum($game->db_game['round_length']*$app->pow_reward_in_round($game->db_game, $round_id)/pow(10,8))." coins to miners. Total coins: ".$app->format_bignum($app->ideal_coins_in_existence_after_round($game->db_game, $round_id)/pow(10,8))."<br/>\n";
}
?>