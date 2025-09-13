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
			$claim_count = $game->claim_max_from_faucet($user_game);

			if ($claim_count > 0) $app->output_message(1, "Successful!", false);
			else $app->output_message(4, "No money is available right now from the faucet.", false);
		}
		else if ($action == "check") {
			list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available, $num_claims_now) = $game->user_faucet_info($user_game['user_id'], $user_game['game_id']);
			
			$faucet_message = "";

			$faucet_account = $game->check_set_faucet_account();

			$faucet_ios = $game->check_faucet($user_game, $num_claims_now);

			$claim_amount_int = 0;
			foreach ($faucet_ios as $faucet_io) {
				$claim_amount_int += $faucet_io['colored_amount_sum'];
			}

			if ($num_claims_now > 0 && $claim_amount_int > 0) {
				$faucet_message .= $app->render_view('faucet_button', [
					'claim_amount_int' => $claim_amount_int,
					'game' => $game,
				]);
			}
			else {
				if ($time_available) {
					list($earliest_join_time, $most_recent_claim_time, $user_faucet_claims, $eligible_for_faucet, $time_available, $num_claims_then) = $game->user_faucet_info($user_game['user_id'], $user_game['game_id'], $time_available);
					$next_claim_amount_int = 0;

					$ref_user_game = null;
					$faucet_ios = $game->check_faucet($ref_user_game, $num_claims_then);

					$next_claim_amount_int = 0;
					foreach ($faucet_ios as $faucet_io) {
						$next_claim_amount_int += $faucet_io['colored_amount_sum'];
					}

					if ($next_claim_amount_int > 0) {
						$faucet_message .= "You'll be eligible to claim ".$game->display_coins($next_claim_amount_int)." from the faucet in ".$app->format_seconds($time_available-time()).".";
					}
					else $faucet_message = "There's no money in the faucet right now.";
				}
				else $faucet_message .= "You're not eligible to claim coins from this faucet.";
			}
			
			$app->output_message(1, $faucet_message);
		}
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "You must be logged in.", false);
?>