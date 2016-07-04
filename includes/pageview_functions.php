<?php

function do_pageview() {
	$pageview = insert_pageview();
	$viewer_id = $pageview[1];
	$q = "SELECT * FROM viewers WHERE viewer_id='".$viewer_id."';";
	$r = run_query($q);
	$viewer = mysql_fetch_array($r);
	return $viewer;
}

function get_viewer($viewer_id) {
	$q = "SELECT * FROM viewers WHERE viewer_id='".$viewer_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		return mysql_fetch_array($r);
	}
	else return NULL;
}

function getBrowser() {
	$u_agent = $_SERVER['HTTP_USER_AGENT'];
	$bname = 'Unknown';
	$platform = 'Unknown';
	$version= "";
	
	//First get the platform?
	if (preg_match('/linux/i', $u_agent)) {
		$platform = 'linux';
	}
	elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
		$platform = 'mac';
	}
	elseif (preg_match('/windows|win32/i', $u_agent)) {
		$platform = 'windows';
	}
	
	// Next get the name of the useragent yes seperately and for good reason
	if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) {
		$bname = 'Internet Explorer';
		$ub = "MSIE";
	}
	elseif(preg_match('/Firefox/i',$u_agent)) {
		$bname = 'Mozilla Firefox';
		$ub = "Firefox";
	}
	elseif(preg_match('/Chrome/i',$u_agent)) {
		$bname = 'Google Chrome';
		$ub = "Chrome";
	}
	elseif(preg_match('/Safari/i',$u_agent)) {
		$bname = 'Apple Safari';
		$ub = "Safari";
	}
	elseif(preg_match('/Opera/i',$u_agent)) {
		$bname = 'Opera';
		$ub = "Opera";
	}
	elseif(preg_match('/Netscape/i',$u_agent)) {
		$bname = 'Netscape';
		$ub = "Netscape";
	}
	// finally get the correct version number
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) .
	')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if (!preg_match_all($pattern, $u_agent, $matches)) {
		// we have no matching number just continue
	}
	
	$version= $matches['version'][0];
	
	if ($version==null || $version=="") {$version="?";}
	
	return array(
		'userAgent'	=> $u_agent,
		'name'		=> $bname,
		'version'	=> $version,
		'platform'	=> $platform,
		'pattern'	=> $pattern
	);
}

function ip_identifier() {
	$q = "SELECT * FROM viewer_identifiers WHERE type='ip' AND identifier='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$identifier = mysql_fetch_array($r);
		return $identifier;
	}
	else return -1;
}
function cookie_identifier() {
	if (isset($_COOKIE["uuu"])) {
		$q = "SELECT * FROM viewer_identifiers WHERE type='cookie' AND identifier='".mysql_real_escape_string($_COOKIE["uuu"])."';";
		$r = run_query($q);
		if (mysql_numrows($r) > 0) {
			$identifier = mysql_fetch_array($r);
			return $identifier;
		}
		else return -1;
	}
	else return -1;
}
function insert_pageview($thisuser) {
	$ip_identifier = ip_identifier();
	$cookie_identifier = cookie_identifier();
	
	if ($ip_identifier != -1 && $cookie_identifier != -1) {}
	else if ($ip_identifier == -1 && $cookie_identifier == -1) {
		$q = "INSERT INTO viewers SET time_created='".time()."';";
		$r = run_query($q);
		$viewer_id = mysql_insert_id();
		$q = "INSERT INTO viewer_identifiers SET type='ip', identifier='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."', viewer_id='".$viewer_id."';";
		$r = run_query($q);
		$rando = random_string(64);
		$q = "INSERT INTO viewer_identifiers SET type='cookie', identifier='$rando', viewer_id='".$viewer_id."';";
		$r = run_query($q);
		setcookie("uuu", $rando, time()+(10 * 365 * 24 * 60 * 60));
	}
	else if ($ip_identifier == -1) {
		$q = "INSERT INTO viewer_identifiers SET type='ip', identifier='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."', viewer_id='".$cookie_identifier['viewer_id']."';";
		$r = run_query($q);
		$ip_id = mysql_insert_id();
		$q = "SELECT * FROM viewer_identifiers WHERE identifier_id='".$ip_id."';";
		$r = run_query($q);
		$ip_identifier = mysql_fetch_array($r);
	}
	else if ($cookie_identifier == -1) {
		$rando = random_string(64);
		setcookie("uuu", $rando, time()+(10 * 365 * 24 * 60 * 60));
		$q = "INSERT INTO viewer_identifiers SET viewer_id='".$ip_identifier['viewer_id']."', type='cookie', identifier='".mysql_real_escape_string($rando)."';";
		$r = run_query($q);
		$cookie_id = mysql_insert_id();
		$q = "SELECT * FROM viewer_identifiers WHERE identifier_id='".$cookie_id."';";
		$r = run_query($q);
		$cookie_identifier = mysql_fetch_array($r);
	}
	
	$domain = $_SERVER['HTTP_REFERER'];
	if (substr($domain, 0, 8) == "https://") $domain = substr($domain, 8, strlen($domain)-8);
	if (substr($domain, 0, 7) == "http://") $domain = substr($domain, 7, strlen($domain)-7);
	if (substr($domain, 0, 4) == "www.") $domain = substr($domain, 4, strlen($domain)-4);
	$domain = str_replace("?", "/", $domain);
	$domain = explode("/", $domain);
	$domain = $domain[0];
	$domain = explode(".", $domain);
	$domain = $domain[count($domain)-2].".".$domain[count($domain)-1];
	
	$refer_url = "";
	if ($_SERVER['HTTP_REFERER'] != "") $refer_url = $_SERVER['HTTP_REFERER'];
	
	$q = "SELECT * FROM browserstrings WHERE viewer_id='".$cookie_identifier['viewer_id']."' AND browser_string='".mysql_real_escape_string($_SERVER['HTTP_USER_AGENT'])."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$browserstring = mysql_fetch_array($r);
		$browserstring_id = $browserstring['browserstring_id'];
	}
	else {
		$ub = getBrowser();
		$b_searchname = strtolower(str_replace(" ", "_", $ub['name']));
		$q = "SELECT browser_id FROM browsers WHERE name='".mysql_real_escape_string($b_searchname)."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$brow = mysql_fetch_row($r);
			$browser_id = $brow[0];
		}
		else {
			$q = "INSERT INTO browsers SET name='".mysql_real_escape_string($b_searchname)."';";
			$r = run_query($q);
			$browser_id = mysql_insert_id();
		}
		
		if (IsTorExitPoint()) $browser_id = 1; // ID number for Tor
		
		$q = "INSERT INTO browserstrings SET viewer_id='".$cookie_identifier['viewer_id']."', browser_string='".mysql_real_escape_string($_SERVER['HTTP_USER_AGENT'])."', browser_id='$browser_id', ";
		$q .= "name='".mysql_real_escape_string($ub['name'])."', version='".mysql_real_escape_string($ub['version'])."', platform='".mysql_real_escape_string($ub['platform'])."', pattern='".mysql_real_escape_string($ub['pattern'])."';";
		$r = run_query($q);
		$browserstring_id = mysql_insert_id();
	}
	
	$page_url = mysql_real_escape_string($_SERVER['REQUEST_URI']);
	$q = "SELECT page_url_id FROM page_urls WHERE url='".$page_url."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$pv_page_id = mysql_fetch_row($r);
		$pv_page_id = $pv_page_id[0];
	}
	else {
		$q = "INSERT INTO page_urls SET url='".$page_url."';";
		$r = run_query($q);
		$pv_page_id = mysql_insert_id();
	}
	$q = "INSERT INTO pageviews SET ";
	if ($thisuser) $q .= "user_id='".$thisuser['user_id']."', ";
	$q .= "browserstring_id='".$browserstring_id."', viewer_id='".$cookie_identifier['viewer_id']."', ip_id='".$ip_identifier['identifier_id']."', cookie_id='".$cookie_identifier['identifier_id']."', time='".time()."', pv_page_id='".$pv_page_id."', refer_url='".mysql_real_escape_string($refer_url)."';";
	$r = run_query($q);
	$pageview_id = mysql_insert_id();
	
	if ($thisuser) {
		set_user_active($thisuser['user_id']);
	}
	$result[0] = $pageview_id;
	$result[1] = $cookie_identifier['viewer_id'];
	
	return $result;
}
function viewer2user_byIP($user_id) {
	$q = "SELECT * FROM pageviews P, viewer_identifiers V WHERE P.ip_id > 0 AND P.ip_id=V.identifier_id AND V.type='ip' AND P.ip_processed=0";
	if ($user_id != "all_users") $q .= " AND P.user_id='".$user_id."'";
	$q .= ";";
	$r = run_query($q);
	while ($pv = mysql_fetch_array($r)) {
		$qq = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$pv['viewer_id']."' AND to_id='".$pv['user_id']."';";
		$rr = run_query($qq);
		if (mysql_numrows($rr) > 0) {}
		else {
			$qq = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$pv['viewer_id']."', to_id='".$pv['user_id']."';";
			$rr = run_query($qq);
			echo "Connected viewer #".$pv['viewer_id']." to user account #".$pv['user_id']."<br/>\n";
		}
		$qq = "UPDATE pageviews SET ip_processed=1 WHERE pageview_id='".$pv['pageview_id']."';";
		$rr = run_query($qq);
	}
}
function viewer2user_IPlookup() {
	$html  = "";
	$q = "SELECT viewer_id, user_id FROM viewer_identifiers I, pageviews P WHERE I.type='ip' AND I.identifier=P.ip_address GROUP BY viewer_id;";
	$r = run_query($q);
	while ($pair = mysql_fetch_row($r)) {
		$qq = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$pair[0]."' AND to_id='".$pair[1]."';";
		$rr = run_query($qq);
		if (mysql_numrows($rr) == 0) {
			$qq = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$pair[0]."', to_id='".$pair[1]."';";
			$rr = run_query($qq);
			$html .= "Connected viewer #".$pair[0]." to user #".$pair[1]."<br/>\n";
		}
	}
	return $html;
}
function IsTorExitPoint(){
	if (gethostbyname(ReverseIPOctets($_SERVER['REMOTE_ADDR']).".".$_SERVER['SERVER_PORT'].".".ReverseIPOctets($_SERVER['SERVER_ADDR']).".ip-port.exitlist.torproject.org")=="127.0.0.2") {
		return true;
	} else {
		return false;
	} 
}
function ReverseIPOctets($inputip){
	$ipoc = explode(".",$inputip);
	return $ipoc[3].".".$ipoc[2].".".$ipoc[1].".".$ipoc[0];
}
?>