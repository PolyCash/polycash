<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	$user_strategy = $game->fetch_user_strategy($user_game);
	
	$featured_strategy_id = (int) $_REQUEST['featured_strategy_id'];
	
	$success_message = "Your strategy has been successfully updated.";
	
	if ($featured_strategy_id == 0) {
		$app->run_query("UPDATE user_strategies SET voting_strategy='manual', featured_strategy_id=NULL, time_next_apply=NULL WHERE strategy_id=:strategy_id;", [
			'strategy_id' => $user_strategy['strategy_id']
		]);
		$app->output_message(1, $success_message, false);
	}
	else {
		$featured_strategy = $app->run_query("SELECT * FROM featured_strategies WHERE featured_strategy_id=:featured_strategy_id;", [
			'featured_strategy_id' => $featured_strategy_id
		])->fetch();

		if ($featured_strategy && $featured_strategy['game_id'] == $game->db_game['game_id']) {
			$strategy_param_values = json_decode($user_strategy['featured_strategy_params'], true);
			$strategy_params = json_decode($featured_strategy['strategy_params'], true);

			if ($strategy_params !== null) {
				$values_this_strategy = [];

				foreach ($strategy_params as $param_name => $param_info) {
					$values_this_strategy[$param_name] = $_REQUEST['strategy_'.$featured_strategy_id.'_'.$param_name] ?? null;
				}

				$strategy_param_values[$featured_strategy_id] = $values_this_strategy;
			}

			$app->run_query("UPDATE user_strategies SET voting_strategy='featured', featured_strategy_id=:featured_strategy_id, time_next_apply=NULL, featured_strategy_params=:strategy_param_values WHERE strategy_id=:strategy_id;", [
				'featured_strategy_id' => $featured_strategy['featured_strategy_id'],
				'strategy_id' => $user_strategy['strategy_id'],
				'strategy_param_values' => json_encode($strategy_param_values),
			]);
			
			$app->output_message(1, $success_message, false);
		}
		else $app->output_message(3, "Error identifying the featured strategy.", false);
	}
}
else $app->output_message(2, "Invalid game or user ID.", false);
?>