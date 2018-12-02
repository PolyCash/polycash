<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id','blockchain_id','quantity','apply_user_strategies'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['game_id'])) {
		$game_id = intval($_REQUEST['game_id']);
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
	}
	else if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
		$blockchain = new Blockchain($app, $blockchain_id);
		$game = false;
	}
	
	if ($blockchain->db_blockchain['p2p_mode'] == "none") {
		$quantity = intval($_REQUEST['quantity']);
		if (!$quantity) $quantity = 1;

		for ($i=0; $i<$quantity; $i++) {
			$log_text = "";
			$blockchain->new_block($log_text);
			
			if ($game && !empty($_REQUEST['apply_user_strategies'])) {
				$block_of_round = $game->block_id_to_round_index($game->blockchain->last_block_id()+1);
				if (!empty($_REQUEST['apply_user_strategies'])) echo $game->apply_user_strategies();
			}
		}
		echo "Done!\n";
	}
	else echo "A block can't be added for this game.\n";
}
else echo "You need admin privileges to run this script.\n";
?>
