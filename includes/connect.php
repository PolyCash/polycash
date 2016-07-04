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
		$stat['voting_point'] = 0;
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
?>