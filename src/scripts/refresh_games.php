<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$refresh_games_params = [];
	$refresh_games_q = "SELECT * FROM games";
	if (!empty($_REQUEST['game_id'])) {
		$refresh_games_q .= " WHERE game_id=:game_id";
		$refresh_games_params['game_id'] = $_REQUEST['game_id'];
	}
	$refresh_games = $app->run_query($refresh_games_q, $refresh_games_params);

	$show_internal_params = true;
	
	while ($db_game = $refresh_games->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$ensure_block = $blockchain->last_block_id()+1;
		if ($game->db_game['finite_events'] == 1) $ensure_block = max($ensure_block, $game->max_gde_starting_block());
		$game->ensure_events_until_block($ensure_block, true);
		
		$game->update_option_votes();
		GameDefinition::set_cached_definition_hashes($game);
		$game->set_cached_fields();
		
		echo "Ensured events until ".$ensure_block."<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>
