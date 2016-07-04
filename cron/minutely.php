<?php
include("../includes/connect.php");

die('Script disabled');

if ($_REQUEST['key'] == "2r987jifwow") {
	$q = "UPDATE users SET logged_in=0 WHERE last_active<".(time()-60*2).";";
	$r = run_query($q);
	
	$q = "SELECT * FROM games WHERE game_id='".get_site_constant('primary_game_id')."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	$last_block_id = last_block_id(get_site_constant('primary_game_id'));
	
	$num = rand(0, round($game['seconds_per_block']/60)-1);
	if ($_REQUEST['force_new_block'] == "1") $num = 0;
	
	if ($num == 0) {
		echo new_block(get_site_constant('primary_game_id'));
	}
	else {
		echo "No block (".$num.")<br/>";
	}
	
	// Apply user strategies
	echo apply_user_strategies($game);
}
else echo "Error: permission denied.";
?>