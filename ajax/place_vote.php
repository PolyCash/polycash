<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$account_value = account_coin_value($thisuser['game_id'], $thisuser);
	$immature_balance = immature_balance($thisuser['game_id'], $thisuser);
	$mature_balance = $account_value - $immature_balance;
	
	$last_block_id = last_block_id($thisuser['game_id']);
	
	if (($last_block_id+1)%get_site_constant('round_length') == 0) {
		echo "4=====The final block of the round is being mined, so you can't vote right now.";
	}
	else {
		$nation_id = intval($_REQUEST['nation_id']);
		
		if ($nation_id > 0 && $nation_id <= 16) {
			$amount = floatval($_REQUEST['amount']);
			
			if ($amount == round($amount, 5)) {
				$amount = $amount*pow(10,8);
				
				if ($amount <= $mature_balance && $amount > 0) {
					$transaction_id = new_webwallet_transaction($thisuser['game_id'], $nation_id, $amount, $thisuser['user_id'], $last_block_id+1, 'transaction');
					
					echo "0=====";
				}
				else echo "1=====You don't have that many coins available to vote right now.";
			}
			else echo "2=====Please enter amounts rounded to 5 decimal places.";
		}
		else echo "3=====Invalid nation ID.";
	}
}
else echo "5=====Please <a href=\"/wallet/\">log in</a>.";
?>