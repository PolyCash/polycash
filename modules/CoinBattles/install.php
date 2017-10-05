<?php
ini_set('memory_limit', '512M');
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include_once(dirname(__FILE__)."/CoinBattlesGameDefinition.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$module = $app->check_set_module("CoinBattles");

	$q = "SELECT * FROM games WHERE module=".$app->quote_escape($module['module_name']).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$db_game = $r->fetch();
		
		echo "Found existing game, skipping...<br/>\n";
	}
	else {
		echo "Creating new game...<br/>\n";
		
		$game_def = new CoinBattlesGameDefinition($app);
		$new_game_def_txt = $app->game_def_to_text($game_def->game_def);
		
		$error_message = false;
		$new_game = $app->create_game_from_definition($new_game_def_txt, $thisuser, "CoinBattles", $error_message, false);
		
		if ($error_message) echo $error_message."<br/>\n";
		else {
			try {
				$rpc_conn_string = 'http://'.$new_game->blockchain->db_blockchain['rpc_username'].':'.$new_game->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$new_game->blockchain->db_blockchain['rpc_port'].'/';
				$coin_rpc = new jsonRPCClient($rpc_conn_string);
			}
			catch (Exception $e) {
				die("Error, failed to load RPC connection for ".$new_game->blockchain->db_blockchain['blockchain_name'].".\n");
			}
			
			$new_game->delete_reset_game('reset');
			$new_game->blockchain->unset_first_required_block();
			
			$new_game->update_db_game();
			$game_def->add_oracle_urls($new_game, $coin_rpc);
			echo "Done adding oracle URLS<br/>\n";
		}
		echo "Next please <a href=\"/scripts/reset_game.php?key=".$GLOBALS['cron_key_string']."&game_id=".$new_game->db_game['game_id']."\">reset this game</a><br/>\n";
	}
	?>
	Done!!<br/>
	<a href="/">Check installation</a>
	<?php
}
else echo "Please supply the correct key.<br/>\n";
?>