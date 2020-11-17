<?php
class PageviewController {
	public $app;
	
	function __construct($app) {
		$this->app = $app;
	}
	function get_viewer($viewer_id) {
		return $this->app->run_query("SELECT * FROM viewers WHERE viewer_id=:viewer_id;", ['viewer_id'=>$viewer_id])->fetch();
	}
	function ip_identifier() {
		return $this->app->run_query("SELECT * FROM viewer_identifiers WHERE type='ip' AND identifier=:identifier;", ['identifier'=>$_SERVER['REMOTE_ADDR']])->fetch();
	}
	function fetch_identifier_by_id($identifier_id) {
		return $this->app->run_query("SELECT * FROM viewer_identifiers WHERE identifier_id=:identifier_id;", ['identifier_id'=>$identifier_id])->fetch();
	}
	function insert_pageview($thisuser) {
		$ip_identifier = $this->ip_identifier();
		
		if (!$ip_identifier) {
			$this->app->run_insert_query("viewers", ['time_created'=>time()]);
			$viewer_id = $this->app->last_insert_id();
			
			$this->app->run_insert_query("viewer_identifiers", [
				'identifier' => $_SERVER['REMOTE_ADDR'],
				'type' => 'ip',
				'viewer_id' => $viewer_id
			]);
			
			$ip_identifier = $this->fetch_identifier_by_id($this->app->last_insert_id());
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
			$domain_parts = explode(".", $domain);
			$domain = $domain_parts[count($domain_parts)-1];
			if (count($domain_parts) > 1) $domain = $domain_parts[count($domain_parts)-2].".".$domain;
			
			$refer_url = $_SERVER['HTTP_REFERER'];
		}
		
		if (strlen($_SERVER['REQUEST_URI']) > 255) $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 0, 255);
		
		$pv_page_id = (int)($this->app->run_query("SELECT page_url_id FROM page_urls WHERE url=:url;", ['url'=>$_SERVER['REQUEST_URI']])->fetch(PDO::FETCH_NUM)[0]);
		
		if (!$pv_page_id) {
			$this->app->run_insert_query("page_urls", ['url'=>$_SERVER['REQUEST_URI']]);
			$pv_page_id = $this->app->last_insert_id();
		}
		
		if ($ip_identifier['viewer_id']) {
			$new_pv_params = [
				'viewer_id' => $ip_identifier['viewer_id'],
				'ip_id' => $ip_identifier['identifier_id'],
				'time' => time(),
				'pageview_date' => date("Y-m-d"),
				'pv_page_id' => $pv_page_id,
				'refer_url' => $refer_url
			];
			if ($thisuser) {
				$new_pv_params['user_id'] = $thisuser->db_user['user_id'];
			}
			$this->app->run_insert_query("pageviews", $new_pv_params);
			$pageview_id = $this->app->last_insert_id();
			
			$result[0] = $pageview_id;
			$result[1] = $ip_identifier['viewer_id'];
			return $result[1];
		}
		else return false;
	}
}
?>