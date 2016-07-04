<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM webwallet_transactions WHERE currency_mode='".$thisuser['currency_mode']."' AND transaction_desc='giveaway' AND user_id='".$thisuser['user_id']."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 0) {
		$q = "INSERT INTO webwallet_transactions SET currency_mode='".$thisuser['currency_mode']."', transaction_desc='giveaway', amount=100000000000, user_id='".$thisuser['user_id']."', block_id='".(last_block_id($thisuser['currency_mode'])+1)."', address_id='".user_address_id($thisuser['user_id'], false)."', time_created='".time()."';";
		$r = run_query($q);
		echo "1";
	}
	else echo "0";
}
?>