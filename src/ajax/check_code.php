<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$card_id = (int) $_REQUEST['card_id'];
$peer_id = (int) $_REQUEST['peer_id'];

$code = $_REQUEST['code'];
$code_hash = $app->card_secret_to_hash($code);

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

$card = $app->fetch_card_by_peer_and_id($peer_id, $card_id);

if ($card) {
	if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
	else $redirect_url = false;
	
	$this_peer = $app->get_peer_by_server_name(AppSettings::getParam('base_url'), true);
	
	if ($card['peer_id'] != $this_peer['peer_id']) {
		$remote_peer = $app->fetch_peer_by_id($card['peer_id']);
	}
	else $remote_peer = false;
	
	if (AppSettings::getParam('pageview_tracking_enabled')) {
		$num_bruteforce = count($app->run_query("SELECT * FROM card_failedchecks WHERE ip_address=:ip_address AND check_time > :check_time;", [
			'ip_address' => $_SERVER['REMOTE_ADDR'],
			'check_time' => (time()-3600*24*4)
		])->fetchAll());
	}
	else $num_bruteforce = 0;
	
	$correct_secret = false;
	if ($remote_peer) {
		$remote_url = $remote_peer['base_url']."/api/card/".$card['peer_card_id']."/check/".$code_hash;
		$remote_response = get_object_vars(json_decode(file_get_contents($remote_url)));
		if ($remote_response['status_code'] == 1) $correct_secret = true;
	}
	else if ($code_hash == $card['secret_hash'] && $num_bruteforce < 100) $correct_secret = true;
	
	if ($card['status'] == "sold") {
		if ($correct_secret) {
			$password = "";
			if (!empty($_REQUEST['password'])) $password = $_REQUEST['password'];
			
			if ($action == "login") { // Check if the card's valid and try create a gift card account
				if ($password == "" || $password == hash("sha256", "")) {
					$app->output_message(5, "Invalid password.", false);
				}
				else {
					$success = $app->try_create_card_account($card, $thisuser, $password);
					if ($success[0]) {
						if (empty($card['secret_hash'])) {
							$app->run_query("UPDATE cards SET secret_hash=:secret_hash WHERE card_id=:card_id;", [
								'secret_hash' => $code_hash,
								'card_id' => $card['card_id']
							]);
							$card['secret_hash'] = $code_hash;
						}
						
						$message = "/cards/";
						if ($card['default_game_id'] > 0) {
							list($status_code, $message) = $app->redeem_card_to_account($thisuser, $card, "to_account");
							
							$db_game = $app->fetch_game_by_id($card['default_game_id']);
							$message = "/wallet/".$db_game['url_identifier']."/";
						}
						
						if ($redirect_url) $message = $redirect_url['url'];
						
						$app->output_message(1, $message, false);
					}
					else $app->output_message(6, "Failed to create card account.", false);
				}
			}
			else $app->output_message(4, "Correct!", false);
		}
		else {
			$app->run_insert_query("card_failedchecks", [
				'card_id' => $card['card_id']
				'ip_address' => AppSettings::getParam('pageview_tracking_enabled') ? $_SERVER['REMOTE_ADDR'] : null,
				'check_time' => time(),
				'attempted_code' => $code
			]);
			
			$app->output_message(0, "Unspecified error", false);
		}
	}
	else {
		if ($action == "login") {
			if ($card['status'] == "redeemed" || $card['status'] == "claimed") {
				$card_user = $app->run_query("SELECT * FROM card_users WHERE card_user_id=:card_user_id;", ['card_user_id'=>$card['card_user_id']])->fetch();
				
				if ($card_user['password'] == $_REQUEST['password']) {
					$supplied_secret_hash = $app->card_secret_to_hash($_REQUEST['code']);
					
					if ($correct_secret) {
						$session_key = $_COOKIE['my_session'];
						$expire_time = time()+3600*24;
						
						$app->run_insert_query("card_sessions", [
							'card_user_id' => $card_user['card_user_id'],
							'session_key' => $session_key,
							'login_time' => time(),
							'expire_time' => $expire_time,
							'ip_address' => AppSettings::getParam('pageview_tracking_enabled') ? $_SERVER['REMOTE_ADDR'] : null,
							'synchronizer_token' => $app->random_string(32)
						]);
						
						$message = "/wallet/";
						if ($redirect_url) $message = $redirect_url['url'];
						
						echo $app->output_message(2, $message, false);
					}
					else $app->output_message(3, "Invalid card secret supplied", false);;
				}
				else $app->output_message(3, "Invalid password", false);
			}
			else $app->output_message(0, "Unspecified error", false);
		}
		else $app->output_message(0, "Unspecified error", false);
	}
}
else $app->output_message(0, "Unspecified error", false);
?>