<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($app->running_as_admin()) {
	$previous_rounds = 4;
	
	$q = "SELECT * FROM featured_strategies fs JOIN currency_accounts ca ON fs.reference_account_id=ca.account_id JOIN games g ON ca.game_id=g.game_id;";
	$r = $app->run_query($q);
	
	while ($featured_strategy = $r->fetch()) {
		$blockchain = new Blockchain($app, $featured_strategy['blockchain_id']);
		$game = new Game($blockchain, $featured_strategy['game_id']);
		$game->load_current_events();
		$current_event = $game->current_events[0];
		$event_ref_block = $current_event->db_event['event_starting_block'];
		
		$performances = array();
		
		for ($i=0; $i<$previous_rounds; $i++) {
			$qq = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_starting_block<".$event_ref_block." ORDER BY event_index DESC;";
			$rr = $app->run_query($qq);
			$first_prev_event = $rr->fetch();
			$event_ref_block = $first_prev_event['event_starting_block'];
			
			$qq = "SELECT * FROM events WHERE game_id='".$game->db_game['game_id']."' AND event_starting_block='".$first_prev_event['event_starting_block']."' ORDER BY event_index ASC;";
			$rr = $app->run_query($qq);
			
			while ($db_event = $rr->fetch()) {
				$bal1 = $game->account_balance_at_block($featured_strategy['account_id'], $db_event['event_final_block'], false);
				$bal2 = $game->account_balance_at_block($featured_strategy['account_id'], $db_event['event_final_block'], true);
				$performance = ($bal2/$bal1)-1;
				$performances[$previous_rounds-$i-1] = $performance;
				echo $db_event['event_starting_block']."-".$db_event['event_final_block']."<br/>\n";
			}
		}
		$performance_sum = array_sum($performances);
		$average_performance = $performance_sum/count($performances);
		
		echo $featured_strategy['strategy_name'].": ";
		echo ($average_performance*100)."% average";
		echo "<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>