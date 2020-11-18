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
					
					$site_domain = self::$settings->site_domain;
					
					$http_prefix = "http";
					if (strlen($site_domain) >= strlen($http_prefix) && substr($site_domain, 0, strlen($http_prefix)) == $http_prefix) die('Please don\'t include a prefix like "http://" in your site domain');
					
					$base_url .= $site_domain;
					
					if (empty(self::$settings->server)) die('Please add this to your src/config/config.json: "server": "Apache"');
					
					if (self::$settings->server != "Apache" && !empty($_SERVER['SERVER_PORT'])) $base_url .= ":".$_SERVER['SERVER_PORT'];
					
					self::$settings->base_url = $base_url;
				}
				else die("Failed to read the config file.");
			}
			else {
				$example_config_path = self::$srcPath."/config/example_config.json";
				if ($example_config_fh = fopen($example_config_path, 'r')) {
					if ($raw_example_config = fread($example_config_fh, filesize($example_config_path))) {
						if ($example_config_obj = json_decode($raw_example_config)) {
							if (!empty($_SERVER['SERVER_SOFTWARE'])) {
								$server_parts = explode("/", $_SERVER['SERVER_SOFTWARE']);
								$server = $server_parts[0];
								
								if ($server != 'Mongoose') $server = 'Apache';
								
								$new_config = (array) $example_config_obj;
								$new_config['server'] = $server;
								
								if ($server == "Mongoose") {
									$new_config['sqlite_db'] = "polycash_sqlite.db";
									$new_config['site_domain'] = "127.0.0.1";
									$new_config['use_https'] = true;
                                    $new_config['only_user_username'] = "admin";
                                    $new_config['only_user_password'] = "admin";
								}
								
								if ($new_config_fh = fopen($config_path, 'w')) {
									if (fwrite($new_config_fh, json_encode($new_config, JSON_PRETTY_PRINT))) {
										?>
										Your configuration file was created successfully.<br/>
										<script type="text/javascript">
										window.onload = function() {
											setTimeout(function() {
												window.location = '/install.php?key=<?php echo $new_config["operator_key"]; ?>';
											}, 1000);
										};
										</script>
										<?php
										die();
									}
									else die("Failed to write ".$config_path."\n");
								}
								else die("Failed to create ".$config_path."\n");
							}
							else die("Please use a browser to install PolyCash.\n");
						}
						else die("Failed to parse JSON in ".$example_config_path."\n");
					}
					else die("Failed to read ".$example_config_path."\n");
				}
				else die("Failed to open ".$example_config_path."\n");
			}
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
	
	public static function arrayToMapOnKey(&$array, $key, $many_per_key=false) {
		$map = [];
		
		foreach ($array as &$element) {
			$key_val = null;
			
			if (isset($element->$key)) $key_val = $element->$key;
			else if (array_key_exists($key, $element)) $key_val = $element[$key];
			
			if ($many_per_key) {
				if (!array_key_exists($key_val, $map)) $map[$key_val] = [];
				array_push($map[$key_val], (object) $element);
			}
			else $map[$key_val] = (object) $element;
		}
		
		return $map;
	}
}
?>