<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$blockchain_id = false;
$game_id = false;

if (!empty($_REQUEST['game_id'])) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_db_game_by_id($game_id);
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
	$search_term = $_REQUEST['search_term'];
	
	$q = "SELECT e.*, g.url_identifier AS game_url_identifier FROM events e JOIN games g ON e.game_id=g.game_id WHERE e.event_name=".$app->quote_escape($search_term).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$db_event = $r->fetch();
		$app->output_message(1, "/explorer/games/".$db_event['game_url_identifier']."/events/".$db_event['event_index'], false);
	}
	else {
		$q = "SELECT * FROM addresses WHERE primary_blockchain_id=".$blockchain->db_blockchain['blockchain_id']." AND address=".$app->quote_escape($search_term).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) {
			$db_address = $r->fetch();
			
			if ($game) {
				$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/addresses/".$db_address['address'], false);
			}
			else $app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/addresses/".$db_address['address'], false);
		}
		else {
			$db_transaction = $blockchain->fetch_transaction_by_hash($search_term);
			
			if ($db_transaction) {
				if ($game) {
					$app->output_message(1, "/explorer/games/".$game->db_game['url_identifier']."/transactions/".$db_transaction['tx_hash'], false);
				}
				else $app->output_message(1, "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/transactions/".$db_transaction['tx_hash'], false);
			}
			else $app->output_message(3, "No results were found.", false);
		}
	}
}
else $app->output_message(2, "Please supply a valid blockchain ID.", false);
?>