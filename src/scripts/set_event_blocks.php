<?php
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$log_text = "";
	
	$relevant_blockchains_params = [];
	$relevant_blockchains_q = "SELECT * FROM blockchains b JOIN games g ON b.blockchain_id=g.blockchain_id WHERE g.game_status='running'";
	if (!empty($_REQUEST['game_id'])) {
		$relevant_blockchains_q .= " AND g.game_id=:game_id";
		$relevant_blockchains_params['game_id'] = $_REQUEST['game_id'];
	}
	$relevant_blockchains_q .= " GROUP BY b.blockchain_id ORDER BY b.blockchain_id ASC;";
	$relevant_blockchains = $app->run_query($relevant_blockchains_q, $relevant_blockchains_params);
	
	while ($db_blockchain = $relevant_blockchains->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$last_block_id = $blockchain->last_block_id();
		
		$relevant_games_params = [
			'blockchain_id' => $db_blockchain['blockchain_id']
		];
		$relevant_games_q = "SELECT * FROM games WHERE game_status='running' AND blockchain_id=:blockchain_id";
		if (!empty($_REQUEST['game_id'])) {
			$relevant_games_q .= " AND game_id=:game_id";
			$relevant_games_params['game_id'] = $_REQUEST['game_id'];
		}
		$relevant_games = $app->run_query($relevant_games_q, $relevant_games_params);
		
		while ($db_game = $relevant_games->fetch()) {
			$game = new Game($blockchain, $db_game['game_id']);
			$log_text .= $game->set_event_blocks(false);
		}
	}
	echo $log_text;
}
else echo "You need admin privileges to run this script.\n";
?>