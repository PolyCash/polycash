<?php
class ExponentialRewardsGameDefinition {
	public $app;
	
	public function __construct(&$app) {
		$this->app = $app;
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$events = [];
		
		$from_round = $game->round_to_display_round($game->block_to_round($from_block));
		$to_round = $game->round_to_display_round($game->block_to_round($to_block));
		
		for ($round=$from_round; $round<=$to_round; $round++) {
			$starting_block = $game->db_game['game_starting_block']+($round*$game->db_game['round_length']);
			$final_block = $starting_block+(5*$game->db_game['round_length']);
			
			array_push($events, [
				"event_index" => $round,
				"event_starting_block" => $starting_block,
				"event_final_block" => $final_block,
				"event_outcome_block" => $final_block,
				"event_payout_block" => $final_block+1,
				"event_name" => "Staking Event #".$round,
				"option_name" => "pool",
				"option_name_plural" => "pools",
				"payout_rule" => "binary",
				"payout_rate" => 1,
				"outcome_index" => 0,
				"possible_outcomes" => [
					[
						"title" => "Default Staking Event"
					]
				]
			]);
		}
		
		return $events;
	}
}
?>