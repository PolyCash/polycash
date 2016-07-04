<?php
class Match {
	public $db_match;
	
	function __construct($match_id) {
		$q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.match_id='".$match_id."';";
		$r = $GLOBALS['app']->run_query($q);
		$this->db_match = mysql_fetch_array($r);
	}
	
	function add_user_to_match($user_id, $position, $check_existing) {
		$already_exists = false;
		if ($check_existing) {
			$q = "SELECT * FROM match_memberships WHERE match_id='".$this->db_match['match_id']."' AND user_id='".$user_id."';";
			$r = $GLOBALS['app']->run_query($q);
			if (mysql_numrows($r) > 0) $already_exists = true;
		}
		if ($position === false) {
			$q = "SELECT * FROM match_memberships WHERE match_id='".$this->db_match['match_id']."' ORDER BY player_position DESC LIMIT 1;";
			$r = $GLOBALS['app']->run_query($q);
			if (mysql_numrows($r) > 0) {
				$previous_player = mysql_fetch_row($r);
				$position = $previous_player['player_position']+1;
			}
			else $position = 0;
		}
		if (!$already_exists) {
			$q = "INSERT INTO match_memberships SET match_id='".$this->db_match['match_id']."', user_id='".$user_id."', player_position=".$position.", time_joined='".time()."';";
			$r = $GLOBALS['app']->run_query($q);
			$q = "UPDATE matches SET num_joined=num_joined+1 WHERE match_id='".$this->db_match['match_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			
			$q = "SELECT * FROM match_types t JOIN matches m ON t.match_type_id=m.match_type_id WHERE m.match_id='".$this->db_match['match_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			$match = mysql_fetch_array($r);
			if ($match['num_players'] == $match['num_joined']) $match_status = "running";
			else $match_status = "pending";
			set_match_status($match, $match_status);
		}
	}

	function user_match_membership($user_id) {
		$q = "SELECT * FROM match_memberships WHERE user_id='".$user_id."' AND match_id='".$this->db_match['match_id']."';";
		$r = $GLOBALS['app']->run_query($q);
		if (mysql_numrows($r) > 0) {
			return mysql_fetch_array($r);
		}
		else return false;
	}

	function start_match_move($membership_id, $type, $amount) {
		$initial_round_number = $this->db_match['current_round_number'];
		
		$qqq = "INSERT INTO match_moves SET membership_id='".$membership_id."', move_type='".$type."', amount='".$amount."', round_number='".$this->db_match['current_round_number']."', move_number='".($this->db_match['last_move_number']+1)."', time_created='".time()."';";
		$rrr = $GLOBALS['app']->run_query($qqq);
		$move_id = mysql_insert_id();
		
		$qqq = "UPDATE matches SET last_move_number=last_move_number+1 WHERE match_id='".$this->db_match['match_id']."';";
		$rrr = $GLOBALS['app']->run_query($qqq);
		
		if ($match['turn_based'] == 0) {
			$qqq = "UPDATE matches SET current_round_number=FLOOR(last_move_number/".$this->db_match['num_players'].") WHERE match_id='".$this->db_match['match_id']."';";
			$rrr = $GLOBALS['app']->run_query($qqq);
		}
		
		if ($this->db_match['current_round_number'] != $initial_round_number) {
			$qqq = "SELECT * FROM match_moves mv JOIN match_memberships mem ON mv.membership_id=mem.membership_id WHERE mem.match_id='".$this->db_match['match_id']."' AND mv.round_number='".$initial_round_number."';";
			$rrr = $GLOBALS['app']->run_query($qqq);
			while ($match_move = mysql_fetch_array($rrr)) {
				$this->finalize_match_move($match_move['move_id']);
			}
			$this->finish_match_round($initial_round_number);
		}
		
		return $move_id;
	}

	function finalize_match_move($move_id) {
		$q = "SELECT * FROM match_moves WHERE move_id='".$move_id."';";
		$r = $GLOBALS['app']->run_query($q);
		$match_move = mysql_fetch_array($r);
		
		if ($type == "deposit") {
			$qqq = "INSERT INTO match_IOs SET membership_id='".$match_move['membership_id']."', match_id='".$this->db_match['match_id']."', create_move_id='".$move_id."', amount='".$match_move['amount']."';";
			$rrr = $GLOBALS['app']->run_query($qqq);
		}
		else if ($type == "burn") {
			$qqq = "SELECT * FROM match_IOs WHERE membership_id='".$match_move['membership_id']."' AND spend_status='unspent' ORDER BY amount DESC;";
			$rrr = $GLOBALS['app']->run_query($qqq);
			
			$input_sum = 0;
			
			while ($match_io = mysql_fetch_array($rrr)) {
				if ($input_sum < $match_move['amount']) {
					$q = "UPDATE match_IOs SET spend_status='spent', spend_move_id='".$move_id."' WHERE io_id='".$match_io['io_id']."';";
					$r = $GLOBALS['app']->run_query($q);
					$input_sum += $match_io['amount'];
				}
			}
			
			$overshoot_amount = $input_sum - $amount;
			
			if ($overshoot_amount > 0) {
				$q = "INSERT INTO match_IOs SET membership_id='".$match_move['membership_id']."', match_id='".$this->db_match['match_id']."', create_move_id='".$move_id."', amount='".$overshoot_amount."';";
				$r = $GLOBALS['app']->run_query($q);
				$output_id = mysql_insert_id();	
			}
			
			$qqq = "INSERT INTO match_IOs SET spend_status='spent', membership_id='".$match_move['membership_id']."', match_id='".$this->db_match['match_id']."', create_move_id='".$move_id."', amount='".$match_move['amount']."';";
			$rrr = $GLOBALS['app']->run_query($qqq);
		}
		
		return $move_id;
	}

	function get_match_round($round_number) {
		$q = "SELECT * FROM match_rounds WHERE match_id='".$this->db_match['match_id']."' AND round_number='".$round_number."';";
		$r = $GLOBALS['app']->run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$match_round = mysql_fetch_array($r);
		}
		else {
			$q = "INSERT INTO match_rounds SET match_id='".$this->db_match['match_id']."', round_number='".$round_number."';";
			$r = $GLOBALS['app']->run_query($q);
			$match_round_id = mysql_insert_id();
			
			$q = "SELECT * FROM match_rounds WHERE match_round_id='".$match_round_id."';";
			$r = $GLOBALS['app']->run_query($q);
			$match_round = mysql_fetch_array($r);
		}
		
		return $match_round;
	}

	function finish_match_round($round_number) {
		$q = "SELECT * FROM match_moves mv JOIN match_memberships mem ON mv.membership_id=mem.membership_id JOIN users u ON mem.user_id=u.user_id WHERE mem.match_id='".$this->db_match['match_id']."' AND mv.round_number=".$round_number." AND mv.move_type='burn' ORDER BY mv.amount DESC;";
		$r = $GLOBALS['app']->run_query($q);
		
		$winner = false;
		$num_tied_for_first = 0;
		$best_amount = false;
		
		while ($move = mysql_fetch_array($r)) {
			if (!$winner) {
				$winner = $move;
				$best_amount = $move['amount'];
				$num_tied_for_first++;
			}
			else {
				if ($move['amount'] == $best_amount) $num_tied_for_first++;
			}
		}
		
		$match_round = $this->get_match_round($round_number);
		
		if ($num_tied_for_first == 1) {
			$q = "UPDATE match_rounds SET status='won', winning_membership_id='".$winner['membership_id']."' WHERE match_round_id='".$match_round['match_round_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			$this->add_match_message("Player #".($winner['player_position']+1)." won round #".$round_number." with ".$winner['amount']/pow(10,8)." coins.", false, false, false);
		}
		else {
			$q = "UPDATE match_rounds SET status='tied' WHERE match_round_id='".$match_round['match_round_id']."';";
			$r = $GLOBALS['app']->run_query($q);
			$this->add_match_message($num_tied_for_first." players tied for round #".$round_number, false, false, false);
		}
	}

	function match_account_value($membership_id) {
		$q = "SELECT SUM(amount) FROM match_IOs WHERE spend_status='unspent' AND membership_id='".$membership_id."';";
		$r = $GLOBALS['app']->run_query($q);
		$sum = mysql_fetch_row($r);
		return $sum[0];
	}

	function match_immature_balance($membership_id) {
		return 0;
	}

	function match_mature_balance($membership_id) {
		$account_value = $this->match_account_value($membership_id);
		$immature_balance = $this->match_immature_balance($membership_id);
		
		return ($account_value - $immature_balance);
	}

	function initialize_match() {
		if ($match['turn_based'] == 1) $firstplayer_position = rand(0, $this->db_match['num_players']-1);
		else $firstplayer_position = 0;
		
		$qq = "SELECT * FROM match_memberships mm JOIN users u ON mm.user_id=u.user_id WHERE mm.match_id='".$this->db_match['match_id']."' ORDER BY mm.player_position ASC;";
		$rr = $GLOBALS['app']->run_query($qq);
		while ($membership = mysql_fetch_array($rr)) {
			$this->add_match_message("Anonymous joined the game at ".date("g:ia", $membership['time_joined'])." as player #".($membership['player_position']+1), false, false, $membership['user_id']);
			$this->add_match_message("You joined the game at ".date("g:ia", $membership['time_joined'])." as player #".($membership['player_position']+1), false, $membership['user_id'], false);
			
			$move_id = $this->start_match_move($membership['membership_id'], 'deposit', $this->db_match['initial_coins_per_player']);
			$this->finalize_match_move($move_id);
		}
		$deposit_msg = "The dealer hands out ".number_format($this->db_match['initial_coins_per_player']/pow(10,8))." coins to each player, the game begins.";
		$this->add_match_message($deposit_msg, false, false, false);
		
		if ($this->db_match['turn_based'] == 1) {
			if ($this->db_match['num_players'] == 2) {
				if ($firstplayer_position == 0) $heads_tails = "heads";
				else $heads_tails = "tails";
				$this->add_match_message("Player 1 calls heads.", false, false, false);
				$this->add_match_message("The dealer flips a coin..", false, false, false);
				$this->add_match_message("The coin comes up $heads_tails, player ".($firstplayer_position+1)." goes first.", false, false, false);
			}
			else {
				$this->add_match_message("The dealer rolls a ".$match['num_players']."-sided die..", false, false, false);
				$this->add_match_message("The dice comes up ".($firstplayer_position+1).", player ".($firstplayer_position+1)." goes first.", false, false, false);
			}
		}
		
		return $firstplayer_position;
	}

	function set_match_status($status) {
		$q = "UPDATE matches SET status='".$status."'";
		if ($match['firstplayer_position'] == -1 && $status == "running") {
			$firstplayer_position = $this->initialize_match();
			$q .= ", firstplayer_position=".$firstplayer_position;
		}
		$q .= " WHERE match_id='".$this->db_match['match_id']."';";
		$r = $GLOBALS['app']->run_query($q);
	}

	function add_match_message($message, $from_user_id, $to_user_id, $hide_user_id) {
		$q = "INSERT INTO match_messages SET match_id='".$this->db_match['match_id']."', message='".mysql_real_escape_string($message)."'";
		if ($from_user_id) $q .= ", from_user_id='".$from_user_id."'";
		if ($to_user_id) $q .= ", to_user_id='".$to_user_id."'";
		if ($hide_user_id) $q .= ", hide_user_id='".$hide_user_id."'";
		$q .= ", time_created='".time()."';";
		$r = $GLOBALS['app']->run_query($q);
	}

	function show_match_messages($user_id, $last_message_id) {
		$q = "SELECT * FROM match_messages WHERE match_id='".$this->db_match['match_id']."' AND (to_user_id='".$user_id."' OR to_user_id IS NULL) AND (hide_user_id != '".$user_id."' OR hide_user_id IS NULL)";
		if ($last_message_id) $q .= " AND message_id > ".$last_message_id;
		$q .= " ORDER BY time_created ASC;";
		$r = $GLOBALS['app']->run_query($q);
		$html = "";
		while ($message = mysql_fetch_array($r)) {
			$html .= $message['message']."<br/>\n";
		}
		return $html;
	}

	function match_current_player() {
		$player_position = ($this->db_match['firstplayer_position']+$this->db_match['last_move_number'])%$this->db_match['num_players'];
		$q = "SELECT * FROM match_memberships mm JOIN users u ON mm.user_id=u.user_id WHERE mm.player_position='".$player_position."' AND mm.match_id='".$this->db_match['match_id']."';";
		$r = $GLOBALS['app']->run_query($q);
		$player = mysql_fetch_array($r);
		return $player;
	}

	function last_match_message() {
		$q = "SELECT message_id FROM match_messages WHERE match_id='".$this->db_match['match_id']."' ORDER BY message_id DESC LIMIT 1;";
		$r = $GLOBALS['app']->run_query($q);
		if (mysql_numrows($r) > 0) {
			$last_message = mysql_fetch_array($r);
			return $last_message['message_id'];
		}
		else return 0;
	}
	
	function match_body(&$my_membership, $thisuser) {
		$html = "";
		
		if ($this->db_match['status'] == "pending") {
			$html .= "Great, this game is ready to begin!<br/>";
			$html .= '<button class="btn btn-success" onclick="start_match('.$this->db_match['match_id'].');">Begin the game</button>';
		}
		else if ($match['status'] == "running") {
			$q = "SELECT * FROM match_memberships mem JOIN users u ON mem.user_id=u.user_id WHERE mem.match_id='".$this->db_match['match_id']."' ORDER BY player_position ASC;";
			$r = $GLOBALS['app']->run_query($q);
			while ($player = mysql_fetch_array($r)) {
				$qq = "SELECT COUNT(*) FROM match_rounds WHERE match_id='".$this->db_match['match_id']."' AND winning_membership_id='".$player['membership_id']."';";
				$rr = $GLOBALS['app']->run_query($qq);
				$player_wins = mysql_fetch_row($rr);
				$player_wins = $player_wins[0];
				
				$html .= '<div class="row"';
				if ($thisuser->db_user['user_id'] == $player['user_id']) $html .= ' style="font-weight: bold;"';
				$html .= '><div class="col-sm-8">';
				if ($thisuser->db_user['user_id'] == $player['user_id']) $html .= "You have: ";
				else $html .= "Player #".($player['player_position']+1).": ";
				$html .= $player_wins." win";
				if ($player_wins != 1) $html .= "s";
				$html .= ", ".$this->match_mature_balance($player['membership_id'])/pow(10,8)." coins left";
				$html .= '</div><div class="col-sm-4">';
				$qq = "SELECT * FROM match_moves WHERE membership_id='".$player['membership_id']."' AND round_number='".$this->db_match['current_round_number']."';";
				$rr = $GLOBALS['app']->run_query($qq);
				if (mysql_numrows($rr) > 0) $html .= "Moved submitted";
				else $html .= "Awaiting move...";
				$html .= "</div></div>\n";
			}
			
			$html .= "<br/>You're currently on round ".$this->db_match['current_round_number']." of ".$this->db_match['num_rounds']."<br/>\n";
			
			$q = "SELECT * FROM match_moves WHERE membership_id='".$my_membership['membership_id']."' AND round_number='".$this->db_match['current_round_number']."';";
			$r = $GLOBALS['app']->run_query($q);
			if (mysql_numrows($r) > 0) {
				$my_move = mysql_fetch_array($r);
				$html .= 'You put <font class="greentext">'.$my_move['amount']/pow(10,8)." coins</font> down on this round.<br/>\n";
				$html .= "Waiting on your opponent...";
			}
			else {
				$html .= 'Please enter an amount or use the sliders below, then submit your move for this round.<br/><br/>';
				$html .= '
				<div class="row">
					<div class="col-sm-4">
						<input class="form-control" id="match_move_amount" type="tel" size="6" placeholder="0.00" />
					</div>
				</div>
				
				<div id="match_slider" class="noUiSlider"></div>
				
				<button id="match_slider_label" class="btn btn-primary" onclick="submit_move('.$this->db_match['match_id'].');">Submit Move</button>';
			}
		}
		else if ($this->db_match['status'] == "finished") {}
		
		return $html;
	}

	function round_result_html($round_number, $thisuser) {
		$html = "";
		
		$match_round = $this->get_match_round($round_number);
		
		if ($match_round['status'] == "won") {
			$qq = "SELECT * FROM match_memberships WHERE membership_id='".$match_round['winning_membership_id']."';";
			$rr = $GLOBALS['app']->run_query($qq);
			$winner = mysql_fetch_array($rr);
			$html .= "<h1>";
			if ($winner['user_id'] == $thisuser->db_user['user_id']) $html .= "You won round #".$round_number."!";
			else $html .= "Player #".($winner['player_position']+1)." won round #".$round_number;
			$html .= "</h1>\n";
		}
		
		$q = "SELECT * FROM match_memberships mem JOIN users u ON mem.user_id=u.user_id JOIN match_moves mv ON mv.membership_id=mem.membership_id WHERE mem.match_id='".$this->db_match['match_id']."' AND mv.round_number='".$round_number."' ORDER BY mv.amount DESC;";
		$r = $GLOBALS['app']->run_query($q);
		while ($player = mysql_fetch_array($r)) {
			$html .= '<div class="row"><div class="col-sm-6">';
			$html .= "Player #".($player['player_position']+1);
			$html .= '</div><div class="col-sm-6 text-right">';
			$html .= $player['amount']/pow(10,8)." coins";
			$html .= "</div></div>\n";
		}
		
		return $html;
	}
}
?>