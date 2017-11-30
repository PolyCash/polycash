<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$card_id = (int) $_REQUEST['card_id'];
$issuer_id = (int) $_REQUEST['issuer_id'];

$q = "SELECT c.* FROM cards c LEFT JOIN card_designs d ON c.design_id=d.design_id WHERE c.issuer_id='".$issuer_id."' AND c.issuer_card_id='".$card_id."';";
$r = $app->run_query($q);

$code = $_REQUEST['code'];
$code_hash = $app->card_secret_to_hash($code);

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

if ($r->rowCount() == 1) {
	$card = $r->fetch();
	
	$this_issuer = $app->get_issuer_by_server_name($GLOBALS['base_url']);
	
	if ($card['issuer_id'] != $this_issuer['issuer_id']) {
		$remote_issuer = $app->run_query("SELECT * FROM card_issuers WHERE issuer_id='".$card['issuer_id']."';")->fetch();
	}
	else $remote_issuer = false;
	
	if ($GLOBALS['pageview_tracking_enabled']) {
		$bruteforce_q = "SELECT * FROM card_failedchecks WHERE ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR'])." AND check_time > ".(time()-3600*24*4).";";
		$bruteforce_r = $app->run_query($bruteforce_q);
		$num_bruteforce = $bruteforce_r->rowCount();
	}
	else $num_bruteforce = 0;
	
	$correct_secret = false;
	if ($remote_issuer) {
		$remote_url = $remote_issuer['base_url']."/api/card/".$card['issuer_card_id']."/check/".$code_hash;
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
					echo "5";
				}
				else {
					$_SESSION['code'] = $code;
					
					$success = $app->try_create_card_account($card, $thisuser, $password);
					if ($success[0]) {
						echo "1";
					}
					else echo "6";
					
					$_SESSION['code'] = "";
				}
			}
			else { // Check if the card is valid, but user hasn't chosen what to do yet
				$_SESSION['action'] = "redeem";
				$_SESSION['card_id'] = $card['card_id'];
				$_SESSION['code'] = $code;
				echo "1";
			}
		}
		else {
			$q = "INSERT INTO card_failedchecks SET card_id=".$app->quote_escape($card['card_id']).", ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']).", check_time='".time()."', attempted_code=".$app->quote_escape($code).";";
			$r = $app->run_query($q);
			
			echo "0";
		}
	}
	else {
		if ($action == "login") {
			if ($card['status'] == "redeemed") {
				$q = "SELECT * FROM card_users WHERE card_user_id='".$card['card_user_id']."';";
				$r = $app->run_query($q);
				$card_user = $r->fetch();
				
				if ($card_user['password'] == $_REQUEST['password']) {
					$supplied_secret_hash = $app->card_secret_to_hash($_REQUEST['code']);
					
					if ($correct_secret) {
						$session_key = session_id();
						$expire_time = time()+3600*24;
						
						$query = "INSERT INTO card_sessions SET card_user_id=".$card_user['card_user_id'];
						$query .= ", session_key=".$app->quote_escape($session_key).", login_time='".time()."', expire_time='".$expire_time."'";
						if ($GLOBALS['pageview_tracking_enabled']) $query .= ", ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR']);
						$query .= ";";
						$result = $app->run_query($query);
						
						echo "2";
					}
					else echo "3";
				}
				else echo "3";
			}
			else echo "0";
		}
		else echo "0";
	}
}
else echo "0";
?>