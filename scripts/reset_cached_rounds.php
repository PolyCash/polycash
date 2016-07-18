<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$game = new Game($app, $game_id);
	
	if ($game) {
		$q = "DELETE FROM cached_rounds WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		$app->run_query("DELETE FROM cached_round_options WHERE game_id='".$game->db_game['game_id']."';");
		
		$last_block_id = $game->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
		
		$start_round = max(1, $game->block_to_round($game->db_game['game_starting_block']));
		
		for ($round_id=$start_round; $round_id<=$current_round-1; $round_id++) {
			if ($game->db_game['game_type'] == "real") {
				$game->add_round_from_rpc($round_id);
			}
			else {
				$game->add_round_from_db($round_id, $round_id*$game->db_game['round_length'], false);
			}
			echo "Added cached round #".$round_id." to ".$game->db_game['name']."<br/>\n";
		}
	}
	else echo "Error identifying the game.";
}
else echo "Incorrect key.";
?>
