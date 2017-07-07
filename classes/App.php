<?php
class App {
	public $dbh;
	
	public function __construct($dbh) {
		$this->dbh = $dbh;
		$this->has_db = false;
	}
	
	public function quote_escape($string) {
		return $this->dbh->quote($string);
	}
	
	public function set_db($db_name) {
		$this->dbh->query("USE ".$db_name) or $this->log_then_die("There was an error accessing the '".$db_name."' database.");
		$this->has_db = true;
	}
	
	public function last_insert_id() {
		return $this->dbh->lastInsertId();
	}
	
	public function run_query($query) {
		if ($GLOBALS['show_query_errors'] == TRUE) $result = $this->dbh->query($query) or $this->log_then_die("Error in query: ".$query.", ".$this->dbh->errorInfo()[2]);
		else $result = $this->dbh->query($query) or die("Error in query");
		return $result;
	}
	
	public function log_then_die($message) {
		$this->log_message($message);
		throw new Exception($message);
	}
	
	public function log_message($message) {
		if ($this->has_db) {
			$this->run_query("INSERT INTO log_messages SET message=".$this->quote_escape($message).";");
		}
	}
	
	public function utf8_clean($str) {
		return iconv('UTF-8', 'UTF-8//IGNORE', $str);
	}

	public function make_alphanumeric($string, $extrachars) {
		$allowed_chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ".$extrachars;
		$new_string = "";
		
		for ($i=0; $i<strlen($string); $i++) {
			if (is_numeric(strpos($allowed_chars, $string[$i])))
				$new_string .= $string[$i];
		}
		return $new_string;
	}

	public function random_string($length) {
		$characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$bits_per_char = ceil(log(strlen($characters), 2));
		$hex_chars_per_char = ceil($bits_per_char/4);
		$hex_chars_needed = $length*$hex_chars_per_char;
		$rand_data = bin2hex(openssl_random_pseudo_bytes(ceil($hex_chars_needed/2), $crypto_strong));
		if(!$crypto_strong) $this->log_then_die("An insecure random string of length ".$length." was generated.");
		
		$string = "";
		for ($i=0; $i<$length; $i++) {
			$hex_chars = substr($rand_data, $i*$hex_chars_per_char, $hex_chars_per_char);
			$rand_num = hexdec($hex_chars);
			$rand_index = $rand_num%strlen($characters);
			$string .= $characters[$rand_index];
		}
		return $string;
	}
	
	public function normalize_username($username) {
		return $this->make_alphanumeric(strip_tags($username), "$-()/!.,:;#@");
	}
	
	public function normalize_password($password, $salt) {
		return hash("sha256", $salt.$password);
	}
	
	public function strong_strip_tags($string) {
		return htmlspecialchars(strip_tags($string));
	}
	
	public function recaptcha_check_answer($recaptcha_privatekey, $ip_address, $g_recaptcha_response) {
		$response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$recaptcha_privatekey."&response=".$g_recaptcha_response."&remoteip=".$ip_address), true);
		if ($response['success'] == false) return false;
		else return true;
	}
	
	public function update_schema() {
		$migrations_path = realpath(dirname(__FILE__)."/../sql");
		
		$keep_looping = true;
		
		try {
			$migration_id = ((int)$this->get_site_constant("last_migration_id"))+1;
		}
		catch (Exception $e) {
			$keep_looping = false;
		}
		
		if ($keep_looping) {
			while ($keep_looping) {
				$fname = $migrations_path."/".$migration_id.".sql";
				if (is_file($fname)) {
					$cmd = $this->mysql_binary_location()." -u ".$GLOBALS['mysql_user']." -h ".$GLOBALS['mysql_server'];
					if ($GLOBALS['mysql_password'] != "") $cmd .= " -p".$GLOBALS['mysql_password'];
					$cmd .= " ".$GLOBALS['mysql_database']." < ".$fname;
					exec($cmd);
					$migration_id++;
				}
				else {
					$keep_looping = false;
					$migration_id--;
				}
			}
			$this->set_site_constant("last_migration_id", $migration_id);
		}
	}
	
	public function argv_to_array($argv) {
		$arr = array();
		$arg_i = 0;
		foreach ($argv as $arg) {
			if ($arg_i > 0) {
				$arg_parts = explode("=", $arg);
				if(count($arg_parts) == 2)
					$arr[$arg_parts[0]] = $arg_parts[1];
				else
					$arr[$arg_i-1] = $arg_parts[0];
			}
			$arg_i++;
		}
		return $arr;
	}
	
	public function mysql_binary_location() {
		if (!empty($GLOBALS['mysql_binary_location'])) return $GLOBALS['mysql_binary_location'];
		else {
			$var = $this->run_query("SHOW VARIABLES LIKE 'basedir';")->fetch();
			$var_val = str_replace("\\", "/", $var['Value']);
			if (!in_array($var_val[strlen($var_val)-1], array('/', '\\'))) $var_val .= "/";
			if (PHP_OS == "WINNT") return $var_val."bin/mysql.exe";
			else return $var_val."bin/mysql";
		}
	}
	
	public function php_binary_location() {
		if (!empty($GLOBALS['php_binary_location'])) return $GLOBALS['php_binary_location'];
		else if (PHP_OS == "WINNT") return str_replace("\\", "/", dirname(ini_get('extension_dir')))."/php.exe";
		else return PHP_BINDIR ."/php";
	}
	
	public function start_regular_background_processes($key_string) {
		$html = "";
		$process_count = 0;
		
		$pipe_config = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);
		$pipes = array();
		
		if (PHP_OS == "WINNT") $script_path_name = dirname(dirname(__FILE__));
		else $script_path_name = realpath(dirname(dirname(__FILE__)));
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_blocks.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_games.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_main.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$main_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($main_process)) $process_count++;
		else $html .= "Failed to start the main process.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_check_payments.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$payments_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($payments_process)) $process_count++;
		else $html .= "Failed to start a process for processing payments.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/address_miner.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$address_miner_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($address_miner_process)) $process_count++;
		else $html .= "Failed to start a process for mining addresses.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/fetch_currency_prices.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$currency_prices_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($currency_prices_process)) $process_count++;
		else $html .= "Failed to start a process for updating currency prices.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_cached_urls.php" key='.$key_string;
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$cached_url_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($cached_url_process)) $process_count++;
		else $html .= "Failed to start a process for loading cached urls.<br/>\n";
		
		$html .= "Started ".$process_count." background processes.<br/>\n";
		return $html;
	}
	
	public function generate_games($default_blockchain_id) {
		$q = "SELECT * FROM game_types ORDER BY game_type_id ASC;";
		$r = $this->run_query($q);
		while ($game_type = $r->fetch(PDO::FETCH_ASSOC)) {
			$this->generate_games_by_type($game_type, $default_blockchain_id);
		}
	}
	
	public function generate_games_by_type($game_type, $default_blockchain_id) {
		$q = "SELECT * FROM games WHERE game_type_id='".$game_type['game_type_id']."' AND game_status IN('editable','published','running');";
		$r = $this->run_query($q);
		$num_running_games = $r->rowCount();
		$needed_games = $game_type['target_open_games'] - $num_running_games;
		for ($i=0; $i<$needed_games; $i++) {
			$this->generate_game_by_type($game_type, $default_blockchain_id);
		}
	}
	
	public function generate_game_by_type($game_type, $default_blockchain_id) {
		$skip_game_type_vars = explode(",", "name,url_identifier,target_open_games,default_game_winning_inflation,default_logo_image_id,identifier_case_sensitive");
		
		$series_index_q = "SELECT MAX(game_series_index) FROM games WHERE game_type_id='".$game_type['game_type_id']."';";
		$series_index_r = $this->run_query($series_index_q);
		$series_index = (int) $series_index_r->fetch()['MAX(game_series_index)'] + 1;
		
		$game_name = $game_type['name'];
		if ($game_type['event_rule'] == "entity_type_option_group") $game_name .= $series_index;
		
		$url_identifier = $this->game_url_identifier($game_name);
		
		$q = "INSERT INTO games SET blockchain_id='".$default_blockchain_id."', game_status='published', game_series_index=".$series_index.", name=".$this->quote_escape($game_name).", url_identifier=".$this->quote_escape($url_identifier).", game_winning_inflation=".$this->quote_escape($game_type['default_game_winning_inflation']).", logo_image_id=".$this->quote_escape($game_type['default_logo_image_id']).", ";
		foreach ($game_type AS $var => $val) {
			if (!in_array($var, $skip_game_type_vars)) {
				if (!empty($val)) $q .= $var.'='.$this->quote_escape($val).', ';
			}
		}
		$q = substr($q, 0, strlen($q)-2).";";
		$r = $this->run_query($q);
		$game_id = $this->last_insert_id();
		
		$blockchain = new Blockchain($this, $default_blockchain_id);
		$game = new Game($blockchain, $game_id);
		
		if ($game->db_game['final_round'] > 0) $event_block = $game->db_game['final_round']*$game->db_game['round_length'];
		else $event_block = $game->db_game['round_length']+1;
		$game->ensure_events_until_block($event_block);
	}
	
	public function get_redirect_url($url) {
		$q = "SELECT * FROM redirect_urls WHERE url=".$this->quote_escape($url).";";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			$redirect_url = $r->fetch();
		}
		else {
			$q = "INSERT INTO redirect_urls SET url=".$this->quote_escape($url).", time_created='".time()."';";
			$r = $this->run_query($q);
			$redirect_url_id = $this->last_insert_id();
			
			$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
			$r = $this->run_query($q);
			$redirect_url = $r->fetch();
		}
		return $redirect_url;
	}

	public function mail_async($email, $from_name, $from, $subject, $message, $bcc, $cc) {
		$q = "INSERT INTO async_email_deliveries SET to_email=".$this->quote_escape($email).", from_name=".$this->quote_escape($from_name).", from_email=".$this->quote_escape($from).", subject=".$this->quote_escape($subject).", message=".$this->quote_escape($message).", bcc=".$this->quote_escape($bcc).", cc=".$this->quote_escape($cc).", time_created='".time()."';";
		$r = $this->run_query($q);
		$delivery_id = $this->last_insert_id();
		
		$command = $this->php_binary_location()." ".realpath(dirname(dirname(__FILE__)))."/scripts/async_email_deliver.php key=".$GLOBALS['cron_key_string']." delivery_id=".$delivery_id." > /dev/null 2>/dev/null &";
		exec($command);
		
		/*$curl_url = $GLOBALS['base_url']."/scripts/async_email_deliver.php?delivery_id=".$delivery_id;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $curl_url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);*/

		return $delivery_id;
	}
	
	public function get_site_constant($constant_name) {
		$q = "SELECT * FROM site_constants WHERE constant_name='".$constant_name."';";
		$r = $this->run_query($q);
		if ($r->rowCount() == 1) {
			$constant = $r->fetch();
			return $constant['constant_value'];
		}
		else return "";
	}

	public function set_site_constant($constant_name, $constant_value) {
		try {
			$q = "SELECT * FROM site_constants WHERE constant_name='".$constant_name."';";
			$r = $this->run_query($q);
			$run_query = true;
		}
		catch (Exception $e) {
			// site_constants table does not exist yet.
			$run_query = false;
		}
		
		if ($run_query) {
			if ($r->rowCount() > 0) {
				$constant = $r->fetch();
				$q = "UPDATE site_constants SET constant_value='".$constant_value."' WHERE constant_id='".$constant['constant_id']."';";
				$r = $this->run_query($q);
			}
			else {
				$q = "INSERT INTO site_constants SET constant_name='".$constant_name."', constant_value='".$constant_value."';";
				$r = $this->run_query($q);
			}
		}
	}
	
	public function to_significant_digits($number, $significant_digits) {
		if ($number === 0) return 0;
		if ($number < 1) $significant_digits++;
		$number_digits = (int)(log10($number));
		$returnval = (pow(10, $number_digits - $significant_digits + 1)) * floor($number/(pow(10, $number_digits - $significant_digits + 1)));
		return $returnval;
	}

	public function format_bignum($number) {
		if ($number >= 0) $sign = "";
		else $sign = "-";
		
		$number = abs($number);
		if ($number > 1) $number = $this->to_significant_digits($number, 5);
		
		if ($number > pow(10, 9)) {
			return $sign.($number/pow(10, 9))."B";
		}
		else if ($number > pow(10, 6)) {
			return $sign.($number/pow(10, 6))."M";
		}
		else if ($number > pow(10, 4)) {
			return $sign.($number/pow(10, 3))."k";
		}
		else return $sign.rtrim(rtrim(number_format(sprintf('%.8F', $number), 8), '0'), ".");
	}
	
	public function to_ranktext($rank) {
		return $rank.date("S", strtotime("1/".$rank."/".date("Y")));
	}
	
	public function cancel_transaction($transaction_id, $affected_input_ids, $created_input_ids) {
		$q = "DELETE FROM transactions WHERE transaction_id='".$transaction_id."';";
		$r = $this->run_query($q);
		
		if (count($affected_input_ids) > 0) {
			$q = "UPDATE transaction_ios SET spend_status='unspent', spend_transaction_id=NULL, spend_block_id=NULL WHERE io_id IN (".implode(",", $affected_input_ids).")";
			$r = $this->run_query($q);
		}
		
		if ($created_input_ids && count($created_input_ids) > 0) {
			$q = "DELETE FROM transaction_ios WHERE io_id IN (".implode(",", $created_input_ids).");";
			$r = $this->run_query($q);
		}
	}

	public function transaction_coins_in($transaction_id) {
		$qq = "SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.spend_transaction_id='".$transaction_id."';";
		$rr = $this->run_query($qq);
		$coins_in = $rr->fetch(PDO::FETCH_NUM);
		if ($coins_in[0] > 0) return $coins_in[0];
		else return 0;
	}

	public function transaction_coins_out($transaction_id) {
		$qq = "SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$transaction_id."';";
		$rr = $this->run_query($qq);
		$coins_out = $rr->fetch(PDO::FETCH_NUM);
		if ($coins_out[0] > 0) return $coins_out[0];
		else return 0;
	}

	public function transaction_voted_coins_out($transaction_id) {
		$qq = "SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$transaction_id."' AND a.option_index > 0;";
		$rr = $this->run_query($qq);
		$voted_coins_out = $rr->fetch(PDO::FETCH_NUM);
		if ($voted_coins_out[0] > 0) return $voted_coins_out[0];
		else return 0;
	}

	public function output_message($status_code, $message, $dump_object) {
		if (empty($dump_object)) $dump_object = array("status_code"=>$status_code, "message"=>$message);
		else {
			$dump_object['status_code'] = $status_code;
			$dump_object['message'] = $message;
		}
		echo json_encode($dump_object);
	}
	
	public function try_apply_invite_key($user_id, $invite_key, &$invite_game) {
		$reload_page = false;
		$invite_key = $this->quote_escape($invite_key);
		
		$q = "SELECT * FROM game_invitations WHERE invitation_key=".$invite_key.";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() == 1) {
			$invitation = $r->fetch();
			
			if ($invitation['used'] == 0 && $invitation['used_user_id'] == "" && $invitation['used_time'] == 0) {
				$db_game = $this->run_query("SELECT * FROM games WHERE game_id='".$invitation['game_id']."';")->fetch();
				
				$qq = "UPDATE game_invitations SET used_user_id='".$user_id."', used_time='".time()."', used=1";
				if ($GLOBALS['pageview_tracking_enabled']) $q .= ", used_ip='".$_SERVER['REMOTE_ADDR']."'";
				$qq .= " WHERE invitation_id='".$invitation['invitation_id']."';";
				$rr = $this->run_query($qq);
				
				$user = new User($this, $user_id);
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$invite_game = new Game($blockchain, $invitation['game_id']);
				
				$user_game = $user->ensure_user_in_game($invite_game);
				
				return true;
			}
			else return false;
		}
		else return false;
	}
	
	public function format_seconds($seconds) {
		$seconds = intval($seconds);
		$weeks = floor($seconds/(3600*24*7));
		$days = floor($seconds/(3600*24));
		$hours = floor($seconds / 3600);
		$minutes = floor($seconds / 60);
		
		if ($weeks > 0) {
			if ($weeks == 1) $str = $weeks." week";
			else $str = $weeks." weeks";
			$days = $days - 7*$weeks;
			if ($days != 1) $str .= " and ".$days." days";
			else $str .= " and ".$days." day";
			return $str;
		}
		else if ($days > 1) {
			return $days." days";
		}
		else if ($hours > 0) {
			$str = "";
			if ($hours != 1) $str .= $hours." hours";
			else $str .= $hours." hour";
			$remainder_min = round(($seconds - (3600*$hours))/60);
			if ($remainder_min > 0) {
				$str .= " and ".$remainder_min." ";
				if ($remainder_min == '1') $str .= "minute";
				else $str .= "minutes";
			}
			return $str;
		}
		else if ($minutes > 0) {
			$remainder_sec = $seconds-$minutes*60;
			$str = "";
			if ($minutes != 1) $str .= $minutes." minutes";
			else return $str .= $minutes." minute";
			if ($remainder_sec > 0) $str .= " and ".$remainder_sec." seconds";
			return $str;
		}
		else {
			if ($seconds != 1) return $seconds." seconds";
			else return $seconds." second";
		}
	}
	
	public function game_url_identifier($game_name) {
		$url_identifier = "";
		$append_index = 0;
		$keeplooping = true;
		
		do {
			if ($append_index > 0) $append = "(".$append_index.")";
			else $append = "";
			$url_identifier = $this->normalize_uri_part($game_name.$append);
			$q = "SELECT * FROM games WHERE url_identifier=".$this->quote_escape($url_identifier).";";
			$r = $this->run_query($q);
			if ($r->rowCount() == 0) $keeplooping = false;
			else $append_index++;
		}
		while ($keeplooping);
		
		return $url_identifier;
	}
	
	public function normalize_uri_part($uri_part) {
		return $this->make_alphanumeric(str_replace(" ", "-", strtolower($uri_part)), "-().:;");
	}
	
	public function prepend_a_or_an($word) {
		$firstletter = strtolower($word[0]);
		if (strpos('aeiou', $firstletter)) return "an ".$word;
		else return "a ".$word;
	}
	
	public function friendly_intval($val) {
		if ($val > 0) return $val;
		else return 0;
	}
	
	public function fetch_game_from_url() {
		$login_url_parts = explode("/", rtrim(ltrim($_SERVER['REQUEST_URI'], "/"), "/"));
		if ($login_url_parts[0] == "wallet" && count($login_url_parts) > 1) {
			$q = "SELECT * FROM games WHERE url_identifier=".$this->quote_escape($login_url_parts[1]).";";
			$r = $this->run_query($q);
			if ($r->rowCount() == 1) {
				return $r->fetch();
			}
			else return false;
		}
		else return false;
	}
	
	public function latest_currency_price($currency_id) {
		$q = "SELECT * FROM currency_prices WHERE currency_id='".$currency_id."' AND reference_currency_id='".$this->get_site_constant('reference_currency_id')."' ORDER BY price_id DESC LIMIT 1;";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function get_currency_by_abbreviation($currency_abbreviation) {
		$q = "SELECT * FROM currencies WHERE abbreviation='".strtoupper($currency_abbreviation)."';";
		$r = $this->run_query($q);

		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function get_reference_currency() {
		$q = "SELECT * FROM currencies WHERE currency_id='".$this->get_site_constant('reference_currency_id')."';";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) return $r->fetch();
		else die('Error, reference_currency_id is not set properly in site_constants.');
	}
	
	public function update_all_currency_prices() {
		$reference_currency_id = $this->get_site_constant('reference_currency_id');
		$q = "SELECT * FROM currencies c JOIN oracle_urls o ON c.oracle_url_id=o.oracle_url_id WHERE c.currency_id != '".$reference_currency_id."' GROUP BY o.oracle_url_id;";
		$r = $this->run_query($q);
		
		while ($currency_url = $r->fetch()) {
			$api_response_raw = file_get_contents($currency_url['url']);
			$api_response = json_decode($api_response_raw);
			
			$qq = "SELECT * FROM currencies WHERE oracle_url_id='".$currency_url['oracle_url_id']."';";
			$rr = $this->run_query($qq);
			
			while ($currency = $rr->fetch()) {
				if ($currency_url['format_id'] == 2) {
					$price = $api_response->USD->bid;
				}
				else if ($currency_url['format_id'] == 1) {
					if (!empty($api_response->rates)) {
						$api_rates = (array) $api_response->rates;
						$price = 1/($api_rates[$currency['abbreviation']]);
					}
				}
				
				$qqq = "INSERT INTO currency_prices SET currency_id='".$currency['currency_id']."', reference_currency_id='".$reference_currency_id."', price='".$price."', time_added='".time()."';";
				$rrr = $this->run_query($qqq);
			}
		}
	}
	
	public function update_currency_price($currency_id) {
		$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
		$r = $this->run_query($q);

		if ($r->rowCount() > 0) {
			$currency = $r->fetch();

			if ($currency['abbreviation'] == "BTC") {
				$reference_currency = $this->get_reference_currency();
				
				$api_url = "https://api.bitcoinaverage.com/ticker/global/all";
				$api_response_raw = file_get_contents($api_url);
				$api_response = json_decode($api_response_raw);
				
				$price = $api_response->$reference_currency['abbreviation']->bid;

				if ($price > 0) {
					$q = "INSERT INTO currency_prices SET currency_id='".$currency_id."', reference_currency_id='".$reference_currency['currency_id']."', price='".$price."', time_added='".time()."';";
					$r = $this->run_query($q);
					$currency_price_id = $this->last_insert_id();

					$q = "SELECT * FROM currency_prices WHERE price_id='".$currency_price_id."';";
					$r = $this->run_query($q);
					return $r->fetch();
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function currency_conversion_rate($numerator_currency_id, $denominator_currency_id) {
		$latest_numerator_rate = $this->latest_currency_price($numerator_currency_id);
		$latest_denominator_rate = $this->latest_currency_price($denominator_currency_id);

		$returnvals['numerator_price_id'] = $latest_numerator_rate['price_id'];
		$returnvals['denominator_price_id'] = $latest_denominator_rate['price_id'];
		$returnvals['conversion_rate'] = round(pow(10,8)*$latest_denominator_rate['price']/$latest_numerator_rate['price'])/pow(10,8);
		return $returnvals;
	}
	
	public function historical_currency_conversion_rate($numerator_price_id, $denominator_price_id) {
		$q = "SELECT * FROM currency_prices WHERE price_id='".$numerator_price_id."';";
		$r = $this->run_query($q);
		$numerator_rate = $r->fetch();

		$q = "SELECT * FROM currency_prices WHERE price_id='".$denominator_price_id."';";
		$r = $this->run_query($q);
		$denominator_rate = $r->fetch();

		return round(pow(10,8)*$denominator_rate['price']/$numerator_rate['price'])/pow(10,8);
	}
	
	public function new_currency_invoice(&$pay_currency, $pay_amount, &$user, &$user_game, $invoice_type) {
		$currency_account = $user->fetch_currency_account($pay_currency['currency_id']);
		$address_key = $this->new_address_key($pay_currency['currency_id'], $currency_account);
		
		$time = time();
		$q = "INSERT INTO currency_invoices SET time_created='".$time."', pay_currency_id='".$pay_currency['currency_id']."', address_id='".$address_key['address_id']."', expire_time='".($time+$GLOBALS['invoice_expiration_seconds'])."', user_game_id='".$user_game['user_game_id']."', invoice_type='".$invoice_type."', status='unpaid', invoice_key_string='".$this->random_string(32)."', pay_amount='".$pay_amount."';";
		$r = $this->run_query($q);
		$invoice_id = $this->last_insert_id();
		
		$q = "SELECT * FROM currency_invoices WHERE invoice_id='".$invoice_id."';";
		$r = $this->run_query($q);
		return $r->fetch();
	}
	
	public function vote_details_general($mature_balance) {
		return "";
		/*$html = '
		<div class="row">
			<div class="col-xs-4">Your balance:</div>
			<div class="col-xs-8 greentext">'.number_format(floor($mature_balance/pow(10,5))/1000, 2).' EMP</div>
		</div>	';
		return $html;*/
	}

	public function vote_option_details($option, $rank, $confirmed_votes, $unconfirmed_votes, $sum_votes) {
		$html = '
		<div class="row">
			<div class="col-xs-4">Current&nbsp;rank:</div>
			<div class="col-xs-8">'.$this->to_ranktext($rank).'</div>
		</div>
		<div class="row">
			<div class="col-xs-4">Confirmed Votes:</div>
			<div class="col-xs-8">'.$this->format_bignum($confirmed_votes/pow(10,8)).' votes ('.(empty($sum_votes)? 0 : (ceil(100*100*$confirmed_votes/$sum_votes)/100)).'%)</div>
		</div>
		<div class="row">
			<div class="col-xs-4">Unconfirmed Votes:</div>
			<div class="col-xs-8">'.$this->format_bignum($unconfirmed_votes/pow(10,8)).' votes ('.(empty($sum_votes)? 0 : (ceil(100*100*$unconfirmed_votes/$sum_votes)/100)).'%)</div>
		</div>';
		return $html;
	}
	
	public function new_address_key($currency_id, &$account) {
		$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
		$r = $this->run_query($q);
		$currency = $r->fetch();
		
		if ($currency['blockchain_id'] > 0) {
			$blockchain = new Blockchain($this, $currency['blockchain_id']);
			
			try {
				$coin_rpc = new jsonRPCClient('http://'.$blockchain->db_blockchain['rpc_username'].':'.$blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchain->db_blockchain['rpc_port'].'/');
				
				$address_text = $coin_rpc->getnewaddress();
				$encWIF = "";
				$save_method = "wallet.dat";
			}
			catch (Exception $e) {
				if ($currency['short_name'] == "litecoin") $keySet = litecoin::getNewKeySet();
				else $keySet = bitcoin::getNewKeySet();
				
				if (empty($GLOBALS['rsa_pub_key']) || empty($keySet['pubAdd']) || empty($keySet['privWIF'])) {
					$this->log_message('Error generating a payment address. Please visit /install.php and then set $GLOBALS["rsa_pub_key"] in includes/config.php');
					$save_method = "skip";
				}
				else {
					$encWIF = bin2hex(bitsci::rsa_encrypt($keySet['privWIF'], $GLOBALS['rsa_pub_key']));
					$address_text = $keySet['pubAdd'];
					$save_method = "db";
				}
			}
			
			if ($save_method == "skip") return false;
			else {
				$db_address = $blockchain->create_or_fetch_address($address_text, true, false, false, false, true);
				if ($account) {
					$q = "UPDATE addresses SET user_id='".$account['user_id']."' WHERE address_id='".$db_address['address_id']."';";
					$r = $this->run_query($q);
					$q = "UPDATE transaction_ios SET user_id='".$account['user_id']."' WHERE address_id='".$db_address['address_id']."';";
					$r = $this->run_query($q);
				}
				
				$q = "SELECT * FROM address_keys WHERE address_id='".$db_address['address_id']."';";
				$r = $this->run_query($q);
				
				if ($r->rowCount() > 0) {
					$address_key = $r->fetch();
					
					if ($account) {
						$q = "UPDATE address_keys SET account_id='".$account['account_id']."' WHERE address_key_id='".$address_key['address_key_id']."';";
						$r = $this->run_query($q);
					}
					return $address_key;
				}
				else return false;
			}
		}
		else return false;
	}
	
	public function decimal_to_float($number) {
		if (strpos($number, ".") === false) return $number;
		else return rtrim(rtrim($number, '0'), '.');
	}
	
	public function display_featured_games() {
		echo '<div class="paragraph">';
		$q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.featured=1 AND (g.game_status='published' OR g.game_status='running') ORDER BY g.featured_score DESC, g.game_id DESC;";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			$cell_width = 12;
			
			$counter = 0;
			echo '<div class="row">';
			
			while ($db_game = $r->fetch()) {
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$featured_game = new Game($blockchain, $db_game['game_id']);
				$mining_block_id = $blockchain->last_block_id()+1;
				$current_round_id = $featured_game->block_to_round($mining_block_id);
				?>
				<script type="text/javascript">
				games.push(new Game(<?php
					echo $db_game['game_id'];
					echo ', false';
					echo ', false'.', ';
					echo 'false';
					echo ', ""';
					echo ', "'.$db_game['payout_weight'].'"';
					echo ', '.$db_game['round_length'];
					echo ', 0';
					echo ', "'.$db_game['url_identifier'].'"';
					echo ', "'.$db_game['coin_name'].'"';
					echo ', "'.$db_game['coin_name_plural'].'"';
					echo ', "home", "'.$featured_game->event_ids().'", "'.$featured_game->logo_image_url().'", "'.$featured_game->vote_effectiveness_function().'"';
				?>));
				
				games[<?php echo $counter; ?>].game_loop_event();
				</script>
				<?php
				echo '<div class="col-md-'.$cell_width.'">';
				echo '<center><h1 style="display: inline-block" title="'.$featured_game->game_description().'">'.$featured_game->db_game['name'].'</h1>';
				if ($featured_game->db_game['short_description'] != "") echo "<p>".$featured_game->db_game['short_description']."</p>";
				
				echo '<div id="game'.$counter.'_events"></div>';
				echo '<script type="text/javascript" id="game'.$counter.'_new_event_js">'.$featured_game->new_event_js($counter, false).'</script>';
				
				echo '<br/><a href="/'.$featured_game->db_game['url_identifier'].'/" class="btn btn-success">Play Now</a>';
				echo ' <a href="/explorer/games/'.$featured_game->db_game['url_identifier'].'/events/" class="btn btn-primary">Blockchain Explorer</a>';
				echo '</center><br/>';
				
				if ($counter%(12/$cell_width) == 1) echo '</div><div class="row">';
				$counter++;
				echo '</div>';
			}
			echo '</div>';
		}
		else {
			echo "No public games are running right now.<br/>\n";
		}
		echo '</div>';
	}
	
	public function refresh_utxo_user_ids($only_unspent_utxos) {
		$update_user_id_q = "UPDATE transaction_ios io JOIN addresses a ON io.address_id=a.address_id SET io.user_id=a.user_id";
		if ($only_unspent_utxos) $update_user_id_q .= " WHERE io.spend_status='unspent'";
		$update_user_id_q .= ";";
		$update_user_id_r = $this->run_query($update_user_id_q);
	}
	
	public function image_url(&$db_image) {
		$url = '/images/custom/'.$db_image['image_id'];
		if ($db_image['access_key'] != "") $url .= '_'.$db_image['access_key'];
		$url .= '.'.$db_image['extension'];
		return $url;
	}
	
	public function delete_unconfirmable_transactions() {
		/*$start_time = microtime(true);
		$unconfirmed_tx_r = $this->run_query("SELECT * FROM transactions WHERE block_id IS NULL ORDER BY game_id ASC;");
		$game_id = false;
		
		while ($unconfirmed_tx = $unconfirmed_tx_r->fetch()) {
			if ($unconfirmed_tx['game_id'] != $game_id) {
				$game_id = $unconfirmed_tx['game_id'];
				$game = new Game($this, $game_id);
			}
			
			$coins_in = $this->transaction_coins_in($unconfirmed_tx['transaction_id']);
			//$coins_out = $this->transaction_coins_out($unconfirmed_tx['transaction_id']);
			if ($coins_in == 0) {
				$this->run_query("DELETE t.*, io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';");
			}
			else if ($unconfirmed_tx['fee_amount'] < 0) {
				$this->run_query("DELETE t.*, io.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.transaction_id='".$unconfirmed_tx['transaction_id']."';");
			}
		}
		echo "\nTook ".(microtime(true)-$start_time)." sec to delete unconfirmable transactions.\n\n";
		*/
	}
	
	public function game_admin_row(&$thisuser, $user_game, $selected_game_id) {
		$html = '
		<div class="row game_row';
		if ($user_game['game_id'] == $selected_game_id) $html .= ' boldtext';
		$html .= '">
			<div class="col-sm-1 game_cell">
				'.ucwords($user_game['game_status']).'
			</div>
			<div class="col-sm-5 game_cell">
				<a target="_blank" href="/wallet/'.$user_game['url_identifier'].'/">'.$user_game['name'].'</a>
			</div>
			<div class="col-sm-3 game_cell">
				<a id="fetch_game_link_'.$user_game['game_id'].'" href="" onclick="switch_to_game('.$user_game['game_id'].', \'fetch\'); return false;">Settings</a>
			</div>
			<div class="col-sm-3 game_cell">';
			$perm_to_invite = $thisuser->user_can_invite_game($user_game);
			if ($perm_to_invite) {
				$html .= '
				<a href="" onclick="manage_game_invitations('.$user_game['game_id'].'); return false;">Invitations</a>';
			}
			$html .= '
			</div>
		</div>';
		return $html;
	}
	
	public function game_info_table(&$db_game) {
		$html = "";
		
		$blocks_per_hour = 3600/$db_game['seconds_per_block'];
		$round_reward = ($db_game['pos_reward']+$db_game['pow_reward']*$db_game['round_length'])/pow(10,8);
		$seconds_per_round = $db_game['seconds_per_block']*$db_game['round_length'];
		
		$invite_currency = false;
		if ($db_game['invite_currency'] > 0) {
			$q = "SELECT * FROM currencies WHERE currency_id='".$db_game['invite_currency']."';";
			$r = $this->run_query($q);
			$invite_currency = $r->fetch();
		}
		
		if ($db_game['game_id'] > 0) { // This public function can also be called with a game variation
			$html .= '<div class="row"><div class="col-sm-5">Game title:</div><div class="col-sm-7">'.$db_game['name']."</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Blockchain:</div><div class="col-sm-7">';
		if ($db_game['blockchain_id'] > 0) {
			$q = "SELECT * FROM blockchains WHERE blockchain_id='".$db_game['blockchain_id']."';";
			$db_blockchain = $this->run_query($q)->fetch();
			$html .= '<a href="/explorer/blockchains/'.$db_blockchain['url_identifier'].'/blocks/">'.$db_blockchain['blockchain_name'].'</a>';
		}
		else $html .= "None";
		$html .= "</div></div>\n";
		
		if ($db_game['game_id'] > 0) {
			$blockchain = new Blockchain($this, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$html .= '<div class="row"><div class="col-sm-5">Game definition:</div><div class="col-sm-7"><a target="_blank" href="'.$GLOBALS['base_url'].'/scripts/show_game_definition.php?game_id='.$db_game['game_id'].'" title="'.$this->game_definition_hash($game).'">'.$this->game_definition_hash_short($game).'</a></div></div>';
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Length of game:</div><div class="col-sm-7">';
		if ($db_game['final_round'] > 0) $html .= $db_game['final_round']." rounds (".$this->format_seconds($seconds_per_round*$db_game['final_round']).")";
		else $html .= "Game does not end";
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Starts on block:</div><div class="col-sm-7">'.$db_game['game_starting_block']."</div></div>\n";
		
		if ($db_game['buyin_policy'] != "none") {
			$html .= '<div class="row"><div class="col-sm-5">Escrow address:</div><div class="col-sm-7" style="font-size: 11px;">';
			if ($db_game['escrow_address'] == "") $html .= "None";
			else $html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/addresses/'.$db_game['escrow_address'].'">'.$db_game['escrow_address'].'</a>';
			$html .= "</div></div>\n";
		}
		
		$genesis_amount_disp = $this->format_bignum($db_game['genesis_amount']/pow(10,8));
		$html .= '<div class="row"><div class="col-sm-5">Genesis transaction:</div><div class="col-sm-7">';
		$html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/transactions/'.$db_game['genesis_tx_hash'].'">';
		$html .= $genesis_amount_disp.' ';
		if ($genesis_amount_disp == "1") $html .= $db_game['coin_name'];
		else $html .= $db_game['coin_name_plural'];
		$html .= '</a>';
		$html .= "</div></div>\n";
		
		if ($db_game['game_id'] > 0) {
			$sample_block_id = $game->blockchain->last_block_id();
			$game->refresh_coins_in_existence();
			
			$circulation_amount_disp = $this->format_bignum($game->coins_in_existence($sample_block_id)/pow(10,8));
			$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->db_game['coin_name_plural']).' in circulation:</div><div class="col-sm-7">';
			$html .= $circulation_amount_disp.' ';
			if ($circulation_amount_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			if ($db_game['buyin_policy'] != "none") {
				$escrow_amount_disp = $this->format_bignum($game->escrow_value($sample_block_id)/pow(10,8));
				$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->blockchain->db_blockchain['coin_name_plural']).' in escrow:</div><div class="col-sm-7">';
				$html .= $escrow_amount_disp.' ';
				if ($escrow_amount_disp == "1") $html .= $game->blockchain->db_blockchain['coin_name'];
				else $html .= $game->blockchain->db_blockchain['coin_name_plural'];
				$html .= "</div></div>\n";
				
				$exchange_rate_disp = $this->format_bignum($game->coins_in_existence($sample_block_id)/$game->escrow_value($sample_block_id));
				$html .= '<div class="row"><div class="col-sm-5">Current exchange rate:</div><div class="col-sm-7">';
				$html .= $exchange_rate_disp.' ';
				if ($exchange_rate_disp == "1") $html .= $db_game['coin_name'];
				else $html .= $db_game['coin_name_plural'];
				$html .= ' per '.$game->blockchain->db_blockchain['coin_name'];
				$html .= "</div></div>\n";
			}
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Buy-in policy:</div><div class="col-sm-7">';
		if ($db_game['buyin_policy'] == "unlimited") $html .= "Unlimited";
		else if ($db_game['buyin_policy'] == "none") $html .= "Not allowed";
		else if ($db_game['buyin_policy'] == "per_user_cap") $html .= "Up to ".$this->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per player";
		else if ($db_game['buyin_policy'] == "game_cap") $html .= $this->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s available";
		else if ($db_game['buyin_policy'] == "game_and_user_cap") $html .= $this->format_bignum($db_game['per_user_buyin_cap'])." ".$invite_currency['short_name']."s per person until ".$this->format_bignum($db_game['game_buyin_cap'])." ".$invite_currency['short_name']."s are reached";
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Inflation:</div><div class="col-sm-7">';	
		if ($db_game['inflation'] == "linear") $html .= "Linear (".$this->format_bignum($round_reward)." coins per round)";
		else if ($db_game['inflation'] == "fixed_exponential") $html .= "Fixed Exponential (".(100*$db_game['exponential_inflation_rate'])."% per round)";
		else $html .= "Exponential<br/>".$this->votes_per_coin($db_game)." votes per ".$db_game['coin_name']." (".(100*$db_game['exponential_inflation_rate'])."% per round)";
		$html .= "</div></div>\n";
		
		$total_inflation_pct = $this->game_final_inflation_pct($db_game);
		if ($total_inflation_pct) {
			$html .= '<div class="row"><div class="col-sm-5">Potential inflation:</div><div class="col-sm-7">'.number_format($total_inflation_pct)."%</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Distribution:</div><div class="col-sm-7">';
		if ($db_game['inflation'] == "linear") $html .= $this->format_bignum($db_game['pos_reward']/pow(10,8))." to voters, ".$this->format_bignum($db_game['pow_reward']*$db_game['round_length']/pow(10,8))." to miners";
		else $html .= (100 - 100*$db_game['exponential_inflation_minershare'])."% to voters, ".(100*$db_game['exponential_inflation_minershare'])."% to miners";
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Blocks per round:</div><div class="col-sm-7">'.$db_game['round_length']."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Block target time:</div><div class="col-sm-7">'.$this->format_seconds($db_game['seconds_per_block'])."</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Average time per round:</div><div class="col-sm-7">'.$this->format_seconds($db_game['round_length']*$db_game['seconds_per_block'])."</div></div>\n";
		
		if ($db_game['maturity'] != 0) {
			$html .= '<div class="row"><div class="col-sm-5">Transaction maturity:</div><div class="col-sm-7">'.$db_game['maturity']." block";
			if ($db_game['maturity'] != 1) $html .= "s";
			$html .= "</div></div>\n";
		}
		
		return $html;
	}
	
	public function fetch_game_definition(&$game) {
		$game_definition = array();
		if ($game->blockchain->db_blockchain['p2p_mode'] == "none") $game_definition['blockchain_identifier'] = "private";
		else $game_definition['blockchain_identifier'] = $game->blockchain->db_blockchain['url_identifier'];
		
		$verbatim_vars = $this->game_definition_verbatim_vars();
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($game->db_game[$var_name] == "0" || $game->db_game[$var_name] > 0) $var_val = round($game->db_game[$var_name]);
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $game->db_game[$var_name];
			else $var_val = $game->db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		if ($game->db_game['event_rule'] == "game_definition") {
			$event_verbatim_vars = $this->event_verbatim_vars();
			$events_obj = array();
			
			$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' ORDER BY event_index ASC;";
			$r = $this->run_query($q);
			
			$i=0;
			while ($game_defined_event = $r->fetch()) {
				$temp_event = array();
				
				for ($j=0; $j<count($event_verbatim_vars); $j++) {
					$var_type = $event_verbatim_vars[$j][0];
					$var_name = $event_verbatim_vars[$j][1];
					$var_val = $game_defined_event[$var_name];
					if ($var_type == "int" && $var_val != "") $var_val = (int) $var_val;
					$temp_event[$var_name] = $var_val;
				}
				
				$qq = "SELECT * FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$i."' ORDER BY option_index ASC;";
				$rr = $this->run_query($qq);
				$j = 0;
				while ($game_defined_option = $rr->fetch()) {
					$temp_event['possible_outcomes'][$j] = array("title"=>$game_defined_option['name']);
					$j++;
				}
				$events_obj[$i] = $temp_event;
				$i++;
			}
			$game_definition['events'] = $events_obj;
		}
		return $game_definition;
	}
	
	public function game_definition_hash(&$game) {
		$game_def = $this->fetch_game_definition($game);
		$game_def_str = $this->game_def_to_text($game_def);
		$game_def_hash = $this->game_def_to_hash($game_def_str);
		return $game_def_hash;
	}
	
	public function game_definition_hash_short(&$game) {
		$game_def_hash = $this->game_definition_hash($game);
		$short_hash = substr($game_def_hash, 0, 16);
		return $short_hash;
	}
	
	public function game_def_to_hash($game_def_str) {
		return hash("sha256", $game_def_str);
	}
	
	public function game_def_to_text(&$game_def) {
		return json_encode($game_def, JSON_PRETTY_PRINT);
	}
	
	public function game_final_inflation_pct(&$db_game) {
		if ($db_game['final_round'] > 0) {
			if ($db_game['inflation'] == "fixed_exponential" || $db_game['inflation'] == "exponential") {
				$inflation_factor = pow(1+$db_game['exponential_inflation_rate'], $db_game['final_round']);
			}
			else {
				if ($db_game['start_condition'] == "players_joined") {
					$db_game['initial_coins'] = $db_game['genesis_amount'];
					$final_coins = $this->ideal_coins_in_existence_after_round($db_game, $db_game['final_round']);
					$inflation_factor = $final_coins/$db_game['initial_coins'];
				}
				else return false;
			}
			$inflation_pct = round(($inflation_factor-1)*100);
			return $inflation_pct;
		}
		else return false;
	}
	
	public function ideal_coins_in_existence_after_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['genesis_amount'] + $round_id*($db_game['pos_reward'] + $db_game['round_length']*$db_game['pow_reward']);
		else if ($db_game['inflation'] == "fixed_exponential") return floor($db_game['genesis_amount'] * pow(1 + $db_game['exponential_inflation_rate'], $round_id));
	}
	
	public function coins_created_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "exponential") {
			$blockchain = new Blockchain($this, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$coi_block = ($round_id-1)*$game->db_game['round_length'];
			$coins_in_existence = $game->coins_in_existence($coi_block);
			return $coins_in_existence*$game->db_game['exponential_inflation_rate'];
		}
		else {
			$thisround_coins = $this->ideal_coins_in_existence_after_round($db_game, $round_id);
			$prevround_coins = $this->ideal_coins_in_existence_after_round($db_game, $round_id-1);
			if (is_nan($thisround_coins) || is_nan($prevround_coins) || is_infinite($thisround_coins) || is_infinite($prevround_coins)) return 0;
			else return $thisround_coins - $prevround_coins;
		}
	}

	public function pow_reward_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['pow_reward'];
		else if ($db_game['inflation'] == "fixed_exponential" || $db_game['inflation'] == "exponential") {
			$round_coins_created = $this->coins_created_in_round($db_game, $round_id);
			$round_pow_coins = floor($db_game['exponential_inflation_minershare']*$round_coins_created);
			return floor($round_pow_coins/$db_game['round_length']);
		}
		else return 0;
	}

	public function pos_reward_in_round(&$db_game, $round_id) {
		if ($db_game['inflation'] == "linear") return $db_game['pos_reward'];
		else if ($db_game['inflation'] == "fixed_exponential") {
			if ($round_id > 1 || empty($db_game['game_id'])) {
				$round_coins_created = $this->coins_created_in_round($db_game, $round_id);
			}
			else {
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$game = new Game($blockchain, $db_game['game_id']);
				$round_coins_created = $game->coins_in_existence(false)*$db_game['exponential_inflation_rate'];
			}
			return floor((1-$db_game['exponential_inflation_minershare'])*$round_coins_created);
		}
		else {
			$blockchain = new Blockchain($this, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			$mining_block_id = $blockchain->last_block_id()+1;
			$current_round = $game->block_to_round($mining_block_id);
			
			if ($round_id == $current_round) {
				$q = "SELECT SUM(".$game->db_game['payout_weight']."_score), SUM(unconfirmed_".$game->db_game['payout_weight']."_score) FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id='".$game->db_game['game_id']."';";
				$r = $this->run_query($q);
				$r = $r->fetch();
				$score = $r['SUM('.$game->db_game['payout_weight'].'_score)']+$r['SUM(unconfirmed_'.$game->db_game['payout_weight'].'_score)'];
			}
			else {
				$q = "SELECT SUM(".$game->db_game['payout_weight']."_score) FROM event_outcome_options eoo JOIN events e ON eoo.event_id=e.event_id WHERE e.game_id='".$game->db_game['game_id']."' AND round_id='".$round_id."';";
				$r = $this->run_query($q);
				$r = $r->fetch();
				$score = $r["SUM(".$game->db_game['payout_weight']."_score)"];
			}
			
			return $score/$this->votes_per_coin($db_game);
		}
	}
	
	public function votes_per_coin($db_game) {
		if ($db_game['inflation'] == "exponential") {
			if ($db_game['payout_weight'] == "coin_round") $votes_per_coin = 1/$db_game['exponential_inflation_rate'];
			else $votes_per_coin = $db_game['round_length']/$db_game['exponential_inflation_rate'];
			return $votes_per_coin;
		}
		else return 0;
	}
	
	public function fetch_currency_by_id($currency_id) {
		$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
		$r = $this->run_query($q);
		return $r->fetch();
	}
	
	public function fetch_external_address_by_id($external_address_id) {
		$q = "SELECT * FROM external_addresses WHERE address_id='".$external_address_id."';";
		$r = $this->run_query($q);
		return $r->fetch();
	}
	
	public function fetch_currency_invoice_by_id($currency_invoice_id) {
		$q = "SELECT * FROM currency_invoices WHERE invoice_id='".$currency_invoice_id."';";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function check_process_running($lock_name) {
		if ($GLOBALS['process_lock_method'] == "db") $process_running = (int) $this->get_site_constant($lock_name);
		else {
			$cmd = "ps aux|grep \"".realpath(dirname($_SERVER["SCRIPT_FILENAME"]))."/".basename($_SERVER["SCRIPT_FILENAME"])."\"|grep -v grep|wc -l";
			$running = (int) (trim(exec($cmd))-1);
			if ($running < 0) $running = 0;
			else if ($running > 1) $running = 1;
			$num_running = $running;
			$this->log_message("$num_running $cmd");
			
			$cmd = "ps aux|grep \"".basename($_SERVER["SCRIPT_FILENAME"])."\"|grep -v grep|wc -l";
			$running = (int) (trim(exec($cmd))-1);
			if ($running < 0) $running = 0;
			else if ($running > 1) $running = 1;
			$num_running += $running;
			$this->log_message("$num_running $cmd");
			
			if ($num_running > 0) $process_running = true;
			else $process_running = false;
		}
		return $process_running;
	}
	
	public function voting_character_definitions() {
		if ($this->get_site_constant('identifier_case_sensitive') == 1) {
			$voting_characters = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
			$firstchar_divisions = array(26,16,8,4,2,1);
		}
		else {
			$voting_characters = "123456789abcdefghijklmnopqrstuvwxyz";
			$firstchar_divisions = array(19,8,4,2,1);
		}
		$range_max = -1;
		for ($i=0; $i<count($firstchar_divisions); $i++) {
			$num_this_length = $firstchar_divisions[$i]*pow(strlen($voting_characters), $i);
			$length_to_range[$i+1] = array($range_max+1, $range_max+$num_this_length);
			$range_max = $range_max+$num_this_length;
		}
		$returnvals['voting_characters'] = $voting_characters;
		$returnvals['firstchar_divisions'] = $firstchar_divisions;
		$returnvals['length_to_range'] = $length_to_range;
		return $returnvals;
	}
	
	public function vote_identifier_to_option_index($vote_identifier) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		
		$firstchar = $vote_identifier[0];
		$firstchar_index = strpos($voting_characters, $firstchar);
		$firstchar_offset = 0;
		
		$range = $length_to_range[strlen($vote_identifier)];
		if ($range) {
			if (strlen($vote_identifier) == 1) {
				$firstchar_range_offset = 0;
				$firstchar_char_offset = 0;
			}
			else {
				$firstchar_range_offset = $length_to_range[strlen($vote_identifier)-1][1]+1;
				$firstchar_char_offset = 0;
				for ($i=0; $i<strlen($vote_identifier)-1; $i++) {
					$firstchar_char_offset += $firstchar_divisions[$i];
				}
			}
			$firstchar_index_within_range = $firstchar_index-$firstchar_char_offset;
			$option_id = $firstchar_range_offset+$firstchar_index_within_range*pow(strlen($voting_characters), strlen($vote_identifier)-1);
			
			for ($i=1; $i<strlen($vote_identifier); $i++) {
				$char = $vote_identifier[$i];
				$char_id = strpos($voting_characters, $char);
				$option_id += $char_id*pow(strlen($voting_characters), strlen($vote_identifier)-$i-1);
			}
			return $option_id;
		}
		else return false;
	}
	
	public function option_index_to_vote_identifier($option_index) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		$firstchar_offset = 0;
		
		foreach ($length_to_range as $length => $range) {
			if ($option_index >= $range[0] && $option_index <= $range[1]) {
				$num_firstchars = $firstchar_divisions[$length-1];
				$index_within_range = $option_index-$range[0];
				$chars = "";
				$current_num = $index_within_range;
				$modulus = strlen($voting_characters);
				for ($i=0; $i<$length-1; $i++) {
					$remainder = $current_num%$modulus;
					$current_num = floor($current_num/$modulus);
					$chars .= $voting_characters[$remainder];
				}
				$firstchar_index = $firstchar_offset+$current_num;
				$chars .= $voting_characters[(int)$firstchar_index];
			}
			$firstchar_offset += $firstchar_divisions[$length-1];
		}
		
		return strrev($chars);
	}

	public function addr_text_to_vote_identifier($addr_text) {
		$defs = $this->voting_character_definitions();
		$firstchar_divisions = $defs['firstchar_divisions'];
		$voting_characters = $defs['voting_characters'];
		$length_to_range = $defs['length_to_range'];
		
		if ($this->get_site_constant('identifier_case_sensitive') == 0) $addr_text = strtolower($addr_text);
		
		$firstchar_pos = $this->get_site_constant('identifier_first_char');
		if (empty($firstchar_pos) || $firstchar_pos != (int) $firstchar_pos) die("Error: site constant 'identifier_first_char' must be defined.\n");
		
		$firstchar = $addr_text[$firstchar_pos];
		$firstchar_index = strpos($voting_characters, $firstchar);
		$firstchar_offset = 0;
		
		foreach ($length_to_range as $length => $range) {
			$firstchar_begin_index = $firstchar_offset;
			$firstchar_end_index = $firstchar_begin_index+$firstchar_divisions[$length-1]-1;
			if ($firstchar_index >= $firstchar_begin_index && $firstchar_index <= $firstchar_end_index) {
				return substr($addr_text, $firstchar_pos, $length);
			}
			$firstchar_offset = $firstchar_end_index+1;
		}
		return substr($addr_text, $firstchar_pos, 1);
	}
	
	public function fetch_account_by_id($account_id) {
		$q = "SELECT * FROM currency_accounts WHERE account_id='".$account_id."';";
		$r = $this->run_query($q);
		return $r->fetch();
	}
	
	public function event_verbatim_vars() {
		return array(
			array('int', 'event_index', true),
			array('int', 'next_event_index', true),
			array('int', 'event_starting_block', true),
			array('int', 'event_final_block', true),
			array('int', 'event_payout_block', true),
			array('string', 'event_name', false),
			array('string', 'option_block_rule', false),
			array('string', 'option_name', false),
			array('string', 'option_name_plural', false),
			array('int', 'outcome_index', true)
		);
	}
	
	public function game_definition_verbatim_vars() {
		return array(
			array('float', 'protocol_version', true),
			array('string', 'url_identifier', false),
			array('string', 'name', false),
			array('string', 'event_type_name', false),
			array('string', 'event_type_name_plural', false),
			array('string', 'event_rule', true),
			array('int', 'event_entity_type_id', true),
			array('int', 'option_group_id', true),
			array('int', 'events_per_round', true),
			array('string', 'inflation', true),
			array('float', 'exponential_inflation_rate', true),
			array('int', 'pos_reward', true),
			array('int', 'round_length', true),
			array('int', 'maturity', true),
			array('string', 'payout_weight', true),
			array('int', 'final_round', true),
			array('string', 'buyin_policy', true),
			array('float', 'game_buyin_cap', true),
			array('string', 'sellout_policy', true),
			array('int', 'sellout_confirmations', true),
			array('string', 'coin_name', false),
			array('string', 'coin_name_plural', false),
			array('string', 'coin_abbreviation', false),
			array('string', 'escrow_address', true),
			array('string', 'genesis_tx_hash', true),
			array('int', 'genesis_amount', true),
			array('int', 'game_starting_block', true),
			array('string', 'game_winning_rule', true),
			array('string', 'game_winning_field', true),
			array('float', 'game_winning_inflation', true),
			array('string', 'default_vote_effectiveness_function', true),
			array('float', 'default_max_voting_fraction', true),
			array('int', 'default_option_max_width', false),
			array('int', 'default_payout_block_delay', true)
		);
	}
	
	public function migrate_game_definitions($game, $initial_game_def_hash, $new_game_def_hash) {
		$initial_game_def_r = $this->run_query("SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($initial_game_def_hash).";");
		
		if ($initial_game_def_r->rowCount() == 1) {
			$initial_game_def = $initial_game_def_r->fetch();
			$initial_game_obj = json_decode($initial_game_def['definition']);
			
			$new_game_def_r = $this->run_query("SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($new_game_def_hash).";");
			
			if ($new_game_def_r->rowCount() == 1) {
				$new_game_def = $new_game_def_r->fetch();
				$new_game_obj = json_decode($new_game_def['definition']);
				
				$min_starting_block = min($initial_game_obj->game_starting_block, $new_game_obj->game_starting_block);
				
				$verbatim_vars = $this->game_definition_verbatim_vars();
				$reset_block = false;
				
				for ($i=0; $i<count($verbatim_vars); $i++) {
					$var = $verbatim_vars[$i];
					if ($var[2] == true) {
						if ($initial_game_obj->$var[1] != $new_game_obj->$var[1]) {
							if ($reset_block === false) $reset_block = $min_starting_block;
							$reset_block = min($reset_block, $min_starting_block);
							
							$q = "UPDATE games SET ".$var[2]."=".$this->quote_escape($new_game_obj->$var[1])." WHERE game_id=".$game->db_game['game_id'].";";
							$r = $this->run_query($q);
						}
					}
				}
				
				$event_verbatim_vars = $this->event_verbatim_vars();
				
				$matched_events = min(count($initial_game_obj->events), count($new_game_obj->events));
				$new_events = count($new_game_obj->events);
				
				for ($i=0; $i<$matched_events; $i++) {
					if ($new_game_obj->events[$i] != $initial_game_obj->events[$i]) {
						if ($reset_block === false) $reset_block = $new_game_obj->events[$i]->event_starting_block;
						$reset_block = min($reset_block, $new_game_obj->events[$i]->event_starting_block, $initial_game_obj->events[$i]->event_starting_block);
					}
				}
				
				if ($new_events > $matched_events) {
					if ($reset_block === false) $reset_block = $new_game_obj->events[$matched_events]->db_event['event_starting_block'];
					
					for ($i=$matched_events; $i<$new_events; $i++) {
						$this->check_set_gde($game, $i, $new_game_obj->events[$i], $event_verbatim_vars);
					}
				}
				
				if ($reset_block) {
					$game->delete_from_block($reset_block);
					$game->update_db_game();
					$game->ensure_events_until_block($game->blockchain->last_block_id()+1);
					$game->load_current_events();
					$game->sync();
				}
			}
			else echo "No match for ".$new_game_def_hash."<br/>\n";
		}
		else echo "No match for ".$initial_game_def_hash."<br/>\n";
	}
	
	public function check_set_gde(&$game, $event_index, $gde, $event_verbatim_vars) {
		$db_gde = false;
		
		$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$event_index."';";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			$db_gde = $r->fetch();
			$q = "UPDATE game_defined_events SET ";
		}
		else {
			$q = "INSERT INTO game_defined_events SET game_id='".$game->db_game['game_id']."', ";
		}
		
		for ($j=0; $j<count($event_verbatim_vars); $j++) {
			$var_type = $event_verbatim_vars[$j][0];
			$var_val = (string) $gde[$event_verbatim_vars[$j][1]];
			
			if ($var_val === "" || strtolower($var_val) == "null") $escaped_var_val = "NULL";
			else $escaped_var_val = $this->quote_escape($var_val);
			
			$q .= $event_verbatim_vars[$j][1]."=".$escaped_var_val.", ";
		}
		$q = substr($q, 0, strlen($q)-2);
		
		if ($db_gde) {
			$q .= " WHERE game_defined_event_id='".$db_gde['game_defined_event_id']."'";
		}
		$q .= ";";
		$r = $this->run_query($q);
		
		$possible_outcomes = $gde['possible_outcomes'];
		
		$this->run_query("DELETE FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$event_index."';");
		
		for ($k=0; $k<count($possible_outcomes); $k++) {
			$q = "INSERT INTO game_defined_options SET game_id='".$game->db_game['game_id']."', event_index='".$event_index."', option_index='".$k."', name=".$this->quote_escape($possible_outcomes[$k]['title']);
			if (!empty($possible_outcomes[$k]['entity_id'])) $q .= ", entity_id='".$possible_outcomes[$k]['entity_id']."'";
			$q .= ";";
			$r = $this->run_query($q);
		}
	}
	
	public function check_set_game_definition($definition_hash, $game_definition) {
		$q = "SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($definition_hash).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "INSERT INTO game_definitions SET definition_hash=".$this->quote_escape($definition_hash).", definition=".$this->quote_escape(json_encode($game_definition, JSON_PRETTY_PRINT)).";";
			$r = $this->run_query($q);
		}
	}
	
	public function check_set_module($module_name) {
		$q = "SELECT * FROM modules WHERE module_name=".$this->quote_escape($module_name).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$module = $r->fetch();
		}
		else {
			$q = "INSERT INTO modules SET module_name=".$this->quote_escape($module_name).";";
			$r = $this->run_query($q);
			$module_id = $this->last_insert_id();
			
			$module = $this->run_query("SELECT * FROM modules WHERE module_id=".$module_id.";")->fetch();
		}
		
		return $module;
	}
	
	public function create_game_from_definition(&$game_definition, &$thisuser, $module, &$error_message, $db_game) {
		$game_def = json_decode($game_definition) or die("Error: the game definition you entered could not be imported.<br/>Please make sure to enter properly formatted JSON.<br/><a href=\"/import/\">Try again</a>");
		
		$error_message = "";
		
		if ($game_def->blockchain_identifier != "") {
			if ($game_def->blockchain_identifier == "private") {
				$chain_id = $this->random_string(6);
				$url_identifier = "private-chain-".$chain_id;
				$chain_pow_reward = 25*pow(10,8);
				$q = "INSERT INTO blockchains SET online=1, p2p_mode='none', blockchain_name='Private Chain', url_identifier='".$url_identifier."', coin_name='chaincoin', coin_name_plural='chaincoins', seconds_per_block=10, initial_pow_reward=".$chain_pow_reward.";";
				$r = $this->run_query($q);
				$blockchain_id = $this->last_insert_id();
				$new_blockchain = new Blockchain($this, $blockchain_id);
				if ($thisuser) $new_blockchain->set_blockchain_creator($thisuser);
				$game_def->blockchain_identifier = $url_identifier;
			}
			
			$q = "SELECT * FROM blockchains WHERE url_identifier=".$this->quote_escape($game_def->blockchain_identifier).";";
			$r = $this->run_query($q);
			
			if ($r->rowCount() == 1) {
				$db_blockchain = $r->fetch();
				$blockchain = new Blockchain($this, $db_blockchain['blockchain_id']);
				
				$coin_rpc = false;
				
				$game_def->url_identifier = $this->normalize_uri_part($game_def->url_identifier);
				
				if ($game_def->url_identifier != "") {
					$verbatim_vars = $this->game_definition_verbatim_vars();
					
					$q = "SELECT * FROM games WHERE url_identifier=".$this->quote_escape($game_def->url_identifier).";";
					$r = $this->run_query($q);
					
					if (!empty($db_game)) {
						$q = "UPDATE games SET seconds_per_block='".$db_blockchain['seconds_per_block']."'";
						for ($i=0; $i<count($verbatim_vars); $i++) {
							$var_type = $verbatim_vars[$i][0];
							$var_name = $verbatim_vars[$i][1];
							$q .= ", ".$var_name."=".$this->quote_escape($game_def->$var_name);
						}
						$q .= " WHERE game_id='".$db_game['game_id']."';";
						$r = $this->run_query($q);
						
						$new_game = new Game($blockchain, $db_game['game_id']);
					}
					else if ($r->rowCount() == 0) {
						$q = "INSERT INTO games SET ";
						if ($module) $q .= "module=".$this->quote_escape($module).", ";
						if ($thisuser) $q .= "creator_id='".$thisuser->db_user['user_id']."', ";
						$q .= "blockchain_id='".$db_blockchain['blockchain_id']."', game_status='published', featured=1, seconds_per_block='".$db_blockchain['seconds_per_block']."', start_condition='fixed_block', giveaway_status='public_free', invite_currency='".$blockchain->currency_id()."', logo_image_id=34";
						for ($i=0; $i<count($verbatim_vars); $i++) {
							$var_type = $verbatim_vars[$i][0];
							$var_name = $verbatim_vars[$i][1];
							$q .= ", ".$var_name."=".$this->quote_escape($game_def->$var_name);
						}
						$q .= ";";
						$r = $this->run_query($q);
						$new_game_id = $this->last_insert_id();
						
						$new_game = new Game($blockchain, $new_game_id);
						
						if ($new_game->db_game['p2p_mode'] == "none") {
							if ($thisuser) $user_game = $thisuser->ensure_user_in_game($new_game);
							
							if (empty($new_game->db_game['genesis_tx_hash'])) {
								$game_genesis_tx_hash = $this->random_string(64);
								
								$q = "UPDATE games SET genesis_tx_hash=".$this->quote_escape($game_genesis_tx_hash)." WHERE game_id='".$new_game->db_game['game_id']."';";
								$r = $this->run_query($q);
								$new_game->db_game['genesis_tx_hash'] = $game_genesis_tx_hash;
							}
							else $game_genesis_tx_hash = $new_game->db_game['genesis_tx_hash'];
							
							$new_game->genesis_hash = $game_genesis_tx_hash;
							if ($thisuser) $new_game->user_game = $user_game;
							
							$blockchain->add_genesis_block($new_game);
							
							$block_hash = $this->random_string(64);
							$blockchain->private_add_block($new_game, $block_hash, 1);
						}
						else {
							try {
								$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
								$test_rpc = $coin_rpc->getinfo();
							} catch (Exception $e) {
								echo "Error, skipped ".$db_blockchain['blockchain_name']." because RPC connection failed.<br/>\n";
								die();
							}
						}
					}
					
					if ($coin_rpc) {
						$blockchain->set_first_required_block($coin_rpc);
					}
					
					if ($new_game->db_game['event_rule'] == "game_definition") {
						$q = "DELETE FROM game_defined_events WHERE game_id='".$new_game->db_game['game_id']."';";
						$r = $this->run_query($q);
						
						$q = "DELETE FROM game_defined_options WHERE game_id='".$new_game->db_game['game_id']."';";
						$r = $this->run_query($q);
						
						$game_defined_events = $game_def->events;
						$game_event_params = $this->event_verbatim_vars();
						
						for ($i=0; $i<count($game_defined_events); $i++) {
							$q = "INSERT INTO game_defined_events SET game_id='".$new_game->db_game['game_id']."'";
							
							for ($j=0; $j<count($game_event_params); $j++) {
								$var_type = $game_event_params[$j][0];
								eval('$var_val = (string) $game_defined_events[$i]->'.$game_event_params[$j][1].';');
								
								if ($var_val === "" || strtolower($var_val) == "null") $escaped_var_val = "NULL";
								else $escaped_var_val = $this->quote_escape($var_val);
								
								$q .= ", ".$game_event_params[$j][1]."=".$escaped_var_val;
							}
							$q .= ";";
							$r = $this->run_query($q);
							
							$possible_outcomes = $game_defined_events[$i]->possible_outcomes;
							
							for ($k=0; $k<count($game_defined_events[$i]->possible_outcomes); $k++) {
								$q = "INSERT INTO game_defined_options SET game_id='".$new_game->db_game['game_id']."', event_index='".$i."', option_index='".$k."', name=".$this->quote_escape($possible_outcomes[$k]->title);
								if (!empty($possible_outcomes[$k]->entity_id)) $q .= ", entity_id=".$possible_outcomes[$k]->entity_id;
								$q .= ";";
								$r = $this->run_query($q);
							}
						}
					}
					
					$new_game->check_set_game_definition();
					
					$error_message = false;
					return $new_game;
				}
				else $error_message = "Error, invalid game URL identifier.";
			}
			else $error_message = "Error, failed to identify the right blockchain.";
		}
		else $error_message = "Error, failed to identify the right blockchain.";
		
		return false;
	}
	
	public function check_set_option_group($description, $singular_form, $plural_form) {
		$group_q = "SELECT * FROM option_groups WHERE description=".$this->quote_escape($description).";";
		$group_r = $this->run_query($group_q);
		
		if ($group_r->rowCount() > 0) {
			return $group_r->fetch();
		}
		else {
			$group_q = "INSERT INTO option_groups SET description=".$this->quote_escape($description).", option_name=".$this->quote_escape($singular_form).", option_name_plural=".$this->quote_escape($plural_form).";";
			$group_r = $this->run_query($group_q);
			$group_id = $this->last_insert_id();
			$group_q = "SELECT * FROM option_groups WHERE group_id=".$group_id.";";
			return $this->run_query($group_q)->fetch();
		}
	}
	
	public function check_set_entity($entity_type_id, $name) {
		$q = "SELECT * FROM entities WHERE ";
		if ($entity_type_id) $q .= "entity_type_id='".$entity_type_id."' AND ";
		$q .= "entity_name=".$this->quote_escape($name).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else {
			$q = "INSERT INTO entities SET entity_name=".$this->quote_escape($name);
			if ($entity_type_id) $q .= ", entity_type_id='".$entity_type_id."'";
			$q .= ";";
			$r = $this->run_query($q);
			$entity_id = $this->last_insert_id();
			$q = "SELECT * FROM entities WHERE entity_id=".$entity_id.";";
			return $this->run_query($q)->fetch();
		}
	}
	
	public function check_set_entity_type($name) {
		$q = "SELECT * FROM entity_types WHERE entity_name=".$this->quote_escape($name).";";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else {
			$q = "INSERT INTO entity_types SET entity_type_id='".$entity_type_id."', entity_name=".$this->quote_escape($name).";";
			$r = $this->run_query($q);
			$entity_type_id = $this->last_insert_id();
			$q = "SELECT * FROM entity_types WHERE entity_type_id=".$entity_type_id.";";
			return $this->run_query($q)->fetch();
		}
	}
	
	public function async_fetch_url($url, $require_now) {
		$q = "SELECT * FROM cached_urls WHERE url=".$this->quote_escape($url).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$cached_url = $r->fetch();
			
			if ($require_now && empty($cached_url['time_fetched'])) {
				$start_load_time = microtime(true);
				$http_response = file_get_contents($cached_url['url']) or die("Failed to fetch url: $url");
				$q = "UPDATE cached_urls SET cached_result=".$this->quote_escape($http_response).", time_fetched='".time()."', load_time='".(microtime(true)-$start_load_time)."' WHERE cached_url_id='".$cached_url['cached_url_id']."';";
				$r = $this->run_query($q);
				
				$q = "SELECT * FROM cached_urls WHERE cached_url_id=".$cached_url['cached_url_id'].";";
				$r = $this->run_query($q);
				$cached_url = $r->fetch();
			}
		}
		else {
			$q = "INSERT INTO cached_urls SET url=".$this->quote_escape($url).", time_created='".time()."'";
			if ($require_now) {
				$start_load_time = microtime(true);
				$http_response = file_get_contents($url) or die("Failed to fetch url: $url");
				$q .= ", time_fetched='".time()."', cached_result=".$this->quote_escape($http_response).", load_time='".(microtime(true)-$start_load_time)."'";
			}
			$q .= ";";
			$r = $this->run_query($q);
			$cached_url_id = $this->last_insert_id();
			
			$q = "SELECT * FROM cached_urls WHERE cached_url_id=".$cached_url_id.";";
			$r = $this->run_query($q);
			$cached_url = $r->fetch();
		}
		
		return $cached_url;
	}
}
?>