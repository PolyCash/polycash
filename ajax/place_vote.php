<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$account_value = account_coin_value($thisuser['game_id'], $thisuser);
	$immature_balance = immature_balance($thisuser['game_id'], $thisuser);
	$mature_balance = $account_value - $immature_balance;
	
	$noinfo_fail_output = "1=====Invalid URL";
	
	$io_ids_csv = $_REQUEST['io_ids'];
	$io_ids = explode(",", $io_ids_csv);
	
	$nation_ids_csv = $_REQUEST['nation_ids'];
	$nation_ids = explode(",", $nation_ids_csv);
	
	$amounts_csv = $_REQUEST['amounts'];
	$amounts = explode(",", $amounts_csv);
	
	if (count($io_ids) > 0 && count($amounts) > 0) {}
	else die($noinfo_fail_output);
	
	if (count($amounts) != count($nation_ids)) die("2=====Nation IDs and amounts do not match");
	
	for ($i=0; $i<count($io_ids); $i++) {
		$io_id = intval($io_ids[$i]);
		if ($io_id > 0) {
			$qq = "SELECT * FROM transaction_IOs WHERE io_id='".$io_id."';";
			$rr = run_query($qq);
			if (mysql_numrows($rr) == 1) {
				$io = mysql_fetch_array($rr);
				
				if ($io['user_id'] != $thisuser['user_id'] || $io['spend_status'] != "unspent" || $io['game_id'] != $thisuser['game_id']) die($noinfo_fail_output);
				else {
					if ($io['create_block_id'] <= last_block_id($thisuser['game_id'])-get_site_constant('maturity') || $io['instantly_mature'] == 1) $io_ids[$i] = $io_id;
					else die("3=====One of the coin inputs you selected is not yet mature.");
				}
			}
			else die($noinfo_fail_output);
		}
		else die($noinfo_fail_output);
	}
	
	$amount_sum = 0;
	
	for ($i=0; $i<count($nation_ids); $i++) {
		$nation_id = intval($nation_ids[$i]);
		if ($nation_id > 0 && $nation_id <= 16) {
			$nation_ids[$i] = $nation_id;
		}
		else die("4=====Invalid nation ID");
		
		$amount = intval($amounts[$i]);
		if ($amount > 0) {
			$amounts[$i] = $amount;
			$amount_sum += $amount;
		}
		else die("5=====An invalid amount was included.");
	}
	
	$last_block_id = last_block_id($thisuser['game_id']);
	
	if (($last_block_id+1)%get_site_constant('round_length') == 0) {
		echo "6=====The final block of the round is being mined, so you can't vote right now.";
	}
	else {
		if ($amount_sum <= $mature_balance && $amount_sum > 0) {
			$transaction_id = new_webwallet_multi_transaction($thisuser['game_id'], $nation_ids, $amounts, $thisuser['user_id'], $thisuser['user_id'], $last_block_id+1, 'transaction', $io_ids, false, false);
			
			if ($transaction_id) {
				echo "0=====Your voting transaction has been submitted!";
			}
			else {
				echo "7=====Error, the transaction was canceled.";
			}
		}
		else echo "8=====You don't have that many coins available to vote right now.";
	}
}
else echo "9=====Please <a href=\"/wallet/\">log in</a>.";
?>