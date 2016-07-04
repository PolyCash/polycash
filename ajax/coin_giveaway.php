<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM invitations WHERE used_user_id='".$thisuser['user_id']."' AND used_time=0 AND used=0;";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$invitation = mysql_fetch_array($r);
		
		$q = "INSERT INTO webwallet_transactions SET game_id='".$thisuser['game_id']."', currency_mode='beta', transaction_desc='giveaway', amount=100000000000, user_id='".$thisuser['user_id']."', block_id='".(last_block_id($thisuser['game_id'], $thisuser['currency_mode'])+1)."', address_id='".user_address_id($thisuser['game_id'], $thisuser['user_id'], false)."', time_created='".time()."';";
		$r = run_query($q);
		
		$q = "UPDATE invitations SET used_time='".time()."', used=1, used_ip='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."' WHERE invitation_id='".$invitation['invitation_id']."';";
		$r = run_query($q);
		
		echo "1";
	}
	else echo "0";
}
?>