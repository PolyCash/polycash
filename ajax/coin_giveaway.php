<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM invitations WHERE used_user_id='".$thisuser['user_id']."' AND used_time=0 AND used=0;";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$invitation = mysql_fetch_array($r);
		
		new_webwallet_multi_transaction($thisuser['game_id'], false, array(100000000000), false, $thisuser['user_id'], last_block_id($thisuser['game_id']), 'giveaway', false, false, false);
		
		$q = "UPDATE invitations SET used_time='".time()."', used=1, used_ip='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."' WHERE invitation_id='".$invitation['invitation_id']."';";
		$r = run_query($q);
		
		echo "1";
	}
	else echo "0";
}
?>