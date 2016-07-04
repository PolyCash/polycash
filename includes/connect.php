<?php
include("mysql_config.php");

if ($GLOBALS['enforce_domain'] != "") {
	$domain_parts = explode(".", $_SERVER['HTTP_HOST']);
	$domain = $domain_parts[count($domain_parts)-2].".".$domain_parts[count($domain_parts)-1];
	if ($domain != $GLOBALS['enforce_domain']) {
		header("Location: http://".$GLOBALS['enforce_domain']);
		die();
	}
}

date_default_timezone_set('America/Chicago');

include("pageview_functions.php");

mysql_connect($server, $user, $password) or die("<H3>Server unreachable</H3>");
mysql_select_db($database) or die ( "<H3>Database non existent</H3>");

function run_query($query) {
	if ($GLOBALS['show_query_errors'] == TRUE) $result = mysql_query($query) or die("Error in query: ".$query.", ".mysql_error());
	else $result = mysql_query($query) or die("Error in query");
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
	$q = "SELECT SUM(amount) FROM transaction_IOs WHERE spend_status='unspent' AND game_id='".$game_id."' AND user_id='".$user['user_id']."';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	return $sum[0];
}

function immature_balance($game_id, $user) {
	$q = "SELECT SUM(amount) FROM transaction_IOs WHERE spend_status='unspent' AND game_id='".$game_id."' AND user_id='".$user['user_id']."' AND create_block_id > ".(last_block_id($game_id)-get_site_constant("maturity"))." AND instantly_mature = 0;";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0];
}

function mature_balance($game_id, $user) {
	$account_value = account_coin_value($game_id, $user);
	$immature_balance = immature_balance($game_id, $user);
	
	return ($account_value - $immature_balance);
}

function user_coin_blocks($user_id, $game_id, $last_block_id) {
	$q = "SELECT SUM(amount*(".($last_block_id+1)."-create_block_id)) FROM transaction_IOs WHERE spend_status='unspent' AND game_id='".$game_id."' AND user_id='".$user_id."' AND (create_block_id <= ".(last_block_id($game_id)-get_site_constant("maturity"))." OR instantly_mature = 1);";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	return $sum[0];
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

function round_voting_stats($game, $round_id) {
	if ($game['payout_weight'] == "coin") {
		$score_field = "gn.current_vote_score";
		$sum_field = "i.amount";
	}
	else {
		$score_field = "gn.coin_block_score";
		$sum_field = "i.coin_blocks_destroyed";
	}
	
	$last_block_id = last_block_id($game['game_id']);
	$current_round = block_to_round($last_block_id+1);
	
	if ($round_id == $current_round) {
		$q = "SELECT n.*, gn.* FROM nations n, game_nations gn WHERE n.nation_id=gn.nation_id AND gn.game_id='".$game['game_id']."' ORDER BY ".$score_field." DESC, n.nation_id ASC;";
		return run_query($q);
	}
	else {
		$q = "SELECT n.*, gn.* FROM transaction_IOs i, game_nations gn, nations n WHERE gn.nation_id=n.nation_id AND i.game_id='".$game['game_id']."' AND i.nation_id=gn.nation_id AND i.create_block_id >= ".((($round_id-1)*10)+1)." AND i.create_block_id <= ".($round_id*10-1)." GROUP BY i.nation_id ORDER BY SUM(".$sum_field.") DESC;";
		return run_query($q);
	}
}

function total_score_in_round($game_id, $round_id, $payout_weight) {
	if ($payout_weight == "coin") $score_field = "amount";
	else $score_field = "coin_blocks_destroyed";
	$q = "SELECT SUM(".$score_field.") FROM transaction_IOs WHERE game_id='".$game_id."' AND nation_id > 0 AND amount > 0 AND create_block_id >= ".((($round_id-1)*10)+1)." AND create_block_id <= ".($round_id*10-1).";";
	$r = run_query($q);
	$total_votes = mysql_fetch_row($r);
	$total_votes = $total_votes[0];
	if ($total_votes > 0) {} else $total_votes = 0;
	return $total_votes;
}

function round_voting_stats_all($game, $voting_round) {
	$round_voting_stats = round_voting_stats($game, $voting_round);
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
	
	$q = "SELECT * FROM game_nations WHERE game_id='".$game['game_id']."'";
	if ($nation_id_csv != "") $q .= " AND nation_id NOT IN (".$nation_id_csv.")";
	$q .= " ORDER BY nation_id ASC;";
	$r = run_query($q);
	
	while ($stat = mysql_fetch_array($r)) {
		$stat['current_vote_score'] = 0;
		$stat['coins_currently_voted'] = 0;
		$stat['coin_block_voted'] = 0;
		$stats_all[$counter] = $stat;
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	
	$score_sum = total_score_in_round($game['game_id'], $voting_round, $game['payout_weight']);
	$output_arr[0] = $score_sum;
	$output_arr[1] = floor($score_sum*get_site_constant('max_voting_fraction'));
	$output_arr[2] = $stats_all;
	$output_arr[3] = $nation_id_to_rank;
	
	return $output_arr;
}

function get_round_winner($round_stats_all, $game) {
	if ($game['payout_weight'] == "coin") $score_field = "current_vote_score";
	else $score_field = "coin_block_score";
	
	$winner_nation_id = false;
	$winner_index = false;
	$max_score_sum = $round_stats_all[1];
	$round_stats = $round_stats_all[2];
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i][$score_field] <= $max_score_sum && $round_stats[$i][$score_field] > 0) {
			$winner_nation_id = $round_stats[$i]['nation_id'];
			$winner_index = $i;
		}
	}
	if ($winner_nation_id) {
		$q = "SELECT * FROM nations WHERE nation_id='".$winner_nation_id."';";
		$r = run_query($q);
		$nation = mysql_fetch_array($r);
		
		$nation['winning_score'] = $round_stats[$winner_index][$score_field];
		
		return $nation;
	}
	else return false;
}

function current_round_table($game, $current_round, $user, $show_intro_text) {
	if ($game['payout_weight'] == "coin") $score_field = "current_vote_score";
	else $score_field = "coin_block_score";
	
	$last_block_id = last_block_id($game['game_id']);
	$current_round = block_to_round($last_block_id+1);
	$block_within_round = $last_block_id%get_site_constant('round_length')+1;
	
	$round_stats_all = round_voting_stats_all($game, $current_round);
	$score_sum = $round_stats_all[0];
	$max_score_sum = $round_stats_all[1];
	$round_stats = $round_stats_all[2];
	$winner_nation_id = FALSE;
	
	$html = "<div style=\"padding: 5px;\">";
	
	if ($show_intro_text) {
		if ($block_within_round != 10) $html .= "<h2>Current Rankings - Round #".$current_round."</h2>\n";
		else {
			$winner = get_round_winner($round_stats_all, $game);
			if ($winner) $html .= "<h1>".$winner['name']." won round #".$current_round."</h1>";
			else $html .= "<h1>No winner in round #".$current_round."</h1>";
		}
		if ($last_block_id == 0) $html .= 'Currently mining the first block.<br/>';
		else $html .= 'Last block completed: #'.$last_block_id.', currently mining #'.($last_block_id+1).'<br/>';
		
		if ($block_within_round == 10) {
			$html .= format_bignum($score_sum/pow(10,8)).' votes were cast in this round.<br/>';
			$my_votes = my_votes_in_round($game, $current_round, $user['user_id']);
			$my_winning_votes = $my_votes[0][$winner['nation_id']][$game['payout_weight']."s"];
			if ($my_winning_votes > 0) {
				$win_amount = floor(750*pow(10,8)*$my_winning_votes/$winner['winning_score'])/pow(10,8);
				$html .= "You correctly ";
				if ($game['payout_weight'] == "coin") $html .= "voted ".format_bignum($my_winning_votes/pow(10,8))." coins";
				else $html .= "cast ".format_bignum($my_winning_votes/pow(10,8))." votes";
				$html .= " and won <font class=\"greentext\">+".number_format($win_amount, 2)."</font> coins.<br/>\n";
			}
			else if ($winner) {
				$html .= "You didn't cast any votes for ".$winner['name'].".<br/>\n";
			}
		}
		else {
			$html .= format_bignum($score_sum/pow(10,8)).' votes cast so far, current votes count towards block '.$block_within_round.'/'.get_site_constant('round_length').' in round #'.$current_round.'<br/>';
			$seconds_left = round((get_site_constant('round_length') - $last_block_id%get_site_constant('round_length') - 1)*$game['seconds_per_block']);
			$minutes_left = round($seconds_left/60);
			$html .= 'Approximately ';
			if ($minutes_left > 1) $html .= $minutes_left." minutes";
			else $html .= $seconds_left." seconds";
			$html .= ' of voting left in this round.<br/>';
		}
	}
	
	$html .= "<div class='row'>";
	
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i][$score_field] <= $max_score_sum && $round_stats[$i][$score_field] > 0) $winner_nation_id = $round_stats[$i]['nation_id'];
		$html .= '
		<div class="col-md-3">
			<div class="vote_nation_box';
			if ($round_stats[$i][$score_field] > $max_score_sum) $html .=  " redtext";
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
								$pct_votes = 100*(floor(1000*$round_stats[$i][$score_field]/$score_sum)/1000);
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
	
	$q = "SELECT * FROM games WHERE game_id='".$user['game_id']."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.game_id='".$game['game_id']."' AND r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
	$r = run_query($q);
	
	while ($round = mysql_fetch_array($r)) {
		$first_voting_block_id = ($round['round_id']-1)*get_site_constant('round_length')+1;
		$last_voting_block_id = $first_voting_block_id+get_site_constant('round_length')-1;
		$score_sum = 0;
		$details_html = "";
		
		$nation_score = nation_score_in_round($game, $round['winning_nation_id'], $round['round_id']);
		
		$html .= '<div class="row" style="font-size: 13px;">';
		$html .= '<div class="col-sm-1">Round&nbsp;#'.$round['round_id'].'</div>';
		$html .= '<div class="col-sm-4">';
		if ($round['name'] != "") $html .= $round['name']." won with ".number_format($round['winning_score']/pow(10,8), 2)." votes";
		else $html .= "No winner";
		$html .= '</div>';
		
		$returnvals = my_votes_in_round($game, $round['round_id'], $user['user_id']);
		$my_votes = $returnvals[0];
		$coins_voted = $returnvals[1];
		
		if ($my_votes[$round['winning_nation_id']] > 0) {
			if ($game['payout_weight'] == "coin_block") $win_text = "You correctly cast ".number_format(round($my_votes[$round['winning_nation_id']]['coin_blocks']/pow(10,8), 2), 2)." votes.";
			else $win_text = "You correctly voted ".number_format(round($my_votes[$round['winning_nation_id']]['coins']/pow(10,8), 2), 2)." coins.";
		}
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
		
		if ($game['payout_weight'] == "coin") $payout_amt = 750*$my_votes[$round['winning_nation_id']]['coins']/$nation_score;
		else $payout_amt = 750*$my_votes[$round['winning_nation_id']]['coin_blocks']/$nation_score;
		
		$html .= '">+'.number_format($payout_amt, 2).' EMP</font>';
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

function my_last_transaction_id($user_id, $game_id) {
	if ($user_id > 0 && $game_id > 0) {
		$start_q = "SELECT t.transaction_id FROM webwallet_transactions t, addresses a, transaction_IOs i WHERE a.address_id=i.address_id AND ";
		$end_q .= " AND a.user_id='".$user_id."' AND i.game_id='".$game_id."' ORDER BY t.transaction_id DESC LIMIT 1;";
		
		$create_r = run_query($start_q."i.create_transaction_id=t.transaction_id".$end_q);
		$create_trans_id = mysql_fetch_row($create_r);
		$create_trans_id = $create_trans_id[0];
		
		$spend_r = run_query($start_q."i.spend_transaction_id=t.transaction_id".$end_q);
		$spend_trans_id = mysql_fetch_row($spend_r);
		$spend_trans_id = $spend_trans_id[0];
		
		if ($create_trans_id > $spend_trans_id) return $create_trans_id;
		else return $spend_trans_id;
	}
	else return 0;
}

function to_significant_digits($number, $significant_digits) {
	if ($number === 0) return 0;
	if ($number < 1) $significant_digits++;
	$number_digits = (int)(log10($number));
	$returnval = (pow(10, $number_digits - $significant_digits + 1)) * floor($number/(pow(10, $number_digits - $significant_digits + 1)));
	return $returnval;
}

function format_bignum($number) {
	$rounded_number = to_significant_digits($number, 3);
	
	if ($rounded_number > pow(10, 9)) {
		return $rounded_number/pow(10, 9)."B";
	}
	else if ($rounded_number > pow(10, 6)) {
		return $rounded_number/pow(10, 6)."M";
	}
	else if ($rounded_number > pow(10, 4)) {
		return $rounded_number/pow(10, 3)."k";
	}
	else if ($rounded_number > pow(10, 3)) {
		return number_format($rounded_number);
	}
	else return $rounded_number;
}

function wallet_text_stats($thisuser, $game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance) {
	$html = "<div class=\"row\"><div class=\"col-sm-2\">Available&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"greentext\">".number_format(floor($mature_balance/pow(10,5))/1000, 2)."</font> EmpireCoins</div></div>\n";
	if ($game['payout_weight'] == "coin_block") {
		$html .= "<div class=\"row\"><div class=\"col-sm-2\">Votes:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"greentext\">".format_bignum(user_coin_blocks($thisuser['user_id'], $game['game_id'], $last_block_id)/pow(10,8))."</font> votes available</div></div>\n";
	}
	$html .= "<div class=\"row\"><div class=\"col-sm-2\">Locked&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"redtext\">".number_format($immature_balance/pow(10,8), 2)."</font> EmpireCoins</div>";
	if ($immature_balance > 0) $html .= "<div class=\"col-sm-1\"><a href=\"\" onclick=\"$('#lockedfunds_details').toggle('fast'); return false;\">Details</a></div>";
	$html .= "</div>\n";
	$html .= "Last block completed: #".$last_block_id.", currently mining #".($last_block_id+1)."<br/>\n";
	$html .= "Current votes count towards block ".$block_within_round."/".get_site_constant('round_length')." in round #".$current_round."<br/>\n";
	
	if ($immature_balance > 0) {
		$q = "SELECT * FROM webwallet_transactions t, transaction_IOs i LEFT JOIN nations n ON i.nation_id=n.nation_id WHERE t.transaction_id=i.create_transaction_id AND i.game_id='".$thisuser['game_id']."' AND i.user_id='".$thisuser['user_id']."' AND i.create_block_id > ".(last_block_id($thisuser['game_id']) - get_site_constant('maturity'))." ORDER BY i.io_id ASC;";
		$r = run_query($q);
		
		$html .= "<div style='display: none; border: 1px solid #ccc; padding: 8px; border-radius: 8px; margin-top: 8px;' id='lockedfunds_details'>";
		while ($next_transaction = mysql_fetch_array($r)) {
			$avail_block = get_site_constant('maturity') + $next_transaction['create_block_id'] + 1;
			$seconds_to_avail = round(($avail_block - $last_block_id - 1)*$game['seconds_per_block']);
			$minutes_to_avail = round($seconds_to_avail/60);
			
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
			$html .= "<font class=\"greentext\">".round($next_transaction['amount']/(pow(10, 8)), 2)."</font> ";
			
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "coins in block ".$next_transaction['create_block_id'].". Coins";
			else $html .= "coins received in block #".$next_transaction['create_block_id'];
			
			$html .= " can be spent in block #".$avail_block.". (Approximately ";
			if ($minutes_to_avail > 1) $html .= $minutes_to_avail." minutes";
			else $html .= $seconds_to_avail." seconds";
			$html .= "). ";
			if ($next_transaction['nation_id'] > 0) {
				$html .= "You voted for ".$next_transaction['name']." in round #".block_to_round($next_transaction['create_block_id']).". ";
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

function vote_nation_details($nation, $rank, $nation_score, $score_sum, $losing_streak) {
	$html .= '
	<div class="row">
		<div class="col-xs-6">Current&nbsp;rank:</div>
		<div class="col-xs-6">'.$rank.date("S", strtotime("1/".$rank."/2015")).'</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Votes:</div>
		<div class="col-xs-5">'.number_format($nation_score/pow(10,8), 2).' votes</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Percent&nbsp;of&nbsp;votes:</div>
		<div class="col-xs-5">';
	if ($score_sum > 0) $html .= (ceil(100*100*$nation_score/$score_sum)/100);
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

function new_payout_transaction($game, $voting_round, $block_id, $winning_nation, $winning_score) {
	$log_text = "";
	
	if ($game['payout_weight'] == "coin") $score_field = "amount";
	else $score_field = "coin_blocks_destroyed";
	
	$q = "INSERT INTO webwallet_transactions SET game_id='".$game['game_id']."', transaction_desc='votebase', amount=0, block_id='".$block_id."', time_created='".time()."';";
	$r = run_query($q);
	$transaction_id = mysql_insert_id();
	
	// Loop through the correctly voted UTXOs
	$q = "SELECT * FROM transaction_IOs i, users u WHERE i.game_id='".$game['game_id']."' AND i.user_id=u.user_id AND i.create_block_id >= ".((($voting_round-2)*get_site_constant('round_length'))+1)." AND i.create_block_id <= ".(($voting_round-1)*get_site_constant('round_length')-1)." AND i.nation_id=".$winning_nation.";";
	$r = run_query($q);
	
	$total_paid = 0;
	
	while ($input = mysql_fetch_array($r)) {
		$payout_amount = floor(750*pow(10,8)*$input[$score_field]/$winning_score);
		
		$total_paid += $payout_amount;
		
		$qq = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game['game_id']."', user_id='".$input['user_id']."', address_id='".$input['address_id']."', nation_id=NULL, create_transaction_id='".$transaction_id."', amount='".$payout_amount."', create_block_id='".$block_id."';";
		$rr = run_query($qq);
		$output_id = mysql_insert_id();
		
		$qq = "UPDATE transaction_IOs SET payout_io_id='".$output_id."' WHERE io_id='".$input['io_id']."';";
		$rr = run_query($qq);
		
		$log_text .= "Pay ".$payout_amount/(pow(10,8))." EMP to ".$input['username']."<br/>\n";
	}
	
	$q = "UPDATE webwallet_transactions SET amount='".$total_paid."' WHERE transaction_id='".$transaction_id."';";
	$r = run_query($q);
	
	$returnvals[0] = $transaction_id;
	$returnvals[1] = $log_text;
	
	return $returnvals;
}

function new_betbase_transaction($game, $round_id, $mining_block_id, $winning_nation) {
	$log_text = "";
	
	$q = "INSERT INTO webwallet_transactions SET game_id='".$game['game_id']."', transaction_desc='betbase', block_id='".($mining_block_id-1)."', time_created='".time()."';";
	$r = run_query($q);
	$transaction_id = mysql_insert_id();
	
	$bet_mid_q = "transaction_IOs i, addresses a WHERE i.game_id='".$game['game_id']."' AND i.address_id=a.address_id AND a.bet_round_id = ".$round_id." AND i.create_block_id <= ".round_to_last_betting_block($round_id);
	
	$total_burned_q = "SELECT SUM(i.amount) FROM ".$bet_mid_q.";";
	$total_burned_r = run_query($total_burned_q);
	$total_burned = mysql_fetch_row($total_burned_r);
	$total_burned = $total_burned[0];
	
	if ($total_burned > 0) {
		$winners_burned_q = "SELECT SUM(i.amount) FROM ".$bet_mid_q;
		if ($winning_nation) $winners_burned_q .= " AND bet_nation_id=".$winning_nation.";";
		else $winners_burned_q .= " AND bet_nation_id IS NULL;";
		$winners_burned_r = run_query($winners_burned_q);
		$winners_burned = mysql_fetch_row($winners_burned_r);
		$winners_burned = $winners_burned[0];
		
		$win_multiplier = 0;
		if ($winners_burned > 0) $win_multiplier = floor(pow(10,8)*$total_burned/$winners_burned)/pow(10,8);
		
		$log_text .= $total_burned/pow(10,8)." coins should be paid to the winning bettors (x".$win_multiplier.").<br/>\n";
		
		if ($winners_burned > 0) {
			$bet_winners_q = "SELECT * FROM ".$bet_mid_q." AND bet_nation_id=".$winning_nation.";";
			$bet_winners_r = run_query($bet_winners_q);
			
			$betbase_sum = 0;
			
			while ($bet_winner = mysql_fetch_array($bet_winners_r)) {
				$win_amount = floor($bet_winner['amount']*$win_multiplier);
				$payback_address = bet_transaction_payback_address($bet_winner['create_transaction_id']);
				
				if ($payback_address) {
					$qq = "INSERT INTO transaction_IOs SET spend_status='unspent', instantly_mature=0, game_id='".$game['game_id']."', user_id='".$payback_address['user_id']."', address_id='".$payback_address['address_id']."'";
					if ($payback_address['nation_id'] > 0) $qq .= ", nation_id=".$payback_address['nation_id'];
					$qq .= ", create_transaction_id='".$transaction_id."', amount='".$win_amount."', create_block_id='".($mining_block_id-1)."';";
					$rr = run_query($qq);
					$output_id = mysql_insert_id();
					
					$qq = "UPDATE transaction_IOs SET payout_io_id='".$output_id."' WHERE io_id='".$bet_winner['io_id']."';";
					$rr = run_query($qq);
					
					$log_text .= "Pay ".$win_amount/(pow(10,8))." coins to ".$payback_address['address']." for winning the bet.<br/>\n";
					
					$betbase_sum += $win_amount;
				}
				else $log_text .= "No payback address was found for transaction #".$bet_winner['create_transaction_id']."<br/>\n";
			}
			
			$q = "UPDATE webwallet_transactions SET amount='".$betbase_sum."' WHERE transaction_id='".$transaction_id."';";
			$r = run_query($q);
		}
		else $log_text .= "None of the bettors predicted this outcome!<br/>\n";
	}
	else $log_text .= "No one placed losable bets on this round.<br/>\n";
	
	$returnvals[0] = $transaction_id;
	$returnvals[1] = $log_text;
	
	return $returnvals;
}

function new_webwallet_multi_transaction($game_id, $nation_ids, $amounts, $from_user_id, $to_user_id, $block_id, $type, $io_ids, $address_ids, $remainder_address_id) {
	if (!$type || $type == "") $type = "transaction";
	
	$amount = 0;
	for ($i=0; $i<count($amounts); $i++) {
		$amount += $amounts[$i];
	}
	
	if ($type == "giveaway") $instantly_mature = 1;
	else $instantly_mature = 0;
	
	$from_user['user_id'] = $from_user_id;
	
	$account_value = account_coin_value($game_id, $from_user);
	$immature_balance = immature_balance($game_id, $from_user);
	$mature_balance = $account_value - $immature_balance;
	
	if ((count($nation_ids) == count($amounts) || ($type == "bet" && count($amounts) == count($address_ids))) && ($amount <= $mature_balance || $type == "giveaway" || $type == "votebase")) {
		$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."'";
		if ($nation_id) $q .= ", nation_id=NULL";
		$q .= ", transaction_desc='".$type."', amount=".$amount.", ";
		if ($from_user_id) $q .= "from_user_id='".$from_user_id."', ";
		if ($to_user_id) $q .= "to_user_id='".$to_user_id."', ";
		if ($type == "bet") {
			$qq = "SELECT bet_round_id FROM addresses WHERE address_id='".$address_ids[0]."';";
			$rr = run_query($qq);
			$bet_round_id = mysql_fetch_row($rr);
			$bet_round_id = $bet_round_id[0];
			$q .= "bet_round_id='".$bet_round_id."', ";
		}
		$q .= "address_id=NULL, block_id='".$block_id."', time_created='".time()."';";
		$r = run_query($q);
		$transaction_id = mysql_insert_id();
		
		$overshoot_amount = 0;
		$overshoot_return_addr_id = $remainder_address_id;
		
		if ($type == "giveaway" || $type == "votebase") {}
		else {
			$q = "SELECT * FROM transaction_IOs WHERE spend_status='unspent' AND user_id='".$from_user_id."' AND game_id='".$game_id."' AND (create_block_id <= ".(last_block_id($game_id)-get_site_constant('maturity'))." OR instantly_mature=1)";
			if ($io_ids) $q .= " AND io_id IN (".implode(",", $io_ids).")";
			$q .= " ORDER BY amount ASC;";
			$r = run_query($q);
			$input_sum = 0;
			$coin_blocks_destroyed = 0;
			while ($transaction_input = mysql_fetch_array($r)) {
				if ($input_sum < $amount) {
					$qq = "UPDATE transaction_IOs SET spend_status='spent', spend_transaction_id='".$transaction_id."', spend_block_id='".$block_id."' WHERE io_id='".$transaction_input['io_id']."';";
					$rr = run_query($qq);
					$input_sum += $transaction_input['amount'];
					if (!$overshoot_return_addr_id) $overshoot_return_addr_id = $transaction_input['address_id'];
					$coin_blocks_destroyed += ($block_id - $transaction_input['create_block_id'])*$transaction_input['amount'];
				}
			}
			$overshoot_amount = $input_sum - $amount;
		}
		
		for ($i=0; $i<count($amounts); $i++) {
			if ($address_ids) {
				if (count($address_ids) == count($amounts)) $address_id = $address_ids[$i];
				else $address_id = $address_ids[0];
			}
			else $address_id = user_address_id($game_id, $to_user_id, $nation_ids[$i]);
			
			$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
			$r = run_query($q);
			$address = mysql_fetch_array($r);
			
			$output_cbd = floor($coin_blocks_destroyed*($amounts[$i]/$input_sum));
			$q = "INSERT INTO transaction_IOs SET spend_status='unspent', ";
			if ($to_user_id) $q .= "user_id='".$to_user_id."', ";
			$q .= "coin_blocks_destroyed='".$output_cbd."', instantly_mature='".$instantly_mature."', game_id='".$game_id."', address_id='".$address_id."', nation_id='".$address['nation_id']."', create_transaction_id='".$transaction_id."', amount='".$amounts[$i]."', create_block_id='".$block_id."';";
			$r = run_query($q);
			$output_id = mysql_insert_id();
		}
		
		if ($overshoot_amount > 0) {
			$overshoot_cbd = floor($coin_blocks_destroyed*($overshoot_amount/$input_sum));
			
			$q = "SELECT * FROM addresses WHERE address_id='".$overshoot_return_addr_id."';";
			$r = run_query($q);
			$overshoot_address = mysql_fetch_array($r);
			
			$q = "INSERT INTO transaction_IOs SET spend_status='unspent', game_id='".$game_id."', coin_blocks_destroyed='".$overshoot_cbd."', user_id='".$from_user_id."', address_id='".$overshoot_return_addr_id."', nation_id='".$overshoot_address['nation_id']."', create_transaction_id='".$transaction_id."', amount='".$overshoot_amount."', create_block_id='".$block_id."';";
			$r = run_query($q);
			$output_id = mysql_insert_id();
		}
		
		$round_id = block_to_round($block_id);
		
		$q = "UPDATE game_nations n INNER JOIN (
			SELECT nation_id, SUM(amount) sum_amount, SUM(coin_blocks_destroyed) sum_cbd FROM transaction_IOs 
			WHERE game_id='".$game_id."' AND create_block_id >= ".((($round_id-1)*10)+1)." AND create_block_id <= ".($round_id*10-1)." AND amount > 0
			GROUP BY nation_id
		) i ON n.nation_id=i.nation_id SET n.coins_currently_voted=i.sum_amount, n.coin_block_score=i.sum_cbd, n.current_vote_score=i.sum_amount WHERE n.game_id='".$game_id."';";
		$r = run_query($q);
		
		return $transaction_id;
	}
	else return false;
}

function nation_score_in_round($game, $nation_id, $round_id) {
	if ($game['payout_weight'] == "coin") $score_field = "amount";
	else $score_field = "coin_blocks_destroyed";
	
	$q = "SELECT SUM(".$score_field.") FROM transaction_IOs WHERE game_id='".$game['game_id']."' AND create_block_id >= ".((($round_id-1)*10)+1)." AND create_block_id <= ".($round_id*10-1)." AND nation_id='".$nation_id."';";
	$r = run_query($q);
	$score = mysql_fetch_row($r);
	return $score[0];
}

function my_votes_in_round($game, $round_id, $user_id) {
	$q = "SELECT n.*, SUM(i.amount), SUM(i.coin_blocks_destroyed) FROM transaction_IOs i, nations n WHERE i.game_id='".$game['game_id']."' AND i.nation_id=n.nation_id AND i.create_block_id >= ".((($round_id-1)*10)+1)." AND i.create_block_id <= ".($round_id*10-1)." AND i.user_id='".$user_id."' GROUP BY i.nation_id ORDER BY n.nation_id ASC;";
	$r = run_query($q);
	$coins_voted = 0;
	$coin_blocks_voted = 0;
	$my_votes = array();
	while ($votesum = mysql_fetch_array($r)) {
		$my_votes[$votesum['nation_id']]['coins'] = $votesum['SUM(i.amount)'];
		$my_votes[$votesum['nation_id']]['coin_blocks'] = $votesum['SUM(i.coin_blocks_destroyed)'];
		$coins_voted += $votesum['SUM(i.amount)'];
		$coin_blocks_voted += $votesum['SUM(i.coin_blocks_destroyed)'];
	}
	$returnvals[0] = $my_votes;
	$returnvals[1] = $coins_voted;
	$returnvals[2] = $coin_blocks_voted;
	return $returnvals;
}

function my_votes_table($game, $round_id, $user) {
	if ($game['payout_weight'] == "coin") $score_field = "amount";
	else $score_field = "coin_blocks_destroyed";
	
	$html = "";
	$q = "SELECT n.*, SUM(i.amount), SUM(i.coin_blocks_destroyed) FROM transaction_IOs i, nations n WHERE i.game_id='".$game['game_id']."' AND i.nation_id=n.nation_id AND i.create_block_id >= ".((($round_id-1)*10)+1)." AND i.create_block_id <= ".($round_id*10-1)." AND i.user_id='".$user['user_id']."' GROUP BY i.nation_id ORDER BY SUM(i.amount) DESC;";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$html .= "<div style=\"border: 1px solid #ddd; padding: 5px;\">";
		$html .= "<div class=\"row\" style=\"font-weight: bold;\">";
		$html .= "<div class=\"col-sm-4\">Nation</div>";
		$html .= "<div class=\"col-sm-4\">Amount</div>";
		$html .= "<div class=\"col-sm-4\">Payout</div>";
		$html .= "</div>\n";
		while ($my_vote = mysql_fetch_array($r)) {
			$expected_payout = floor(750*pow(10,8)*($my_vote['SUM(i.'.$score_field.')']/nation_score_in_round($game, $my_vote['nation_id'], $round_id)))/pow(10,8);
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-4\">".$my_vote['name']."</div>";
			$html .= "<div class=\"col-sm-4 greentext\">".number_format($my_vote['SUM(i.'.$score_field.')']/pow(10,8), 2)." votes</div>";
			$html .= "<div class=\"col-sm-4 greentext\">+".number_format($expected_payout, 2)." EMP</div>";
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

function initialize_vote_nation_details($game, $nation_id2rank, $score_sum, $user_id) {
	$html = "";
	$nation_q = "SELECT * FROM nations n INNER JOIN game_nations gn ON n.nation_id=gn.nation_id WHERE gn.game_id='".$game['game_id']."' ORDER BY n.nation_id ASC;";
	$nation_r = run_query($nation_q);
	
	$nation_id = 0;
	while ($nation = mysql_fetch_array($nation_r)) {
		$rank = $nation_id2rank[$nation['nation_id']]+1;
		if ($game['payout_weight'] == "coin") $voting_sum = $nation['coins_currently_voted'];
		else $voting_sum = $nation['coin_block_score'];
		$html .= '
		<div style="display: none;" class="modal fade" id="vote_confirm_'.$nation['nation_id'].'">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
						<h2>Vote for '.$nation['name'].'</h2>
						<div id="vote_nation_details_'.$nation['nation_id'].'">
							'.vote_nation_details($nation, $rank, $voting_sum, $score_sum, $nation['losing_streak']).'
						</div>
						<div id="vote_details_'.$nation['nation_id'].'"></div>
						<div class="redtext" id="vote_error_'.$nation['nation_id'].'"></div>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary" id="vote_confirm_btn_'.$nation['nation_id'].'" onclick="add_nation_to_vote('.$nation['nation_id'].', \''.$nation['name'].'\');">Add '.$nation['name'].' to my vote</button>
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
	$last_block_id = last_block_id($game['game_id']);
	
	$q = "INSERT INTO blocks SET game_id='".$game['game_id']."', block_id='".($last_block_id+1)."', time_created='".time()."';";
	$r = run_query($q);
	$last_block_id = mysql_insert_id();
	
	$q = "SELECT * FROM blocks WHERE internal_block_id='".$last_block_id."';";
	$r = run_query($q);
	$block = mysql_fetch_array($r);
	$last_block_id = $block['block_id'];
	
	$mining_block_id = $last_block_id+1;
	
	$voting_round = block_to_round($mining_block_id);
	
	$log_text .= "Created block $last_block_id<br/>\n";
	
	// Send notifications for coins that just became available
	if ($game['game_type'] != "instant") {
		$q = "SELECT u.* FROM users u, transaction_IOs i WHERE i.game_id='".$game['game_id']."' AND i.user_id=u.user_id AND u.notification_preference='email' AND u.notification_email != '' AND i.create_block_id='".($last_block_id - get_site_constant('maturity'))."' AND i.amount > 0 GROUP BY u.user_id;";
		$r = run_query($q);
		while ($notify_user = mysql_fetch_array($r)) {
			$account_value = account_coin_value($game['game_id'], $notify_user);
			$immature_balance = immature_balance($game['game_id'], $notify_user);
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
		$round_voting_stats = round_voting_stats_all($game, $voting_round-1);
		
		$score_sum = $round_voting_stats[0];
		$max_score_sum = $round_voting_stats[1];
		$nation_id2rank = $round_voting_stats[3];
		$round_voting_stats = $round_voting_stats[2];
		
		$winning_nation = FALSE;
		$winning_votesum = 0;
		$winning_score = 0;
		$rank = 1;
		for ($rank=1; $rank<=get_site_constant('num_voting_options'); $rank++) {
			$nation_id = $round_voting_stats[$rank-1]['nation_id'];
			$nation_rank2db_id[$rank] = $nation_id;
			$nation_score = nation_score_in_round($game, $nation_id, $voting_round-1);
			
			if ($nation_score > $max_score_sum) {}
			else if (!$winning_nation && $nation_score > 0) {
				$winning_nation = $nation_id;
				$winning_votesum = $nation_score;
				$winning_score = $nation_score;
			}
		}
		
		$log_text .= "Total votes: ".($score_sum/(pow(10, 8)))."<br/>\n";
		$log_text .= "Cutoff: ".($max_score_sum/(pow(10, 8)))."<br/>\n";
		
		$q = "UPDATE game_nations SET current_vote_score=0, coins_currently_voted=0, coin_block_score=0, losing_streak=losing_streak+1 WHERE game_id='".$game['game_id']."';";
		$r = run_query($q);
		
		if ($winning_nation) {
			$q = "UPDATE game_nations SET losing_streak=0 WHERE game_id='".$game['game_id']."' AND nation_id='".$winning_nation."';";
			$r = run_query($q);
			
			$log_text .= $round_voting_stats[$nation_id2rank[$winning_nation]]['name']." wins with ".($winning_votesum/(pow(10, 8)))." EMP voted.<br/>";
			$payout_response = new_payout_transaction($game, $voting_round, $last_block_id, $winning_nation, $winning_votesum);
			$transaction_id = $payout_response[0];
			$log_text .= "Payout response: ".$payout_response[1];
			$log_text .= "<br/>\n";
		}
		else $log_text .= "No winner<br/>";
			
		$betbase_response = new_betbase_transaction($game, $voting_round-1, $last_block_id+1, $winning_nation);
		$log_text .= $betbase_response[1];
		
		$q = "INSERT INTO cached_rounds SET game_id='".$game['game_id']."', round_id='".($voting_round-1)."', payout_block_id='".$last_block_id."'";
		if ($winning_nation) $q .= ", winning_nation_id='".$winning_nation."'";
		$q .= ", winning_score='".$winning_score."', score_sum='".$score_sum."', time_created='".time()."'";
		for ($position=1; $position<=16; $position++) {
			$q .= ", position_".$position."='".$nation_rank2db_id[$position]."'";
		}
		$q .= ";";
		$r = run_query($q);
	}
	return $log_text;
}

function apply_user_strategies($game) {
	$log_text = "";
	$last_block_id = last_block_id($game['game_id']);
	$mining_block_id = $last_block_id+1;
	
	$current_round_id = block_to_round($mining_block_id);
	$block_of_round = $mining_block_id%get_site_constant('round_length');
	
	if ($block_of_round != 0) {
		$q = "SELECT * FROM users u INNER JOIN user_games g ON u.user_id=g.user_id INNER JOIN user_strategies s ON g.strategy_id=s.strategy_id WHERE g.game_id='".$game['game_id']."'";
		//if ($game['game_id'] == get_site_constant('primary_game_id')) $q .= " AND (u.logged_in=0 OR u.game_id='".$game['game_id']."')";
		$q .= " AND (s.voting_strategy='by_rank' OR s.voting_strategy='by_nation' OR s.voting_strategy='api') AND s.vote_on_block_".$block_of_round."=1 ORDER BY RAND();";
		$r = run_query($q);
		
		$log_text .= "Applying user strategies for block #".$mining_block_id.", looping through ".mysql_numrows($r)." users.<br/>";
		
		while ($strategy_user = mysql_fetch_array($r)) {
			$user_coin_value = account_coin_value($game['game_id'], $strategy_user);
			$immature_balance = immature_balance($game['game_id'], $strategy_user);
			$mature_balance = $user_coin_value - $immature_balance;
			$free_balance = $mature_balance - $strategy_user['min_coins_available']*pow(10,8);
			
			if ($mature_balance > 0) {
				if ($strategy_user['voting_strategy'] == "api") {
					$api_result = file_get_contents("http://162.253.154.32/proxy908341/?url=".urlencode($strategy_user['api_url']));
					$api_obj = json_decode($api_result);
					
					if ($api_obj->recommendations && count($api_obj->recommendations) > 0 && in_array($api_obj->recommendation_unit, array('coin','percent'))) {
						$amount_error = false;
						$amount_sum = 0;
						$empire_id_error = false;
						
						$log_text .= $strategy_user['username']." has ".$mature_balance/pow(10,8)." coins available, hitting url: ".$strategy_user['api_url']."<br/>\n";
						
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
							$vote_nation_ids = array();
							$vote_amounts = array();
							
							for ($rec_id=0; $rec_id<count($api_obj->recommendations); $rec_id++) {
								if ($api_obj->recommendation_unit == "coin") $vote_amount = $api_obj->recommendations[$rec_id]->recommended_amount;
								else $vote_amount = floor($mature_balance*$api_obj->recommendations[$rec_id]->recommended_amount/100);
								
								$vote_nation_id = $api_obj->recommendations[$rec_id]->empire_id + 1;
								
								$vote_nation_ids[count($vote_nation_ids)] = $vote_nation_id;
								$vote_amounts[count($vote_amounts)] = $vote_amount;
								
								$log_text .= "Vote ".$vote_amount." for ".$vote_nation_id."<br/>\n";
							}
							
							$transaction_id = new_webwallet_multi_transaction($game['game_id'], $vote_nation_ids, $vote_amounts, $strategy_user['user_id'], $strategy_user['user_id'], $mining_block_id, 'transaction', false, false, false);
						}
					}
				}
				else {
					$pct_free = 100*$mature_balance/$user_coin_value;
					
					if ($pct_free >= $strategy_user['aggregate_threshold']) {
						$round_stats = round_voting_stats_all($game, $current_round_id);
						$score_sum = $round_stats[0];
						$ranked_stats = $round_stats[2];
						$nation_id2rank = $round_stats[3];
						
						$nation_pct_sum = 0;
						$skipped_pct_points = 0;
						$skipped_nations = "";
						$num_nations_skipped = 0;
						
						if ($strategy_user['voting_strategy'] == "by_rank") $by_rank_ranks = explode(",", $strategy_user['by_rank_ranks']);
						
						for ($nation_id=1; $nation_id<=16; $nation_id++) {
							if ($strategy_user['voting_strategy'] == "by_nation") $nation_pct_sum += $strategy_user['nation_pct_'.$nation_id];
							
							$pct_of_votes = 100*$ranked_stats[$nation_id2rank[$nation_id]]['voting_sum']/$score_sum;
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
							
							$nation_ids = array();
							$amounts = array();
							
							for ($rank=1; $rank<=16; $rank++) {
								if (in_array($rank, $by_rank_ranks) && !$skipped_nations[$ranked_stats[$rank-1]['nation_id']]) {
									$log_text .= "Vote ".round($coins_each/pow(10,8), 3)." EMP for ".$ranked_stats[$rank-1]['name'].", ranked ".$rank."<br/>";
									
									$nation_ids[count($nation_ids)] = $ranked_stats[$rank-1]['nation_id'];
									$amounts[count($amounts)] = $coins_each;
								}
							}
							$transaction_id = new_webwallet_multi_transaction($game['game_id'], $nation_ids, $amounts, $strategy_user['user_id'], $strategy_user['user_id'], $mining_block_id, 'transaction', false, false, false);
						}
						else { // by_nation
							$log_text .= "Dividing by nation for ".$strategy_user['username']." (".($free_balance/pow(10,8))." EMP)<br/>\n";
							
							$mult_factor = 1;
							if ($skipped_pct_points > 0) {
								$mult_factor = floor(pow(10,6)*$nation_pct_sum/($nation_pct_sum-$skipped_pct_points))/pow(10,6);
							}
							
							if ($nation_pct_sum == 100) {
								$nation_ids = array();
								$amounts = array();
								
								for ($nation_id=1; $nation_id<=16; $nation_id++) {
									if (!$skipped_nations[$nation_id] && $strategy_user['nation_pct_'.$nation_id] > 0) {
										$effective_frac = floor(pow(10,4)*$strategy_user['nation_pct_'.$nation_id]*$mult_factor)/pow(10,6);
										$coin_amount = floor($effective_frac*$free_balance);
										
										$log_text .= "Vote ".$strategy_user['nation_pct_'.$nation_id]."% (".round($coin_amount/pow(10,8), 3)." EMP) for ".$ranked_stats[$nation_id2rank[$nation_id]]['name']."<br/>";
										
										$nation_ids[count($nation_ids)] = $nation_id;
										$amounts[count($amounts)] = $coin_amount;
									}
								}
								$transaction_id = new_webwallet_multi_transaction($game['game_id'], $nation_ids, $amounts, $strategy_user['user_id'], $strategy_user['user_id'], $mining_block_id, 'transaction', false, false, false);
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

function delete_reset_game($delete_or_reset, $game_id) {
	$q = "DELETE FROM webwallet_transactions WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	$q = "DELETE FROM transaction_IOs WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	$q = "DELETE FROM blocks WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	$q = "DELETE FROM cached_rounds WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	$q = "DELETE FROM game_nations WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	$q = "DELETE FROM addresses WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	if ($delete_or_reset == "reset") {
		ensure_game_nations($game_id);
		
		$q = "SELECT * FROM user_games WHERE game_id='".$game_id."';";
		$r = run_query($q);
		while ($user_game = mysql_fetch_array($r)) {
			generate_user_addresses($user_game['game_id'], $user_game['user_id']);
			for ($i=0; $i<5; $i++) {
				new_webwallet_multi_transaction($game_id, false, array(20000000000), false, $user_game['user_id'], last_block_id($game_id), 'giveaway', false, false, false);
			}
		}
	}
	else {
		$q = "DELETE g.*, ug.* FROM games g, user_games ug WHERE g.game_id=".$game_id." AND ug.game_id=g.game_id;";
		$r = run_query($q);
		
		$q = "DELETE FROM user_strategies WHERE game_id='".$game_id."';";
		$r = run_query($q);
	}
	return true;
}

function block_id_to_round_index($mining_block_id) {
	return (($mining_block_id-1)%get_site_constant('round_length'))+1;
}

function render_transaction($transaction, $selected_address_id, $firstcell_text) {
	$html = "";
	$html .= '<div class="row bordered_row"><div class="col-md-6">';
	if ($firstcell_text != "") $html .= $firstcell_text."<br/>\n";
	
	if ($transaction['transaction_desc'] == "votebase") {
		$html .= "Voting Payout&nbsp;&nbsp;".round($transaction['amount']/pow(10,8), 2)." coins";
	}
	else {
		$qq = "SELECT * FROM transaction_IOs i, addresses a LEFT JOIN nations n ON a.nation_id=n.nation_id WHERE i.spend_transaction_id='".$transaction['transaction_id']."' AND i.address_id=a.address_id ORDER BY i.amount DESC;";
		$rr = run_query($qq);
		$input_sum = 0;
		while ($input = mysql_fetch_array($rr)) {
			$html .= number_format($input['amount']/pow(10,8), 2)."&nbsp;coins&nbsp; ";
			$html .= "<a class=\"display_address\" style=\"";
			if ($input['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
			$html .= "\" href=\"/explorer/addresses/".$input['address']."\">".$input['address']."</a>";
			if ($input['name'] != "") $html .= "&nbsp;&nbsp;(".$input['name'].")";
			$html .= "<br/>\n";
			$input_sum += $input['amount'];
		}
	}
	$html .= '</div><div class="col-md-6">';
	$qq = "SELECT i.*, n.*, a.*, p.amount AS payout_amount FROM transaction_IOs i LEFT JOIN transaction_IOs p ON i.payout_io_id=p.io_id, addresses a LEFT JOIN nations n ON a.nation_id=n.nation_id WHERE i.create_transaction_id='".$transaction['transaction_id']."' AND i.address_id=a.address_id ORDER BY i.amount DESC;";
	$rr = run_query($qq);
	$output_sum = 0;
	while ($output = mysql_fetch_array($rr)) {
		$html .= "<a class=\"display_address\" style=\"";
		if ($output['address_id'] == $selected_address_id) $html .= " font-weight: bold; color: #000;";
		$html .= "\" href=\"/explorer/addresses/".$output['address']."\">".$output['address']."</a>&nbsp; ";
		$html .= number_format($output['amount']/pow(10,8), 2)."&nbsp;coins";
		if ($output['name'] != "") $html .= "&nbsp;&nbsp;".$output['name'];
		if ($output['payout_amount'] > 0) $html .= "&nbsp;&nbsp;<font class=\"greentext\">+".round($output['payout_amount']/pow(10,8), 2)."</font>";
		$html .= "<br/>\n";
		$output_sum += $output['amount'];
	}
	$html .= '</div></div>'."\n";
	
	return $html;
}
function select_input_buttons($user_id, $game) {
	$js = "mature_ios.length = 0;\n";
	$html = "";
	$input_buttons_html = "";
	
	$last_block_id = last_block_id($game['game_id']);
	
	$output_q = "SELECT * FROM transaction_IOs i, addresses a WHERE i.address_id=a.address_id AND i.spend_status='unspent' AND a.user_id='".$user_id."' AND i.game_id='".$game['game_id']."' ORDER BY i.amount ASC;";
	$output_r = run_query($output_q);
	
	$utxos = array();
	$viewable_count = 0;
	
	while ($utxo = mysql_fetch_array($output_r)) {
		$utxos[count($utxos)] = $utxo;
		$input_buttons_html .= '<div ';
		
		if ($utxo['create_block_id'] > $last_block_id-get_site_constant('maturity') && $utxo['instantly_mature'] == 0) {
			$utxo['initially_hidden'] = true;
			$input_buttons_html .= 'style="display: none;" ';
		}
		else $viewable_count++;
		
		$input_buttons_html .= 'id="select_utxo_'.$utxo['io_id'].'" class="select_utxo" onclick="add_utxo_to_vote(\''.$utxo['io_id'].'\', '.$utxo['amount'].', '.$utxo['create_block_id'].');">';
		$input_buttons_html .= '</div>'."\n";
		
		$js .= "mature_ios.push(new mature_io(mature_ios.length, ".$utxo['io_id'].", ".$utxo['amount'].", ".$utxo['create_block_id']."));\n";
	}
	$js .= "refresh_mature_io_btns();\n";
	
	$html .= "<div id=\"select_input_buttons_msg\">";
	if ($viewable_count > 0) {
		$html .= "To compose a voting transaction, please add money with the boxes below and then select the nations that you want to vote for.";
	}
	else {
		$html .= "You don't have any coins available to vote right now.";
	}
	$html .= "</div>\n";
	
	$html .= $input_buttons_html;

	$html .= "<script type=\"text/javascript\">".$js."</script>\n";
	
	return $html;
}
function mature_io_ids_csv($user_id, $game_id) {
	if ($user_id > 0 && $game_id > 0) {
		$ids_csv = "";
		$io_q = "SELECT i.io_id FROM transaction_IOs i, addresses a WHERE i.address_id=a.address_id AND i.spend_status='unspent' AND a.user_id='".$user_id."' AND i.game_id='".$game_id."' AND (i.create_block_id <= ".(last_block_id($game_id)-get_site_constant("maturity"))." OR i.instantly_mature = 1) ORDER BY i.io_id ASC;";
		$io_r = run_query($io_q);
		while ($io = mysql_fetch_row($io_r)) {
			$ids_csv .= $io[0].",";
		}
		if ($ids_csv != "") $ids_csv = substr($ids_csv, 0, strlen($ids_csv)-1);
		return $ids_csv;
	}
	else return "";
}
function bet_round_range($game) {
	$last_block_id = last_block_id($game['game_id']);
	$mining_block_within_round = block_id_to_round_index($last_block_id+1);
	$current_round = block_to_round($last_block_id+1);
	
	if ($mining_block_within_round <= 5) $start_round_id = $current_round;
	else $start_round_id = $current_round+1;
	$stop_round_id = $start_round_id+99;
	
	return array($start_round_id, $stop_round_id);
}
function round_to_last_betting_block($round_id) {
	return ($round_id-1)*get_site_constant('round_length')+5;
}
function select_bet_round($game, $current_round) {
	$html = '<select id="bet_round" class="form-control" required="required" onchange="bet_round_changed();">';
	$html .= '<option value="">-- Please Select --</option>'."\n";
	$bet_round_range = bet_round_range($game);
	for ($round_id=$bet_round_range[0]; $round_id<=$bet_round_range[1]; $round_id++) {
		$html .= "<option value=\"".$round_id."\">Round #".$round_id;
		if ($round_id == $current_round) $html .= " (Current round)";
		else {
			$seconds_until = floor(($round_id-$current_round)*get_site_constant('round_length')*$game['seconds_per_block']);
			$minutes_until = floor($seconds_until/60);
			$hours_until = floor($seconds_until/3600);
			$html .= " (";
			if ($hours_until > 1) $html .= "+".$hours_until." hours";
			else if ($minutes_until > 1) $html .= "+".$minutes_until." minutes";
			else $html .= "+".$seconds_until." seconds";
			$html .= ")";
		}
		$html .= "</option>\n";
	}
	$html .= '</select>'."\n";
	return $html;
}

function burn_address_text($game, $round_id, $winner) {
	$addr_text = "";
	if ($winner) {
		$q = "SELECT * FROM nations WHERE nation_id='".$winner."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$nation = mysql_fetch_array($r);
			$addr_text .= strtolower($nation['name'])."_wins";
		}
		else return false;
	}
	else {
		$addr_text .= "no_winner";
	}
	$addr_text .= "_round_".$round_id;
	
	return $addr_text;
}

function get_bet_burn_address($game, $round_id, $nation_id) {
	if ($game['losable_bets_enabled'] == 1) {
		$burn_address_text = burn_address_text($game, $round_id, $nation_id);
		
		$q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."' AND address='".$burn_address_text."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) > 0) {
			$burn_address = mysql_fetch_array($r);
		}
		else {
			$q = "INSERT INTO addresses SET game_id='".$game['game_id']."', address='".$burn_address_text."', bet_round_id='".$round_id."'";
			$q .= ", bet_nation_id='".$nation_id."'";
			$q .= ";";
			$r = run_query($q);
			$burn_address_id = mysql_insert_id();
			
			$q = "SELECT * FROM addresses WHERE address_id='".$burn_address_id."';";
			$r = run_query($q);
			$burn_address = mysql_fetch_array($r);
		}
		return $burn_address;
	}
	else return false;
}

function bet_transaction_payback_address($transaction_id) {
	$q = "SELECT * FROM transaction_IOs i, webwallet_transactions t, addresses a WHERE t.transaction_id='".$transaction_id."' AND i.spend_transaction_id=t.transaction_id AND i.address_id=a.address_id ORDER BY a.address ASC LIMIT 1;";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		return mysql_fetch_array($r);
	}
	else return false;
}

function rounds_complete_html($game, $max_round_id, $limit) {
	$html = "";
	$q = "SELECT * FROM cached_rounds r, nations n WHERE r.game_id='".$game['game_id']."' AND r.winning_nation_id=n.nation_id AND r.round_id <= ".$max_round_id." ORDER BY r.round_id DESC LIMIT ".$limit.";";
	$r = run_query($q);
	$last_round_shown = 0;
	while ($cached_round = mysql_fetch_array($r)) {
		$html .= "<div class=\"row bordered_row\">";
		$html .= "<div class=\"col-sm-2\"><a href=\"/explorer/rounds/".$cached_round['round_id']."\">Round #".$cached_round['round_id']."</a></div>";
		$html .= "<div class=\"col-sm-7\">".$cached_round['name']." wins with ".format_bignum($cached_round['winning_score']/pow(10,8))." votes (".round(100*$cached_round['winning_score']/$cached_round['score_sum'], 2)."%)</div>";
		$html .= "<div class=\"col-sm-3\">".format_bignum($cached_round['score_sum']/pow(10,8))." votes cast</div>";
		$html .= "</div>\n";
		$last_round_shown = $cached_round['round_id'];
	}
	
	$returnvals[0] = $last_round_shown;
	$returnvals[1] = $html;
	
	return $returnvals;
}

function my_bets($game, $user) {
	$html = "";
	$q = "SELECT * FROM webwallet_transactions WHERE transaction_desc='bet' AND game_id='".$game['game_id']."' AND from_user_id='".$user['user_id']."' GROUP BY bet_round_id ORDER BY bet_round_id ASC;";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$last_block_id = last_block_id($user['game_id']);
		$current_round = block_to_round($last_block_id+1);
		
		$html .= "<h2>You've placed bets on ".mysql_numrows($r)." round";
		if (mysql_numrows($r) != 1) $html .= "s";
		$html .= ".</h2>\n";
		$html .= "<div style=\"border: 1px solid #bbb; border-top: 0px;\">";
		while ($bet_round = mysql_fetch_array($r)) {
			$html .= "<div class=\"row bordered_row\" style=\"margin: 0px; padding: 6px;\">";
			$disp_html = "";
			$qq = "SELECT a.*, n.*, SUM(i.amount) FROM webwallet_transactions t JOIN transaction_IOs i ON i.create_transaction_id=t.transaction_id JOIN addresses a ON i.address_id=a.address_id LEFT JOIN nations n ON a.bet_nation_id=n.nation_id WHERE t.game_id='".$game['game_id']."' AND t.from_user_id='".$user['user_id']."' AND t.bet_round_id='".$bet_round['bet_round_id']."' AND a.bet_round_id > 0 GROUP BY a.address_id ORDER BY SUM(i.amount) DESC;";
			$rr = run_query($qq);
			$coins_bet_for_round = 0;
			while ($nation_bet = mysql_fetch_array($rr)) {
				if ($nation_bet['name'] == "") $nation_bet['name'] = "No Winner";
				$coins_bet_for_round += $nation_bet['SUM(i.amount)'];
				$disp_html .= "<div class=\"row\">";
				$disp_html .= "<div class=\"col-md-5\">".number_format($nation_bet['SUM(i.amount)']/pow(10,8), 2)." coins towards ".$nation_bet['name']."</div>";
				$disp_html .= "<div class=\"col-md-5\"><a href=\"/explorer/addresses/".$nation_bet['address']."\">".$nation_bet['address']."</a></div>";
				$disp_html .= "</div>\n";
			}
			if ($bet_round['bet_round_id'] >= $current_round) {
				$html .= "You made bets totalling ".number_format($coins_bet_for_round/pow(10,8), 2)." coins on round ".$bet_round['bet_round_id'].".";
			}
			else {
				$qq = "SELECT SUM(i.amount) FROM webwallet_transactions t JOIN transaction_IOs i ON t.transaction_id=i.create_transaction_id JOIN addresses a ON i.address_id=a.address_id WHERE t.block_id='".($bet_round['bet_round_id']*get_site_constant('round_length'))."' AND t.transaction_desc='betbase' AND a.user_id='".$user['user_id']."';";
				$rr = run_query($qq);
				$amount_won = mysql_fetch_row($rr);
				$amount_won = $amount_won[0];
				if ($amount_won > 0) {
					$html .= "You bet ".number_format($coins_bet_for_round/pow(10,8), 2)." coins and won ".number_format($amount_won/pow(10,8), 2)." back for a ";
					if (round(($amount_won-$coins_bet_for_round)/pow(10,8), 2) >= 0) $html .= "profit of <font class=\"greentext\">+".number_format(round(($amount_won-$coins_bet_for_round)/pow(10,8), 2), 2)."</font> coins.";
					else $html .= "loss of <font class=\"redtext\">".number_format(($coins_bet_for_round-$amount_won)/pow(10,8), 2)."</font> coins.";
				}
			}
			$html .= "&nbsp;&nbsp; <a href=\"\" onclick=\"$('#my_bets_details_".$bet_round['bet_round_id']."').toggle('fast'); return false;\">Details</a><br/>\n";
			$html .= "<div id=\"my_bets_details_".$bet_round['bet_round_id']."\" style=\"display: none;\">".$disp_html."</div>\n";
			$html .= "</div>\n";
		}
		$html .= "</div>\n";
	}
	return $html;
}
?>