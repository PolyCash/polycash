<?php
date_default_timezone_set('America/Chicago');

include("mysql_config.php");
include("pageview_functions.php");

mysql_connect($server, $user, $password) or die("<H3>Server unreachable</H3>");
mysql_select_db($database) or die ( "<H3>Database non existent</H3>");

function run_query($query) {
	$result = mysql_query($query) or die("Error in query: ".$query.", ".mysql_error());
	return $result;
}

function make_alphanumeric($string, $extrachars) {
	$string = strtolower($string);
	$allowed_chars = "0123456789abcdefghijklmnopqrstuvwxyz".$extrachars;
	$new_string = "";
	
	for ($i=0; $i<strlen($string); $i++) {
		if (is_numeric(strpos($allowed_chars, $string[$i])))
			$new_string .= $string[$i];
	}
	return $new_string;
}

function random_string($length) {
    $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $string ="";

    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, strlen($characters)-1)];
    }

    return $string;
}

function recaptcha_check_answer($recaptcha_privatekey, $ip_address, $g_recaptcha_response) {
	$response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$recaptcha_privatekey."&response=".$g_recaptcha_response."&remoteip=".$ip_address), true);
	if ($response['success'] == false) return false;
	else return true;
}

function get_redirect_url($url) {
	$q = "SELECT * FROM redirect_urls WHERE url='".mysql_real_escape_string($url)."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$redirect_url = mysql_fetch_array($r);
	}
	else {
		$q = "INSERT INTO redirect_urls SET url='".mysql_real_escape_string($url)."', time_created='".time()."';";
		$r = run_query($q);
		$redirect_url_id = mysql_insert_id();
		
		$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
		$r = run_query($q);
		$redirect_url = mysql_fetch_array($r);
	}
	return $redirect_url;
}

function mail_async($email, $from_name, $from, $subject, $message, $bcc, $cc) {
	$q = "INSERT INTO async_email_deliveries SET to_email='".mysql_real_escape_string($email)."', from_name='".$from_name."', from_email='".mysql_real_escape_string($from)."', subject='".mysql_real_escape_string($subject)."', message='".mysql_real_escape_string($message)."', bcc='".mysql_real_escape_string($bcc)."', cc='".mysql_real_escape_string($cc)."', time_created='".time()."';";
	$r = run_query($q);
	$delivery_id = mysql_insert_id();
	
	$command = "/usr/bin/php ".$GLOBALS['install_path']."/scripts/async_email_deliver.php ".$delivery_id." > /dev/null 2>/dev/null &";
	exec($command);
	
	return $delivery_id;
}
	
function account_coin_value($game_id, $user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND user_id='".$user['user_id']."';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	return $sum[0];
}

function immature_balance($game_id, $user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND user_id='".$user['user_id']."' AND block_id > ".(last_block_id($game_id)-get_site_constant("maturity"))." AND amount > 0 AND transaction_desc != 'giveaway';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0];
}

function mature_balance($game_id, $user) {
	$account_value = account_coin_value($game_id, $user);
	$immature_balance = immature_balance($game_id, $user);
	
	return ($account_value - $immature_balance);
}

function current_block($game_id) {
	$q = "SELECT * FROM blocks WHERE game_id='".$game_id."' ORDER BY block_id DESC LIMIT 1;";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) return mysql_fetch_array($r);
	else return false;
}

function last_block_id($game_id) {
	$block = current_block($game_id);
	if ($block) return $block['block_id'];
	else return 0;
}

function block_to_round($mining_block_id) {
	return ceil($mining_block_id/10);
}

function get_site_constant($constant_name) {
	$q = "SELECT * FROM site_constants WHERE constant_name='".$constant_name."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$constant = mysql_fetch_array($r);
		return $constant['constant_value'];
	}
	else return "";
}
function round_voting_stats($game_id, $round_id) {
	$last_block_id = last_block_id($game_id);
	$current_round = block_to_round($last_block_id+1);
	
	if ($round_id == $current_round) {
		$q = "SELECT n.*, gn.* FROM nations n, game_nations gn WHERE n.nation_id=gn.nation_id AND gn.game_id='".$game_id."' ORDER BY gn.current_vote_score DESC, n.nation_id ASC;";
		return run_query($q);
	}
	else {
		$q = "SELECT n.*, gn.* FROM webwallet_transactions t, game_nations gn, nations n WHERE gn.nation_id=n.nation_id AND t.game_id='".$game_id."' AND t.nation_id=gn.nation_id AND t.amount>0 AND t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." GROUP BY t.nation_id ORDER BY SUM(t.amount) DESC;";
		return run_query($q);
	}
}
function total_votes_in_round($game_id, $round_id) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND nation_id > 0 AND amount > 0 AND block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1).";";
	$r = run_query($q);
	$total_votes = mysql_fetch_row($r);
	$total_votes = $total_votes[0];
	if ($total_votes > 0) {} else $total_votes = 0;
	return $total_votes;
}
function round_voting_stats_all($game_id, $voting_round) {
	$sumVotes = 0;
	$round_voting_stats = round_voting_stats($game_id, $voting_round);
	$stats_all = false;
	$counter = 0;
	$nation_id_csv = "";
	$nation_id_to_rank = "";
	
	while ($stat = mysql_fetch_array($round_voting_stats)) {
		$stats_all[$counter] = $stat;
		$nation_id_csv .= $stat['nation_id'].",";
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	if ($nation_id_csv != "") $nation_id_csv = substr($nation_id_csv, 0, strlen($nation_id_csv)-1);
	
	$q = "SELECT * FROM game_nations WHERE game_id='".$game_id."'";
	if ($nation_id_csv != "") $q .= " AND nation_id NOT IN (".$nation_id_csv.")";
	$q .= " ORDER BY nation_id ASC;";
	$r = run_query($q);
	
	while ($stat = mysql_fetch_array($r)) {
		$stat['current_vote_score'] = 0;
		$stat['coins_currently_voted'] = 0;
		$stats_all[$counter] = $stat;
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	
	$sum_votes = total_votes_in_round($game_id, $voting_round);
	$output_arr[0] = $sum_votes;
	$output_arr[1] = floor($sum_votes*get_site_constant('max_voting_fraction'));
	$output_arr[2] = $stats_all;
	$output_arr[3] = $nation_id_to_rank;
	
	return $output_arr;
}
function get_round_winner($round_stats_all) {
	$winner_nation_id = false;
	$max_vote_sum = $round_stats_all[1];
	$round_stats = $round_stats_all[2];
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i]['coins_currently_voted'] <= $max_vote_sum && $round_stats[$i]['current_vote_score'] > 0) $winner_nation_id = $round_stats[$i]['nation_id'];
	}
	if ($winner_nation_id) {
		$q = "SELECT * FROM nations WHERE nation_id='".$winner_nation_id."';";
		$r = run_query($q);
		$nation = mysql_fetch_array($r);
		return $nation;
	}
	else return false;
}
function current_round_table($game, $current_round, $user, $show_intro_text) {
	$last_block_id = last_block_id($game['game_id']);
	$current_round = block_to_round($last_block_id+1);
	$block_within_round = $last_block_id%get_site_constant('round_length')+1;
	
	$round_stats_all = round_voting_stats_all($game['game_id'], $current_round);
	$total_vote_sum = $round_stats_all[0];
	$max_vote_sum = $round_stats_all[1];
	$round_stats = $round_stats_all[2];
	$winner_nation_id = FALSE;
	
	$html = "<div style=\"padding: 5px;\">";
	
	if ($show_intro_text) {
		if ($block_within_round != 10) $html .= "<h2>Current Rankings - Round #".$current_round."</h2>\n";
		else {
			$winner = get_round_winner($round_stats_all);
			if ($winner) $html .= "<h1>".$winner['name']." won round #".$current_round."</h1>";
			else $html .= "No winner in round #".$current_round."</h1>";
		}
		if ($last_block_id == 0) $html .= 'Currently mining the first block.<br/>';
		else $html .= 'Last block completed: #'.$last_block_id.', currently mining #'.($last_block_id+1).'<br/>';
		$html .= 'Current votes count towards block '.$block_within_round.'/'.get_site_constant('round_length').' in round #'.$current_round.'<br/>';
		
		$seconds_left = round((get_site_constant('round_length')-$last_block_id%get_site_constant('round_length'))*$game['seconds_per_block']);
		$minutes_left = round($seconds_left/60);
		$html .= 'Approximately ';
		if ($minutes_left > 1) $html .= $minutes_left." minutes";
		else $html .= $seconds_left." seconds";
		$html .= ' left in this round.<br/>';
	}
	
	$html .= "<div class='row'>";
	
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i]['coins_currently_voted'] <= $max_vote_sum && $round_stats[$i]['current_vote_score'] > 0) $winner_nation_id = $round_stats[$i]['nation_id'];
		$html .= '
		<div class="col-md-3">
			<div class="vote_nation_box';
			if ($round_stats[$i]['coins_currently_voted'] > $max_vote_sum) $html .=  " redtext";
			else if ($winner_nation_id == $round_stats[$i]['nation_id']) $html .=  " greentext";
			$html .='" id="vote_nation_'.$i.'" onmouseover="nation_selected('.$i.');" onclick="nation_selected('.$i.'); start_vote('.$round_stats[$i]['nation_id'].');">
				<input type="hidden" id="nation_id2rank_'.$round_stats[$i]['nation_id'].'" value="'.$i.'" />
				<input type="hidden" id="rank2nation_id_'.$i.'" value="'.$round_stats[$i]['nation_id'].'" />
				<table>
					<tr>
						<td>
							<div class="vote_nation_flag '.strtolower(str_replace(' ', '', $round_stats[$i]['name'])).'"></div>
						</td>
						<td style="width: 100%;">
							<span style="float: left;">
								<div class="vote_nation_flag_label">'.($i+1).'. '.$round_stats[$i]['name'].'</div>
							</span>
							<span style="float: right; padding-right: 5px;">';
								$pct_votes = 100*(floor(1000*$round_stats[$i]['coins_currently_voted']/$total_vote_sum)/1000);
								$html .= $pct_votes;
								$html .= '%
							</span>
						</td>
					</tr>
				</table>
			</div>
		</div>';
		
		if ($ncount%4 == 1) $html .= '</div><div class="row">';
	}
	$html .= "</div>";
	$html .= "</div>";
	return $html;
}
function performance_history($user, $from_round_id, $to_round_id) {
	$html = "";
	$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.game_id='".$user['game_id']."' AND r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
	$r = run_query($q);
	
	while ($round = mysql_fetch_array($r)) {
		$first_voting_block_id = ($round['round_id']-1)*get_site_constant('round_length')+1;
		$last_voting_block_id = $first_voting_block_id+get_site_constant('round_length')-1;
		$vote_sum = 0;
		$details_html = "";
		
		$nation_score = nation_score_in_round($user['game_id'], $round['winning_nation_id'], $round['round_id']);
		
		$html .= '<div class="row" style="font-size: 13px;">';
		$html .= '<div class="col-sm-1">Round&nbsp;#'.$round['round_id'].'</div>';
		$html .= '<div class="col-sm-4">';
		if ($round['name'] != "") $html .= $round['name']." won with ".number_format($round['winning_vote_sum']/pow(10,8), 2)." EMP";
		else $html .= "No winner";
		$html .= '</div>';
		
		$returnvals = my_votes_in_round($user['game_id'], $round['round_id'], $user['user_id']);
		$my_votes = $returnvals[0];
		$coins_voted = $returnvals[1];
		
		if ($my_votes[$round['winning_nation_id']] > 0) $win_text = "You correctly voted ".number_format(round($my_votes[$round['winning_nation_id']]/pow(10,8), 2), 2)." coins.";
		else if ($coins_voted > 0) $win_text = "You didn't vote for the winning empire.";
		else $win_text = "You didn't cast any votes.";
		
		$html .= '<div class="col-sm-5">';
		$html .= $win_text;
		$html .= ' <a href="/explorer/rounds/'.$round['round_id'].'" target="_blank">Details</a>';
		$html .= '</div>';
		
		$html .= '<div class="col-sm-2">';
		$html .= '<font class="';
		if ($my_votes[$round['winning_nation_id']] > 0) $html .= 'greentext';
		else $html .= 'redtext';
		$html .= '">+'.number_format(750*$my_votes[$round['winning_nation_id']]/$nation_score, 2).' EMP</font>';
		$html .= '</div>';
		
		$html .= "</div>\n";
	}
	return $html;
}
function last_voting_transaction_id($game_id) {
	$q = "SELECT transaction_id FROM webwallet_transactions WHERE game_id='".$game_id."' AND nation_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
	$r = run_query($q);
	$r = mysql_fetch_row($r);
	if ($r[0] > 0) {} else $r[0] = 0;
	return $r[0];
}
function last_transaction_id($game_id) {
	$q = "SELECT transaction_id FROM webwallet_transactions WHERE game_id='".$game_id."' ORDER BY transaction_id DESC LIMIT 1;";
	$r = run_query($q);
	$r = mysql_fetch_row($r);
	if ($r[0] > 0) {} else $r[0] = 0;
	return $r[0];
}
function wallet_text_stats($thisuser, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance, $seconds_per_block) {
	$html = "<div class=\"row\"><div class=\"col-sm-2\">Available&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"greentext\">".number_format(floor($mature_balance/pow(10,5))/1000, 2)."</font> EmpireCoins</div></div>\n";
	$html .= "<div class=\"row\"><div class=\"col-sm-2\">Locked&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"redtext\">".number_format($immature_balance/pow(10,8), 2)."</font> EmpireCoins</div>";
	if ($immature_balance > 0) $html .= "<div class=\"col-sm-1\"><a href=\"\" onclick=\"$('#lockedfunds_details').toggle('fast'); return false;\">Details</a></div>";
	$html .= "</div>\n";
	$html .= "Last block completed: #".$last_block_id.", currently mining #".($last_block_id+1)."<br/>\n";
	$html .= "Current votes count towards block ".$block_within_round."/".get_site_constant('round_length')." in round #".$current_round."<br/>\n";
	
	if ($immature_balance > 0) {
		$q = "SELECT * FROM webwallet_transactions t LEFT JOIN nations n ON t.nation_id=n.nation_id WHERE t.game_id='".$thisuser['game_id']."' AND t.amount > 0 AND t.user_id='".$thisuser['user_id']."' AND t.block_id > ".(last_block_id($thisuser['game_id']) - get_site_constant('maturity'))." AND t.transaction_desc != 'giveaway' ORDER BY t.block_id ASC, t.transaction_id ASC;";
		$r = run_query($q);
		
		$html .= "<div style='display: none; border: 1px solid #ccc; padding: 8px; border-radius: 8px; margin-top: 8px;' id='lockedfunds_details'>";
		while ($next_transaction = mysql_fetch_array($r)) {
			$avail_block = get_site_constant('maturity') + $next_transaction['block_id'] + 1;
			$seconds_to_avail = round(($avail_block - $last_block_id - 1)*$seconds_per_block);
			$minutes_to_avail = round($seconds_to_avail/60);
			
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
			$html .= "<font class=\"greentext\">".round($next_transaction['amount']/(pow(10, 8)), 2)."</font> ";
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "coins in block ".$next_transaction['block_id'].". Coins";
			else $html .= "coins received in block #".$next_transaction['block_id'];
			$html .= " can be spent in block #".$avail_block.". (Approximately ";
			if ($minutes_to_avail > 1) $html .= $minutes_to_avail." minutes";
			else $html .= $seconds_to_avail." seconds";
			$html .= "). ";
			if ($next_transaction['nation_id'] > 0) {
				$html .= "You voted for ".$next_transaction['name']." in round #".block_to_round($next_transaction['block_id']).". ";
			}
			$html .= "<br/>\n";
		}
		$html .= "</div>\n";
	}
	return $html;
}
function vote_details_general($mature_balance) {
	$html = '
	<div class="row">
		<div class="col-xs-6">Your balance:</div>
		<div class="col-xs-6 greentext">'.number_format(floor($mature_balance/pow(10,5))/1000, 2).' EMP</div>
	</div>	';
	return $html;
}
function vote_nation_details($nation, $rank, $voting_sum, $total_vote_sum, $losing_streak) {
	$html .= '
	<div class="row">
		<div class="col-xs-6">Current&nbsp;rank:</div>
		<div class="col-xs-6">'.$rank.date("S", strtotime("1/".$rank."/2015")).'</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Coin&nbsp;votes:</div>
		<div class="col-xs-5">'.number_format($voting_sum/pow(10,8), 2).' EMP</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Percent&nbsp;of&nbsp;votes:</div>
		<div class="col-xs-5">';
	if ($total_vote_sum > 0) $html .= (ceil(100*10000*$voting_sum/$total_vote_sum)/10000);
	else $html .= '0';
	$html .= '%</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Last&nbsp;win:</div>
		<div class="col-xs-5">';
	if ($losing_streak > 0) $html .= ($losing_streak+1).'&nbsp;rounds&nbsp;ago';
	else $html .= "Last&nbsp;round";
	$html .= '
		</div>
	</div>';
	return $html;
}
function generate_user_addresses($game_id, $user_id) {
	$q = "SELECT * FROM nations n WHERE NOT EXISTS(SELECT * FROM addresses a WHERE a.user_id='".$user_id."' AND a.game_id='".$game_id."' AND a.nation_id=n.nation_id) ORDER BY n.nation_id ASC;";
	$r = run_query($q);
	while ($nation = mysql_fetch_array($r)) {
		$new_address = "E";
		$rand1 = rand(0, 1);
		$rand2 = rand(0, 1);
		if ($rand1 == 0) $new_address .= "e";
		else $new_address .= "E";
		if ($rand2 == 0) $new_address .= strtoupper($nation['address_character']);
		else $new_address .= $nation['address_character'];
		$new_address .= random_string(31);
		
		$qq = "INSERT INTO addresses SET game_id='".$game_id."', nation_id='".$nation['nation_id']."', user_id='".$user_id."', address='".$new_address."', time_created='".time()."';";
		$rr = run_query($qq);
	}
	
	$q = "SELECT * FROM addresses WHERE nation_id IS NULL AND game_id='".$game_id."' AND user_id='".$user_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 0) {
		$new_address = "Ex";
		$new_address .= random_string(32);
		
		$qq = "INSERT INTO addresses SET game_id='".$game_id."', user_id='".$user_id."', address='".$new_address."', time_created='".time()."';";
		$rr = run_query($qq);
	}
}
function user_address_id($game_id, $user_id, $nation_id) {
	$q = "SELECT * FROM addresses WHERE game_id='".$game_id."' AND user_id='".$user_id."'";
	if ($nation_id) $q .= " AND nation_id='".$nation_id."'";
	else $q .= " AND nation_id IS NULL";
	$q .= ";";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$address = mysql_fetch_array($r);
		return $address['address_id'];
	}
	else return false;
}

function new_webwallet_transaction($game_id, $nation_id, $amount, $user_id, $block_id, $type) {
	if (!$type || $type == "") $type = "transaction";
	
	$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."'";
	if ($nation_id) $q .= ", nation_id='".$nation_id."'";
	$q .= ", transaction_desc='".$type."', amount=".$amount.", user_id='".$user_id."', address_id='".user_address_id($game_id, $user_id, $nation_id)."', block_id='".$block_id."', time_created='".time()."';";
	$r = run_query($q);
	$transaction_id = mysql_insert_id();
	
	if ($type != "giveaway" && $type != "votebase") {
		$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."', transaction_desc='transaction', amount=".(-1)*$amount.", user_id='".$user_id."', block_id='".$block_id."', time_created='".time()."';";
		$r = run_query($q);
	}
	$round_id = block_to_round($block_id);
	
	if ($nation_id > 0) {
		$q = "UPDATE game_nations n INNER JOIN (
			SELECT nation_id, SUM(amount) sum_amount FROM webwallet_transactions 
			WHERE game_id='".$game_id."' AND block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1)." AND amount > 0
			GROUP BY nation_id
		) tt ON n.nation_id=tt.nation_id SET n.coins_currently_voted=tt.sum_amount, n.current_vote_score=tt.sum_amount WHERE n.game_id='".$game_id."';";
		$r = run_query($q);
	}
	
	return $transaction_id;
}

function nation_score_in_round($game_id, $nation_id, $round_id) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1)." AND amount > 0 AND nation_id='".$nation_id."';";
	$r = run_query($q);
	$score = mysql_fetch_row($r);
	return $score[0];
}

function my_votes_in_round($game_id, $round_id, $user_id) {
	$q = "SELECT n.*, SUM(t.amount) FROM webwallet_transactions t, nations n WHERE t.game_id='".$game_id."' AND t.nation_id=n.nation_id AND t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." AND t.user_id='".$user_id."' AND t.amount > 0 GROUP BY t.nation_id ORDER BY n.nation_id ASC;";
	$r = run_query($q);
	$coins_voted = 0;
	$my_votes = array();
	while ($votesum = mysql_fetch_array($r)) {
		$my_votes[$votesum['nation_id']] = $votesum['SUM(t.amount)'];
		$coins_voted += $votesum['SUM(t.amount)'];
	}
	$returnvals[0] = $my_votes;
	$returnvals[1] = $coins_voted;
	return $returnvals;
}

function my_votes_table($game_id, $round_id, $user) {
	$html = "";
	$q = "SELECT n.*, SUM(t.amount) FROM webwallet_transactions t, nations n WHERE t.game_id='".$game_id."' AND t.nation_id=n.nation_id AND t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." AND t.user_id='".$user['user_id']."' AND t.amount > 0 GROUP BY t.nation_id ORDER BY SUM(t.amount) DESC;";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$html .= "<div style=\"border: 1px solid #ddd; padding: 5px;\">";
		$html .= "<div class=\"row\" style=\"font-weight: bold;\">";
		$html .= "<div class=\"col-sm-4\">Nation</div>";
		$html .= "<div class=\"col-sm-4\">Amount</div>";
		$html .= "<div class=\"col-sm-4\">Payout</div>";
		$html .= "</div>\n";
		while ($my_vote = mysql_fetch_array($r)) {
			$expected_payout = floor(750*pow(10,8)*($my_vote['SUM(t.amount)']/nation_score_in_round($game_id, $my_vote['nation_id'], $round_id)))/pow(10,8);
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-4\">".$my_vote['name']."</div>";
			$html .= "<div class=\"col-sm-4 greentext\">".number_format(round($my_vote['SUM(t.amount)']/pow(10,8), 5))." EMP</div>";
			$html .= "<div class=\"col-sm-4 greentext\">+".number_format(round($expected_payout, 5))." EMP</div>";
			$html .= "</div>\n";
		}
		$html .= "</div>";
	}
	else {
		$html .= "You haven't voted yet in this round.";
	}
	return $html;
}

function set_user_active($user_id) {
	$q = "UPDATE users SET logged_in=1, last_active='".time()."' WHERE user_id='".$user_id."';";
	$r = run_query($q);
}
function initialize_vote_nation_details($game_id, $nation_id2rank, $total_vote_sum) {
	$html = "";
	$nation_q = "SELECT * FROM nations n INNER JOIN game_nations gn ON n.nation_id=gn.nation_id WHERE gn.game_id='".$game_id."' ORDER BY n.nation_id ASC;";
	$nation_r = run_query($nation_q);
	
	$nation_id = 0;
	while ($nation = mysql_fetch_array($nation_r)) {
		$rank = $nation_id2rank[$nation['nation_id']]+1;
		$voting_sum = $nation['coins_currently_voted'];
		$html .= '
		<div style="display: none;" class="modal fade" id="vote_confirm_'.$nation['nation_id'].'">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
						<h2>Vote for '.$nation['name'].'</h2>
						<div id="vote_nation_details_'.$nation['nation_id'].'">
							'.vote_nation_details($nation, $rank, $voting_sum, $total_vote_sum, $nation['losing_streak']).'
						</div>
						<div id="vote_details_'.$nation['nation_id'].'"></div>
						<br/>
						How many EmpireCoins do you want to vote?<br/>
						<div class="row">
							<div class="col-xs-4">
								Amount:
							</div>
							<div class="col-sm-4">
								<input type="text" class="form-control responsive_input" placeholder="0.00" size="10" id="vote_amount_'.$nation['nation_id'].'" />
							</div>
							<div class="col-sm-4">
								EmpireCoins
							</div>
						</div>
						<div class="redtext" id="vote_error_'.$nation['nation_id'].'"></div>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary" id="vote_confirm_btn_'.$nation['nation_id'].'" onclick="confirm_vote('.$nation['nation_id'].');">Confirm Vote</button>
						<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>';
		$n_counter++;
	}
	return $html;
}
function try_apply_invite_key($game_id, $user_id, $invite_key) {
	$invite_key = mysql_real_escape_string($invite_key);
	
	$q = "SELECT * FROM invitations WHERE invitation_key='".$invite_key."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$invitation = mysql_fetch_array($r);
		
		if ($invitation['used'] == 0 && $invitation['used_user_id'] == "" && $invitation['used_time'] == 0) {
			$qq = "UPDATE invitations SET used_user_id='".$user_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
			$rr = run_query($qq);
			return true;
		}
		else return false;
	}
	else return false;
}
function ensure_user_in_game($user_id, $game_id) {
	$q = "SELECT * FROM user_games WHERE user_id='".$user_id."' AND game_id='".$game_id."';";
	$r = run_query($q);
	
	if (mysql_numrows($r) == 0) {
		$q = "INSERT INTO user_games SET user_id='".$user_id."', game_id='".$game_id."';";
		$r = run_query($q);
		$user_game_id = mysql_insert_id();
		
		$q = "SELECT * FROM user_games WHERE user_game_id='".$user_game_id."';";
		$r = run_query($q);
		$user_game = mysql_fetch_array($r);
	}
	else {
		$user_game = mysql_fetch_array($r);
	}
	
	if ($user_game['strategy_id'] > 0) {}
	else {
		$q = "INSERT INTO user_strategies SET game_id='".$game_id."', user_id='".$user_game['user_id']."';";
		$r = run_query($q);
		$strategy_id = mysql_insert_id();
		
		$q = "SELECT * FROM users u, user_games g, user_strategies s WHERE u.user_id=g.user_id AND u.game_id=g.game_id AND g.strategy_id=s.strategy_id AND u.user_id='".$user_id."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 1) {
			$copy_strategy = mysql_fetch_array($r);
			
			$strategy_vars = explode(",", "voting_strategy,aggregate_threshold,by_rank_ranks,api_url,min_votesum_pct,max_votesum_pct,min_coins_available");
			$q = "UPDATE user_strategies SET ";
			for ($i=0; $i<count($strategy_vars); $i++) {
				$q .= $strategy_vars[$i]."='".mysql_real_escape_string($copy_strategy[$strategy_vars[$i]])."', ";
			}
			for ($i=1; $i<=9; $i++) {
				$q .= "vote_on_block_".$i."=".$copy_strategy['vote_on_block_'.$i].", ";
			}
			for ($i=1; $i<=16; $i++) {
				$q .= "nation_pct_".$i."=".$copy_strategy['nation_pct_'.$i].", ";
			}
			$q = substr($q, 0, strlen($q)-2)." WHERE strategy_id='".$strategy_id."';";
			$r = run_query($q);
		}
		
		$q = "UPDATE user_games SET strategy_id='".$strategy_id."' WHERE user_game_id='".$user_game['user_game_id']."';";
		$r = run_query($q);
	}
	
	generate_user_addresses($game_id, $user_id);
}
function new_block($game_id) {
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	$log_text = "";
	$last_block_id = last_block_id($game_id);
	
	$q = "INSERT INTO blocks SET game_id='".$game_id."', block_id='".($last_block_id+1)."', time_created='".time()."';";
	$r = run_query($q);
	$last_block_id = mysql_insert_id();
	
	$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
	$r = run_query($q);
	$block = mysql_fetch_array($r);
	$last_block_id = $block['block_id'];
	
	$mining_block_id = $last_block_id+1;
	
	$voting_round = block_to_round($mining_block_id);
	
	/*if (get_site_constant('blocks_in_era') == 0 || $voting_round%get_site_constant('blocks_in_era') == 1) {
		$q = "UPDATE nations SET cached_force_multiplier=".get_site_constant('num_voting_options').", relevant_wins=1;";
		$r = run_query($q);
	}*/
	
	$log_text .= "Created block $last_block_id<br/>\n";
	
	// Send notifications for coins that just became available
	if ($game['game_type'] != "instant") {
		$q = "SELECT u.* FROM users u, webwallet_transactions t WHERE t.game_id='".$game_id."' AND u.game_id=t.game_id AND t.user_id=u.user_id AND u.notification_preference='email' AND u.notification_email != '' AND t.block_id='".($last_block_id - get_site_constant('maturity'))."' AND t.amount > 0 GROUP BY u.user_id;";
		$r = run_query($q);
		while ($notify_user = mysql_fetch_array($r)) {
			$account_value = account_coin_value($game_id, $notify_user);
			$immature_balance = immature_balance($game_id, $notify_user);
			$mature_balance = $account_value - $immature_balance;
			
			if ($mature_balance >= $account_value*$notify_user['aggregate_threshold']/100) {
				$subject = number_format($mature_balance/pow(10,8), 5)." EmpireCoins are now available to vote.";
				$message = "<p>Some of your EmpireCoins just became available.</p>";
				$message .= "<p>You currently have ".number_format($mature_balance/pow(10,8), 5)." coins available to vote. To cast a vote, please log in:</p>";
				$message .= "<p><a href=\"http://empireco.in/wallet/\">http://empireco.in/wallet/</a></p>";
				$message .= "<p>This message was sent by EmpireCo.in<br/>To disable these notifications, please log in and then click \"Voting Strategy\"";
				
				$delivery_id = mail_async($notify_user['notification_email'], "EmpireCo.in", "noreply@empireco.in", $subject, $message, "", "");
				
				$log_text .= "A notification of new coins available has been sent to ".$notify_user['notification_email'].".<br/>\n";
			}
		}
	}
	
	// Run payouts
	if ($last_block_id%get_site_constant('round_length') == 0) {
		$log_text .= "<br/>Running payout on voting round #".($voting_round-1).", it's now round ".$voting_round."<br/>\n";
		$round_voting_stats = round_voting_stats_all($game_id, $voting_round-1);
		
		$vote_sum = $round_voting_stats[0];
		$max_vote_sum = $round_voting_stats[1];
		$nation_id2rank = $round_voting_stats[3];
		$round_voting_stats = $round_voting_stats[2];
		
		$winning_nation = FALSE;
		$winning_votesum = 0;
		$winning_score = 0;
		$rank = 1;
		for ($rank=1; $rank<=get_site_constant('num_voting_options'); $rank++) {
			$nation_id = $round_voting_stats[$rank-1]['nation_id'];
			$nation_rank2db_id[$rank] = $nation_id;
			$nation_score = nation_score_in_round($game_id, $nation_id, $voting_round-1);
			
			if ($nation_score > $max_vote_sum) {}
			else if (!$winning_nation && $nation_score > 0) {
				$winning_nation = $nation_id;
				$winning_votesum = $nation_score;
				$winning_score = $nation_score;
			}
		}
		
		$log_text .= "Total votes: ".($vote_sum/(pow(10, 8)))." EMP<br/>\n";
		$log_text .= "Cutoff: ".($max_vote_sum/(pow(10, 8)))." EMP<br/>\n";
		
		$q = "UPDATE game_nations SET current_vote_score=0, coins_currently_voted=0, losing_streak=losing_streak+1 WHERE game_id='".$game_id."';";
		$r = run_query($q);
		
		if ($winning_nation) {
			/*if (get_site_constant('blocks_in_era') > 0) {
				$q = "UPDATE nations SET relevant_wins=relevant_wins+1 WHERE nation_id='".$winning_nation."';";
				$r = run_query($q);
				
				$q = "UPDATE nations SET cached_force_multiplier=ROUND((16+".($voting_round%100 - 1).")/relevant_wins, 8);";
				$r = run_query($q);
			}*/
			
			$q = "UPDATE game_nations SET losing_streak=0 WHERE game_id='".$game_id."' AND nation_id='".$winning_nation."';";
			$r = run_query($q);
			
			$log_text .= $round_voting_stats[$nation_id2rank[$winning_nation]]['name']." wins with ".($winning_votesum/(pow(10, 8)))." EMP voted.<br/>";
			
			$q = "SELECT * FROM webwallet_transactions t, users u WHERE t.game_id='".$game_id."' AND t.user_id=u.user_id AND t.block_id >= ".((($voting_round-2)*get_site_constant('round_length'))+1)." AND t.block_id <= ".(($voting_round-1)*get_site_constant('round_length')-1)." AND t.amount > 0 AND t.nation_id=".$winning_nation.";";
			$r = run_query($q);
			
			while ($transaction = mysql_fetch_array($r)) {
				$payout_amount = floor(750*pow(10,8)*$transaction['amount']/$winning_votesum);
				
				new_webwallet_transaction($game_id, false, $payout_amount, $transaction['user_id'], $last_block_id, 'votebase');
				
				$log_text .= "Pay ".$payout_amount/(pow(10,8))." EMP to ".$transaction['username']."<br/>\n";
			}
		}
		else $log_text .= "No winner<br/>";
		
		$log_text .= "<br/>\n";
		
		$q = "INSERT INTO cached_rounds SET game_id='".$game_id."', round_id='".($voting_round-1)."', payout_block_id='".$last_block_id."'";
		if ($winning_nation) $q .= ", winning_nation_id='".$winning_nation."'";
		$q .= ", winning_vote_sum='".$winning_votesum."', winning_score='".$winning_score."', total_vote_sum='".$vote_sum."', time_created='".time()."'";
		for ($position=1; $position<=16; $position++) {
			$q .= ", position_".$position."='".$nation_rank2db_id[$position]."'";
		}
		$q .= ";";
		$r = run_query($q);
	}
	return $log_text;
}

function apply_user_strategies($game_id) {
	$log_text = "";
	$last_block_id = last_block_id($game_id);
	$mining_block_id = $last_block_id+1;
	
	$current_round_id = block_to_round($mining_block_id);
	$block_of_round = $mining_block_id%get_site_constant('round_length');
	
	if ($block_of_round != 0) {
		$q = "SELECT * FROM users u INNER JOIN user_games g ON u.user_id=g.user_id INNER JOIN user_strategies s ON g.strategy_id=s.strategy_id WHERE g.game_id='".$game_id."'";
		if ($game_id == get_site_constant('primary_game_id')) $q .= " AND (u.logged_in=0 OR u.game_id='".$game_id."')";
		$q .= " AND (s.voting_strategy='by_rank' OR s.voting_strategy='by_nation' OR s.voting_strategy='api') AND s.vote_on_block_".$block_of_round."=1 ORDER BY RAND();";
		$r = run_query($q);
		
		$log_text .= "Applying user strategies for block #".$mining_block_id.", looping through ".mysql_numrows($r)." users.<br/>";
		
		while ($strategy_user = mysql_fetch_array($r)) {
			$user_coin_value = account_coin_value($game_id, $strategy_user);
			$immature_balance = immature_balance($game_id, $strategy_user);
			$mature_balance = $user_coin_value - $immature_balance;
			$free_balance = $mature_balance - $strategy_user['min_coins_available']*pow(10,8);
			
			if ($user_coin_value > 0) {
				if ($strategy_user['voting_strategy'] == "api") {
					$api_result = file_get_contents("http://162.253.154.32/proxy908341/?url=".urlencode($strategy_user['api_url']));
					$api_obj = json_decode($api_result);
					
					if ($api_obj->recommendations && count($api_obj->recommendations) > 0 && in_array($api_obj->recommendation_unit, array('coin','percent'))) {
						$amount_error = false;
						$amount_sum = 0;
						$empire_id_error = false;
						
						$log_text .= "For ".$strategy_user['username'].", hitting url: ".$strategy_user['api_url']."<br/>\n";
						
						for ($rec_id=0; $rec_id<count($api_obj->recommendations); $rec_id++) {
							if ($api_obj->recommendations[$rec_id]->recommended_amount && $api_obj->recommendations[$rec_id]->recommended_amount > 0 && intval($api_obj->recommendations[$rec_id]->recommended_amount) == $api_obj->recommendations[$rec_id]->recommended_amount) $amount_sum += $api_obj->recommendations[$rec_id]->recommended_amount;
							else $amount_error = true;
							
							if ($api_obj->recommendations[$rec_id]->empire_id >= 0 && $api_obj->recommendations[$rec_id]->empire_id < 16) {}
							else $empire_id_error = true;
						}
						
						if ($api_obj->recommendation_unit == "coin") {
							if ($amount_sum <= $mature_balance) {}
							else $amount_error = true;
						}
						else {
							if ($amount_sum <= 100) {}
							else $amount_error = true;
						}
						
						if ($amount_error) {
							$log_text .= "Error, an invalid amount was specified.";
						}
						else if ($empire_id_error) {
							$log_text .= "Error, one of the empire IDs was invalid.";
						}
						else {
							for ($rec_id=0; $rec_id<count($api_obj->recommendations); $rec_id++) {
								if ($api_obj->recommendation_unit == "coin") $vote_amount = $api_obj->recommendations[$rec_id]->recommended_amount;
								else $vote_amount = floor($mature_balance*$api_obj->recommendations[$rec_id]->recommended_amount/100);
								
								$vote_nation_id = $api_obj->recommendations[$rec_id]->empire_id + 1;
								$log_text .= "Vote ".$vote_amount." for ".$vote_nation_id."<br/>\n";
								
								$transaction_id = new_webwallet_transaction($game_id, $vote_nation_id, $vote_amount, $strategy_user['user_id'], $mining_block_id, 'transaction');
							}
						}
					}
				}
				else {
					$pct_free = 100*$mature_balance/$user_coin_value;
					
					if ($pct_free >= $strategy_user['aggregate_threshold'] && $free_balance > 0) {
						$round_stats = round_voting_stats_all($game_id, $current_round_id);
						$total_vote_sum = $round_stats[0];
						$ranked_stats = $round_stats[2];
						$nation_id2rank = $round_stats[3];
						
						$nation_pct_sum = 0;
						$skipped_pct_points = 0;
						$skipped_nations = "";
						$num_nations_skipped = 0;
						
						if ($strategy_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $strategy_user['by_rank_ranks']);
						
						for ($nation_id=1; $nation_id<=16; $nation_id++) {
							if ($strategy_user['voting_strategy'] == "by_nation") $nation_pct_sum += $strategy_user['nation_pct_'.$nation_id];
							
							$pct_of_votes = 100*$ranked_stats[$nation_id2rank[$nation_id]]['voting_sum']/$total_vote_sum;
							if ($pct_of_votes >= $strategy_user['min_votesum_pct'] && $pct_of_votes <= $strategy_user['max_votesum_pct']) {}
							else {
								$skipped_nations[$nation_id] = TRUE;
								if ($strategy_user['voting_strategy'] == "by_nation") $skipped_pct_points += $strategy_user['nation_pct_'.$nation_id];
								else if (in_array($nation_id2rank[$nation_id], $by_rank_ranks)) $num_nations_skipped++;
							}
						}
						
						if ($strategy_user['voting_strategy'] == "by_rank") {
							$divide_into = count($by_rank_ranks)-$num_nations_skipped;
							
							$coins_each = floor($free_balance/$divide_into);
							
							$log_text .= "Dividing by rank among ".$divide_into." nations for ".$strategy_user['username']."<br/>";
							
							for ($rank=1; $rank<=16; $rank++) {
								if (in_array($rank, $by_rank_ranks) && !$skipped_nations[$ranked_stats[$rank-1]['nation_id']]) {
									$log_text .= "Vote ".round($coins_each/pow(10,8), 3)." EMP for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>";
									
									$transaction_id = new_webwallet_transaction($game_id, $ranked_stats[$rank-1]['nation_id'], $coins_each, $strategy_user['user_id'], $mining_block_id, 'transaction');
								}
							}
						}
						else { // by_nation
							$log_text .= "Dividing by nation for ".$strategy_user['username']." (".($free_balance/pow(10,8))." EMP)<br/>\n";
							
							$mult_factor = 1;
							if ($skipped_pct_points > 0) {
								$mult_factor = floor(pow(10,6)*$nation_pct_sum/($nation_pct_sum-$skipped_pct_points))/pow(10,6);
							}
							
							if ($nation_pct_sum == 100) {
								for ($nation_id=1; $nation_id<=16; $nation_id++) {
									if (!$skipped_nations[$nation_id] && $strategy_user['nation_pct_'.$nation_id] > 0) {
										$effective_frac = floor(pow(10,4)*$strategy_user['nation_pct_'.$nation_id]*$mult_factor)/pow(10,6);
										$coin_amount = floor($effective_frac*$free_balance);
										
										$log_text .= "Vote ".$strategy_user['nation_pct_'.$nation_id]."% (".round($coin_amount/pow(10,8), 3)." EMP) for ".$ranked_stats[$nation_id2rank[$nation_id]]['name']."<br/>";
										
										$transaction_id = new_webwallet_transaction($game_id, $nation_id, $coin_amount, $strategy_user['user_id'], $mining_block_id, 'transaction');
									}
								}
							}
						}
					}
				}
			}
		}
	}
	return $log_text;
}

function ensure_game_nations($game_id) {
	$qq = "SELECT * FROM nations;";
	$rr = run_query($qq);
	while ($nation = mysql_fetch_array($rr)) {
		$qqq = "INSERT INTO game_nations SET game_id='".$game_id."', nation_id='".$nation['nation_id']."';";
		$rrr = run_query($qqq);
	}
}
?>