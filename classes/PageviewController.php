<?php
class PageviewController {
	public $app;
	
	function __construct($app) {
		$this->app = $app;
	}
	function get_viewer($viewer_id) {
		$q = "SELECT * FROM viewers WHERE viewer_id='".$viewer_id."';";
		$r = $this->app->run_query($q);
		if ($r->rowCount() == 1) {
			return $r->fetch();
		}
		else return NULL;
	}
	function ip_identifier() {
		$q = "SELECT * FROM viewer_identifiers WHERE type='ip' AND identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).";";
		$r = $this->app->run_query($q);
		if ($r->rowCount() > 0) {
			$identifier = $r->fetch();
			return $identifier;
		}
		else return false;
	}
	function cookie_identifier() {
		if (isset($_COOKIE["cookie_str"])) {
			$q = "SELECT * FROM viewer_identifiers WHERE type='cookie' AND identifier=".$this->app->quote_escape($_COOKIE["cookie_str"]).";";
			$r = $this->app->run_query($q);
			if ($r->rowCount() > 0) {
				$identifier = $r->fetch();
				return $identifier;
			}
			else return false;
		}
		else return false;
	}
	function insert_pageview($thisuser) {
		$ip_identifier = $this->ip_identifier();
		$cookie_identifier = $this->cookie_identifier();
		$cookie_time_sec = 365*24*60*60;
		$cookie_length = 32;
		
		if ($ip_identifier && $cookie_identifier) {}
		else if (!$ip_identifier && !$cookie_identifier) {
			$q = "INSERT INTO viewers SET time_created='".time()."';";
			$r = $this->app->run_query($q);
			$viewer_id = $this->app->last_insert_id();
			$q = "INSERT INTO viewer_identifiers SET type='ip', identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).", viewer_id='".$viewer_id."';";
			$r = $this->app->run_query($q);
			$cookie_str = $this->app->random_string($cookie_length);
			$q = "INSERT INTO viewer_identifiers SET type='cookie', identifier=".$this->app->quote_escape($cookie_str).", viewer_id='".$viewer_id."';";
			$r = $this->app->run_query($q);
			setcookie("cookie_str", $cookie_str, time()+$cookie_time_sec);
		}
		else if (!$ip_identifier) {
			$q = "INSERT INTO viewer_identifiers SET type='ip', identifier=".$this->app->quote_escape($_SERVER['REMOTE_ADDR']).", viewer_id='".$cookie_identifier['viewer_id']."';";
			$r = $this->app->run_query($q);
			$ip_id = $this->app->last_insert_id();
			$q = "SELECT * FROM viewer_identifiers WHERE identifier_id='".$ip_id."';";
			$r = $this->app->run_query($q);
			$ip_identifier = $r->fetch();
		}
		else if (!$cookie_identifier) {
			$cookie_str = $this->app->random_string($cookie_length);
			setcookie("cookie_str", $cookie_str, time()+$cookie_time_sec);
			$q = "INSERT INTO viewer_identifiers SET viewer_id='".$ip_identifier['viewer_id']."', type='cookie', identifier=".$this->app->quote_escape($cookie_str).";";
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
		$q .= "viewer_id='".$cookie_identifier['viewer_id']."', ip_id='".$ip_identifier['identifier_id']."', cookie_id='".$cookie_identifier['identifier_id']."', time='".time()."', pv_page_id='".$pv_page_id."', refer_url=".$this->app->quote_escape($refer_url).";";
		$r = $this->app->run_query($q);
		$pageview_id = $this->app->last_insert_id();
		
		$result[0] = $pageview_id;
		$result[1] = $cookie_identifier['viewer_id'];
		
		return $result[1];
	}
}
?>