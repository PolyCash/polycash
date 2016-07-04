<?php
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
        $string .= $characters[mt_rand(0, strlen($characters))];
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
	
function account_coin_value($user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE user_id='".$user['user_id']."' AND currency_mode='".$user['currency_mode']."';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0]/(pow(10, 8));
}

function immature_balance($user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE user_id='".$user['user_id']."' AND currency_mode='".$user['currency_mode']."' AND block_id > ".(last_block_id($user['currency_mode'])-get_site_constant("maturity"))." AND amount > 0 AND transaction_desc != 'giveaway';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0]/(pow(10, 8));
}

function mature_balance($user) {
	$account_value = account_coin_value($user);
	$immature_balance = immature_balance($user);
	
	return ($account_value - $immature_balance)/(pow(10, 8));
}

function current_block($currency_mode) {
	$q = "SELECT * FROM blocks WHERE currency_mode='".$currency_mode."' ORDER BY block_id DESC LIMIT 1;";
	$r = run_query($q);
	$block = mysql_fetch_array($r);
	return $block;
}

function last_block_id($currency_mode) {
	$block = current_block($currency_mode);
	return $block['block_id'];
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
function round_voting_stats($round_id) {
	$q = "SELECT SUM(t.amount) AS voting_sum, FLOOR(SUM(t.amount)*n.cached_force_multiplier) AS voting_score, n.* FROM webwallet_transactions t, nations n WHERE t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." AND t.nation_id=n.nation_id AND t.amount > 0 GROUP BY t.nation_id ORDER BY voting_score DESC, n.cached_force_multiplier DESC, n.nation_id ASC;";
	return run_query($q);
}
function round_voting_stats_all($voting_round) {
	$sumVotes = 0;
	$round_voting_stats = round_voting_stats($voting_round);
	$stats_all = "";
	$counter = 0;
	$nation_id_csv = "";
	$nation_id_to_rank = "";
	
	while ($stat = mysql_fetch_array($round_voting_stats)) {
		$stats_all[$counter] = $stat;
		$nation_id_csv .= $stat['nation_id'].",";
		$sumVotes += $stat['voting_sum'];
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	if ($nation_id_csv != "") $nation_id_csv = substr($nation_id_csv, 0, strlen($nation_id_csv)-1);
	
	$q = "SELECT * FROM nations";
	if ($nation_id_csv != "") $q .= " WHERE nation_id NOT IN (".$nation_id_csv.")";
	$q .= " ORDER BY cached_force_multiplier DESC, nation_id ASC;";
	$r = run_query($q);
	
	while ($stat = mysql_fetch_array($r)) {
		$stat['voting_score'] = 0;
		$stat['voting_sum'] = 0;
		$stats_all[$counter] = $stat;
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	
	$output_arr[0] = $sumVotes;
	$output_arr[1] = floor($sumVotes/2);
	$output_arr[2] = $stats_all;
	$output_arr[3] = $nation_id_to_rank;
	
	return $output_arr;
}
function current_round_table($current_round, $user, $show_vote_links, $show_intro_text) {
	$html = "<div style=\"padding: 5px;\">";
	
	if ($show_intro_text) $html .= "<b>Current Rankings - Round #".$current_round.". Approximately ".(get_site_constant('round_length')-last_block_id('beta')%get_site_constant('round_length'))*get_site_constant('minutes_per_block')." minutes left.</b><br/>";
	
	$round_stats = round_voting_stats_all($current_round);
	$totalVoteSum = $round_stats[0];
	$maxVoteSum = $round_stats[1];
	$round_stats = $round_stats[2];
	$winner_nation_id = FALSE;
	
	$html .= "<div class=\"row bottomborder\">";
	$html .= "<div class=\"col-sm-3\">Empire</div>\n";
	$html .= "<div class=\"col-sm-1\">Multiplier</div>\n";
	$html .= "<div class=\"col-sm-2\">Coins Voted</div>\n";
	$html .= "<div class=\"col-sm-1\">%</div>\n";
	$html .= "<div class=\"col-sm-2\">Score</div>\n";
	$html .= "<div class=\"col-sm-2\">Your Votes</div>";
	$html .= "<div class=\"col-sm-1\">&nbsp;</div>";
	$html .=  "</div>";
	
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i]['voting_sum'] <= $maxVoteSum && $round_stats[$i]['voting_score'] > 0) $winner_nation_id = $round_stats[$i]['nation_id'];
		$html .= "<div class=\"row";
		if ($round_stats[$i]['voting_sum'] > $maxVoteSum) $html .=  " redtext";
		else if ($winner_nation_id == $round_stats[$i]['nation_id']) $html .=  " greentext";
		$html .= "\">";
		$html .= "<div class=\"col-sm-3\">".($i+1).". ".$round_stats[$i]['name']."</div>\n";
		$html .= "<div class=\"col-sm-1\">&times;".round($round_stats[$i]['cached_force_multiplier'], 3)."</div>\n";
		$html .= "<div class=\"col-sm-2\">".number_format(($round_stats[$i]['voting_sum'])/pow(10,8), 3)." EMP</div>\n";
		$html .= "<div class=\"col-sm-1\">".number_format(ceil(100*100*$round_stats[$i]['voting_sum']/$totalVoteSum)/100, 2)."%</div>\n";
		$html .= "<div class=\"col-sm-2\">".number_format(($round_stats[$i]['voting_score'])/pow(10,8))." points</div>\n";
		$html .= "<div class=\"col-sm-2\">";
		if ($user) {
			$qq = "SELECT SUM(amount) FROM webwallet_transactions WHERE currency_mode='beta' AND nation_id='".$round_stats[$i]['nation_id']."' AND user_id='".$user['user_id']."' AND block_id >= ".(($current_round-1)*get_site_constant('round_length')+1)." AND block_id <= ".($current_round*get_site_constant('round_length')-1).";";
			$rr = run_query($qq);
			$user_coinvotes = mysql_fetch_row($rr);
			$user_coinvotes = $user_coinvotes[0]/pow(10,8);
			$html .=  number_format($user_coinvotes, 3)." EMP";
		}
		$html .= "</div>";
		if ($show_vote_links) $html .= "<div class=\"col-sm-1\"><a href=\"\" onclick=\"start_vote(".$round_stats[$i]['nation_id'].");return false;\">Vote</a></div>";
		$html .=  "</div>";
	}
	$html .= "</div>";
	return $html;
}
function performance_history($user, $from_round_id, $to_round_id) {
	$html = "";
	$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
	$r = run_query($q);
	while ($round = mysql_fetch_array($r)) {
		$first_voting_block_id = ($round['round_id']-1)*get_site_constant('round_length')+1;
		$last_voting_block_id = $first_voting_block_id+get_site_constant('round_length')-1;
		$vote_sum = 0;
		$details_html = "";
		
		$html .= '<div class="row" style="font-size: 13px;">';
		$html .= '<div class="col-sm-1">Round&nbsp;#'.$round['round_id'].'</div>';
		$html .= '<div class="col-sm-4">';
		if ($round['name'] != "") $html .= $round['name']." won with ".number_format($round['winning_vote_sum']/pow(10,8), 3)." EMP";
		else $html .= "No winner";
		$html .= '</div>';
		
		$default_win_text = "You didn't vote for the winning empire.";
		$win_text = $default_win_text;
		$qq = "SELECT COUNT(*), SUM(t.amount), n.* FROM webwallet_transactions t, nations n WHERE t.block_id >= ".$first_voting_block_id." AND t.block_id <= ".$last_voting_block_id." AND t.user_id='".$user['user_id']."' AND t.nation_id=n.nation_id GROUP BY n.nation_id;";
		$rr = run_query($qq);
		if (mysql_numrows($rr) > 0) {
			while ($nation_votes = mysql_fetch_array($rr)) {
				$vote_sum += $nation_votes['SUM(t.amount)'];
				$details_html .= '<font class="';
				if ($nation_votes['nation_id'] == $round['winning_nation_id']) {
					$win_text = "You correctly voted ".round($nation_votes['SUM(t.amount)']/pow(10,8), 3)." EMP";
					$details_html .= 'greentext';
				}
				else $details_html .= 'redtext';
				$details_html .= '">You had '.$nation_votes['COUNT(*)']." vote";
				if ($nation_votes['COUNT(*)'] != 1) $details_html .= "s";
				$details_html .= " totalling ".round($nation_votes['SUM(t.amount)']/pow(10,8), 3)." EMP for ".$nation_votes['name'];
				$details_html .= '</font><br/>';
			}
		}
		else $details_html .= "You didn't cast any votes.";
		
		$html .= '<div class="col-sm-5">';
		$html .= $win_text;
		$html .= ' <a href="" onclick="$(\'#win_details_'.$round['round_id'].'\').toggle(\'fast\'); return false;">Details</a>';
		$html .= '<div id="win_details_'.$round['round_id'].'" style="margin: 4px 0px; padding: 4px; border-radius: 5px; border: 1px solid #bbb; display: none;">';
		$html .= $details_html;
		$html .= '</div>';
		$html .= '</div>';
		
		$html .= '<div class="col-sm-2">';
		$qq = "SELECT SUM(amount) FROM webwallet_transactions WHERE block_id='".$round['payout_block_id']."' AND user_id='".$user['user_id']."' AND transaction_desc='votebase';";
		$rr = run_query($qq);
		$win_amount = mysql_fetch_row($rr);
		$win_amount = $win_amount[0]/pow(10,8);
		$html .= '<font class="';
		if ($win_amount > 0) $html .= 'greentext';
		else $html .= 'redtext';
		$html .= '">+'.number_format($win_amount, 3).' EMP</font>';
		$html .= '</div>';
		
		$html .= "</div>\n";
	}
	return $html;
}
function last_voting_transaction_id() {
	$q = "SELECT transaction_id FROM webwallet_transactions WHERE nation_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
	$r = run_query($q);
	$r = mysql_fetch_row($r);
	return $r[0];
}
function wallet_text_stats($thisuser, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance) {
	$html = "Last block completed: #".$last_block_id.", currently mining #".($last_block_id+1)."<br/>\n";
	$html .= "Current votes count towards block ".$block_within_round."/".get_site_constant('round_length')." in round #".$current_round."<br/>\n";
	$html .= "Locked funds: <font class=\"redtext\">".number_format($immature_balance, 3)."</font> EmpireCoins";
	if ($immature_balance > 0) $html .= " <a href=\"\" onclick=\"$('#lockedfunds_details').toggle('fast'); return false;\">Details</a>";
	$html .= "<br/>\n";
	$html .= "Available funds: <font class=\"greentext\">".number_format(floor($mature_balance*1000)/1000, 3)."</font> EmpireCoins<br/>\n";
	
	if ($immature_balance > 0) {
		$q = "SELECT * FROM webwallet_transactions t LEFT JOIN nations n ON t.nation_id=n.nation_id WHERE t.amount > 0 AND t.user_id='".$thisuser['user_id']."' AND t.currency_mode='".$thisuser['currency_mode']."' AND t.block_id > ".(last_block_id($thisuser['currency_mode']) - get_site_constant('maturity'))." AND t.transaction_desc != 'giveaway' ORDER BY t.block_id ASC, t.transaction_id ASC;";
		$r = run_query($q);
		
		$html .= "<div style='display: none; border: 1px solid #ccc; padding: 8px; border-radius: 8px; margin-top: 8px;' id='lockedfunds_details'>";
		while ($next_transaction = mysql_fetch_array($r)) {
			$avail_block = get_site_constant('maturity') + $next_transaction['block_id'] + 1;
			$minutes_to_avail = ($avail_block - $last_block_id - 1)*get_site_constant("minutes_per_block");
			
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
			$html .= "<font class=\"greentext\">".round($next_transaction['amount']/(pow(10, 8)), 3)."</font> ";
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "coins in block ".$next_transaction['block_id'].". Coins";
			else $html .= "coins received in block #".$next_transaction['block_id'];
			$html .= " can be spent in block #".$avail_block.". (Approximately ".$minutes_to_avail." minutes). ";
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
		<div class="col-xs-6 greentext">'.number_format(floor($mature_balance*1000)/1000, 3).' EMP</div>
	</div>	';
	return $html;
}
function vote_nation_details($nation, $rank, $voting_sum, $totalVoteSum) {
	$html .= '
	<div class="row">
		<div class="col-xs-6">Force multiplier:</div>
		<div class="col-xs-6">&times;'.number_format($nation['cached_force_multiplier'], 5).'</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Rank:</div>
		<div class="col-xs-6">'.$rank.date("S", strtotime("1/".$rank."/2015")).'</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Votes:</div>
		<div class="col-xs-5">'.number_format($voting_sum/pow(10,8), 3).' EMP</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Voting&nbsp;percentage:</div>
		<div class="col-xs-5">'.(ceil(100*10000*$voting_sum/$totalVoteSum)/10000).'%</div>
	</div>';
	return $html;
}
?>