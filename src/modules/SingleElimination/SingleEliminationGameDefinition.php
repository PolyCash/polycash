<?php
class SingleEliminationGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->teams = [];
		$this->event_index2teams = [];
		$this->teams_txt = "0,Algeria
0,Argentina
1,Australia
1,Belgium
2,Bosnia and Herzegovina
2,Brazil
3,Cameroon
3,Chile
4,Colombia
4,Costa Rica
5,Croatia
5,Ecuador
6,England
6,France
7,Germany
7,Ghana
8,Greece
8,Honduras
9,Iran
9,Italy
10,Ivory Coast
10,Japan
11,Mexico
11,Netherlands
12,Nigeria
12,Portugal
13,Russia
13,South Korea
14,Spain
14,Switzerland
15,United States
15,Uruguay";

		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": null,
			"protocol_version": 0,
			"url_identifier": "fantasy-cup",
			"name": "Fantasy Soccer World Cup",
			"module": "SingleElimination",
			"category_id": 1,
			"decimal_places": 4,
			"finite_events": false,
			"save_every_definition": false,
			"event_type_name": "match",
			"event_type_name_plural": "matches",
			"event_rule": "game_definition",
			"event_entity_type_id": 0,
			"option_group_id": 0,
			"events_per_round": 0,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.001,
			"pos_reward": 0,
			"round_length": 20,
			"payout_weight": "coin_block",
			"final_round": false,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "fantasycoin",
			"coin_name_plural": "fantasycoins",
			"coin_abbreviation": "FAN",
			"escrow_address": "LeZ5YYWjg6hxgFzSfZJ26bsLHSexAuiEY5",
			"genesis_tx_hash": "c8bbddf093fcbe73f543aa7b4bbb1e25",
			"genesis_amount": 100000000000000,
			"game_starting_block": 1,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 1,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary"
		}';
		$this->load();
	}
	
	public function load() {
		$this->teams = [];
		$game_def = json_decode($this->game_def_base_txt);
		$teams_txt = explode("\n", $this->teams_txt);
		$i = 0;
		foreach ($teams_txt as $team_line) {
			$vals = explode(",", trim($team_line));
			array_push($this->teams, ['team_index'=>$i, 'initial_event_index'=>$vals[0], 'team_name'=>$vals[1]]);
			$i++;
		}
		
		$event_index2teams = [];
		for ($i=0; $i<count($this->teams); $i++) {
			$eindex = $this->teams[$i]['initial_event_index'];
			if (empty($event_index2teams[$eindex])) {
				$event_index2teams[$eindex] = array($i);
			}
			else {
				array_push($event_index2teams[$eindex], $i);
			}
		}
		$this->event_index2teams = $event_index2teams;
		
		$this->game_def = $game_def;
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$rounds_per_tournament = $this->get_rounds_per_tournament();
		$events = [];
		$general_entity_type = $this->app->check_set_entity_type("general entity");
		
		$from_round = $game->round_to_display_round($game->block_to_round($from_block));
		$to_round = $game->round_to_display_round($game->block_to_round($to_block));
		
		for ($round=$from_round; $round<=$to_round; $round++) {
			$meta_round = floor(($round-1)/$rounds_per_tournament);
			$this_round = ($round-1)%$rounds_per_tournament+1;
			$rounds_left = $rounds_per_tournament - ($this_round+1);
			$num_events = $this->num_events_in_round($round, $rounds_per_tournament);
			$prevround_offset = $this->round_to_prevround_offset($round, false);
			$event_index = $prevround_offset;
			
			for ($thisround_event_i=0; $thisround_event_i<$num_events; $thisround_event_i++) {
				$possible_outcomes = [];
				$event_name = $this->generate_event_labels($possible_outcomes, $round, $this_round, $thisround_event_i, $general_entity_type['entity_type_id'], $event_index, $game);
				
				$payout_block = $game->db_game['game_starting_block']+$round*$game->db_game['round_length']-1;
				
				$event = array(
					"event_index" => $event_index,
					"next_event_index" => $this->event_index_to_next_event_index($event_index),
					"event_starting_block" => $game->db_game['game_starting_block']+($round-1)*$game->db_game['round_length'],
					"event_final_block" => $game->db_game['game_starting_block']+$round*$game->db_game['round_length']-1,
					"event_outcome_block" => $payout_block,
					"event_payout_block" => $payout_block,
					"option_block_rule" => "football_match",
					"event_name" => $event_name,
					"option_name" => "outcome",
					"option_name_plural" => "outcomes",
					"payout_rule" => "binary",
					"payout_rate" => 1,
					"outcome_index" => null,
					"possible_outcomes" => $possible_outcomes
				);
				
				array_push($events, $event);
				$event_index++;
			}
		}
		
		return $events;
	}
	
	public function generate_event_labels(&$possible_outcomes, $round, $this_round, $thisround_event_i, $entity_type_id, $event_index, &$game) {
		$rounds_per_tournament = $this->get_rounds_per_tournament();
		$tournament_index = floor(($round-1)/$rounds_per_tournament);
		$events_this_round = $this->num_events_in_round($round, $rounds_per_tournament);
		$round_of = $events_this_round*2;
		
		$event_name = "";
		if ($round_of == 2) $event_name .= "Finals: ";
		else if ($round_of == 4) $event_name .= "Semifinals: ";
		else if ($round_of == 8) $event_name .= "Quarterfinals: ";
		else $event_name .= "Round of $round_of: ";
		
		if ($this_round == 1) {
			$team_i = 1;
			$team_indices = $this->event_index2teams[$thisround_event_i];
			
			foreach ($team_indices as $team_index) {
				$entity = false;
				$team_label = $this->teams[$team_index]['team_name'];
				$entity = $this->app->check_set_entity($entity_type_id, $team_label);
				
				$possible_outcome = array("title" => $team_label." wins");
				if (!empty($entity)) $possible_outcome["entity_id"] = $entity['entity_id'];
				
				array_push($possible_outcomes, $possible_outcome);
				$team_i++;
			}
			
			$event_name .= $this->teams[$team_indices[0]]['team_name']." vs. ".$this->teams[$team_indices[1]]['team_name'];
		}
		else {
			if (empty($game)) $game_id = "";
			else $game_id = $game->db_game['game_id'];
			
			$entities_by_game = $this->app->fetch_game_defined_options($game_id, $event_index, false, true)->fetchAll();
			
			if (count($entities_by_game) > 0) {
				foreach ($entities_by_game as $entity) {
					$event_name .= $entity['entity_name']." vs. ";
					
					$possible_outcome = array("title" => $entity['entity_name']." wins", "entity_id" => $entity['entity_id']);
					array_push($possible_outcomes, $possible_outcome);
				}
				$event_name = substr($event_name, 0, strlen($event_name)-strlen(" vs. "));
			}
			else {
				$entity1 = $entity = $this->app->check_set_entity($entity_type_id, "Team 1");
				$entity2 = $entity = $this->app->check_set_entity($entity_type_id, "Team 2");
				array_push($possible_outcomes, array("title" => "Team 1 wins", "entity_id" => $entity1['entity_id']));
				array_push($possible_outcomes, array("title" => "Team 2 wins", "entity_id" => $entity2['entity_id']));
				$event_name .= "Team 1 vs Team 2";
			}
		}
		
		return $event_name;
	}
	
	public function rename_event(&$gde, &$game) {
		$general_entity_type = $this->app->check_set_entity_type("general entity");
		$possible_outcomes = [];
		$round = $game->round_to_display_round($game->block_to_round($gde['event_starting_block']));
		$this_round = ($round-1)%$this->get_rounds_per_tournament()+1;
		$event_name = $this->generate_event_labels($possible_outcomes, $round, $this_round, false, $general_entity_type['entity_type_id'], $gde['event_index'], $game);
		
		return array($possible_outcomes, $event_name);
	}
	
	public function break_tie(&$game, &$db_event, &$first_option, &$second_option) {
		$final_block = $game->blockchain->fetch_block_by_id($db_event['event_final_block']);
		
		if ($final_block) {
			$random_data = hash("sha256", $final_block['block_hash']);
			
			$filter_arr = false;
			$events_by_block = $game->events_by_block($final_block['block_id'], $filter_arr);
			$events_in_round = count($events_by_block);
			
			$rand_chars_per_event = 3;
			$event_offset = $db_event['event_index'] - $events_by_block[0]->db_event['event_index'];
			
			$total_rand_chars_needed = $rand_chars_per_event*$events_in_round;
			$last_rand_hash = $random_data;
			
			while (strlen($random_data) < $total_rand_chars_needed) {
				$last_rand_hash = hash("sha256", $last_rand_hash);
				$random_data .= $last_rand_hash;
			}
			
			$rand_offset_start = $rand_chars_per_event*$event_offset;
			$rand_chars = substr($random_data, $rand_offset_start, $rand_chars_per_event);
			
			$pk_shootouts = [];
			$winning_option = false;
			
			do {
				$rand_binary = str_pad(base_convert($rand_chars, 16, 2), $rand_chars_per_event*4, "0", STR_PAD_LEFT);
				
				if (strlen($rand_chars) != $rand_chars_per_event || strlen($rand_binary) < 4*$rand_chars_per_event-1) throw new Exception("randh: $rand_chars, randb: $rand_binary");
				
				$team1_pk_score = 0;
				$team2_pk_score = 0;
				
				for ($i=0; $i<5; $i++) {
					$scored = $rand_binary[$i];
					$team1_pk_score += $scored;
				}
				for ($i=5; $i<10; $i++) {
					$scored = $rand_binary[$i];
					$team2_pk_score += $scored;
				}
				
				array_push($pk_shootouts, array($team1_pk_score, $team2_pk_score));
				
				if ($team1_pk_score == $team2_pk_score) {
					$rand_chars = hash("sha256", $rand_chars);
					$rand_chars = substr($rand_chars, 0, $rand_chars_per_event);
				}
				else {
					if ($team1_pk_score > $team2_pk_score) $winning_option = $first_option;
					else $winning_option = $second_option;
				}
			}
			while ($winning_option === false);
			
			return array($winning_option, $pk_shootouts);
		}
		else return false;
	}
	
	public function set_event_outcome(&$game, &$payout_event) {
		$first_options = $this->app->run_query("SELECT *, SUM(ob.score) AS score FROM option_blocks ob JOIN options o ON ob.option_id=o.option_id LEFT JOIN entities e ON o.entity_id=e.entity_id WHERE o.event_id=:event_id GROUP BY o.option_id ORDER BY o.option_index ASC;", [
			'event_id' => $payout_event->db_event['event_id']
		])->fetchAll();
		
		if (count($first_options) > 0) {
			$first_option = $first_options[0];
			$second_option = $first_options[1];
			$winning_option = false;
			
			if ($first_option['score'] != $second_option['score']) {
				if ($first_option['score'] > $second_option['score']) $winning_option = $first_option;
				else $winning_option = $second_option;
			}
			else {
				list($winning_option, $pk_shootout_data) = $this->break_tie($game, $payout_event->db_event, $first_option, $second_option);
			}
			$gde_option_index = $winning_option['option_index']%2;
			
			$next_event_index = $this->event_index_to_next_event_index($payout_event->db_event['event_index']);
			
			$game->set_game_defined_outcome($payout_event->db_event['event_index'], $gde_option_index);
			
			if ($next_event_index) {	
				if (!empty($winning_option['entity_id'])) {
					$pos_in_next_event = $payout_event->db_event['event_index']%2;
					
					$gdo = $this->app->fetch_game_defined_options($game->db_game['game_id'], $next_event_index, $pos_in_next_event, false)->fetch();
					
					if ($gdo) {
						$this->app->run_query("UPDATE game_defined_options SET entity_id=:entity_id, name=:name WHERE game_defined_option_id=:game_defined_option_id;", [
							'entity_id' => $winning_option['entity_id'],
							'name' => $winning_option['entity_name']." wins",
							'game_defined_option_id' => $gdo['game_defined_option_id']
						]);
					}
				}
				
				$next_gde = $this->app->fetch_game_defined_event_by_index($game->db_game['game_id'], $next_event_index);
				
				list($possible_outcomes, $event_name) = $this->rename_event($next_gde, $game);
				
				$this->app->run_query("UPDATE game_defined_events SET event_name=:event_name WHERE game_id=:game_id AND event_index=:event_index;", [
					'event_name' => $event_name,
					'game_id' => $game->db_game['game_id'],
					'event_index' => $next_event_index
				]);
			}
		}
		
		return "";
	}
	
	public function event_index_to_next_event_index($event_index) {
		$rounds_per_tournament = $this->get_rounds_per_tournament();
		$round = $this->event_index_to_round($event_index, $rounds_per_tournament);
		$this_round = ($round-1)%$rounds_per_tournament+1;
		$tournament_index = floor(($round-1)/$rounds_per_tournament);
		$events_per_tournament = pow(2, $rounds_per_tournament)-1;
		
		if ($this_round >= $rounds_per_tournament) return false;
		else {
			$thisround_events = $this->num_events_in_round($round, $rounds_per_tournament);
			$thisround_offset = $this->round_to_prevround_offset($round+1, $rounds_per_tournament);
			$thisround_event_i = ($event_index - $tournament_index*$events_per_tournament) % $thisround_events;
			$next_event_i = floor($thisround_event_i/2);
			$next_event_index = $thisround_offset + $next_event_i;
			return $next_event_index;
		}
	}
	
	public function round_to_prevround_offset($round, $rounds_per_tournament) {
		if (empty($rounds_per_tournament)) $rounds_per_tournament = $this->get_rounds_per_tournament();
		$this_round = ($round-1)%$rounds_per_tournament + 1;
		
		$tournament_index = floor(($round-1)/$rounds_per_tournament);
		$events_per_tournament = pow(2, $rounds_per_tournament)-1;
		$prevround_offset = $tournament_index*$events_per_tournament;
		
		if ($this_round == 1) return $prevround_offset;
		else {
			for ($r=1; $r<$this_round; $r++) {
				$add_amount = pow(2, $rounds_per_tournament-$r);
				$prevround_offset += $add_amount;
			}
			return $prevround_offset;
		}
	}
	
	public function get_rounds_per_tournament() {
		return log(count($this->teams), 2);
	}
	
	public function num_events_in_round($round, $rounds_per_tournament) {
		if (empty($rounds_per_tournament)) $rounds_per_tournament = $this->get_rounds_per_tournament();
		$this_round = ($round-1)%$rounds_per_tournament+1;
		$rounds_left = $rounds_per_tournament - $this_round;
		return pow(2, $rounds_left);
	}
	
	public function event_index_to_round($event_index, $rounds_per_tournament) {
		if (empty($rounds_per_tournament)) $rounds_per_tournament = $this->get_rounds_per_tournament();
		$events_per_tournament = pow(2, $rounds_per_tournament)-1;
		$tournament_index = floor($event_index/$events_per_tournament);
		$event_index_mod = $event_index%$events_per_tournament;
		return $rounds_per_tournament*($tournament_index+1) - ceil(log(count($this->teams) - $event_index_mod, 2)) + 1;
	}
}
?>