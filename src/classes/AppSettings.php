<?php
class AppSettings {
	private static $settings;
	private static $isDev = false;
	private static $srcPath;
	private static $publicPath;
	private static $hasLoaded = false;
	private static $jsDependencies = [];
	
	public static function load() {
		if (!self::$hasLoaded) {
			self::$hasLoaded = true;
			self::$srcPath = realpath(dirname(dirname(__FILE__)));
			self::$publicPath = realpath(dirname(dirname(dirname(__FILE__))))."/public";
			$config_path = self::$srcPath."/config/config.json";
			
			if (is_file($config_path)) {
				$config_fh = fopen($config_path, 'r');
				if ($config_str = fread($config_fh, filesize($config_path))) {
					self::$settings = json_decode($config_str) or die("Failed to parse the config file.");
					
					$base_url = "http://";
					if (self::$settings->use_https) $base_url = "https://";
					
					if (!empty(self::$settings->dev_site_domain) && $_SERVER['SERVER_NAME'] == self::$settings->dev_site_domain) {
						self::setIsDev(true);
						$site_domain = self::$settings->dev_site_domain;
						self::$settings->mysql_database = self::$settings->dev_database;
					}
					else {
						self::setIsDev(false);
						$site_domain = self::$settings->site_domain;
						self::$settings->mysql_database = self::$settings->database;
					}
					
					$http_prefix = "http";
					if (strlen($site_domain) >= strlen($http_prefix) && substr($site_domain, 0, strlen($http_prefix)) == $http_prefix) die('Please don\'t include a prefix like "http://" in your site domain');
					
					$base_url .= $site_domain;
					
					self::$settings->base_url = $base_url;
				}
				else die("Failed to read the config file.");
			}
			else die("Please create the file src/config/config.json");
		}
	}
	
	public static function getParam($paramName) {
		if (isset(self::$settings->$paramName)) return self::$settings->$paramName;
		else return null;
	}
	
	public static function addJsDependency($jsFile) {
		self::$jsDependencies[$jsFile] = true;
	}
	
	public static function checkJsDependency($jsFile) {
		if (isset(self::$jsDependencies[$jsFile]) && self::$jsDependencies[$jsFile]) return true;
		else return false;
	}
	
	public static function getIsDev() {
		return self::$isDev;
	}
	
	private static function setIsDev($isDev) {
		self::$isDev = $isDev;
	}
	
	public static function srcPath() {
		return self::$srcPath;
	}
	
	public static function publicPath() {
		return self::$publicPath;
	}
	
	public static function runningFromCommandline() {
		if (PHP_SAPI == "cli") return true;
		else return false;
	}
	
	public static function standardHash(&$string) {
		return hash("sha256", $string);
	}
	
	public static function arrayToMapOnKey(&$array, $key) {
		$map = [];
		
		foreach ($array as &$element) {
			if (isset($element->$key)) $map[$element->$key] = $element;
			else if (array_key_exists($key, $element)) $map[$element[$key]] = (object) $element;
		}
		
		return $map;
	}
}
?>