<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_strategy = $game->fetch_user_strategy($user_game);
	
	$featured_strategy_id = (int) $_REQUEST['featured_strategy_id'];
	$featured_strategy = $app->run_query("SELECT * FROM featured_strategies WHERE featured_strategy_id=:featured_strategy_id;", [
		'featured_strategy_id' => $featured_strategy_id
	])->fetch();
	
	if ($featured_strategy && $featured_strategy['game_id'] == $game->db_game['game_id']) {
		$app->run_query("UPDATE user_strategies SET voting_strategy='featured', featured_strategy_id=:featured_strategy_id WHERE strategy_id=:strategy_id;", [
			'featured_strategy_id' => $featured_strategy['featured_strategy_id'],
			'strategy_id' => $user_strategy['strategy_id']
		]);
		
		$app->output_message(1, "Strategy updated!", false);
	}
	else $app->output_message(3, "Error identifying the featured strategy.", false);
}
else $app->output_message(2, "Invalid game or user ID.", false);
?>