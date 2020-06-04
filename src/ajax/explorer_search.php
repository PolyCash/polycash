<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$blockchain_id = false;
$game_id = false;

if (!empty($_REQUEST['game_id'])) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
	}
}

if (!$game && !empty($_REQUEST['blockchain_id'])) {
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$blockchain = new Blockchain($app, $blockchain_id);
}

if ($blockchain) {
	$search_term = trim(strip_tags(urldecode($_REQUEST['search_term'])));
	
	$matching_address = $app->run_query("SELECT * FROM addresses WHERE primary_blockchain_id=:blockchain_id AND address=:search_term;", [
		'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		'search_term' => $search_term
	])->fetch();
	
	if ($matching_address) {
		if ($game) {
			$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/addresses/".$matching_address['address'], false);
		}
		else $app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/addresses/".$matching_address['address'], false);
	}
	else {
		$matching_tx = $blockchain->fetch_transaction_by_hash($search_term);
		
		if ($matching_tx) {
			if ($game) {
				$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/transactions/".$matching_tx['tx_hash'], false);
			}
			else $app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$matching_tx['tx_hash'], false);
		}
		else {
			if ($game) {
				$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/search/?for=".$_REQUEST['search_term'], false);
			}
			else $app->output_message(1, "No results were found.", false);
		}
	}
}
else $app->output_message(2, "Please supply a valid blockchain ID.", false);
?>