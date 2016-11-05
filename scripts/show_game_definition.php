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
		$game = new Game($blockchain, $db_game['game_id']);
		$game_definition = $app->fetch_game_definition($game);
		
		echo "<html><head><title>Game definition: ".$app->game_definition_hash($game)."</title></head><body><pre>".json_encode($game_definition, JSON_PRETTY_PRINT)."</pre></body></html>\n";
	}
	else $app->output_message(3, "No game was found matching that game ID.", false);
}
else $app->output_message(2, "Please supply a game ID.", false);
?>