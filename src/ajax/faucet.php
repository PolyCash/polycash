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
			$claim_from_faucet = Faucet::fetchById($app, $_REQUEST['faucet_id']);
			
			if ($claim_from_faucet) {
				$my_faucet_receiver = Faucet::myReceiverForFaucet($app, $thisuser->db_user['user_id'], $claim_from_faucet);
				
				if ($my_faucet_receiver) {
					$claim_count = Faucet::claimMaxFromFaucet($app, $game, $user_game, $my_faucet_receiver, $claim_from_faucet);

					if ($claim_count > 0) $app->output_message(1, "Successful!", false);
					else $app->output_message(4, "No money is available right now from the faucet.", false);
				}
				else $app->output_message(5, "Sorry, you're not a member of that faucet.", false);
			}
			else $app->output_message(4, "Please supply a valid faucet ID.", false);
		}
		else if ($action == "check") {
			$my_faucet_receivers = Faucet::myFaucetReceivers($app, $thisuser->db_user['user_id'], $game->db_game['game_id']);

			$faucet_message = "<p>You're in ".count($my_faucet_receivers)." faucet".(count($my_faucet_receivers) == 1 ? "" :"s")."</p>";

			foreach ($my_faucet_receivers as $my_faucet_receiver) {
				list($eligible_for_faucet, $time_available, $num_claims_now) = Faucet::faucetReceiveInfo($my_faucet_receiver);

				$faucet_ios = Faucet::getReceivableTxosFromFaucet($app, $game, $my_faucet_receiver, $my_faucet_receiver, $num_claims_now);

				$claim_amount_int = 0;
				foreach ($faucet_ios as $faucet_io) {
					$claim_amount_int += $faucet_io['colored_amount_sum'];
				}

				if ($num_claims_now > 0 && $claim_amount_int > 0) {
					$faucet_message .= $app->render_view('faucet_button', [
						'display_from_name' => $my_faucet_receiver['display_from_name'],
						'claim_amount_int' => $claim_amount_int,
						'game' => $game,
						'faucet_id' => $my_faucet_receiver['faucet_id'],
					]);
				}
				else {
					if ($time_available) {
						list($eligible_for_faucet, $time_available, $num_claims_then) = Faucet::faucetReceiveInfo($my_faucet_receiver, $time_available);
						$next_claim_amount_int = 0;

						$faucet_ios = Faucet::getReceivableTxosFromFaucet($app, $game, null, $my_faucet_receiver, $num_claims_then);

						$next_claim_amount_int = 0;
						foreach ($faucet_ios as $faucet_io) {
							$next_claim_amount_int += $faucet_io['colored_amount_sum'];
						}

						if ($next_claim_amount_int > 0) {
							$faucet_message .= "<p>You'll be eligible to claim ".$game->display_coins($next_claim_amount_int)." from ".$my_faucet_receiver['display_from_name']." in ".$app->format_seconds($time_available-time()).".</p>";
						}
						else $faucet_message .= "<p>".$my_faucet_receiver['display_from_name'].": No money was found in the faucet.</p>";
					}
					else $faucet_message .= "<p>".$my_faucet_receiver['display_from_name'].": No money was found in the faucet.</p>";
				}
			}
			
			$app->output_message(1, $faucet_message);
		}
	}
	else $app->output_message(3, "Invalid game ID.", false);
}
else $app->output_message(2, "You must be logged in.", false);
?>