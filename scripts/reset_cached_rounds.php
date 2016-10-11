<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$game = new Game($app, $game_id);
	
	if ($game) {
		$q = "DELETE eo.*, eeo.* FROM event_outcomes eo JOIN events e ON eo.event_id=e.event_id LEFT JOIN event_outcome_options eoo ON eo.outcome_id=eoo.outcome_id WHERE e.game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		$last_block_id = $game->last_block_id();
		$current_round = $game->block_to_round($last_block_id+1);
		
		$start_round = max(1, $game->block_to_round($game->db_game['game_starting_block']));
		
		for ($round_id=$start_round; $round_id<=$current_round-1; $round_id++) {
			if ($game->db_game['p2p_mode'] == "rpc") {
				$game->add_round_from_rpc($round_id);
			}
			else {
				echo "add round #".$round_id."<br/>\n";
				$game->add_round_from_db($round_id, $round_id*$game->db_game['round_length'], true);
			}
			echo "Added cached round #".$round_id." to ".$game->db_game['name']."<br/>\n";
		}
	}
	else echo "Error identifying the game.";
}
else echo "Incorrect key.";
?>
