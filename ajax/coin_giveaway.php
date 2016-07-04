<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM games WHERE game_id='".$thisuser['game_id']."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	if ($game['game_type'] == "simulation" && ($game['giveaway_status'] == "on" || $game['giveaway_status'] == "invite_only")) {
		$giveaway_block_id = last_block_id($thisuser['game_id']);
		if (!$giveaway_block_id) $giveaway_block_id = 0;
		
		if ($game['giveaway_status'] == "invite_only") {
			$q = "SELECT * FROM invitations WHERE game_id='".$game['game_id']."' AND used_user_id='".$thisuser['user_id']."' AND used_time=0 AND used=0;";
			$r = run_query($q);
			
			if (mysql_numrows($r) > 0) {
				$invitation = mysql_fetch_array($r);
				
				for ($i=0; $i<5; $i++) {
					$transaction_id = new_webwallet_multi_transaction($game, false, array(intval($game['giveaway_amount']/5)), false, $thisuser['user_id'], $giveaway_block_id, 'giveaway', false, false, false);
				}
				
				$q = "UPDATE invitations SET used_time='".time()."', used=1, used_ip='".mysql_real_escape_string($_SERVER['REMOTE_ADDR'])."' WHERE invitation_id='".$invitation['invitation_id']."';";
				$r = run_query($q);
				
				echo "1";
			}
			else {
				echo "0";
			}
		}
		else {
			$q = "SELECT * FROM webwallet_transactions t JOIN transaction_IOs io ON t.transaction_id=io.create_transaction_id WHERE io.game_id='".$game['game_id']."' AND io.user_id='".$thisuser['user_id']."' AND t.transaction_desc='giveaway';";
			$r = run_query($q);
			if (mysql_numrows($r) > 0) {
				echo "0";
			}
			else {
				for ($i=0; $i<5; $i++) {
					$transaction_id = new_webwallet_multi_transaction($game, false, array(intval($game['giveaway_amount']/5)), false, $thisuser['user_id'], $giveaway_block_id, 'giveaway', false, false, false);
				}
				echo "1";
			}
		}
	}
	else echo "0";
}
?>