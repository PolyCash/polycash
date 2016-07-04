<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser && $game) {
	$amount = floatval($_REQUEST['amount']);
	$address = $_REQUEST['address'];
	
	if ($amount > 0) {
		$amount = $amount*pow(10,8);
		$last_block_id = last_block_id($game['game_id']);
		$mining_block_id = $last_block_id+1;
		$account_value = account_coin_value($game, $thisuser);
		$immature_balance = immature_balance($game, $thisuser);
		$mature_balance = mature_balance($game, $thisuser);
		
		$remainder_address_id = $_REQUEST['remainder_address_id'];
		
		if ($remainder_address_id == "random") {
			$q = "SELECT * FROM addresses WHERE user_id='".$thisuser['user_id']."' AND option_id > 0 ORDER BY RAND() LIMIT 1;";
			$r = run_query($q);
			$remainder_address = mysql_fetch_array($r);
			$remainder_address_id = $remainder_address['address_id'];
		}
		else $remainder_address_id = intval($remainder_address_id);
		
		$user_strategy = false;
		$success = get_user_strategy($thisuser['user_id'], $game['game_id'], $user_strategy);
		if ($success) {
			if ($amount <= $mature_balance) {
				$q = "SELECT * FROM addresses a LEFT JOIN users u ON a.user_id=u.user_id WHERE a.address='".mysql_real_escape_string($address)."' AND a.game_id='".$game['game_id']."';";
				$r = run_query($q);
				
				if (mysql_numrows($r) == 1) {
					$address = mysql_fetch_array($r);
					
					$transaction_id = new_transaction($game, false, array($amount), $thisuser['user_id'], $address['user_id'], false, 'transaction', false, array($address['address_id']), $remainder_address_id);
					
					$output_obj['result_code'] = 1;
					$output_obj['message'] = "Great, your coins have been sent!";
				}
				else {
					$output_obj['result_code'] = 6;
					$output_obj['message'] = "It looks like you entered an invalid address.";
				}
			}
			else {
				$output_obj['result_code'] = 5;
				$output_obj['message'] = "You don't have that many coins to spend, your transaction has been canceled.";
			}
		}
		else {
			$output_obj['result_code'] = 4;
			$output_obj['message'] = "It looks like you entered an invalid address.";
		}
	}
	else {
		$output_obj['result_code'] = 3;
		$output_obj['message'] = "Please enter a valid amount.";
	}
}
else {
	$output_obj['result_code'] = 2;
	$output_obj['message'] = "Please log in to withdraw coins.";
}

echo json_encode($output_obj);
?>