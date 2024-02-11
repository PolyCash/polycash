<?php
require(dirname(__DIR__)."/includes/connect.php");

$allowed_params = ['game_id', 'from_block', 'to_block', 'step'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$from_block = (int) $_REQUEST['from_block'];
	$to_block = (int) $_REQUEST['to_block'];

	$db_game = $app->fetch_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);

	$coins_per_vote = $app->coins_per_vote($game->db_game);

	for ($block=$from_block; $block<=$to_block; $block+=$_REQUEST['step']) {
		$coins_in_existence = $game->coins_in_existence($block, false);
		$events = $game->events_pending_payout_in_block($block);
		$event_bets_total = 0;
		foreach ($events as $event) {
			$event_bets = ($event->db_event['sum_score']*$coins_per_vote) + $event->db_event['destroy_score'];
			$event_bets_total += $event_bets;
		}
		$events = $game->events_by_block($block, []);
		foreach ($events as $event) {
			$event_bets = ($event->db_event['sum_score']*$coins_per_vote) + $event->db_event['destroy_score'];
			$event_bets_total += $event_bets;
		}
		echo "Block #".$block.", events ".$events[0]->db_event['event_index'].":".$events[count($events)-1]->db_event['event_index'].", ".$app->format_bignum($coins_in_existence/pow(10,$game->db_game['decimal_places']))." + ".$app->format_bignum($event_bets_total/pow(10,$game->db_game['decimal_places']))." = ".$app->format_bignum(($coins_in_existence+$event_bets_total)/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." at block #".$block."<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>