<?php
include('../includes/connect.php');
include("../includes/get_session.php");

die("This script is disabled.");

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);
	
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$game = mysql_fetch_array($r);
		$quantity = 100;
		
		for ($i=0; $i<$quantity; $i++) {
			$invitation = false;
			generate_invitation($game, $thisuser['user_id'], $invitation, false);
		}
		echo "$quantity invitations have been generated.";
	}
}
?>