<?php
require(dirname(__DIR__)."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$allowed_params = ['api_key', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

$user_game = $app->fetch_user_game_by_api_key($_REQUEST['api_key']);

if ($user_game) {
	$user = new User($app, $user_game['user_id']);
	$blockchain = new Blockchain($app, $user_game['blockchain_id']);
	$game = new Game($blockchain, $user_game['game_id']);
	
	$last_block_id = $blockchain->last_block_id();
	
	$hours_between_applications = 9;
	
	$sec_between_applications = 60*60*$hours_between_applications;
	
	if ($game->last_block_id() != $blockchain->last_block_id()) {
		$app->output_message(9, "The game is not fully loaded.", false);
		die();
	}
	if (time() > $user_game['time_next_apply'] || !empty($_REQUEST['force'])) {
		$account = $app->fetch_account_by_id($user_game['account_id']);
		
		if ($account) {
			$my_faucet_receivers = Faucet::myFaucetReceivers($app, $user->db_user['user_id'], $game->db_game['game_id']);
			
			$claim_count = 0;

			foreach ($my_faucet_receivers as $my_faucet_receiver) {
				$claim_count += Faucet::claimMaxFromFaucet($app, $game, $user_game, $my_faucet_receiver, $my_faucet_receiver);
			}

			$app->set_strategy_time_next_apply($user_game['strategy_id'], time()+$sec_between_applications);

			if ($claim_count > 0) $app->output_message(1, "Successfully claimed coins from the faucet.", false);
			else $app->output_message(5, "No coins were available in the faucet.", false);
		}
		else $app->output_message(4, "Invalid account ID.");
	}
	else $app->output_message(3, "Skipping.. this strategy was applied recently.", false);
}
else $app->output_message(2, "Error: the api_key you supplied does not match any user_game.", false);