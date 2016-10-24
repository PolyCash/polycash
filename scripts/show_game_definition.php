<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

$game_id = (int) $_REQUEST['game_id'];

if ($game_id > 0) {
	$db_game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';");
	
	if ($db_game_r->rowCount() > 0) {
		$db_game = $db_game_r->fetch();
		
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		
		$game_definition = array();
		$game_definition['blockchain_identifier'] = $blockchain->db_blockchain['url_identifier'];
		
		$verbatim_vars = $app->game_definition_verbatim_vars();
		
		for ($i=0; $i<count($verbatim_vars); $i++) {
			$var_type = $verbatim_vars[$i][0];
			$var_name = $verbatim_vars[$i][1];
			
			if ($var_type == "int") {
				if ($db_game[$var_name] == "0" || $db_game[$var_name] > 0) $var_val = (int) $db_game[$var_name];
				else $var_val = null;
			}
			else if ($var_type == "float") $var_val = (float) $db_game[$var_name];
			else $var_val = $db_game[$var_name];
			
			$game_definition[$var_name] = $var_val;
		}
		
		echo "<pre>".json_encode($game_definition, JSON_PRETTY_PRINT)."</pre>";
	}
	else $app->output_message(3, "No game was found matching that game ID.", false);
}
else $app->output_message(2, "Please supply a game ID.", false);
?>