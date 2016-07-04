<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$user_id = intval($_REQUEST['user_id']);
	
	$q = "SELECT * FROM users WHERE user_id='".$user_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$user = mysql_fetch_array($r);
		$q = "UPDATE transaction_IOs io JOIN addresses a ON io.address_id=a.address_id SET io.user_id='".$user['user_id']."', a.user_id='".$user['user_id']."' WHERE io.game_id=".get_site_constant('primary_game_id')." AND io.spend_status='unspent' AND io.user_id IS NULL AND a.user_id IS NULL AND a.is_mine=1;";
		$r = run_query($q);
		echo "All unclaimed coins have been granted to ".$user['username']."<br/>\n";
	}
	else {
		echo "Please supply a valid user ID.";
	}
	
	$q = "UPDATE transaction_IOs io JOIN addresses a ON io.address_id=a.address_id SET io.user_id=a.user_id WHERE io.spend_status='unspent';";
	$r = run_query($q);
	echo "q: $q<br/>\n";
}
else echo "Please supply the correct key.";
?>