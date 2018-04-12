<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_strategy = $game->fetch_user_strategy($user_game);
	
	$featured_strategy_id = (int) $_REQUEST['featured_strategy_id'];
	
	$q = "UPDATE user_strategies SET voting_strategy='featured', featured_strategy_id='".$featured_strategy_id."' WHERE strategy_id='".$user_strategy['strategy_id']."';";
	$r = $app->run_query($q);
	
	$app->output_message(1, "Strategy updated!", false);
}
else $app->output_message(2, "Invalid game or user ID.", false);
?>