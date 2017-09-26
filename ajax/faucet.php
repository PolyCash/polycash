<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';");
	
	if ($db_game_r->rowCount() > 0) {
		$db_game = $db_game_r->fetch();
		
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$user_game = $thisuser->ensure_user_in_game($game, false);
		
		$faucet_io = $game->check_faucet($user_game);
		
		if ($faucet_io) {
			$q = "UPDATE address_keys SET account_id='".$user_game['account_id']."' WHERE address_key_id='".$faucet_io['address_key_id']."';";
			$r = $app->run_query($q);
			
			$q = "UPDATE addresses SET user_id='".$thisuser->db_user['user_id']."' WHERE address_id='".$faucet_io['address_id']."';";
			$r = $app->run_query($q);
			
			$q = "UPDATE user_games SET faucet_claims=faucet_claims+1 WHERE user_game_id='".$user_game['user_game_id']."';";
			$r = $app->run_query($q);
			
			$app->set_site_constant("last_faucet_giveaway_time", time());
			
			$app->output_message(1, "Successful!", false);
		}
		else $app->output_message(4, "No money is available right now from the faucet.", false);
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "You must be logged in.", false);
?>