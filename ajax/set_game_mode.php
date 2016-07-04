<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$mode = $_REQUEST['mode'];
	if ($mode != "real") $mode = "instant";
	
	if ($mode == "instant") {
		$q = "SELECT * FROM games WHERE creator_id='".$thisuser['user_id']."' AND game_type='".$mode."';";
		$r = run_query($q);
		
		if (mysql_numrows($r) == 0) {
			$q = "INSERT INTO games SET creator_id='".$thisuser['user_id']."', game_type='".$mode."', name='Practice Mode';";
			$r = run_query($q);
			$game_id = mysql_insert_id();
		}
		else {
			$game = mysql_fetch_array($r);
			$game_id = $game['game_id'];
			
			$q = "DELETE FROM webwallet_transactions WHERE game_id='".$game_id."';";
			$r = run_query($q);
			
			$q = "DELETE FROM blocks WHERE game_id='".$game_id."';";
			$r = run_query($q);
			
			$q = "DELETE FROM cached_rounds WHERE game_id='".$game_id."';";
			$r = run_query($q);
			
			$q = "DELETE FROM game_nations WHERE game_id='".$game_id."';";
			$r = run_query($q);
		}
		
		ensure_game_nations($game_id);
		
		$q = "SELECT * FROM users;";
		$r = run_query($q);
		while ($player = mysql_fetch_array($r)) {
			ensure_user_in_game($player['user_id'], $game_id);
			new_webwallet_transaction($game_id, false, 100000000000, $player['user_id'], last_block_id($game_id), 'giveaway');
		}
		
		$q = "UPDATE users SET game_id='".$game_id."' WHERE user_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		
		echo "1";
	}
	else {
		$q = "UPDATE users SET game_id='".get_site_constant('primary_game_id')."' WHERE user_id='".$thisuser['user_id']."';";
		$r = run_query($q);
		
		echo "1";
	}
}
else echo "Please log in.";
?>