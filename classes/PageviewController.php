<?php
class PageviewController {
	public $app;
	
	function __construct($app) {
		$this->app = $app;
	}
	
	function do_pageview() {
		$pageview = insert_pageview();
		$viewer_id = $pageview[1];
		$q = "SELECT * FROM viewers WHERE viewer_id='".$viewer_id."';";
		$r = $this->app->run_query($q);
		$viewer = $r->fetch();
		return $viewer;
	}

	function get_viewer($viewer_id) {
		$q = "SELECT * FROM viewers WHERE viewer_id='".$viewer_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) {
			return $r->fetch();
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
		$q = "SELECT * FROM viewer_identifiers WHERE type='ip' AND identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).";";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$identifier = $r->fetch();
			return $identifier;
		}
		else return -1;
	}
	function cookie_identifier() {
		if (isset($_COOKIE["uuu"])) {
			$q = "SELECT * FROM viewer_identifiers WHERE type='cookie' AND identifier=".$this->app->quote_escape($_COOKIE["uuu"]).";";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$identifier = $r->fetch();
				return $identifier;
			}
			else return -1;
		}
		else return -1;
	}
	function insert_pageview($thisuser) {
		$ip_identifier = $this->ip_identifier();
		$cookie_identifier = $this->cookie_identifier();
		
		if ($ip_identifier != -1 && $cookie_identifier != -1) {}
		else if ($ip_identifier == -1 && $cookie_identifier == -1) {
			$q = "INSERT INTO viewers SET time_created='".time()."';";
			$r = $this->app->run_query($q);
			$viewer_id = $this->app->last_insert_id();
			$q = "INSERT INTO viewer_identifiers SET type='ip', identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).", viewer_id='".$viewer_id."';";
			$r = $this->app->run_query($q);
			$rando = $this->app->random_string(64);
			$q = "INSERT INTO viewer_identifiers SET type='cookie', identifier='$rando', viewer_id='".$viewer_id."';";
			$r = $this->app->run_query($q);
			setcookie("uuu", $rando, time()+(10 * 365 * 24 * 60 * 60));
		}
		else if ($ip_identifier == -1) {
			$q = "INSERT INTO viewer_identifiers SET type='ip', identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).", viewer_id='".$cookie_identifier['viewer_id']."';";
			$r = $this->app->run_query($q);
			$ip_id = $this->app->last_insert_id();
			$q = "SELECT * FROM viewer_identifiers WHERE identifier_id='".$ip_id."';";
			$r = $this->app->run_query($q);
			$ip_identifier = $r->fetch();
		}
		else if ($cookie_identifier == -1) {
			$rando = $this->app->random_string(64);
			setcookie("uuu", $rando, time()+(10 * 365 * 24 * 60 * 60));
			$q = "INSERT INTO viewer_identifiers SET viewer_id='".$ip_identifier['viewer_id']."', type='cookie', identifier=".$this->app->quote_escape($rando).";";
			$r = $this->app->run_query($q);
			$cookie_id = $this->app->last_insert_id();
			$q = "SELECT * FROM viewer_identifiers WHERE identifier_id='".$cookie_id."';";
			$r = $this->app->run_query($q);
			$cookie_identifier = $r->fetch();
		}
		
		$refer_url = "";
		if (!empty($_SERVER['HTTP_REFERER'])) {
			$domain = $_SERVER['HTTP_REFERER'];
			if (substr($domain, 0, 8) == "https://") $domain = substr($domain, 8, strlen($domain)-8);
			if (substr($domain, 0, 7) == "http://") $domain = substr($domain, 7, strlen($domain)-7);
			if (substr($domain, 0, 4) == "www.") $domain = substr($domain, 4, strlen($domain)-4);
			$domain = str_replace("?", "/", $domain);
			$domain = explode("/", $domain);
			$domain = $domain[0];
			$domain = explode(".", $domain);
			$domain = $domain[count($domain)-2].".".$domain[count($domain)-1];
			
			$refer_url = $_SERVER['HTTP_REFERER'];
		}
		
		$user_agent = "";
		if (!empty($_SERVER['HTTP_USER_AGENT'])) $user_agent = $_SERVER['HTTP_USER_AGENT'];
		$q = "SELECT * FROM browserstrings WHERE viewer_id='".$cookie_identifier['viewer_id']."' AND browser_string=".$this->app->quote_escape($user_agent).";";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$browserstring = $r->fetch();
			$browserstring_id = $browserstring['browserstring_id'];
		}
		else {
			$ub = $this->getBrowser();
			$b_searchname = strtolower(str_replace(" ", "_", $ub['name']));
			$q = "SELECT browser_id FROM browsers WHERE name=".$this->app->quote_escape($b_searchname).";";
			$r = $this->app->run_query($q);
			if ($r->rowCount() == 1) {
				$brow = $r->fetch(PDO::FETCH_NUM);
				$browser_id = $brow[0];
			}
			else {
				$q = "INSERT INTO browsers SET name=".$this->app->quote_escape($b_searchname).";";
				$r = $this->app->run_query($q);
				$browser_id = $this->app->last_insert_id();
			}
			
			$q = "INSERT INTO browserstrings SET viewer_id='".$cookie_identifier['viewer_id']."', browser_string=".$this->app->quote_escape($_SERVER['HTTP_USER_AGENT']).", browser_id='".$browser_id."', ";
			$q .= "name=".$this->app->quote_escape($ub['name']).", version=".$this->app->quote_escape($ub['version']).", platform=".$this->app->quote_escape($ub['platform']).", pattern=".$this->app->quote_escape($ub['pattern']).";";
			$r = $this->app->run_query($q);
			$browserstring_id = $this->app->last_insert_id();
		}
		
		if (strlen($_SERVER['REQUEST_URI']) > 255) $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 0, 255);
		$page_url = $this->app->quote_escape($_SERVER['REQUEST_URI']);
		$q = "SELECT page_url_id FROM page_urls WHERE url=".$page_url.";";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$pv_page_id = $r->fetch(PDO::FETCH_NUM);
			$pv_page_id = $pv_page_id[0];
		}
		else {
			$q = "INSERT INTO page_urls SET url=".$page_url.";";
			$r = $this->app->run_query($q);
			$pv_page_id = $this->app->last_insert_id();
		}
		$q = "INSERT INTO pageviews SET ";
		if ($thisuser) $q .= "user_id='".$thisuser->db_user['user_id']."', ";
		$q .= "browserstring_id='".$browserstring_id."', viewer_id='".$cookie_identifier['viewer_id']."', ip_id='".$ip_identifier['identifier_id']."', cookie_id='".$cookie_identifier['identifier_id']."', time='".time()."', pv_page_id='".$pv_page_id."', refer_url=".$this->app->quote_escape($refer_url).";";
		$r = $this->app->run_query($q);
		$pageview_id = $this->app->last_insert_id();
		
		$result[0] = $pageview_id;
		$result[1] = $cookie_identifier['viewer_id'];
		
		return $result[1];
	}
	function viewer2user_byIP($user_id) {
		$q = "SELECT * FROM pageviews P, viewer_identifiers V WHERE P.ip_id > 0 AND P.ip_id=V.identifier_id AND V.type='ip' AND P.ip_processed=0";
		if ($user_id != "all_users") $q .= " AND P.user_id='".$user_id."'";
		$q .= ";";
		$r = $this->app->run_query($q);
		while ($pv = $r->fetch()) {
			$qq = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$pv['viewer_id']."' AND to_id='".$pv['user_id']."';";
			$rr = $this->app->run_query($qq);
			if ($rr->rowCount() > 0) {}
			else {
				$qq = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$pv['viewer_id']."', to_id='".$pv['user_id']."';";
				$rr = $this->app->run_query($qq);
				echo "Connected viewer #".$pv['viewer_id']." to user account #".$pv['user_id']."<br/>\n";
			}
			$qq = "UPDATE pageviews SET ip_processed=1 WHERE pageview_id='".$pv['pageview_id']."';";
			$rr = $this->app->run_query($qq);
		}
	}
	function viewer2user_IPlookup() {
		$html  = "";
		$q = "SELECT viewer_id, user_id FROM viewer_identifiers I, pageviews P WHERE I.type='ip' AND I.identifier=P.ip_address GROUP BY viewer_id;";
		$r = $this->app->run_query($q);
		while ($pair = $r->fetch(PDO::FETCH_NUM)) {
			$qq = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$pair[0]."' AND to_id='".$pair[1]."';";
			$rr = $this->app->run_query($qq);
			if ($rr->rowCount() == 0) {
				$qq = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$pair[0]."', to_id='".$pair[1]."';";
				$rr = $this->app->run_query($qq);
				$html .= "Connected viewer #".$pair[0]." to user #".$pair[1]."<br/>\n";
			}
		}
		return $html;
	}
	function IsTorExitPoint(){
		if (gethostbyname($this->ReverseIPOctets($_SERVER['REMOTE_ADDR']).".".$_SERVER['SERVER_PORT'].".".$this->ReverseIPOctets($_SERVER['SERVER_ADDR']).".ip-port.exitlist.torproject.org")=="127.0.0.2") {
			return true;
		} else {
			return false;
		} 
	}
	function ReverseIPOctets($inputip){
		$ipoc = explode(".",$inputip);
		return $ipoc[3].".".$ipoc[2].".".$ipoc[1].".".$ipoc[0];
	}
}
?>