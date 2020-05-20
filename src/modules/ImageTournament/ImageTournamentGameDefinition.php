<?php
class ImageTournamentGameDefinition {
	public $app;
	public $game_def;
	public $game_def_base_txt;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->images = [];
		$this->event_index2images = [];
		
		$this->option_group = $app->check_set_option_group("Reddit /r/sexygirls pics", "picture", "pictures");
		
		$this->game_def_base_txt = '{
			"blockchain_identifier": "stakechain",
			"option_group": "Reddit /r/sexygirls pics",
			"protocol_version": 0,
			"url_identifier": "hot-or-not",
			"name": "Hot or Not",
			"module": "ImageTournament",
			"category_id": 1,
			"decimal_places": 4,
			"finite_events": false,
			"save_every_definition": false,
			"event_type_name": "match",
			"event_type_name_plural": "matches",
			"event_rule": "game_definition",
			"event_winning_rule": "max_below_cap",
			"event_entity_type_id": 0,
			"option_group_id": '.$this->option_group['group_id'].',
			"events_per_round": 0,
			"inflation": "exponential",
			"exponential_inflation_rate": 0.005,
			"pos_reward": 0,
			"round_length": 200,
			"maturity": 0,
			"payout_weight": "coin_block",
			"final_round": false,
			"buyin_policy": "unlimited",
			"game_buyin_cap": 0,
			"sellout_policy": "off",
			"sellout_confirmations": 0,
			"coin_name": "fantasycoin",
			"coin_name_plural": "fantasycoins",
			"coin_abbreviation": "FAN",
			"escrow_address": "AKH02ezyXHbLzSHspGHtFwMYaPBgcN0wd8",
			"genesis_tx_hash": "aabbb7b4c8bbddf093fcbe73f5431e25",
			"genesis_amount": 100000000000000,
			"game_starting_block": 1,
			"default_payout_rate": 1,
			"default_vote_effectiveness_function": "linear_decrease",
			"default_effectiveness_param1": 0.5,
			"default_max_voting_fraction": 1.01,
			"default_option_max_width": 200,
			"default_payout_block_delay": 0,
			"default_payout_rule": "binary",
			"view_mode": "simple"
		}';
		$this->load();
	}
	
	public function load() {
		$this->images = [];
		$game_def = json_decode($this->game_def_base_txt);
		
		$members = $this->app->run_query("SELECT * FROM option_group_memberships m JOIN entities e ON m.entity_id=e.entity_id WHERE m.option_group_id=:option_group_id;", [
			'option_group_id' => $this->option_group['group_id']
		]);
		
		$i = 0;
		while ($image = $members->fetch()) {
			array_push($this->images, array('image_index'=>$i, 'initial_event_index'=>floor($i/2), 'image_name'=>$image['entity_name']));
			$i++;
		}
		
		$event_index2images = [];
		for ($i=0; $i<count($this->images); $i++) {
			$eindex = $this->images[$i]['initial_event_index'];
			if (empty($event_index2images[$eindex])) {
				$event_index2images[$eindex] = array($i);
			}
			else {
				array_push($event_index2images[$eindex], $i);
			}
		}
		$this->event_index2images = $event_index2images;
		
		$this->game_def = $game_def;
	}
	
	public function events_starting_between_blocks(&$game, $from_block, $to_block) {
		$rounds_per_tournament = $this->get_rounds_per_tournament();
		$events = [];
		$entity_type = $this->app->check_set_entity_type("images");
		
		$from_round = $game->block_to_round($from_block);
		$to_round = $game->block_to_round($to_block);
		if (!empty($this->game_def->final_round) && $to_round > $this->game_def->final_round) $to_round = $this->game_def->final_round;
		
		for ($round=$from_round; $round<=$to_round; $round++) {
			$meta_round = floor(($round-1)/$rounds_per_tournament);
			$this_round = ($round-1)%$rounds_per_tournament+1;
			$rounds_left = $rounds_per_tournament - ($this_round+1);
			$num_events = $this->num_events_in_round($round, $rounds_per_tournament);
			$prevround_offset = $this->round_to_prevround_offset($round, false);
			$event_index = $prevround_offset;
			
			for ($thisround_event_i=0; $thisround_event_i<$num_events; $thisround_event_i++) {
				$possible_outcomes = [];
				$game = false;
				$event_name = $this->generate_event_labels($possible_outcomes, $round, $this_round, $thisround_event_i, $entity_type['entity_type_id'], $event_index, $game);
				
				$event = array(
					"event_index" => $event_index,
					"next_event_index" => $this->event_index_to_next_event_index($event_index),
					"event_starting_block" => $chain_starting_block+($round-1)*$round_length,
					"event_final_block" => $chain_starting_block+$round*$round_length-1,
					"event_outcome_block" => $chain_starting_block+$round*$round_length-1,
					"event_payout_block" => $chain_starting_block+$round*$round_length-1,
					"option_block_rule" => "",
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
			$image_i = 1;
			$image_indices = $this->event_index2images[$thisround_event_i];
			
			foreach ($image_indices as $image_index) {
				$entity = false;
				$image_label = $this->images[$image_index]['image_name'];
				$entity = $this->app->check_set_entity($entity_type_id, $image_label);
				
				$possible_outcome = array("title" => $image_label." wins");
				if (!empty($entity)) $possible_outcome["entity_id"] = $entity['entity_id'];
				
				array_push($possible_outcomes, $possible_outcome);
				$image_i++;
			}
			
			$event_name .= $this->images[$image_indices[0]]['image_name']." vs. ".$this->images[$image_indices[1]]['image_name'];
		}
		else {
			if (empty($game)) $game_id = "";
			else $game_id = $game->db_game['game_id'];
			
			$entities_by_game = $this->app->fetch_game_defined_options($game_id, $event_index, false, true);
			
			if ($entities_by_game->rowCount() > 0) {
				while ($entity = $entities_by_game->fetch()) {
					$event_name .= $entity['entity_name']." vs. ";
					
					$possible_outcome = array("title" => $entity['entity_name']." wins", "entity_id" => $entity['entity_id']);
					array_push($possible_outcomes, $possible_outcome);
				}
				$event_name = substr($event_name, 0, strlen($event_name)-strlen(" vs. "));
			}
			else {
				$entity1 = $entity = $this->app->check_set_entity($entity_type_id, "Image 1");
				$entity2 = $entity = $this->app->check_set_entity($entity_type_id, "Image 2");
				array_push($possible_outcomes, array("title" => "Image 1 wins", "entity_id" => $entity1['entity_id']));
				array_push($possible_outcomes, array("title" => "Image 2 wins", "entity_id" => $entity2['entity_id']));
				$event_name .= "Image 1 vs Image 2";
			}
		}
		
		return $event_name;
	}
	
	public function set_event_outcome(&$game, $payout_event) {
		$log_text = $payout_event->pay_out_event();
		
		$winning_option = $this->app->run_query("SELECT o.*, en.* FROM events ev JOIN options o ON ev.winning_option_id=o.option_id LEFT JOIN entities en ON o.entity_id=en.entity_id WHERE ev.event_id=:event_id;", [
			'event_id' => $payout_event->db_event['event_id']
		])->fetch();
		
		$gde_option_index = $winning_option['option_index']%2;
		
		$next_event_index = $this->event_index_to_next_event_index($payout_event->db_event['event_index']);
		
		$game->set_game_defined_outcome($payout_event->db_event['event_index'], $gde_option_index);
		
		if ($next_event_index) {	
			if (!empty($winning_option['entity_id'])) {
				$pos_in_next_event = $payout_event->db_event['event_index']%2;
				
				$gdo = $this->app->fetch_game_defined_options($game->db_game['game_id'], $next_event_index, $pos_in_next_event, false);
				
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
		return $log_text;
	}
	
	public function rename_event(&$gde, &$game) {
		$entity_type = $this->app->check_set_entity_type("images");
		$possible_outcomes = [];
		$round = 1+floor(($gde['event_starting_block']-$game->db_game['game_starting_block'])/$this->game_def->round_length);
		$this_round = ($round-1)%$this->get_rounds_per_tournament()+1;
		$event_name = $this->generate_event_labels($possible_outcomes, $round, $this_round, false, $entity_type['entity_type_id'], $gde['event_index'], $game);
		
		return array($possible_outcomes, $event_name);
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
		return log(count($this->images), 2);
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
		return $rounds_per_tournament*($tournament_index+1) - ceil(log(count($this->images) - $event_index_mod, 2)) + 1;
	}
}
?>