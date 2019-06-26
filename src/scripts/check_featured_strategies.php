<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

if ($app->running_as_admin()) {
	$previous_rounds = 4;
	
	$featured_strategies = $app->run_query("SELECT * FROM featured_strategies fs JOIN currency_accounts ca ON fs.reference_account_id=ca.account_id JOIN games g ON ca.game_id=g.game_id;");
	
	while ($featured_strategy = $featured_strategies->fetch()) {
		$blockchain = new Blockchain($app, $featured_strategy['blockchain_id']);
		$game = new Game($blockchain, $featured_strategy['game_id']);
		$game->load_current_events();
		$current_event = $game->current_events[0];
		$event_ref_block = $current_event->db_event['event_starting_block'];
		
		$performances = array();
		
		for ($i=0; $i<$previous_rounds; $i++) {
			$first_prev_event = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block<:ref_block ORDER BY event_index DESC;", [
				'game_id' => $game->db_game['game_id'],
				'ref_block' => $event_ref_block
			])->fetch();
			$event_ref_block = $first_prev_event['event_starting_block'];
			
			$ref_events = $app->run_query("SELECT * FROM events WHERE game_id=:game_id AND event_starting_block=:ref_block ORDER BY event_index ASC;", [
				'game_id' => $game->db_game['game_id'],
				'ref_block' => $first_prev_event['event_starting_block']
			]);
			
			while ($db_event = $ref_events->fetch()) {
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