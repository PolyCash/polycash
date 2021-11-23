<?php
class BetcoinGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "datachain",
			"option_group": "32 most populous countries",
			"protocol_version": 1.001,
			"name": "Betcoin",
			"url_identifier": "betcoin",
			"module": "Betcoin",
			"category_id": null,
			"decimal_places": 4,
			"finite_events": false,
			"save_every_definition": false,
			"max_simultaneous_options": 100,
			"event_type_name": "game",
			"event_type_name_plural": "games",
			"event_rule": "game_definition",
			"event_winning_rule": "game_definition",
			"inflation": "exponential",
			"exponential_inflation_rate": 0.0001,
			"pow_reward_type": "fixed",
			"pow_fixed_reward": 100,
			"round_length": 20,
			"payout_weight": "coin_round",
			"final_round": null,
			"buyin_policy": "for_sale",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "betcoin",
			"coin_name_plural": "betcoins",
			"coin_abbreviation": "BET",
			"escrow_address": "Xh6iNuX4PvBaEYaY7obuiP3b8F61iRXP23",
			"genesis_tx_hash": "1a41098e90b40e9ee41507ede1a2e2c9f069fb97b41cfcc947fc74897262fdf7",
			"genesis_amount": 50000000,
			"game_starting_block": 2661,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "constant",
			"default_effectiveness_param1": 0,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "default",
			"order_options_by": "option_index",
			"target_option_block_score": 90,
			"escrow_amounts": []
		}';
		
		$this->game_def = json_decode($this->game_def_base_txt);
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$blocks_between_events = 10;
		$event_betting_length = $blocks_between_events*8;
		
		$group = $game->blockchain->app->fetch_group_by_id($game->db_game['option_group_id']);
		list($teams, $formatted_teams) = $game->blockchain->app->group_details_json($group);
		$num_teams = count($teams);
		
		$num_matchups_per_season = (pow($num_teams, 2) - $num_teams)/2;
		$matchups_per_season = [];

		for ($matchup_row=0; $matchup_row < $num_teams-1; $matchup_row++) {
			$thisrow_matchups = $num_teams - $matchup_row - 1;
			for ($matchup_col=0; $matchup_col<$thisrow_matchups; $matchup_col++) {
				array_push($matchups_per_season, [
					'home' => $teams[$matchup_col],
					'away' => $teams[$matchup_col+$matchup_row+1],
				]);
			}
		}
		
		$game_starting_event_offset = ceil($game->db_game['game_starting_block']/$blocks_between_events);
		
		$from_event_offset = ceil($from_block/$blocks_between_events) - $game_starting_event_offset;
		$to_game_round = ceil($to_block/$blocks_between_events) - $game_starting_event_offset;
		
		$from_season = floor($from_event_offset/$num_matchups_per_season);
		$to_season = floor($to_game_round/$num_matchups_per_season);
		
		$initial_event_offset = $from_event_offset%$num_matchups_per_season;
		$event_offset_this_season = $initial_event_offset;
		
		$num_matchups_these_blocks = $to_game_round-$from_event_offset+1;
		$remaining_matchups_these_blocks = $num_matchups_these_blocks;
		
		$events = [];
		
		for ($season=$from_season; $season<=$to_season; $season++) {
			$matchups_this_season = $num_matchups_per_season-$event_offset_this_season;
			if ($matchups_this_season > $remaining_matchups_these_blocks) $matchups_this_season = $remaining_matchups_these_blocks;
			
			for ($matchup_pos=$event_offset_this_season; $matchup_pos<$event_offset_this_season+$matchups_this_season; $matchup_pos++) {
				$event_index = $season*$num_matchups_per_season + $matchup_pos;
				$event_starting_block = $game->db_game['game_starting_block']+($event_index*$blocks_between_events);
				$event_final_block = $event_starting_block+$event_betting_length-1;
				$event_determined_from_block = $event_final_block+1;
				$event_determined_to_block = $event_determined_from_block+19;
				$event_payout_block = $event_determined_to_block+1;
				
				$event = [
					"event_index" => $event_index,
					"event_starting_block" => $event_starting_block,
					"event_final_block" => $event_starting_block+$event_betting_length-1,
					"event_determined_from_block" => $event_determined_from_block,
					"event_determined_to_block" => $event_determined_to_block,
					"event_payout_block" => $event_payout_block,
					"event_name" => $matchups_per_season[$matchup_pos]['home']['entity_name']." vs ".$matchups_per_season[$matchup_pos]['away']['entity_name'],
					"option_name" => "team",
					"option_name_plural" => "teams",
					"payout_rule" => "binary",
					"payout_rate" => $game->db_game['default_payout_rate'],
					"outcome_index" => null,
					"option_block_rule" => "basketball_game",
					"season_index" => $season,
					"possible_outcomes" => [
						[
							"title" => $matchups_per_season[$matchup_pos]['home']['entity_name'],
							"entity_id" => $matchups_per_season[$matchup_pos]['home']['entity_id'],
						],[
							"title" => $matchups_per_season[$matchup_pos]['away']['entity_name'],
							"entity_id" => $matchups_per_season[$matchup_pos]['away']['entity_id']
						]
					]
				];
				
				array_push($events, $event);
			}
			
			$event_offset_this_season = 0;
			$remaining_matchups_these_blocks -= $matchups_this_season;
		}
		
		return $events;
	}
	
	public function set_event_outcome(&$game, &$event) {
		$payout_block = $game->blockchain->fetch_block_by_id($event->db_event['event_payout_block']-1);
		
		list($options_by_score, $options_by_index, $is_tie, $score_disp, $in_progress_summary) = $event->option_block_info();
		
		if ($is_tie) {
			$block_hash_last_chars = substr($payout_block['block_hash'], strlen($payout_block['block_hash'])-8, 8);
			$random_number = hexdec($block_hash_last_chars);
			$outcome_index = $random_number%2;
		}
		else $outcome_index = $options_by_score[0]['event_option_index'];
		
		$game->set_game_defined_outcome($event->db_event['event_index'], $outcome_index);
		$event->set_outcome_index($outcome_index);
		
		return "";
	}
}
?>