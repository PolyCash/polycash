<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		$user_game = $thisuser->ensure_user_in_game($game, false);
		
		$action = $_REQUEST['action'] ?? "claim";
		
		if ($action == "claim") {
			$faucet_io = $game->check_faucet($user_game);
			
			if ($faucet_io) {
				$app->run_query("UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;", [
					'account_id' => $user_game['account_id'],
					'address_key_id' => $faucet_io['address_key_id']
				]);
				$app->run_query("UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;", [
					'user_id' => $thisuser->db_user['user_id'],
					'address_id' => $faucet_io['address_id']
				]);
				$app->run_query("UPDATE user_games SET faucet_claims=faucet_claims+1, latest_claim_time=:latest_claim_time WHERE user_game_id=:user_game_id;", [
					'user_game_id' => $user_game['user_game_id'],
					'latest_claim_time' => time()
				]);
				
				$app->set_site_constant("last_faucet_giveaway_time", time());
				
				$app->output_message(1, "Successful!", false);
			}
			else $app->output_message(4, "No money is available right now from the faucet.", false);
		}
		else if ($action == "check") {
			list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available) = $game->user_faucet_info($user_game['user_id'], $user_game['game_id']);
			
			if ($eligible_for_faucet) {
				$faucet_io = $game->check_faucet($user_game);
				
				if ($faucet_io) {
					$html .= '<p><button id="faucet_btn" class="btn btn-success" onclick="thisPageManager.claim_from_faucet();"><i class="fas fa-hand-paper"></i> &nbsp; Claim '.$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].'</button></p>'."\n";
				}
				else $html .= "There's no money in the faucet right now.";
			}
			else {
				if ($time_available) {
					$ref_user_game = false;
					$faucet_io = $game->check_faucet($ref_user_game);
					
					$html .= "You'll be eligible to claim ".$app->format_bignum($faucet_io['colored_amount_sum']/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural']." from the faucet in ".$app->format_seconds($time_available-time()).".";
				}
				else $html .= "You're not eligible to claim coins from this faucet.";
			}
			
			$app->output_message(1, $html);
		}
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "You must be logged in.", false);
?>