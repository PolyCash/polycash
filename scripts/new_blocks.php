<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['blockchain_id'])) $_REQUEST['blockchain_id'] = $cmd_vars['blockchain_id'];
	if (!empty($cmd_vars['quantity'])) $_REQUEST['quantity'] = $cmd_vars['quantity'];
	if (!empty($cmd_vars['apply_user_strategies'])) $_REQUEST['apply_user_strategies'] = $cmd_vars['apply_user_strategies'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
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
		echo "Done!<br/>\n";
	}
	else echo "A block can't be added for this game.";
}
else echo "Incorrect key.";
?>
