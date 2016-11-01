<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['key'] = $cmd_vars['key'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = (int) $_REQUEST['game_id'];

	if ($game_id > 0) {
		$db_game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';");
		
		if ($db_game_r->rowCount() > 0) {
			$db_game = $db_game_r->fetch();
			
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			if (!empty($_REQUEST['block_id'])) {
				$block_id = (int) $_REQUEST['block_id'];
				$game->process_sellouts_in_block($block_id);
			}
			else {
				for ($block_id=$game->db_game['game_starting_block']; $block_id<=$game->blockchain->last_block_id(); $block_id++) {
					$game->process_sellouts_in_block($block_id);
				}
			}
		}
	}
}
else echo "Please supply the correct key.\n";
?>