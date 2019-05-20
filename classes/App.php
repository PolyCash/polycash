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
		if ($GLOBALS['show_query_errors'] == TRUE) {
			$result = $this->dbh->query($query) or die("Query error: ".$this->dbh->errorInfo()[2].": (".strlen($query).") ".substr($query, 0, min(strlen($query), 500))."\n");
		}
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

	public function min_excluding_false($some_array) {
		$min_value = false;
		for ($i=0; $i<count($some_array); $i++) {
			if ((string)$some_array[$i] !== "") {
				if ($min_value === false) $min_value = $some_array[$i];
				else $min_value = min($min_value, $some_array[$i]);
			}
		}
		return $min_value;
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
		$characters = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
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
	
	public function random_hex_string($length) {
		$characters = "0123456789abcdef";
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
	
	public function random_number($length) {
		$characters = "0123456789";
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
	
	public function fetch_db_game_by_id($game_id) {
		$game_q = "SELECT * FROM games WHERE game_id='".((int)$game_id)."';";
		$game_r = $this->run_query($game_q);
		if ($game_r->rowCount() > 0) {
			return $game_r->fetch();
		}
		else return false;
	}
	
	public function fetch_db_game_by_identifier($url_identifier) {
		$game_q = "SELECT * FROM games WHERE url_identifier=".$this->quote_escape($url_identifier).";";
		$game_r = $this->run_query($game_q);
		
		if ($game_r->rowCount() > 0) {
			return $game_r->fetch();
		}
		else return false;
	}
	
	public function fetch_transaction_by_id($transaction_id) {
		$q = "SELECT * FROM transactions WHERE transaction_id='".((int)$transaction_id)."';";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) return $r->fetch();
		else return false;
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
	
	public function safe_merge_argv_to_request(&$argv, &$allowed_params) {
		if ($argv && $this->running_from_commandline()) {
			$arg_i = 0;
			foreach ($argv as $arg) {
				if ($arg_i > 0) {
					$arg_parts = explode("=", $arg);
					if(count($arg_parts) == 2 && in_array($arg_parts[0], $allowed_params)) {
						$_REQUEST[$arg_parts[0]] = $arg_parts[1];
					}
				}
				$arg_i++;
			}
		}
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
	
	public function start_regular_background_processes() {
		$html = "";
		$process_count = 0;
		
		$pipe_config = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);
		$pipes = array();
		
		$last_script_run_time = (int) $this->get_site_constant("last_script_run_time");
		
		if (PHP_OS == "WINNT") $script_path_name = dirname(dirname(__FILE__));
		else $script_path_name = realpath(dirname(dirname(__FILE__)));
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_blocks.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_games.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$block_loading_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($block_loading_process)) $process_count++;
		else $html .= "Failed to start a process for loading blocks.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_main.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$main_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($main_process)) $process_count++;
		else $html .= "Failed to start the main process.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/minutely_check_payments.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$payments_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($payments_process)) $process_count++;
		else $html .= "Failed to start a process for processing payments.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/address_miner.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$address_miner_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($address_miner_process)) $process_count++;
		else $html .= "Failed to start a process for mining addresses.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/fetch_currency_prices.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$currency_prices_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($currency_prices_process)) $process_count++;
		else $html .= "Failed to start a process for updating currency prices.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/load_cached_urls.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$cached_url_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($cached_url_process)) $process_count++;
		else $html .= "Failed to start a process for loading cached urls.<br/>\n";
		sleep(0.1);
		
		$cmd = $this->php_binary_location().' "'.$script_path_name.'/cron/ensure_user_addresses.php"';
		if (PHP_OS == "WINNT") $cmd .= " > NUL 2>&1";
		else $cmd .= " 2>&1 >/dev/null";
		$ensure_addresses_process = proc_open($cmd, $pipe_config, $pipes);
		if (is_resource($ensure_addresses_process)) $process_count++;
		else $html .= "Failed to start a process for ensuring user addresses.<br/>\n";
		
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
		
		$debug_text = $game->ensure_events_until_block($event_block);
	}
	
	public function get_redirect_url($url) {
		$url = strip_tags($url);
		
		$q = "SELECT * FROM redirect_urls WHERE url=".$this->quote_escape($url).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$redirect_url = $r->fetch();
		}
		else {
			$redirect_key = $this->random_string(24);
			
			$q = "INSERT INTO redirect_urls SET redirect_key=".$this->quote_escape($redirect_key).", url=".$this->quote_escape($url).", time_created='".time()."';";
			$r = $this->run_query($q);
			$redirect_url_id = $this->last_insert_id();
			
			$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
			$r = $this->run_query($q);
			$redirect_url = $r->fetch();
		}
		return $redirect_url;
	}

	public function get_redirect_by_key($redirect_key) {
		$q = "SELECT * FROM redirect_urls WHERE redirect_key=".$this->quote_escape($redirect_key).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$redirect_url = $r->fetch();
			return $redirect_url;
		}
		else $redirect_url = false;
	}
	
	public function mail_async($email, $from_name, $from, $subject, $message, $bcc, $cc, $delivery_key) {
		if (empty($delivery_key)) $delivery_key = $this->random_string(16);
		
		$q = "INSERT INTO async_email_deliveries SET to_email=".$this->quote_escape($email).", from_name=".$this->quote_escape($from_name).", from_email=".$this->quote_escape($from).", subject=".$this->quote_escape($subject).", message=".$this->quote_escape($message).", bcc=".$this->quote_escape($bcc).", cc=".$this->quote_escape($cc).", delivery_key=".$this->quote_escape($delivery_key).", time_created='".time()."';";
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
		$number_digits = floor(log10($number));
		$returnval = (pow(10, $number_digits - $significant_digits + 1)) * floor($number/(pow(10, $number_digits - $significant_digits + 1)));
		return $returnval;
	}

	public function format_bignum($number) {
		if ($number >= 0) $sign = "";
		else $sign = "-";
		
		$number = abs($number);
		if ($number > 1) $number = $this->to_significant_digits($number, 5);
		
		if ($number >= pow(10, 9)) {
			return $sign.($number/pow(10, 9))."B";
		}
		else if ($number >= pow(10, 6)) {
			return $sign.($number/pow(10, 6))."M";
		}
		else if ($number > pow(10, 5)) {
			return $sign.($number/pow(10, 3))."k";
		}
		else return $sign.rtrim(rtrim(number_format(sprintf('%.8F', $number), 8), '0'), ".");
	}
	
	public function round_to($number, $min_decimals, $target_sigfigs, $format_string) {
		$decimals = $target_sigfigs-1-floor(log10($number));
		if ($min_decimals !== false) $decimals = max($min_decimals, $decimals);
		if ($format_string) return number_format($number, $decimals);
		else return round($number, $decimals);
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
	
	public function try_apply_invite_key($user_id, $invite_key, &$invite_game, &$user_game) {
		$reload_page = false;
		$invite_key = $this->quote_escape($invite_key);
		
		$q = "SELECT * FROM game_invitations WHERE invitation_key=".$invite_key.";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() == 1) {
			$invitation = $r->fetch();
			
			if ($invitation['used'] == 0 && $invitation['used_user_id'] == "" && $invitation['used_time'] == 0) {
				$db_game = $this->fetch_db_game_by_id($invitation['game_id']);
				
				if ($db_game) {
					$qq = "UPDATE game_invitations SET used_user_id='".$user_id."', used_time='".time()."', used=1";
					if ($GLOBALS['pageview_tracking_enabled']) $q .= ", used_ip='".$_SERVER['REMOTE_ADDR']."'";
					$qq .= " WHERE invitation_id='".$invitation['invitation_id']."';";
					$rr = $this->run_query($qq);
					
					$user = new User($this, $user_id);
					$blockchain = new Blockchain($this, $db_game['blockchain_id']);
					$invite_game = new Game($blockchain, $invitation['game_id']);
					
					$user_game = $user->ensure_user_in_game($invite_game, false);
					
					return true;
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function send_apply_invitation(&$db_user, &$invitation) {
		$invite_game = false;
		$user_game = false;
		$this->try_apply_invite_key($db_user['user_id'], $invitation['invitation_key'], $invite_game, $user_game);
		$invite_game->give_faucet_to_user($user_game);
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
			else $str .= $minutes." minute";
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
		return $this->make_alphanumeric(str_replace(" ", "-", strtolower($uri_part)), "-().");
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
		
		if (in_array($login_url_parts[0], array("wallet", "manage")) && count($login_url_parts) > 1) {
			return $this->fetch_db_game_by_identifier($login_url_parts[1]);
		}
		else return false;
	}
	
	public function currency_price_at_time($currency_id, $ref_currency_id, $ref_time) {
		$q = "SELECT * FROM currency_prices WHERE currency_id='".$currency_id."' AND reference_currency_id='".$ref_currency_id."' AND time_added <= ".$ref_time." ORDER BY time_added DESC LIMIT 1;";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function currency_price_after_time($currency_id, $ref_currency_id, $ref_time, $not_after_time) {
		$q = "SELECT * FROM currency_prices WHERE currency_id='".$currency_id."' AND reference_currency_id='".$ref_currency_id."' AND time_added >= ".$ref_time." AND time_added<=".$not_after_time." ORDER BY time_added ASC LIMIT 1;";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
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
			echo "(".strlen($api_response_raw).") ".$currency_url['url']."<br/>\n";
			$qq = "SELECT * FROM currencies WHERE oracle_url_id='".$currency_url['oracle_url_id']."';";
			$rr = $this->run_query($qq);
			
			while ($currency = $rr->fetch()) {
				if ($currency_url['format_id'] == 2) {
					$api_response = json_decode($api_response_raw);
					$price = $api_response->USD->bid;
				}
				else if ($currency_url['format_id'] == 1) {
					$api_response = json_decode($api_response_raw);
					if (!empty($api_response->rates)) {
						$api_rates = (array) $api_response->rates;
						$price = 1/($api_rates[$currency['abbreviation']]);
					}
				}
				else if ($currency_url['format_id'] == 3) {
					$html_data = $this->first_snippet_between($api_response_raw, '<div id="currency-exchange-rates"', '></div>');
					$price = (float) $this->first_snippet_between($html_data, 'data-btc="', '"');
				}
				
				if ($price > 0) {
					$qqq = "INSERT INTO currency_prices SET currency_id='".$currency['currency_id']."', reference_currency_id='".$reference_currency_id."', price='".$price."', time_added='".time()."';";
					$rrr = $this->run_query($qqq);
				}
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
		if ($numerator_currency_id == $denominator_currency_id) {
			$returnvals['conversion_rate'] = 1;
		}
		else {
			$latest_numerator_rate = $this->latest_currency_price($numerator_currency_id);
			$latest_denominator_rate = $this->latest_currency_price($denominator_currency_id);

			$returnvals['numerator_price_id'] = $latest_numerator_rate['price_id'];
			$returnvals['denominator_price_id'] = $latest_denominator_rate['price_id'];
			$returnvals['conversion_rate'] = round(pow(10,8)*$latest_denominator_rate['price']/$latest_numerator_rate['price'])/pow(10,8);
		}
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
	
	public function new_currency_invoice(&$account, $pay_currency_id, $pay_amount, &$user, &$user_game, $invoice_type) {
		$address_key = $this->new_address_key($account['currency_id'], $account);
		
		$time = time();
		$q = "INSERT INTO currency_invoices SET time_created='".$time."', pay_currency_id='".$pay_currency_id."'";
		if ($address_key) $q .= ", address_id='".$address_key['address_id']."'";
		$q .= ", expire_time='".($time+$GLOBALS['invoice_expiration_seconds'])."', user_game_id='".$user_game['user_game_id']."', invoice_type='".$invoice_type."', status='unpaid', invoice_key_string='".$this->random_string(32)."', pay_amount='".$pay_amount."';";
		$r = $this->run_query($q);
		$invoice_id = $this->last_insert_id();
		
		$q = "SELECT * FROM currency_invoices WHERE invoice_id='".$invoice_id."';";
		$r = $this->run_query($q);
		return $r->fetch();
	}
	
	public function new_address_key($currency_id, &$account) {
		$reject_destroy_addresses = true;
		
		$q = "SELECT * FROM currencies WHERE currency_id='".$currency_id."';";
		$r = $this->run_query($q);
		$currency = $r->fetch();
		
		if ($currency['blockchain_id'] > 0) {
			$blockchain = new Blockchain($this, $currency['blockchain_id']);
			
			if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
				if (empty($blockchain->db_blockchain['rpc_username']) || empty($blockchain->db_blockchain['rpc_password'])) $save_method = "skip";
				else {
					$blockchain->load_coin_rpc();
					
					if ($blockchain->coin_rpc) {
						try {
							$address_text = $blockchain->coin_rpc->getnewaddress();
							$save_method = "wallet.dat";
						}
						catch (Exception $e) {
							$save_method = "skip";
						}
					}
					else $save_method = "skip";
				}
			}
			else {
				$address_text = $this->random_string(34);
				$save_method = "fake";
			}
			
			if ($save_method == "skip") return false;
			else {
				$db_address = $blockchain->create_or_fetch_address($address_text, true, false, false, true, false);
				
				if ($reject_destroy_addresses && $db_address['is_destroy_address'] == 1) return $this->new_address_key($currency_id, $account, $reject_destroy_addresses);
				else {
					if ($account) {
						$q = "UPDATE addresses SET user_id='".$account['user_id']."' WHERE address_id='".$db_address['address_id']."';";
						$r = $this->run_query($q);
						$q = "UPDATE transaction_ios SET user_id='".$account['user_id']."' WHERE address_id='".$db_address['address_id']."';";
						$r = $this->run_query($q);
					}
					
					$q = "SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.address_id='".$db_address['address_id']."';";
					$r = $this->run_query($q);
					
					if ($r->rowCount() > 0) {
						$address_key = $r->fetch();
						
						if ($account) {
							$q = "UPDATE address_keys SET account_id='".$account['account_id']."' WHERE address_key_id='".$address_key['address_key_id']."';";
							$r = $this->run_query($q);
							
							$address_key['account_id'] = $account['account_id'];
						}
					}
					else {
						$q = "INSERT INTO address_keys SET currency_id='".$blockchain->currency_id()."', address_id='".$db_address['address_id']."', save_method='".$save_method."', pub_key=".$this->quote_escape($address_text);
						if (!empty($keySet['privWIF'])) $q .= ", priv_key=".$this->quote_escape($keySet['privWIF']);
						if (!empty($account)) $q .= ", account_id='".$account['account_id']."'";
						$q .= ";";
						$r = $this->run_query($q);
						$address_key_id = $this->last_insert_id();
						
						$address_key = $this->run_query("SELECT * FROM addresses a JOIN address_keys ak ON a.address_id=ak.address_id WHERE ak.address_key_id='".$address_key_id."';")->fetch();
					}
					
					return $address_key;
				}
			}
		}
		else return false;
	}
	
	public function decimal_to_float($number) {
		if (strpos($number, ".") === false) return $number;
		else return rtrim(rtrim($number, '0'), '.');
	}
	
	public function display_games($category_id, $game_id) {
		echo '<div class="paragraph">';
		$q = "SELECT g.*, c.short_name AS currency_short_name FROM games g LEFT JOIN currencies c ON g.invite_currency=c.currency_id WHERE g.featured=1 AND (g.game_status='published' OR g.game_status='running')";
		if (!empty($category_id)) $q .= " AND g.category_id=".$category_id;
		if (!empty($game_id)) $q .= " AND g.game_id=".$game_id;
		$q .= " ORDER BY g.featured_score DESC, g.game_id DESC;";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			$cell_width = 12;
			
			$counter = 0;
			echo '<div class="row">';
			
			while ($db_game = $r->fetch()) {
				$blockchain = new Blockchain($this, $db_game['blockchain_id']);
				$featured_game = new Game($blockchain, $db_game['game_id']);
				$last_block_id = $blockchain->last_block_id();
				$mining_block_id = $last_block_id+1;
				$db_last_block = $blockchain->fetch_block_by_id($last_block_id);
				$current_round_id = $featured_game->block_to_round($mining_block_id);
				
				$filter_arr = false;
				$user = false;
				$event_ids = "";
				$new_event_js = $featured_game->new_event_js($counter, $user, $filter_arr, $event_ids);
				?>
				<script type="text/javascript">
				games.push(new Game(<?php
					echo $db_game['game_id'];
					echo ', false';
					echo ', false';
					echo ', ""';
					echo ', "'.$db_game['payout_weight'].'"';
					echo ', '.$db_game['round_length'];
					echo ', 0';
					echo ', "'.$db_game['url_identifier'].'"';
					echo ', "'.$db_game['coin_name'].'"';
					echo ', "'.$db_game['coin_name_plural'].'"';
					echo ', "'.$blockchain->db_blockchain['coin_name'].'"';
					echo ', "'.$blockchain->db_blockchain['coin_name_plural'].'"';
					echo ', "home", "'.$event_ids.'"';
					echo ', "'.$featured_game->logo_image_url().'"';
					echo ', "'.$featured_game->vote_effectiveness_function().'"';
					echo ', "'.$featured_game->effectiveness_param1().'"';
					echo ', "'.$featured_game->blockchain->db_blockchain['seconds_per_block'].'"';
					echo ', "'.$featured_game->db_game['inflation'].'"';
					echo ', "'.$featured_game->db_game['exponential_inflation_rate'].'"';
					echo ', "'.$db_last_block['time_mined'].'"';
					echo ', "'.$featured_game->db_game['decimal_places'].'"';
					echo ', "'.$featured_game->blockchain->db_blockchain['decimal_places'].'"';
					echo ', "'.$db_game['view_mode'].'"';
					echo ', 0';
					echo ', false';
					echo ', "'.$featured_game->db_game['default_betting_mode'].'"';
					echo ', true';
				?>));
				</script>
				<?php
				echo '<div class="col-md-'.$cell_width.'">';
				echo '<center><h1 style="display: inline-block" title="'.$featured_game->game_description().'">'.$featured_game->db_game['name'].'</h1>';
				if ($featured_game->db_game['short_description'] != "") echo "<p>".$featured_game->db_game['short_description']."</p>";
				
				$faucet_io = $featured_game->check_faucet(false);
				
				echo '<p><a href="/'.$featured_game->db_game['url_identifier'].'/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; ';
				if ($faucet_io) echo 'Join now & receive '.$this->format_bignum($faucet_io['colored_amount_sum']/pow(10,$featured_game->db_game['decimal_places'])).' '.$featured_game->db_game['coin_name_plural'];
				else echo 'Play Now';
				echo "</a>";
				echo ' <a href="/explorer/games/'.$featured_game->db_game['url_identifier'].'/events/" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> &nbsp; '.ucwords($featured_game->db_game['event_type_name']).' Results</a>';
				echo "</p>\n";
				
				if ($featured_game->db_game['module'] == "CoinBattles") {
					$featured_game->load_current_events();
					$event = $featured_game->current_events[0];
					list($html, $js) = $featured_game->module->currency_chart($featured_game, $event->db_event['event_starting_block'], false);
					echo '<div style="margin-bottom: 15px;" id="game'.$counter.'_chart_html">'.$html."</div>\n";
					echo '<div id="game'.$counter.'_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
				}
				
				echo '<div id="game'.$counter.'_events" class="game_events game_events_short"></div>'."\n";
				echo '<script type="text/javascript" id="game'.$counter.'_new_event_js">'."\n";
				echo $new_event_js;
				echo '</script>';
				
				echo "<br/>\n";
				echo '<a href="/'.$featured_game->db_game['url_identifier'].'/" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; ';
				if ($faucet_io) echo 'Join now & receive '.$this->format_bignum($faucet_io['colored_amount_sum']/pow(10,$featured_game->db_game['decimal_places'])).' '.$featured_game->db_game['coin_name_plural'];
				else echo 'Play Now';
				echo '</a>';
				echo ' <a href="/explorer/games/'.$featured_game->db_game['url_identifier'].'/events/" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> &nbsp; '.ucwords($featured_game->db_game['event_type_name']).' Results</a>';
				echo "</center><br/>\n";
				
				if ($counter%(12/$cell_width) == 1) echo '</div><div class="row">';
				$counter++;
				echo "</div>\n";
			}
			echo "</div>\n";
		}
		else {
			echo "No public games are running right now.<br/>\n";
		}
		echo "</div>\n";
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
		$start_time = microtime(true);
		$unconfirmed_tx_r = $this->run_query("SELECT * FROM transactions t JOIN blockchains b ON t.blockchain_id=b.blockchain_id WHERE b.online=1 AND t.block_id IS NULL AND t.transaction_desc='transaction' ORDER BY t.blockchain_id ASC;");
		$game_id = false;
		$delete_count = 0;
		
		while ($unconfirmed_tx = $unconfirmed_tx_r->fetch()) {
			$blockchain = new Blockchain($this, $unconfirmed_tx['blockchain_id']);
			
			$coins_in = $this->transaction_coins_in($unconfirmed_tx['transaction_id']);
			$coins_out = $this->transaction_coins_out($unconfirmed_tx['transaction_id']);
			
			if ($coins_in == 0 || $coins_out > $coins_in) {
				$success = $blockchain->delete_transaction($unconfirmed_tx);

				if ($success) $delete_count++;
			}
		}
		return "Took ".(microtime(true)-$start_time)." sec to delete $delete_count unconfirmable transactions.";
	}
	
	public function game_info_table(&$db_game) {
		$html = '<div class="game_info_table">';
		
		$blocks_per_hour = 3600/$db_game['seconds_per_block'];
		$round_reward = ($db_game['pos_reward']+$db_game['pow_reward']*$db_game['round_length'])/pow(10,$db_game['decimal_places']);
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
			
			$game_def = $this->fetch_game_definition($game, "actual");
			$game_def_str = $this->game_def_to_text($game_def);
			$game_def_hash = $this->game_def_to_hash($game_def_str);
			
			$html .= '<div class="row"><div class="col-sm-5">Game definition:</div><div class="col-sm-7"><a href="/explorer/games/'.$game->db_game['url_identifier'].'/definition/?definition_mode=actual">'.$this->shorten_game_def_hash($game_def_hash).'</a></div></div>';
		}
		
		if ($db_game['final_round'] > 0) {
			$html .= '<div class="row"><div class="col-sm-5">Length of game:</div><div class="col-sm-7">';
			$html .= $db_game['final_round']." rounds (".$this->format_seconds($seconds_per_round*$db_game['final_round']).")";
			$html .= "</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Starts on block:</div><div class="col-sm-7"><a href="/explorer/games/'.$db_game['url_identifier'].'/blocks/'.$db_game['game_starting_block'].'">'.$db_game['game_starting_block']."</a></div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Escrow address:</div><div class="col-sm-7" style="font-size: 11px;">';
		if ($db_game['escrow_address'] == "") $html .= "None";
		else $html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/addresses/'.$db_game['escrow_address'].'">'.$db_game['escrow_address'].'</a>';
		$html .= "</div></div>\n";
		
		$genesis_amount_disp = $this->format_bignum($db_game['genesis_amount']/pow(10,$db_game['decimal_places']));
		$html .= '<div class="row"><div class="col-sm-5">Genesis transaction:</div><div class="col-sm-7">';
		$html .= '<a href="/explorer/games/'.$db_game['url_identifier'].'/transactions/'.$db_game['genesis_tx_hash'].'">';
		$html .= $genesis_amount_disp.' ';
		if ($genesis_amount_disp == "1") $html .= $db_game['coin_name'];
		else $html .= $db_game['coin_name_plural'];
		$html .= '</a>';
		$html .= "</div></div>\n";
		
		if ($db_game['game_id'] > 0) {
			$last_block_id = $game->blockchain->last_block_id();
			$current_round = $game->block_to_round($last_block_id+1);
			$coins_per_vote = $this->coins_per_vote($game->db_game);
			$game->refresh_coins_in_existence();
			$game_pending_bets = $game->pending_bets();
			list($vote_supply, $vote_supply_value) = $game->vote_supply($last_block_id, $current_round, $coins_per_vote);
			$coins_in_existence = $game->coins_in_existence($last_block_id);
			
			$circulation_amount_disp = $this->format_bignum($coins_in_existence/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->db_game['coin_name_plural']).' in circulation:</div><div class="col-sm-7">';
			$html .= $circulation_amount_disp.' ';
			if ($circulation_amount_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$pending_bets_disp = $this->format_bignum($game_pending_bets/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Pending bets:</div><div class="col-sm-7">';
			$html .= $pending_bets_disp.' ';
			if ($pending_bets_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$supply_disp = $this->format_bignum($vote_supply_value/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Unrealized '.$game->db_game['coin_name_plural'].':</div><div class="col-sm-7">';
			$html .= $supply_disp.' ';
			if ($supply_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			$unrealized_supply_disp = $this->format_bignum(($coins_in_existence+$vote_supply_value+$game_pending_bets)/pow(10,$db_game['decimal_places']));
			$html .= '<div class="row"><div class="col-sm-5">Greater supply:</div><div class="col-sm-7">';
			$html .= $unrealized_supply_disp.' ';
			if ($unrealized_supply_disp == "1") $html .= $db_game['coin_name'];
			else $html .= $db_game['coin_name_plural'];
			$html .= "</div></div>\n";
			
			if ($db_game['buyin_policy'] != "none") {
				$escrow_amount_disp = $this->format_bignum($game->escrow_value($last_block_id)/pow(10,$db_game['decimal_places']));
				$html .= '<div class="row"><div class="col-sm-5">'.ucwords($game->blockchain->db_blockchain['coin_name_plural']).' in escrow:</div><div class="col-sm-7">';
				$html .= $escrow_amount_disp.' ';
				if ($escrow_amount_disp == "1") $html .= $game->blockchain->db_blockchain['coin_name'];
				else $html .= $game->blockchain->db_blockchain['coin_name_plural'];
				$html .= "</div></div>\n";
				
				$exchange_rate_disp = $this->format_bignum($coins_in_existence/$game->escrow_value($last_block_id));
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
		else {
			$html .= "Exponential (".(100*$db_game['exponential_inflation_rate'])."% per round)<br/>";
			$html .= $this->format_bignum($this->votes_per_coin($db_game))." ".str_replace("_", " ", $db_game['payout_weight'])."s per ".$db_game['coin_name'];
		}
		$html .= "</div></div>\n";
		
		$total_inflation_pct = $this->game_final_inflation_pct($db_game);
		if ($total_inflation_pct) {
			$html .= '<div class="row"><div class="col-sm-5">Potential inflation:</div><div class="col-sm-7">'.number_format($total_inflation_pct)."%</div></div>\n";
		}
		
		$html .= '<div class="row"><div class="col-sm-5">Distribution:</div><div class="col-sm-7">';
		if ($db_game['inflation'] == "linear") $html .= $this->format_bignum($db_game['pos_reward']/pow(10,$db_game['decimal_places']))." to holders, ".$this->format_bignum($db_game['pow_reward']*$db_game['round_length']/pow(10,$db_game['decimal_places']))." to miners";
		else $html .= (100 - 100*$db_game['exponential_inflation_minershare'])."% to holders, ".(100*$db_game['exponential_inflation_minershare'])."% to miners";
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Blocks per round:</div><div class="col-sm-7">'.$db_game['round_length']."</div></div>\n";
		
		$average_seconds_per_block = $blockchain->seconds_per_block('average');
		$html .= '<div class="row"><div class="col-sm-5">Block time:</div><div class="col-sm-7">'.$this->format_seconds($db_game['seconds_per_block']);
		if ($blockchain && $average_seconds_per_block != $db_game['seconds_per_block']) $html .= " to ".$this->format_seconds($average_seconds_per_block);
		$html .= "</div></div>\n";
		
		$html .= '<div class="row"><div class="col-sm-5">Time per round:</div><div class="col-sm-7">'.$this->format_seconds($db_game['round_length']*$db_game['seconds_per_block']);
		if ($blockchain && $average_seconds_per_block != $db_game['seconds_per_block']) $html .= " to ".$this->format_seconds(round($db_game['round_length']*$average_seconds_per_block/60)*60);
		$html .= "</div></div>\n";
		
		if ($db_game['maturity'] != 0) {
			$html .= '<div class="row"><div class="col-sm-5">Transaction maturity:</div><div class="col-sm-7">'.$db_game['maturity']." block";
			if ($db_game['maturity'] != 1) $html .= "s";
			$html .= "</div></div>\n";
		}
		
		$html .= "</div>\n";
		
		return $html;
	}
	
	public function fetch_game_definition(&$game, $definition_mode) {
		// $definition_mode is "defined" or "actual"
		$game_definition = array();
		$game_definition['blockchain_identifier'] = $game->blockchain->db_blockchain['url_identifier'];
		
		if ($game->db_game['option_group_id'] > 0) {
			$group_q = "SELECT * FROM option_groups WHERE group_id='".$game->db_game['option_group_id']."';";
			$group_r = $this->run_query($group_q);
			$db_group = $group_r->fetch();
			$game_definition['option_group'] = $db_group['description'];
		}
		else $game_definition['option_group'] = "null";
		
		$verbatim_vars = $this->game_definition_verbatim_vars();
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($game->db_game[$var_name] == "0" || $game->db_game[$var_name] > 0) $var_val = (int) $game->db_game[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $game->db_game[$var_name];
			else $var_val = $game->db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		$escrow_amounts = array();
		
		if ($definition_mode == "actual") {
			$q = "SELECT * FROM game_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id='".$game->db_game['game_id']."' ORDER BY c.short_name_plural ASC;";
		}
		else if ($definition_mode == "defined") {
			$q = "SELECT * FROM game_defined_escrow_amounts ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id='".$game->db_game['game_id']."' ORDER BY c.short_name_plural ASC;";
		}
		
		$r = $this->run_query($q);
		
		while ($escrow_amount = $r->fetch()) {
			$escrow_amounts[$escrow_amount['short_name_plural']] = (float) $escrow_amount['amount'];
		}
		
		$game_definition['escrow_amounts'] = $escrow_amounts;
		
		$event_verbatim_vars = $this->event_verbatim_vars();
		$events_obj = array();
		
		if ($definition_mode == "defined") {
			$q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM game_defined_events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id='".$game->db_game['game_id']."' ORDER BY ev.event_index ASC;";
		}
		else {
			$q = "SELECT ev.*, sp.entity_name AS sport_name, lg.entity_name AS league_name FROM events ev LEFT JOIN entities sp ON ev.sport_entity_id=sp.entity_id LEFT JOIN entities lg ON ev.league_entity_id=lg.entity_id WHERE ev.game_id='".$game->db_game['game_id']."' ORDER BY ev.event_index ASC;";
		}
		$r = $this->run_query($q);
		
		$i=0;
		while ($db_event = $r->fetch()) {
			$temp_event = array();
			
			for ($j=0; $j<count($event_verbatim_vars); $j++) {
				$var_type = $event_verbatim_vars[$j][0];
				$var_name = $event_verbatim_vars[$j][1];
				$var_val = $db_event[$var_name];
				
				if ($var_type == "int" && $var_val != "") $var_val = (int) $var_val;
				
				$temp_event[$var_name] = $var_val;
			}
			
			if (!empty($db_event['sport_name'])) $temp_event['sport'] = $db_event['sport_name'];
			if (!empty($db_event['league_name'])) $temp_event['league'] = $db_event['league_name'];
			
			if ($definition_mode == "defined") {
				$qq = "SELECT * FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."' ORDER BY option_index ASC;";
			}
			else {
				$qq = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY event_option_index ASC;";
			}
			$rr = $this->run_query($qq);
			$j = 0;
			while ($option = $rr->fetch()) {
				$temp_event['possible_outcomes'][$j] = array("title"=>$option['name']);
				$j++;
			}
			$events_obj[$i] = $temp_event;
			$i++;
		}
		$game_definition['events'] = $events_obj;
		
		return $game_definition;
	}
	
	public function game_definition_hash(&$game) {
		$game_def = $this->fetch_game_definition($game, "defined");
		$game_def_str = $this->game_def_to_text($game_def);
		$game_def_hash = $this->game_def_to_hash($game_def_str);
		return $game_def_hash;
	}
	
	public function shorten_game_def_hash($hash) {
		return substr($hash, 0, 16);
	}
	
	public function game_def_to_hash(&$game_def_str) {
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
			$q = "SELECT SUM(".$db_game['payout_weight']."_score), SUM(unconfirmed_".$db_game['payout_weight']."_score) FROM options op JOIN events e ON op.event_id=e.event_id WHERE e.game_id='".$db_game['game_id']."';";
			$r = $this->run_query($q);
			$r = $r->fetch();
			$score = $r['SUM('.$db_game['payout_weight'].'_score)']+$r['SUM(unconfirmed_'.$db_game['payout_weight'].'_score)'];
			
			return $score/$this->votes_per_coin($db_game);
		}
	}
	
	public function votes_per_coin($db_game) {
		if ($db_game['inflation'] == "exponential") {
			if ($db_game['exponential_inflation_rate'] == 0) return 0;
			else {
				if ($db_game['payout_weight'] == "coin_round") $votes_per_coin = 1/$db_game['exponential_inflation_rate'];
				else $votes_per_coin = $db_game['round_length']/$db_game['exponential_inflation_rate'];
				return $votes_per_coin;
			}
		}
		else return 0;
	}
	
	public function coins_per_vote($db_game) {
		if ($db_game['inflation'] == "exponential") {
			if ($db_game['payout_weight'] == "coin_round") $coins_per_vote = $db_game['exponential_inflation_rate'];
			else $coins_per_vote = $db_game['exponential_inflation_rate']/$db_game['round_length'];
			return $coins_per_vote;
		}
		else return 0; // To-do
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
		if ($GLOBALS['process_lock_method'] == "db") {
			$process_running = (int) $this->get_site_constant($lock_name);
			
			if ($process_running > 0) {
				if (PHP_OS == "WINNT") {
					$pids = array_column(array_map('str_getcsv', explode("\n",trim(`tasklist /FO csv /NH`))), 1);
					if (in_array($process_running, $pids)) {
						return $process_running;
					}
					else {
						$this->set_site_constant($lock_name, 0);
						return 0;
					}
				}
				else {
					$cmd = "ps -p ".$process_running."|wc -l";
					$cmd_result_lines = (int) exec($cmd);
					if ($cmd_result_lines > 1) return $process_running;
					else {
						$this->set_site_constant($lock_name, 0);
						return 0;
					}
				}
			}
			else return 0;
		}
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
			
			if ($num_running > 0) return 1;
			else return 0;
		}
	}
	
	public function voting_character_definitions() {
		if ($GLOBALS['identifier_case_sensitive'] == 1) {
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
		
		if ($GLOBALS['identifier_case_sensitive'] == 0) $addr_text = strtolower($addr_text);
		
		$firstchar_pos = $GLOBALS['identifier_first_char'];
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
			array('string', 'payout_rule', true),
			array('float', 'track_max_price', true),
			array('float', 'track_min_price', true),
			array('float', 'track_payout_price', true),
			array('string', 'track_name_short', true),
			array('string', 'event_starting_time', true),
			array('string', 'event_final_time', true),
			array('string', 'event_payout_offset_time', true),
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
			array('string', 'name', false),
			array('string', 'url_identifier', false),
			array('string', 'module', true),
			array('int', 'category_id', false),
			array('int', 'decimal_places', true),
			array('string', 'event_type_name', false),
			array('string', 'event_type_name_plural', false),
			array('string', 'event_rule', true),
			array('string', 'event_winning_rule', true),
			array('int', 'event_entity_type_id', true),
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
			array('string', 'default_effectiveness_param1', true),
			array('float', 'default_max_voting_fraction', true),
			array('int', 'default_option_max_width', false),
			array('int', 'default_payout_block_delay', true),
			array('string', 'view_mode', true)
		);
	}
	
	public function blockchain_verbatim_vars() {
		return array(
			array('string', 'blockchain_name'),
			array('string', 'url_identifier'),
			array('string', 'coin_name'),
			array('string', 'coin_name_plural'),
			array('int', 'seconds_per_block'),
			array('int', 'decimal_places'),
			array('int', 'initial_pow_reward')
		);
	}
	
	public function fetch_blockchain_definition(&$blockchain) {
		$verbatim_vars = $this->blockchain_verbatim_vars();
		$blockchain_definition = array();
		
		if (in_array($blockchain->db_blockchain['p2p_mode'], array("web_api", "none"))) {
			if ($blockchain->db_blockchain['p2p_mode'] == "none") {
				$card_issuer = $this->get_issuer_by_server_name($GLOBALS['base_url'], true);
			}
			else {
				$card_issuer = $this->get_issuer_by_id($this->db_blockchain['authoritative_issuer_id']);
			}
			$blockchain_definition['issuer'] = $card_issuer['base_url'];
		}
		else $blockchain_definition['issuer'] = "none";
		
		if (in_array($blockchain->db_blockchain['p2p_mode'], array("none","web_api"))) {
			$blockchain_definition['p2p_mode'] = "web";
		}
		else $blockchain_definition['p2p_mode'] = "rpc";
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($blockchain->db_blockchain[$var_name] == "0" || $blockchain->db_blockchain[$var_name] > 0) $var_val = (int) $blockchain->db_blockchain[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $blockchain->db_blockchain[$var_name];
			else $var_val = $blockchain->db_blockchain[$var_name];
			
			$blockchain_definition[$var_name] = $var_val;
		}
		return $blockchain_definition;
	}
	
	public function migrate_game_definitions($game, $initial_game_def_hash, $new_game_def_hash) {
		$log_message = "";
		$initial_game_def_r = $this->run_query("SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($initial_game_def_hash).";");
		
		if ($initial_game_def_r->rowCount() == 1) {
			$initial_game_def = $initial_game_def_r->fetch();
			$initial_game_obj = get_object_vars(json_decode($initial_game_def['definition']));
			
			$new_game_def_r = $this->run_query("SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($new_game_def_hash).";");
			
			if ($new_game_def_r->rowCount() == 1) {
				$new_game_def = $new_game_def_r->fetch();
				$new_game_obj = get_object_vars(json_decode($new_game_def['definition']));
				
				$min_starting_block = min($initial_game_obj['game_starting_block'], $new_game_obj['game_starting_block']);
				
				$verbatim_vars = $this->game_definition_verbatim_vars();
				$reset_block = false;
				$reset_event_index = false;
				
				$sports_entity_type = $this->check_set_entity_type("sports");
				$leagues_entity_type = $this->check_set_entity_type("leagues");
				
				// Check if any base params are different. If so, reset from game starting block
				for ($i=0; $i<count($verbatim_vars); $i++) {
					$var = $verbatim_vars[$i];
					if ($var[2] == true) {
						if ((string)$initial_game_obj[$var[1]] != (string)$new_game_obj[$var[1]]) {
							$reset_block = $min_starting_block;
							
							$q = "UPDATE games SET ".$var[1]."=".$this->quote_escape($new_game_obj[$var[1]])." WHERE game_id=".$game->db_game['game_id'].";";
							$r = $this->run_query($q);
						}
					}
				}
				
				$q = "DELETE FROM game_escrow_amounts WHERE game_id='".$game->db_game['game_id']."';";
				$r = $this->run_query($q);
				
				if (!empty($new_game_obj['escrow_amounts'])) {
					foreach ($new_game_obj['escrow_amounts'] as $currency_identifier => $amount) {
						$q = "SELECT * FROM currencies WHERE short_name_plural='".$currency_identifier."';";
						$r = $this->run_query($q);
						
						if ($r->rowCount() > 0) {
							$escrow_currency = $r->fetch();
							
							$q = "INSERT INTO game_escrow_amounts SET game_id='".$game->db_game['game_id']."', currency_id='".$escrow_currency['currency_id']."', amount='".$amount."';";
							$r = $this->run_query($q);
						}
					}
				}
				
				$event_verbatim_vars = $this->event_verbatim_vars();
				
				$num_initial_events = 0;
				if (!empty($initial_game_obj['events'])) $num_initial_events = count($initial_game_obj['events']);
				$num_new_events = 0;
				if (!empty($new_game_obj['events'])) $num_new_events = count($new_game_obj['events']);
				
				$matched_events = min($num_initial_events, $num_new_events);
				
				for ($i=0; $i<$matched_events; $i++) {
					$initial_event_text = $this->game_def_to_text($initial_game_obj['events'][$i]);
					
					if ($this->game_def_to_text($new_game_obj['events'][$i]) != $initial_event_text) {
						$reset_block = $this->min_excluding_false(array($reset_block, $initial_game_obj['events'][$i]->event_starting_block, $new_game_obj['events'][$i]->event_starting_block));
						
						if ($reset_event_index === false) $reset_event_index = $new_game_obj['events'][$i]->event_index;
					}
				}
				
				$set_events_from = $this->min_excluding_false(array($reset_event_index, $matched_events+1));
				
				if ($set_events_from !== false) {
					$log_message .= "Resetting events from #".$set_events_from."\n";
					$game->reset_events_from_index($set_events_from);
				}
				
				if ($num_new_events+1 > $set_events_from) {
					if (!is_numeric($reset_block)) $reset_block = $new_game_obj['events'][$set_events_from-1]->event_starting_block;
					
					for ($i=$set_events_from; $i<count($new_game_obj['events'])+1; $i++) {
						$gde = get_object_vars($new_game_obj['events'][$i-1]);
						$this->check_set_gde($game, $gde, $event_verbatim_vars, $sports_entity_type['entity_type_id'], $leagues_entity_type['entity_type_id']);
					}
				}
				
				if (is_numeric($reset_block)) {
					$log_message .= "Resetting blocks from #".$reset_block."\n";
					$game->reset_blocks_from_block($reset_block);
				}
				else $log_message .= "Failed to determine a reset block.\n";
				
				$game->update_db_game();
			}
			else $log_message .= "No match for new game def: ".$new_game_def_hash."\n";
		}
		else $log_message .= "No match for initial game def: ".$initial_game_def_hash."\n";
		
		return $log_message;
	}
	
	public function check_set_gde(&$game, &$gde, &$event_verbatim_vars, $sport_entity_type_id, $league_entity_type_id) {
		$db_gde = false;
		
		$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$gde['event_index']."';";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			$db_gde = $r->fetch();
			$q = "UPDATE game_defined_events SET ";
		}
		else {
			$q = "INSERT INTO game_defined_events SET game_id='".$game->db_game['game_id']."', ";
		}
		
		if (!empty($gde['sport'])) {
			$sport_entity = $this->check_set_entity($sport_entity_type_id, $gde['sport']);
			$q .= "sport_entity_id=".$sport_entity['entity_id'].", ";
		}
		else $q .= "sport_entity_id=NULL, ";
		
		if (!empty($gde['league'])) {
			$league_entity = $this->check_set_entity($league_entity_type_id, $gde['league']);
			$q .= "league_entity_id=".$league_entity['entity_id'].", ";
		}
		else $q .= "league_entity_id=NULL, ";
		
		for ($j=0; $j<count($event_verbatim_vars); $j++) {
			$var_type = $event_verbatim_vars[$j][0];
			if (isset($gde[$event_verbatim_vars[$j][1]])) $var_val = (string) $gde[$event_verbatim_vars[$j][1]];
			else $var_val = "";
			
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
		
		$delete_q = "DELETE FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$gde['event_index']."'";
		if (!empty($gde['possible_outcomes'])) $delete_q .= " AND option_index > ".count($gde['possible_outcomes']);
		$delete_q .= ";";
		$this->run_query($delete_q);
		
		if (!empty($gde['possible_outcomes'])) {
			$existing_gdo_r = $this->run_query("SELECT * FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$gde['event_index']."' ORDER BY option_index ASC;");
			
			for ($k=0; $k<count($gde['possible_outcomes']); $k++) {
				if ($existing_gdo_r->rowCount() > 0) $existing_gdo = $existing_gdo_r->fetch();
				else $existing_gdo = false;
				
				if (is_object($gde['possible_outcomes'][$k])) $possible_outcome = get_object_vars($gde['possible_outcomes'][$k]);
				else $possible_outcome = $gde['possible_outcomes'][$k];
				
				if ($existing_gdo) $q = "UPDATE game_defined_options SET ";
				else $q = "INSERT INTO game_defined_options SET game_id='".$game->db_game['game_id']."', event_index='".$gde['event_index']."', option_index='".$k."', ";
				
				$q .= "name=".$this->quote_escape($possible_outcome['title']);
				if (!empty($possible_outcome['entity_id'])) $q .= ", entity_id='".$possible_outcome['entity_id']."'";
				
				if ($existing_gdo) $q .= " WHERE game_defined_option_id='".$existing_gdo['game_defined_option_id']."'";
				
				$q .= ";";
				$r = $this->run_query($q);
			}
		}
	}
	
	public function check_set_game_definition($game_def_hash, $game_def_str) {
		$q = "SELECT * FROM game_definitions WHERE definition_hash=".$this->quote_escape($game_def_hash).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() == 0) {
			$q = "INSERT INTO game_definitions SET definition_hash=".$this->quote_escape($game_def_hash).", definition=".$this->quote_escape($game_def_str).";";
			$r = $this->run_query($q);
		}
	}
	
	public function check_set_module($module_name) {
		$q = "SELECT * FROM modules WHERE module_name=".$this->quote_escape($module_name).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_module = $r->fetch();
		}
		else {
			$q = "INSERT INTO modules SET module_name=".$this->quote_escape($module_name).";";
			$r = $this->run_query($q);
			$module_id = $this->last_insert_id();
			
			$db_module = $this->run_query("SELECT * FROM modules WHERE module_id=".$module_id.";")->fetch();
		}
		
		return $db_module;
	}
	
	public function create_blockchain_from_definition(&$definition, &$thisuser, &$error_message, &$db_new_blockchain) {
		$blockchain = false;
		$blockchain_def = json_decode($definition) or die("Error: invalid JSON formatted blockchain");
		
		if (!empty($blockchain_def->url_identifier)) {
			$db_blockchain = $this->fetch_blockchain_by_identifier($blockchain_def->url_identifier);
			
			if (!$db_blockchain) {
				$p2p_mode = "web_api";
				if ($blockchain_def->p2p_mode == "rpc") $p2p_mode = "rpc";
				
				$import_q = "INSERT INTO blockchains SET online=1, p2p_mode='".$p2p_mode."', creator_id='".$thisuser->db_user['user_id']."', ";
				
				$issuer = false;
				if ($blockchain_def->issuer != "none") {
					$issuer = $this->get_issuer_by_server_name($blockchain_def->issuer, false);
					if ($issuer) $import_q .= "authoritative_issuer_id='".$issuer['issuer_id']."', ";
				}
				if (!$issuer) $import_q .= "authoritative_issuer_id='NULL', ";
				
				$verbatim_vars = $this->blockchain_verbatim_vars();
				
				for ($var_i=0; $var_i<count($verbatim_vars); $var_i++) {
					$var_type = $verbatim_vars[$var_i][0];
					$var_name = $verbatim_vars[$var_i][1];
					
					$import_q .= $var_name."=".$this->quote_escape($blockchain_def->$var_name).", ";
				}
				$import_q = substr($import_q, 0, strlen($import_q)-2).";";
				
				$import_r = $this->run_query($import_q);
				$blockchain_id = $this->last_insert_id();
				
				$error_message = "Import was a success! Next please <a href=\"/scripts/sync_blockchain_initial.php?key=".$GLOBALS['cron_key_string']."&blockchain_id=".$blockchain_id."\">reset and synchronize ".$blockchain_def->blockchain_name."</a>";
			}
			else $error_message = "Error: this blockchain already exists.";
		}
		else $error_message = "Invalid url_identifier";
	}
	
	public function create_game_from_definition(&$game_definition, &$thisuser, &$error_message, &$db_game) {
		$game = false;
		$game_def = json_decode($game_definition) or die("Error: the game definition you entered could not be imported.<br/>Please make sure to enter properly formatted JSON.<br/><a href=\"/import/\">Try again</a>");
		
		$error_message = "";
		
		if (!empty($game_def->blockchain_identifier)) {
			$new_private_blockchain = false;
			
			if ($game_def->blockchain_identifier == "private") {
				$new_private_blockchain = true;
				$chain_id = $this->random_string(6);
				$decimal_places = 8;
				$url_identifier = "private-chain-".$chain_id;
				$chain_pow_reward = 25*pow(10,$decimal_places);
				
				$q = "INSERT INTO blockchains SET online=1, p2p_mode='none', blockchain_name='Private Chain ".$chain_id."', url_identifier='".$url_identifier."', coin_name='chaincoin', coin_name_plural='chaincoins', seconds_per_block=30, decimal_places=".$decimal_places.", initial_pow_reward=".$chain_pow_reward.";";
				$r = $this->run_query($q);
				$blockchain_id = $this->last_insert_id();
				
				$new_blockchain = new Blockchain($this, $blockchain_id);
				if ($thisuser) $new_blockchain->set_blockchain_creator($thisuser);
				
				$game_def->blockchain_identifier = $url_identifier;
			}
			
			$db_blockchain = $this->fetch_blockchain_by_identifier($game_def->blockchain_identifier);
			
			if ($db_blockchain) {
				$blockchain = new Blockchain($this, $db_blockchain['blockchain_id']);
				
				$game_def->url_identifier = $this->normalize_uri_part($game_def->url_identifier);
				
				if (!empty($game_def->url_identifier)) {
					$verbatim_vars = $this->game_definition_verbatim_vars();
					
					$permission_to_change = false;
					
					$q = "SELECT * FROM games WHERE url_identifier=".$this->quote_escape($game_def->url_identifier).";";
					$r = $this->run_query($q);
					
					if ($r->rowCount() > 0) {
						$db_url_matched_game = $r->fetch();
						
						if ($db_url_matched_game['blockchain_id'] == $blockchain->db_blockchain['blockchain_id']) {
							$url_matched_game = new Game($blockchain, $db_url_matched_game['game_id']);
							
							if ($thisuser) {
								$permission_to_change = $this->user_can_edit_game($thisuser, $url_matched_game);
								
								if ($permission_to_change) $game = $url_matched_game;
								else $error_message = "Error: you can't edit this game.";
							}
							else $error_message = "Permission denied. You must be logged in.";
						}
						else $error_message = "Error: invalid game.blockchain_id.";
					}
					else $permission_to_change = true;
					
					if ($permission_to_change) {
						if (!$game) {
							$db_group = false;
							if (!empty($game_def->option_group)) {
								$db_group = $this->select_group_by_description($game_def->option_group);
								if (!$db_group) {
									$import_error = "";
									$this->import_group_from_file($game_def->option_group, $import_error);
									
									$db_group = $this->select_group_by_description($game_def->option_group);
								}
							}
							
							$q = "INSERT INTO games SET ";
							if ($thisuser) $q .= "creator_id='".$thisuser->db_user['user_id']."', ";
							if ($db_group) $q .= "option_group_id='".$db_group['group_id']."', ";
							$q .= "blockchain_id='".$db_blockchain['blockchain_id']."', game_status='published', featured=1";
							
							for ($i=0; $i<count($verbatim_vars); $i++) {
								$var_type = $verbatim_vars[$i][0];
								$var_name = $verbatim_vars[$i][1];
								
								if ($game_def->$var_name != "") {
									$q .= ", ".$var_name."=".$this->quote_escape($game_def->$var_name);
								}
							}
							$q .= ";";
							$r = $this->run_query($q);
							$game_id = $this->last_insert_id();
							
							if (!empty($game_def->module)) {
								$this->run_query("UPDATE modules SET primary_game_id=".$game_id." WHERE module_name=".$this->quote_escape($game_def->module)." AND primary_game_id IS NULL;");
							}
							
							$game = new Game($blockchain, $game_id);
						}
						
						$q = "DELETE FROM game_defined_escrow_amounts WHERE game_id='".$game->db_game['game_id']."';";
						$r = $this->run_query($q);
						
						if (!empty($game_def->escrow_amounts)) {
							foreach ($game_def->escrow_amounts as $currency_identifier => $amount) {
								$q = "SELECT * FROM currencies WHERE short_name_plural='".$currency_identifier."';";
								$r = $this->run_query($q);
								
								if ($r->rowCount() > 0) {
									$escrow_currency = $r->fetch();
									
									$q = "INSERT INTO game_defined_escrow_amounts SET game_id='".$game->db_game['game_id']."', currency_id='".$escrow_currency['currency_id']."', amount='".$amount."';";
									$r = $this->run_query($q);
								}
							}
						}
						
						$from_game_def = $this->fetch_game_definition($game, "defined");
						$from_game_def_str = $this->game_def_to_text($from_game_def);
						$from_game_def_hash = $this->game_def_to_hash($from_game_def_str);
						$this->check_set_game_definition($from_game_def_hash, $from_game_def_str);
						
						$to_game_def_str = $this->game_def_to_text($game_def);
						$to_game_def_hash = $this->game_def_to_hash($to_game_def_str);
						$this->check_set_game_definition($to_game_def_hash, $to_game_def_str);
						
						if ($from_game_def_hash != $to_game_def_hash) {
							$error_message = $this->migrate_game_definitions($game, $from_game_def_hash, $to_game_def_hash);
							
							$general_entity_type = $this->check_set_entity_type("general entity");
							
							$entity_q = "UPDATE game_defined_options gdo JOIN game_defined_events ev ON gdo.event_index=ev.event_index JOIN entities en ON gdo.name=en.entity_name SET gdo.entity_id=en.entity_id WHERE gdo.game_id='".$game->db_game['game_id']."' AND ev.game_id='".$game->db_game['game_id']."' AND en.entity_type_id='".$general_entity_type['entity_type_id']."';";
							$entity_r = $this->run_query($entity_q);
							
							$entity_q = "UPDATE currencies c JOIN entities en ON c.currency_id=en.currency_id JOIN game_defined_events gde ON gde.track_name_short=c.abbreviation JOIN game_defined_options gdo ON gdo.event_index=gde.event_index SET gde.track_entity_id=en.entity_id, gdo.entity_id=en.entity_id WHERE gdo.game_id='".$game->db_game['game_id']."' AND gde.game_id='".$game->db_game['game_id']."' AND en.entity_type_id='".$general_entity_type['entity_type_id']."';";
							$entity_r = $this->run_query($entity_q);
						}
						else $error_message = "Found no changes to apply.";
						
						$game->update_db_game();
						$db_game = $game->db_game;
					}
				}
				else $error_message = "Error, invalid game URL identifier.";
			}
			else {
				if ($new_private_blockchain) {
					$q = "DELETE FROM blockchains WHERE blockchain_id='".$new_blockchain->db_blockchain['blockchain_id']."';";
					$r = $this->run_query($q);
				}
				$error_message = "Error, failed to identify the right blockchain.";
			}
		}
		else $error_message = "Error, blockchain url identifier was empty.";
		
		return $game;
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
	
	public function fetch_entity_by_id($entity_id) {
		$q = "SELECT * FROM entities WHERE entity_id='".((int)$entity_id)."';";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
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
			
			return $this->fetch_entity_by_id($entity_id);
		}
	}
	
	public function check_set_entity_type($name) {
		$q = "SELECT * FROM entity_types WHERE entity_name=".$this->quote_escape($name).";";
		$r = $this->run_query($q);
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else {
			$q = "INSERT INTO entity_types SET entity_name=".$this->quote_escape($name).";";
			$r = $this->run_query($q);
			$entity_type_id = $this->last_insert_id();
			$q = "SELECT * FROM entity_types WHERE entity_type_id=".$entity_type_id.";";
			return $this->run_query($q)->fetch();
		}
	}
	
	public function cached_url_info($url) {
		$q = "SELECT * FROM cached_urls WHERE url=".$this->quote_escape($url).";";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) return $r->fetch();
		else return false;
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
	
	public function permission_to_claim_address(&$thisuser, &$address_blockchain, &$db_address) {
		if (!empty($thisuser) && $this->user_is_admin($thisuser) && empty($db_address['user_id'])) {
			if ($address_blockchain->db_blockchain['p2p_mode'] == "none") return true;
			else return false;
		}
		else return false;
	}
	
	public function give_address_to_user(&$game, &$user, $db_address) {
		if ($game) {
			$user_game = $user->ensure_user_in_game($game, false);
			
			if ($user_game) {
				$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id='".$db_address['address_id']."';";
				$r = $this->run_query($q);
				
				if ($r->rowCount() == 1) {
					$address_key = $r->fetch();
					
					$q = "UPDATE address_keys SET account_id='".$user_game['account_id']."' WHERE address_key_id='".$address_key['address_key_id']."';";
					$r = $this->run_query($q);
				}
				else {
					$q = "INSERT INTO address_keys SET address_id='".$db_address['address_id']."', account_id='".$user_game['account_id']."', save_method='fake', pub_key=".$this->quote_escape($db_address['address']).";";
					$r = $this->run_query($q);
				}
				$q = "UPDATE addresses SET user_id='".$user->db_user['user_id']."' WHERE address_id='".$db_address['address_id']."';";
				$r = $this->run_query($q);
				
				return true;
			}
			else return false;
		}
		else {
			$blockchain = new Blockchain($this, $db_address['primary_blockchain_id']);
			$currency_id = $blockchain->currency_id();
			
			$account = $this->user_blockchain_account($user->db_user['user_id'], $currency_id);
			
			if ($account) {
				$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.address_id='".$db_address['address_id']."';";
				$r = $this->run_query($q);
				
				if ($r->rowCount() == 1) {
					$address_key = $r->fetch();
					
					$q = "UPDATE address_keys SET account_id='".$account['account_id']."' WHERE address_key_id='".$address_key['address_key_id']."';";
					$r = $this->run_query($q);
				}
				else {
					$q = "INSERT INTO address_keys SET address_id='".$db_address['address_id']."', account_id='".$account['account_id']."', save_method='fake', pub_key=".$this->quote_escape($db_address['address']).";";
					$r = $this->run_query($q);
				}
				$q = "UPDATE addresses SET user_id='".$user->db_user['user_id']."' WHERE address_id='".$db_address['address_id']."';";
				$r = $this->run_query($q);
				
				return true;
			}
			else return false;
		}
	}
	
	public function blockchain_ensure_currencies() {
		$q = "SELECT b.* FROM blockchains b WHERE NOT EXISTS (SELECT * FROM currencies c WHERE b.blockchain_id=c.blockchain_id);";
		$r = $this->run_query($q);
		
		while ($db_blockchain = $r->fetch()) {
			$qq = "INSERT INTO currencies SET blockchain_id='".$db_blockchain['blockchain_id']."', name=".$this->quote_escape($db_blockchain['blockchain_name']).", short_name=".$this->quote_escape($db_blockchain['coin_name']).", short_name_plural=".$this->quote_escape($db_blockchain['coin_name_plural']).", abbreviation=".$this->quote_escape($db_blockchain['coin_name_plural']).";";
			$rr = $this->run_query($qq);
		}
	}
	
	public function user_is_admin(&$user) {
		if (!empty($user)) {
			if ($user->db_user['user_id'] == $this->get_site_constant("admin_user_id")) return true;
			else return false;
		}
		else return false;
	}
	
	public function user_can_edit_game(&$user, &$game) {
		if (!empty($user) && !empty($game->db_game['creator_id'])) {
			if ($user->db_user['user_id'] == $game->db_game['creator_id']) return true;
			else return false;
		}
		else return false;
	}
	
	public function user_blockchain_account($user_id, $currency_id) {
		$qq = "SELECT * FROM currency_accounts WHERE game_id IS NULL AND user_id='".$user_id."' AND currency_id='".$currency_id."';";
		$rr = $this->run_query($qq);
		
		if ($rr->rowCount() > 0) {
			$currency_account = $rr->fetch();
		}
		else $currency_account = false;
		
		return $currency_account;
	}
	
	public function render_error_message(&$error_message, $error_class) {
		if ($error_class == "nostyle") return $error_message;
		else {
			$html = '
			<div class="alert alert-dismissible alert-success">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$error_message.'
			</div>';
			
			return $html;
		}
	}
	
	public function get_card_denominations($currency, $fv_currency_id) {
		$denominations = array();
		
		$q = "SELECT * FROM card_currency_denominations WHERE currency_id='".$currency['currency_id']."' AND fv_currency_id='".$fv_currency_id."' ORDER BY denomination ASC;";
		$r = $this->run_query($q);
		
		while ($denomination = $r->fetch()) {
			array_push($denominations, $denomination);
		}
		
		return $denominations;
	}
	
	public function calculate_cards_cost($usd_per_btc, $denomination, $purity, $how_many) {
		$error = FALSE;
		$total_usd = 0;
		
		if ($purity == "unspecified") $purity = 100;
		
		if ($currency != "btc") $currency = "usd";
		
		if ($currency == "btc") $purity = 100;
		
		if ($purity != round($purity)) $error = TRUE;
		
		if ($how_many > 0 && $how_many <= 1000 && $how_many == round($how_many)) {}
		else $error = TRUE;
		
		if ($purity >= 80 && $purity <= 100 && $purity == round($purity)) {}
		else $error = TRUE;
		
		if ($error) return FALSE;
		else {
			$cards_facevalue_usd = $how_many*$denomination['denomination'];
			if ($denomination['currency_id'] != 1) $cards_facevalue_usd = round($cards_facevalue_usd*$usd_per_btc, 2);
			
			$total_usd += $cards_facevalue_usd;
			
			$print_fees = round(0.25*$how_many, 2);
			$total_usd += $print_fees;
			
			$builtin_discount = round($cards_facevalue_usd*((100-$purity)/100), 2);
			$total_usd = $total_usd - $builtin_discount;
			
			return round($total_usd/$usd_per_btc, 5);
		}
	}
	
	public function position_by_pos($position, $side, $paper_width) {
		$num_cols = 2;
		if ($paper_width == "small") $num_cols = 1;
		
		$position = $position - 1; // use 0,1... ordering instead of 1,2...
		
		if ($paper_width == "small") {
			$left_margin = 0.2;
			$top_margin = 0.5;
			
			$card_w = 2;
			$card_h = 3.5;
		}
		else {
			$left_margin = 0.75;
			$top_margin = 0.5;
			
			$card_w = 3.5;
			$card_h = 2;
		}
		
		if ($side == "front") {
			if ($position % $num_cols == 0) $x = $left_margin;
			else $x = $left_margin + $card_w;
			
			$row = floor($position/$num_cols);
			$y = $top_margin + $row*$card_h;
		}
		else if ($side == "back") {
			if ($position % $num_cols == 0 && $paper_width != "small") $x = $left_margin + $card_w;
			else $x = $left_margin;
			
			$row = floor($position/$num_cols);
			$y = $top_margin + $row*$card_h;
		}
		
		$result[0] = $x;
		$result[1] = $y;
		return $result;
	}
	
	public function try_create_card_account($card, $thisuser, $password) {
		if ($card['status'] == "sold") {
			if (empty($thisuser)) {
				$username = $this->random_string(16);
				$user_password = $this->random_string(16);
				$verify_code = $this->random_string(32);
				$salt = $this->random_string(16);
				
				$thisuser = $this->create_new_user($verify_code, $salt, $username, $user_password);
			}
			
			$q = "INSERT INTO card_users SET card_id='".$card['card_id']."', password=".$this->quote_escape($password).", create_time='".time()."'";
			if ($GLOBALS['pageview_tracking_enabled']) $q .= ", create_ip=".$this->quote_escape($_SERVER['REMOTE_ADDR']);
			$q .= ";";
			$r = $this->run_query($q);
			$card_user_id = $this->last_insert_id();
			
			$q = "UPDATE cards SET user_id='".$thisuser->db_user['user_id']."', card_user_id='".$card_user_id."', claim_time='".time()."' WHERE card_id='".$card['card_id']."';";
			$r = $this->run_query($q);
			
			$this->change_card_status($card, 'claimed');
			
			$session_key = $_COOKIE['my_session'];
			$expire_time = time()+3600*24;
			
			$q = "INSERT INTO card_sessions SET card_user_id='".$card_user_id."', session_key=".$this->quote_escape($session_key).", login_time='".time()."', expire_time='".$expire_time."'";
			if ($GLOBALS['pageview_tracking_enabled']) $q .= ", ip_address=".$this->quote_escape($_SERVER['REMOTE_ADDR']);
			$q .= ";";
			$r = $this->run_query($q);
			
			$redirect_url = false;
			$login_success = $thisuser->log_user_in($redirect_url, false);
			
			$txt = "<p>Your account has been created! ";
			$txt .= "Any time you want to access your money, please visit the link on your gift card.</p>\n";
			
			$txt .= "<a href=\"/cards/\" class=\"btn btn-default\">Go to My Account</a>";
			
			$success = TRUE;
		}
		else {
			$success = FALSE;
			$txt = "";
		}
		
		$returnvals[0] = $success;
		$returnvals[1] = $txt;
		return $returnvals;
	}
	
	public function get_card_currency_balance($card_id, $currency_id) {
		$q = "SELECT * FROM card_currency_balances WHERE card_id='".$card_id."' AND currency_id='".$currency_id."';";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			$balance = $r->fetch();
			
			return $balance['balance'];
		}
		else return 0;
	}
	
	public function get_card_currency_balances($card_id) {
		$q = "SELECT * FROM card_currency_balances b JOIN currencies c ON b.currency_id=c.currency_id WHERE b.card_id='".$card_id."' ORDER BY b.currency_id ASC;";
		$r = $this->run_query($q);
		
		$balances = array();
		
		while ($balance = $r->fetch()) {
			array_push($balances, $balance);
		}
		
		return $balances;
	}
	
	public function set_card_currency_balances($card) {
		$balances_by_currency_id = array();
		
		$q = "SELECT * FROM card_conversions WHERE card_id='".$card['card_id']."';";
		$r = $this->run_query($q);
		
		while ($conversion = $r->fetch()) {
			if (!empty($conversion['currency1_id'])) {
				if (empty($balances_by_currency_id[$conversion['currency1_id']])) $balances_by_currency_id[$conversion['currency1_id']] = 0;
				$balances_by_currency_id[$conversion['currency1_id']] += $conversion['currency1_delta'];
			}
			if (!empty($conversion['currency2_id'])) {
				if (empty($balances_by_currency_id[$conversion['currency2_id']])) $balances_by_currency_id[$conversion['currency2_id']] = 0;
				$balances_by_currency_id[$conversion['currency2_id']] += $conversion['currency2_delta'];
			}
		}
		
		foreach ($balances_by_currency_id as $currency_id => $balance) {
			$q = "SELECT * FROM card_currency_balances WHERE card_id='".$card['card_id']."' AND currency_id='".$currency_id."';";
			$r = $this->run_query($q);
			
			if ($r->rowCount() > 0) {
				$db_balance = $r->fetch();
				$q = "UPDATE card_currency_balances SET balance='".$balance."' WHERE balance_id='".$db_balance['balance_id']."';";
				$r = $this->run_query($q);
			}
			else {
				$q = "INSERT INTO card_currency_balances SET card_id='".$card['card_id']."', currency_id='".$currency_id."', balance='".$balance."';";
				$r = $this->run_query($q);
			}
		}
	}
	
	public function calculate_cards_networth($my_cards) {
		$networth = 0;
		
		$currency_prices = $this->fetch_currency_prices();
		
		foreach ($my_cards as $card) {
			$balances = $this->get_card_currency_balances($card['card_id']);
			
			$networth += $this->calculate_card_networth($card, $balances, $currency_prices);
		}
		
		return $networth;
	}
	
	public function calculate_card_networth($card, $balances, $currency_prices) {
		$value = 0;
		
		foreach ($balances as $balance) {
			if (!empty($currency_prices[$balance['currency_id']])) {
				$value += $balance['balance']/$currency_prices[$balance['currency_id']]['price'];
			}
		}
		
		return $value;
	}
	
	public function get_card_fees($card) {
		return $card['amount']*(100-$card['purity'])/100;
	}
	
	public function try_withdraw_mobilemoney($currency_id, $phone_number, $first_name, $last_name, $amount, &$my_cards) {
		$beyonic = new Beyonic();
		$beyonic->setApiKey($GLOBALS['beyonic_api_key']);
		
		$payment = new MobilePayment($this, false);
		$payment->set_fields($my_cards[0]['card_group_id'], $currency_id, $amount, $phone_number, $first_name, $last_name);
		$payment->create();
		
		$mobilemoney_error = false;
		
		try {
			$beyonic_request = $beyonic->sendRequest('payments', 'POST', false, array(
				'phonenumber' => $phone_number,
				'payment_type' => "money",
				'first_name' => $first_name,
				'last_name' => $last_name,
				'amount' => $amount,
				'currency' => 'UGX',
				'description' => $GLOBALS['site_name_short']
			));
		}
		catch (Exception $e) {
			$mobilemoney_error = true;
			$error_message = "There was an error initiating the payment: ".$e->responseBody;
		}
		
		if (!$mobilemoney_error) {
			$q = "UPDATE mobile_payments SET beyonic_request_id='".$beyonic_request->id."' WHERE payment_id='".$payment->db_payment['payment_id']."';";
			$r = $this->run_query($q);
			
			$this->change_card_status($my_cards[0], 'redeemed');
			
			$q = "UPDATE cards SET status='redeemed' WHERE card_id='".$my_cards[0]['card_id']."';";
			$r = $this->run_query($q);
			
			$q = "INSERT INTO card_withdrawals SET withdraw_method='mobilemoney', card_id='".$my_cards[0]['card_id']."', currency_id='".$currency_id."', status_change_id='".$status_change_id."', withdraw_time='".time()."', amount='".$amount."', ip_address=".$this->quote_escape($_SERVER['REMOTE_ADDR']).";";
			$r = $this->run_query($q);
			$withdrawal_id = $this->last_insert_id();
			
			$q = "INSERT INTO card_conversions SET card_id='".$my_cards[0]['card_id']."'";
			$q .= ", withdrawal_id='".$withdrawal_id."'";
			$q .= ", time_created='".time()."', ip_address=".$this->quote_escape($_SERVER['REMOTE_ADDR']).", currency1_id=".$currency_id.", currency1_delta=".(-1*$amount).";";
			$r = $this->run_query($q);
			
			$this->set_card_currency_balances($my_cards[0]);
			
			$error_message = "Beyonic request was successful!";
		}
		
		return $error_message;
	}
	
	public function get_issuer_by_server_name($server_name, $allow_new) {
		$server_name = trim(strtolower(strip_tags($server_name)));
		$initial_server_name = $server_name;
		if (substr($server_name, 0, 7) == "http://") $server_name = substr($server_name, 7, strlen($server_name)-7);
		if (substr($server_name, 0, 8) == "https://") $server_name = substr($server_name, 8, strlen($server_name)-8);
		if (substr($server_name, 0, 4) == "www.") $server_name = substr($server_name, 4, strlen($server_name)-4);
		if ($server_name[strlen($server_name)-1] == "/") $server_name = substr($server_name, 0, strlen($server_name)-1);
		
		$issuer_r = $this->run_query("SELECT * FROM card_issuers WHERE issuer_identifier=".$this->quote_escape($server_name).";");
		
		if ($issuer_r->rowCount() > 0) {
			$card_issuer = $issuer_r->fetch();
		}
		else if ($allow_new) {
			$this->run_query("INSERT INTO card_issuers SET issuer_identifier=".$this->quote_escape($server_name).", issuer_name=".$this->quote_escape($server_name).", base_url=".$this->quote_escape($initial_server_name).", time_created='".time()."';");
			$issuer_id = $this->last_insert_id();
			
			$card_issuer = $this->run_query("SELECT * FROM card_issuers WHERE issuer_id=".$issuer_id.";")->fetch();
		}
		else $card_issuer = false;
		
		return $card_issuer;
	}
	
	public function get_issuer_by_id($issuer_id) {
		$q = "SELECT * FROM card_issuers WHERE issuer_id='".$issuer_id."';";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function change_card_status(&$db_card, $new_status) {
		$q = "INSERT INTO card_status_changes SET card_id='".$db_card['card_id']."', from_status='".$db_card['status']."', to_status='".$new_status."', change_time='".time()."';";
		$r = $this->run_query($q);
		
		$q = "UPDATE cards SET status='".$new_status."' WHERE card_id='".$db_card['card_id']."';";
		$r = $this->run_query($q);
		
		$db_card['status'] = $new_status;
	}
	
	public function card_secret_to_hash($secret) {
		return hash("sha256", $secret);
	}
	
	public function create_new_user($verify_code, $salt, $username, $password) {
		$q = "INSERT INTO users SET username=".$this->quote_escape($username);
		$q .= ", password=".$this->quote_escape($this->normalize_password($password, $salt)).", salt=".$this->quote_escape($salt);
		if (strpos($username, '@') !== false) $q .= ", notification_email=".$this->quote_escape($username);
		if ($GLOBALS['pageview_tracking_enabled']) $q .= ", ip_address=".$this->quote_escape($_SERVER['REMOTE_ADDR']);
		if ($GLOBALS['new_games_per_user'] != "unlimited" && $GLOBALS['new_games_per_user'] > 0) $q .= ", authorized_games=".$this->quote_escape($GLOBALS['new_games_per_user']);
		$q .= ", login_method='";
		if (strpos($username, '@') === false) $q .= "password";
		else $q .= "email";
		$q .= "', time_created='".time()."', verify_code='".$verify_code."';";
		$r = $this->run_query($q);
		$user_id = $this->last_insert_id();
		
		$thisuser = new User($this, $user_id);
		
		if ($user_id == 1) $this->set_site_constant("admin_user_id", $user_id);
		
		if ($GLOBALS['pageview_tracking_enabled']) {
			$q = "SELECT * FROM viewer_connections WHERE type='viewer2user' AND from_id='".$viewer_id."' AND to_id='".$thisuser->db_user['user_id']."';";
			$r = $this->run_query($q);
			if ($r->rowCount() == 0) {
				$q = "INSERT INTO viewer_connections SET type='viewer2user', from_id='".$viewer_id."', to_id='".$thisuser->db_user['user_id']."';";
				$r = $this->run_query($q);
			}
			
			$q = "UPDATE users SET ip_address=".$this->quote_escape($_SERVER['REMOTE_ADDR'])." WHERE user_id='".$thisuser->db_user['user_id']."';";
			$r = $this->run_query($q);
		}
		
		return $thisuser;
	}
	
	public function fetch_currency_prices() {
		$prices = array();
		
		$q = "SELECT * FROM currencies ORDER BY currency_id ASC;";
		$r = $this->run_query($q);
		
		while ($db_currency = $r->fetch()) {
			$prices[$db_currency['currency_id']] = $this->latest_currency_price($db_currency['currency_id']);
		}
		
		return $prices;
	}
	
	public function account_balance($account_id) {
		$balance_q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account_id."' AND io.spend_status='unspent';";
		$balance_r = $this->run_query($balance_q);
		$balance = $balance_r->fetch();
		return $balance['SUM(io.amount)'];
	}
	
	public function card_public_vars() {
		return array('issuer_card_id', 'mint_time', 'amount', 'purity', 'status');
	}
	
	public function pay_out_card(&$card, $address, $fee) {
		$db_currency = $this->run_query("SELECT * FROM currencies WHERE currency_id='".$card['currency_id']."';")->fetch();
		$blockchain = new Blockchain($this, $db_currency['blockchain_id']);
		
		$io_tx = $blockchain->fetch_transaction_by_hash($card['io_tx_hash']);
		
		if ($io_tx) {
			$io_r = $this->run_query("SELECT * FROM transaction_ios WHERE create_transaction_id='".$io_tx['transaction_id']."' AND out_index='".$card['io_out_index']."';");
			
			if ($io_r->rowCount() > 0) {
				$io = $io_r->fetch();
				$db_address = $blockchain->create_or_fetch_address($address, true, false, false, false, false);
				
				$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
				$amounts = array($io['amount']-$fee_amount);
				
				$payout_tx_error = false;
				$transaction_id = $blockchain->create_transaction("transaction", $amounts, false, array($io['io_id']), array($db_address['address_id']), array(0), $fee_amount, $payout_tx_error);
				
				if ($transaction_id) {
					$transaction = $this->fetch_transaction_by_id($transaction_id);
					
					$this->run_query("UPDATE cards SET redemption_tx_hash=".$this->quote_escape($transaction['tx_hash'])." WHERE card_id='".$card['card_id']."';");
					$card['redemption_tx_hash'] = $transaction['tx_hash'];
					$this->change_card_status($card, 'redeemed');
					
					return $transaction;
				}
				else return false;
			}
			else return false;
		}
		else return false;
	}
	
	public function redeem_card_to_account(&$thisuser, &$card, $claim_type) {
		$message = "";
		$status_code = false;
		
		$db_account = $this->user_blockchain_account($thisuser->db_user['user_id'], $card['fv_currency_id']);
		
		if ($db_account['current_address_id'] > 0) {
			$address_r = $this->run_query("SELECT * FROM addresses WHERE address_id=".$db_account['current_address_id'].";");
			
			if ($address_r->rowCount() > 0) {
				$db_address = $address_r->fetch();
				
				$db_currency = $this->run_query("SELECT * FROM currencies WHERE currency_id='".$db_account['currency_id']."';")->fetch();
				
				$blockchain = new Blockchain($this, $db_currency['blockchain_id']);
				
				$this_issuer = $this->get_issuer_by_server_name($GLOBALS['base_url'], true);
				
				$fee = 0.001;
				$fee_amount = $fee*pow(10, $blockchain->db_blockchain['decimal_places']);
				
				if ($claim_type == "to_game") $success_message = "/accounts/?action=prompt_game_buyin&account_id=".$db_account['account_id']."&amount=".($card['amount']-$fee);
				else $success_message = "/accounts/?action=view_account&account_id=".$db_account['account_id'];
				
				if ($card['issuer_id'] != $this_issuer['issuer_id']) {
					$remote_issuer = $this->run_query("SELECT * FROM card_issuers WHERE issuer_id='".$card['issuer_id']."';")->fetch();
					
					$remote_url = $remote_issuer['base_url']."/api/card/".$card['issuer_card_id']."/withdraw/?secret=".$card['secret_hash']."&fee=".$fee."&address=".$db_address['address'];
					$remote_response_raw = file_get_contents($remote_url);
					$remote_response = get_object_vars(json_decode($remote_response_raw));
					
					if ($remote_response['status_code'] == 1) {
						$status_code=1;
						$message = $success_message;
						$this->change_card_status($card, "redeemed");
						
						$q = "UPDATE cards SET redemption_tx_hash=".$this->quote_escape($remote_response['message'])." WHERE card_id='".$card['card_id']."';";
						$r = $this->run_query($q);
						$card['redemption_tx_hash'] = $remote_response['message'];
					}
					else {$status_code=12; $message = $remote_response['message'];}
				}
				else {
					$io_tx = $blockchain->fetch_transaction_by_hash($card['io_tx_hash']);
					
					if ($io_tx) {
						$io_r = $this->run_query("SELECT * FROM transaction_ios WHERE create_transaction_id='".$io_tx['transaction_id']."' AND out_index='".$card['io_out_index']."';");
						
						if ($io_r->rowCount() > 0) {
							$io = $io_r->fetch();
							$success_message .= "&io_id=".$io['io_id'];
							
							$redeem_tx_error = false;
							$transaction_id = $blockchain->create_transaction("transaction", array($io['amount']-$fee_amount), false, array($io['io_id']), array($db_address['address_id']), array(0), $fee_amount, $redeem_tx_error);
							
							if ($transaction_id) {
								$transaction = $this->fetch_transaction_by_id($transaction_id);
								
								$message = $success_message;
								$this->change_card_status($card, "redeemed");
								$status_code = 1;
								
								$q = "UPDATE cards SET redemption_tx_hash=".$this->quote_escape($transaction['tx_hash'])." WHERE card_id='".$card['card_id']."';";
								$r = $this->run_query($q);
								$card['redemption_tx_hash'] = $transaction['tx_hash'];
							}
							else {$status_code=11; $message="TX Error: ".$error_message;}
						}
						else {$status_code=10; $message="Error: card payment UTXO not found.";}
					}
					else {$status_code=9; $message="Error: card payment transaction not found.";}
				}
			}
			else {$status_code=8; $message="Error: address not found.";}
		}
		else {$status_code=7; $message="Error: this account does not have a valid address ID.";}
		
		return array($status_code, $message);
	}
	
	public function web_api_transaction_ios($transaction_id) {
		$inputs = array();
		$outputs = array();
		
		$tx_in_q = "SELECT a.address, t.tx_hash, io.out_index, io.amount, io.spend_status, io.option_index FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id JOIN transactions t ON io.create_transaction_id=t.transaction_id WHERE io.spend_transaction_id='".$transaction_id."';";
		$tx_in_r = $this->run_query($tx_in_q);
		
		while ($input = $tx_in_r->fetch(PDO::FETCH_ASSOC)) {
			array_push($inputs, $input);
		}
		
		$tx_out_q = "SELECT io.option_index, io.spend_status, io.out_index, io.amount, a.address FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.create_transaction_id='".$transaction_id."';";
		$tx_out_r = $this->run_query($tx_out_q);
		
		while ($output = $tx_out_r->fetch(PDO::FETCH_ASSOC)) {
			array_push($outputs, $output);
		}
		
		return array($inputs, $outputs);
	}
	
	public function curl_post_request($url, $data, $headers) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);

		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		$contents = curl_exec($ch);
		curl_close($ch);
		return $contents;
	}
	
	public function fetch_blockchain_by_identifier($blockchain_identifier) {
		$q = "SELECT * FROM blockchains WHERE url_identifier=".$this->quote_escape($blockchain_identifier).";";
		$r = $this->run_query($q);
		if ($r->rowCount() == 1) return $r->fetch();
		else return false;
	}
	
	public function send_login_link(&$db_thisuser, &$redirect_url, $username) {
		$access_key = $this->random_string(16);
		
		$login_url = $GLOBALS['base_url']."/wallet/?login_key=".$access_key;
		if (!empty($redirect_url)) $login_url .= "&redirect_key=".$redirect_url['redirect_key'];
		
		$q = "INSERT INTO user_login_links SET access_key=".$this->quote_escape($access_key).", username=".$this->quote_escape($username);
		if (!empty($db_thisuser['user_id'])) $q .= ", user_id='".$db_thisuser['user_id']."'";
		$q .= ", time_created='".time()."';";
		$r = $this->run_query($q);
		
		$subject = "Click here to log in to ".$GLOBALS['coin_brand_name'];
		
		$message = "<p>Someone just tried to log in to your ".$GLOBALS['coin_brand_name']." account with username: <b>".$username."</b></p>\n";
		$message .= "<p>To complete the login, please follow <a href=\"".$login_url."\">this link</a>:</p>\n";
		$message .= "<p><a href=\"".$login_url."\">".$login_url."</a></p>\n";
		$message .= "<p>If you didn't try to sign in, please delete this email.</p>\n";
		
		$delivery_id = $this->mail_async($username, $GLOBALS['coin_brand_name'], "no-reply@".$GLOBALS['site_domain'], $subject, $message, "", "", "");
	}
	
	public function first_snippet_between($string, $delim1, $delim2) {
		$startpos = strpos($string, $delim1);
		if ($startpos) {
			$snippet = substr($string, $startpos+strlen($delim1), strlen($string)-$startpos-strlen($delim1));
			$endpos = strpos($snippet, $delim2);
			if ($endpos) {
				$snippet = substr($snippet, 0, $endpos);
				return $snippet;
			}
			else return false;
		}
		else return false;
	}

	public function guess_links_containing($needle, $haystack, $delimiter, $make_unique) {
		$parts = explode($needle, $haystack);
		$urls = "";
		$u = 0;
		for ($i=1; $i<count($parts); $i++) {
			$first = substr($parts[$i-1], strrpos($parts[$i-1], $delimiter)+strlen($delimiter), strlen($parts[$i-1])-strrpos($parts[$i-1], $delimiter)-strlen($delimiter));
			$rest = substr($parts[$i], 0, strpos($parts[$i], $delimiter));
			if (strlen($rest) < 255) {
				$url = trim($first.$needle.$rest);
				
				$urls[$u] = $url;
				$u++;
			}
		}
		
		if ($make_unique) return array_values(array_unique($urls));
		else return $urls;
	}
	
	public function fetch_address_in_account($account_id, $option_index) {
		$q = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account_id."' AND a.option_index='".$option_index."';";
		$r = $this->run_query($q);
		
		if ($r->rowCount() > 0) {
			return $r->fetch();
		}
		else return false;
	}
	
	public function calculate_effectiveness_factor($vote_effectiveness_function, $effectiveness_param1, $event_starting_block, $event_final_block, $block_id) {
		if ($vote_effectiveness_function == "linear_decrease") {
			$slope = -1*$effectiveness_param1;
			$event_length_blocks = $event_final_block-$event_starting_block+1;
			$blocks_in = $block_id-$event_starting_block;
			$frac_complete = $blocks_in/$event_length_blocks;
			$effectiveness = floor(pow(10,8)*$frac_complete*$slope)/pow(10,8) + 1;
			return max(0, $effectiveness);
		}
		else return 1;
	}
	
	public function render_bet(&$bet, &$game, $coins_per_vote, $current_round, &$net_delta, &$net_stake, &$pending_stake, &$num_wins, &$num_losses, &$num_unresolved, $div_td, $last_block_id) {
		$this_bet_html = "";
		$event_total_reward = ($bet['sum_score']+$bet['sum_unconfirmed_score'])*$coins_per_vote + $bet['sum_destroy_score'] + $bet['sum_unconfirmed_destroy_score'];
		$option_effective_reward = $bet['option_effective_destroy_score']+$bet['unconfirmed_effective_destroy_score'] + ($bet['option_votes']+$bet['unconfirmed_votes'])*$coins_per_vote;
		$current_effectiveness = $this->calculate_effectiveness_factor($bet['vote_effectiveness_function'], $bet['effectiveness_param1'], $bet['event_starting_block'], $bet['event_final_block'], $last_block_id+1);
		
		$expected_payout = 0;
		
		if ($bet['spend_status'] != "unconfirmed") {
			$my_inflation_stake = $bet[$game->db_game['payout_weight']."s_destroyed"]*$coins_per_vote;
			$my_effective_stake = $bet['effective_destroy_amount'] + $bet['votes']*$coins_per_vote;
			
			if ($bet['payout_rule'] == "binary") $expected_payout = $event_total_reward*($my_effective_stake/$option_effective_reward);
			else if ((string)$bet['track_payout_price'] != "") $expected_payout = $bet['colored_amount'];
		}
		else {
			$unconfirmed_votes = $bet['ref_'.$game->db_game['payout_weight']."s"];
			if ($current_round != $bet['ref_round_id']) $unconfirmed_votes += $bet['colored_amount']*($current_round-$bet['ref_round_id']);
			$my_inflation_stake = $unconfirmed_votes*$coins_per_vote;
			$my_effective_stake = floor(($bet['destroy_amount']+$my_inflation_stake)*$current_effectiveness);
			
			if ($bet['payout_rule'] == "binary") $expected_payout = $event_total_reward*($my_effective_stake/$option_effective_reward);
		}
		$my_stake = $bet['destroy_amount'] + $my_inflation_stake;
		
		if ($my_stake > 0) {
			$payout_multiplier = $expected_payout/$my_stake;
			
			$net_stake += $my_stake/pow(10,$game->db_game['decimal_places']);
			if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "") $pending_stake += $my_stake/pow(10,$game->db_game['decimal_places']);
			
			if ($div_td == "div") $this_bet_html .= '<div class="col-sm-1 text-center">';
			else $this_bet_html .= '<td>';
			$this_bet_html .= '<a href="';
			if ($div_td == "td") $this_bet_html .= $GLOBALS['base_url'];
			$this_bet_html .= '/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$bet['tx_hash']."/".$bet['game_out_index'].'">';
			if ($game->db_game['inflation'] == "exponential") {
				$this_bet_html .= $this->format_bignum($my_stake/pow(10,$game->db_game['decimal_places']))."&nbsp;".$game->db_game['coin_abbreviation'];
			}
			else {
				$this_bet_html .= $this->format_bignum($bet['votes']/pow(10,$game->db_game['decimal_places']))." votes";
			}
			$this_bet_html .= "</a>";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1 text-center";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= $this->format_bignum($expected_payout/pow(10,$game->db_game['decimal_places']))."&nbsp;".$game->db_game['coin_abbreviation'];
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1 text-center";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			if ($bet['payout_rule'] == "binary") $this_bet_html .= "x".$this->format_bignum($payout_multiplier);
			else $this_bet_html .= "N/A";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-1";
				if ($bet['spend_status'] == "unconfirmed") $this_bet_html .= " yellowtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= round($bet['effectiveness_factor']*100, 2)."%";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") $this_bet_html .= "<div class=\"col-sm-2 text-center\">";
			else $this_bet_html .= "<td>";
			$this_bet_html .= $bet['option_name'];
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			if ($div_td == "div") $this_bet_html .= "<div class=\"col-sm-3\">";
			else $this_bet_html .= "<td>";
			$this_bet_html .= "<a target=\"_blank\" href=\"";
			if ($div_td == "td") $this_bet_html .= $GLOBALS['base_url'];
			$this_bet_html .= "/explorer/games/".$game->db_game['url_identifier']."/events/".$bet['event_index']."\">".$bet['event_name']."</a>";
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
			
			$pct_gain = false;
			
			if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "") {
				$outcome_txt = "Not Resolved";
				$num_unresolved++;
			}
			else {
				if ($bet['payout_rule'] == "binary") {
					if ($bet['winning_option_id'] == $bet['option_id']) {
						$outcome_txt = "Won";
						$delta = ($expected_payout - $my_stake)/pow(10,$game->db_game['decimal_places']);
						$num_wins++;
					}
					else {
						$outcome_txt = "Lost";
						$delta = (-1)*$my_stake/pow(10,$game->db_game['decimal_places']);
						$num_losses++;
					}
				}
				else {
					$delta = ($expected_payout - $my_stake)/pow(10,$game->db_game['decimal_places']);
					$pct_gain = round(100*($expected_payout/$my_stake-1), 2);
					
					if ($delta >= 0) {
						$outcome_txt = "Won";
						$num_wins++;
					}
					else {
						$outcome_txt = "Lost";
						$num_losses++;
					}
				}
				$net_delta += $delta;
			}
			
			if ($div_td == "div") {
				$this_bet_html .= "<div class=\"col-sm-3";
				if (empty($bet['winning_option_id']) && (string)$bet['track_payout_price'] == "") {}
				else if ($delta >= 0) $this_bet_html .= " greentext";
				else $this_bet_html .= " redtext";
				$this_bet_html .= "\">";
			}
			else $this_bet_html .= "<td>";
			$this_bet_html .= $outcome_txt;
			
			if (!empty($bet['winning_option_id']) || (string)$bet['track_payout_price'] != "") {
				$this_bet_html .= " &nbsp;&nbsp; ";
				if ($delta >= 0) $this_bet_html .= "+";
				else $this_bet_html .= "-";
				$this_bet_html .= $this->format_bignum(abs($delta));
				$this_bet_html .= " ".$game->db_game['coin_abbreviation'];
				
				if ($pct_gain !== false) {
					$this_bet_html .= " &nbsp; ";
					if ($pct_gain >= 0) $this_bet_html .= "+";
					else $this_bet_html .= "-";
					$this_bet_html .= abs($pct_gain)."%";
				}
			}
			if ($div_td == "div") $this_bet_html .= "</div>\n";
			else $this_bet_html .= "</td>\n";
		}
		return $this_bet_html;
	}
	
	public function bets_summary(&$game, &$net_stake, &$num_wins, &$num_losses, &$num_unresolved, &$pending_stake, &$net_delta) {
		$num_resolved = $num_wins+$num_losses;
		if ($num_resolved > 0) $win_rate = $num_wins/$num_resolved;
		else $win_rate = 0;
		$num_bets = $num_wins+$num_losses+$num_unresolved;
		
		$html = number_format($num_bets)." bets totalling <font class=\"greentext\">".$this->format_bignum($net_stake)."</font> ".$game->db_game['coin_name_plural']."<br/>\n";
		$html .= "You've won ".number_format($num_wins)." of your ".number_format($num_resolved)." resolved bets (".round($win_rate*100, 1)."%) for a net ";
		if ($net_delta >= 0) $html .= "gain";
		else $html .= "loss";
		$html .= " of <font class=\"";
		if ($net_delta >= 0) $html .= "greentext";
		else $html .= "redtext";
		$html .= "\">".$this->format_bignum(abs($net_delta))."</font> ".$game->db_game['coin_name_plural'];
		if ($num_unresolved > 0) $html .= "\n<br/>You have ".number_format($num_unresolved)." pending bets totalling <font class=\"greentext\">".$this->format_bignum($pending_stake)."</font> ".$game->db_game['coin_name_plural'];
		
		return $html;
	}
	
	public function import_group_from_file($import_group_description, &$error_message) {
		$import_group_fname = realpath(dirname(dirname(__FILE__)))."/lib/groups/".$import_group_description.".csv";
		
		if (is_file($import_group_fname)) {
			$import_group_fh = fopen($import_group_fname, 'r');
			$import_group_content = fread($import_group_fh, filesize($import_group_fname));
			fclose($import_group_fh);
			
			$general_entity_type = $this->check_set_entity_type("general entity");
			
			$csv_lines = explode("\n", $import_group_content);
			$header_vars = explode(",", trim(strtolower($csv_lines[0])));
			$name_col = array_search("entity_name", $header_vars);
			$image_col = array_search("default_image_id", $header_vars);
			$group_params = explode(",", $csv_lines[1]);
			
			$insert_group_q = "INSERT INTO option_groups SET option_name=".$this->quote_escape($group_params[0]).", option_name_plural=".$this->quote_escape($group_params[1]).", description=".$this->quote_escape($import_group_description).";";
			$insert_group_r = $this->run_query($insert_group_q);
			$group_id = $this->last_insert_id();
			
			for ($csv_i=2; $csv_i<count($csv_lines); $csv_i++) {
				$csv_params = explode(",", $csv_lines[$csv_i]);
				$member_entity = $this->check_set_entity($general_entity_type['entity_type_id'], $csv_params[$name_col]);
				
				if (empty($member_entity['default_image_id']) && !empty($csv_params[$image_col])) {
					$member_image_q = "UPDATE entities SET default_image_id='".$csv_params[$image_col]."' WHERE entity_id='".$member_entity['entity_id']."';";
					$member_image_r = $this->run_query($member_image_q);
				}
				$insert_member_q = "INSERT INTO option_group_memberships SET option_group_id='".$group_id."', entity_id='".$member_entity['entity_id']."';";
				$insert_member_r = $this->run_query($insert_member_q);
			}
		}
		else $error_message = "Failed to import group from file.. the file does not exist.\n";
	}
	
	public function flush_buffers() {
		@ob_end_flush();
		@ob_flush();
		@flush();
		@ob_start();
	}
	
	public function select_group_by_description($description) {
		$group_q = "SELECT * FROM option_groups WHERE description=".$this->quote_escape($description).";";
		$group_r = $this->run_query($group_q);
		
		if ($group_r->rowCount() > 0) {
			return $group_r->fetch();
		}
		else return false;
	}
	
	public function running_from_commandline() {
		if (PHP_SAPI == "cli") return true;
		else return false;
	}
	
	public function running_as_admin() {
		if ($this->running_from_commandline()) return true;
		else if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) return true;
		else return false;
	}
	
	public function refresh_address_set_indices(&$address_set) {
		$info = $this->run_query("SELECT MAX(option_index) FROM addresses WHERE address_set_id='".$address_set['address_set_id']."';")->fetch();
		if ($info['MAX(option_index)'] > 0) {
			$this->run_query("UPDATE address_sets SET has_option_indices_until='".$info['MAX(option_index)']."' WHERE address_set_id='".$address_set['address_set_id']."';");
			$address_set['has_option_indices_until'] = $info['MAX(option_index)'];
		}
	}
	
	public function finish_address_sets(&$game, &$game_addrsets, $to_option_index) {
		$fully_successful = true;
		$account = false;
		
		for ($set_i=0; $set_i<count($game_addrsets); $set_i++) {
			if ($game_addrsets[$set_i]['has_option_indices_until'] >= $to_option_index) {}
			else {
				$this->refresh_address_set_indices($game_addrsets[$set_i]);
				
				if ((string)$game_addrsets[$set_i]['has_option_indices_until'] === "") $from_option_index = 0;
				else if ($game_addrsets[$set_i]['has_option_indices_until'] == 0) $from_option_index = 1;
				else $from_option_index = $game_addrsets[$set_i]['has_option_indices_until']+1;
				
				$has_option_indices_until = false;
				$set_successful = true;
				
				for ($option_index=$from_option_index; $option_index<=$to_option_index; $option_index++) {
					if ($game->blockchain->db_blockchain['p2p_mode'] != "rpc") {
						$this->gen_address_by_index($game->blockchain, $account, $game_addrsets[$set_i]['address_set_id'], $option_index);
						
						$has_option_indices_until = $option_index;
					}
					else {
						$qq = "SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND a.option_index='".$option_index."' AND k.account_id IS NULL AND a.address_set_id=NULL;";
						$rr = $this->run_query($qq);
						
						if ($rr->rowCount() > 0) {
							$address = $rr->fetch();
							
							$this->run_query("UPDATE addresses SET address_set_id='".$game_addrsets[$set_i]['address_set_id']."' WHERE address_id='".$address['address_id']."';");
							
							$has_option_indices_until = $option_index;
						}
						else {
							$set_successful = false;
							$set_i = $num_sets_needed;
							$option_index = $to_option_index+1;
						}
					}
				}
				
				if ($has_option_indices_until !== false) {
					$this->run_query("UPDATE address_sets SET has_option_indices_until='".$has_option_indices_until."' WHERE address_set_id='".$game_addrsets[$set_i]['address_set_id']."';");
				}
				
				if (!$set_successful) $fully_successful = false;
			}
		}
		
		return $fully_successful;
	}
	
	public function apply_address_set(&$game, $account_id) {
		$addrset_r = $this->run_query("SELECT * FROM address_sets WHERE game_id='".$game->db_game['game_id']."' AND applied=0 AND has_option_indices_until IS NOT NULL ORDER BY RAND() LIMIT 1;");
		
		if ($addrset_r->rowCount() > 0) {
			$address_set = $addrset_r->fetch();
			
			$this->refresh_address_set_indices($address_set);
			
			$this->run_query("UPDATE address_sets SET applied=1 WHERE address_set_id='".$address_set['address_set_id']."';");
			
			$this->run_query("UPDATE addresses a JOIN address_keys k ON a.address_id=k.address_id SET k.account_id='".$account_id."' WHERE a.address_set_id='".$address_set['address_set_id']."' AND k.account_id IS NULL;");
			
			$this->run_query("UPDATE currency_accounts SET has_option_indices_until=".$address_set['has_option_indices_until']." WHERE account_id='".$account_id."';");
		}
	}
	
	public function gen_address_by_index(&$blockchain, $account, $address_set_id, $option_index) {
		if ($blockchain->db_blockchain['p2p_mode'] != "rpc") {
			$vote_identifier = $this->option_index_to_vote_identifier($option_index);
			$addr_text = "11".$vote_identifier;
			$addr_text .= $this->random_string(34-strlen($addr_text));
			
			if ($option_index == 0) $is_destroy_address=1;
			else $is_destroy_address=0;
			
			if ($option_index == 1) $is_separator_address=1;
			else $is_separator_address=0;
			
			$qq = "INSERT INTO addresses SET is_mine=1";
			if ($account) $qq .= ", user_id='".$account['user_id']."'";
			if ($address_set_id) $qq .= ", address_set_id=".$address_set_id;
			$qq .= ", primary_blockchain_id='".$blockchain->db_blockchain['blockchain_id']."', option_index='".$option_index."', vote_identifier=".$this->quote_escape($vote_identifier).", is_destroy_address='".$is_destroy_address."', is_separator_address='".$is_separator_address."', address=".$this->quote_escape($addr_text).", time_created='".time()."';";
			$rr = $this->run_query($qq);
			$address_id = $this->last_insert_id();
			$this->flush_buffers();
			
			$qq = "INSERT INTO address_keys SET address_id='".$address_id."'";
			if ($account) $qq .= ", account_id='".$account['account_id']."'";
			$qq .= ", save_method='fake', pub_key='".$addr_text."';";
			$rr = $this->run_query($qq);
		}
	}
}
?>