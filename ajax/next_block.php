<?php
include("../includes/connect.php");
include("../includes/get_session.php");
$viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	$q = "SELECT * FROM games WHERE game_id='".$thisuser['game_id']."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	if ($game['game_type'] == "simulation" && $game['creator_id'] == $thisuser['user_id'] && $game['block_timing'] == "user_controlled") {
		$log_text = new_block($game['game_id']);
		$log_text = apply_user_strategies($game);
		echo "1";
	}
	else echo "2";
}
?>