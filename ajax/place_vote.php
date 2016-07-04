<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$account_value = account_coin_value($thisuser);
	$immature_balance = immature_balance($thisuser);
	$mature_balance = $account_value - $immature_balance;
	
	$last_block_id = last_block_id($thisuser['currency_mode']);
	
	if (($last_block_id+1)%get_site_constant('round_length') == 0) {
		echo "4=====The final block of the round is being mined, so you can't vote right now.";
	}
	else {
		$nation_id = intval($_REQUEST['nation_id']);
		
		if ($nation_id > 0 && $nation_id <= 16) {
			$amount = floatval($_REQUEST['amount']);
			if ($amount == round($amount, 5)) {
				if ($amount <= $mature_balance && $amount > 0) {
					$q = "INSERT INTO webwallet_transactions SET currency_mode='".$thisuser['currency_mode']."', nation_id='".$nation_id."', transaction_desc='transaction', amount=".$amount*(pow(10, 8)).", user_id='".$thisuser['user_id']."', block_id='".($last_block_id+1)."', time_created='".time()."';";
					$r = run_query($q);
					
					$q = "INSERT INTO webwallet_transactions SET currency_mode='".$thisuser['currency_mode']."', transaction_desc='transaction', amount=".(-1)*$amount*(pow(10, 8)).", user_id='".$thisuser['user_id']."', block_id='".($last_block_id+1)."', time_created='".time()."';";
					$r = run_query($q);
					
					echo "0=====";
				}
				else echo "1=====You don't have that many coins available to vote right now.";
			}
			else echo "2=====Please enter amounts rounded to 5 decimal places.";
		}
		else echo "3=====Invalid nation ID.";
	}
}
else echo "5=====Please log in.";
?>