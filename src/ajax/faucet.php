<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_db_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$user_game = $thisuser->ensure_user_in_game($game, false);
		
		$faucet_io = $game->check_faucet($user_game);
		
		if ($faucet_io) {
			$app->run_query("UPDATE address_keys SET account_id='".$user_game['account_id']."' WHERE address_key_id='".$faucet_io['address_key_id']."';");
			$app->run_query("UPDATE addresses SET user_id='".$thisuser->db_user['user_id']."' WHERE address_id='".$faucet_io['address_id']."';");
			$app->run_query("UPDATE user_games SET faucet_claims=faucet_claims+1 WHERE user_game_id='".$user_game['user_game_id']."';");
			
			$app->set_site_constant("last_faucet_giveaway_time", time());
			
			$app->output_message(1, "Successful!", false);
		}
		else $app->output_message(4, "No money is available right now from the faucet.", false);
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "You must be logged in.", false);
?>