<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$card_id = (int) $_REQUEST['card_id'];

$q = "SELECT c.* FROM cards c JOIN card_designs d ON c.design_id=d.design_id WHERE c.card_id=".$app->quote_escape($card_id).";";
$r = $app->run_query($q);

$code = $_REQUEST['code'];
$code_hash = $app->card_secret_to_hash($code);

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

if ($r->rowCount() == 1) {
	$card = $r->fetch();
	
	if ($card['status'] == "sold") {
		$bruteforce_q = "SELECT * FROM card_failedchecks WHERE ip_address=".$app->quote_escape($_SERVER['REMOTE_ADDR'])." AND check_time > ".(time()-3600*24*4).";";
		$bruteforce_r = $app->run_query($bruteforce_q);
		$num_bruteforce = $bruteforce_r->rowCount();
		
		if ($code_hash == $card['secret_hash'] && $num_bruteforce < 100) {
			$password = "";
			if (!empty($_REQUEST['password'])) $password = $_REQUEST['password'];
			
			if ($action == "login") { // Check if the card's valid and try create a gift card account
				if ($password == "" || $password == hash("sha256", "")) {
					echo "5";
				}
				else {
					$_SESSION['code'] = $code;
					
					$success = $app->try_create_card_account($card, $thisuser, $code_hash, $password);
					if ($success[0]) {
						//send_giftcard_redeemed_email($thisuser, $seller, $card, $btc_amount);
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
					
					if ($card['secret_hash'] == $supplied_secret_hash) {
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