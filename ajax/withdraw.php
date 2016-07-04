<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser) {
	$amount = $_REQUEST['amount'];
	$address = $_REQUEST['address'];
	
	if ($amount > 0) {
		$last_block_id = last_block_id($thisuser['game_id']);
		$mining_block_id = $last_block_id+1;
		$account_value = account_coin_value($thisuser);
		$immature_balance = immature_balance($thisuser);
		$mature_balance = $account_value - $immature_balance;
		
		if ($amount <= $mature_balance) {
			$q = "SELECT * FROM addresses a LEFT JOIN users u ON a.user_id=u.user_id WHERE a.address='".mysql_real_escape_string($address)."' AND a.game_id='".$thisuser['game_id']."';";
			$r = run_query($q);
			
			if (mysql_numrows($r) == 1) {
				$address = mysql_fetch_array($r);
				
				$q = "INSERT INTO webwallet_transactions SET game_id='".$thisuser['game_id']."', transaction_desc='transaction', amount=".(-1*$amount*pow(10,8)).", user_id='".$thisuser['user_id']."', block_id='".$mining_block_id."', time_created='".time()."';";
				$r = run_query($q);
				
				$q = "INSERT INTO webwallet_transactions SET game_id='".$thisuser['game_id']."', transaction_desc='transaction', amount=".($amount*pow(10,8)).", user_id='".$address['user_id']."', block_id='".$mining_block_id."', nation_id='".$address['nation_id']."', time_created='".time()."';";
				$r = run_query($q);
				
				$output_obj['result_code'] = 5;
				$output_obj['message'] = "Great, your coins have been sent!";
			}
			else {
				$output_obj['result_code'] = 4;
				$output_obj['message'] = "It looks like you entered an invalid address.";
			}
		}
		else {
			$output_obj['result_code'] = 3;
			$output_obj['message'] = "You don't have that many coins to spend, your transaction has been canceled.";
		}
	}
	else {
		$output_obj['result_code'] = 2;
		$output_obj['message'] = "Please enter a valid amount.";
	}
}
else {
	$output_obj['result_code'] = 1;
	$output_obj['message'] = "Please log in to withdraw coins.";
}

echo json_encode($output_obj);
?>