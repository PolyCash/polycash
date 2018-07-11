<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM blockchains b JOIN games g ON b.blockchain_id=g.blockchain_id WHERE g.game_status='running' GROUP BY b.blockchain_id ORDER BY b.blockchain_id ASC;";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$last_block_id = $blockchain->last_block_id();
		
		$game_q = "SELECT * FROM games WHERE game_status='running' AND blockchain_id='".$db_blockchain['blockchain_id']."';";
		$game_r = $app->run_query($game_q);
		
		while ($db_game = $game_r->fetch()) {
			$game = new Game($blockchain, $db_game['game_id']);
			
			$event_q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND ((event_starting_block <= ".$last_block_id." AND event_final_block >= ".$last_block_id.") OR event_starting_time >= '".date("Y-m-d G:i:s", time()-24*3600)."');";
			$event_r = $app->run_query($event_q);
			
			if ($event_r->rowCount() > 0) {
				$game->check_set_game_definition("defined");
				
				while ($gde = $event_r->fetch()) {
					$game->set_gde_blocks_by_time($gde);
				}
				$game->check_set_game_definition("defined");
				
				echo "Setting GDE blocks for ".$game->db_game['name']."<br/>\n";
			}
		}
	}
}
else echo "Incorrect key supplied.\n";
?>