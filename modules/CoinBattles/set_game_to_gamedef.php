<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include_once(dirname(__FILE__)."/CoinBattlesGameDefinition.php");

$game_id = (int) $_REQUEST['game_id'];

$game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';");

if ($game_r->rowCount() > 0) {
	$db_game = $game_r->fetch();
	
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	
	$game = new Game($blockchain, $db_game['game_id']);
	
	$game_def = new CoinBattlesGameDefinition($app);
	
	if ($game->db_game['url_identifier'] != $game_def->game_def->url_identifier) {
		$game_def->game_def->url_identifier = $game->db_game['url_identifier'];
	}
	
	if ($game->db_game['name'] != $game_def->game_def->name) {
		$game_def->game_def->name = $game->db_game['name'];
	}
	
	$initial_game_def = $app->fetch_game_definition($game);
	$initial_game_def_hash = $app->game_definition_hash($game);
	
	$new_game_def_txt = $app->game_def_to_text($game_def->game_def);
	
	$new_game_def_hash = $app->game_def_to_hash($new_game_def_txt);
	
	$game->check_set_game_definition();
	$app->check_set_game_definition($new_game_def_hash, $game_def->game_def);
	
	$app->migrate_game_definitions($game, $initial_game_def_hash, $new_game_def_hash);
	
	echo "migrating to ".$initial_game_def_hash." to ".$new_game_def_hash.", done!!";
}
else echo "Sorry, no match found for that game_id.<br/>\n";
?>