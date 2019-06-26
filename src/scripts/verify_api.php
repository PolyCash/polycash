<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['mode','game_identifier','blockchain_identifier'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$mode = $_REQUEST['mode'];
	
	$blockchain = false;
	$game = false;
	
	if (in_array($mode, ['game_ios','game_events'])) {
		$game_or_blockchain = "game";
		$db_game = $app->fetch_game_by_identifier($_REQUEST['game_identifier']);
		if (empty($db_game)) die("Invalid game identifier supplied.");
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
	}
	else {
		$game_or_blockchain = "blockchain";
		$db_blockchain = $app->fetch_blockchain_by_identifier($_REQUEST['blockchain_identifier']);
		if (empty($db_blockchain)) die("Invalid blockchain identifier supplied.");
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
	}
	
	if ($mode == "game_ios") {
		$total_issued = 0;
		
		$confirmed_gios = $app->run_query("SELECT io.spend_status, io.create_block_id AS io_create_block_id, gio.* FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND gio.game_io_index IS NOT NULL ORDER BY gio.game_io_index ASC;", ['game_id'=>$game->db_game['game_id']]);
		
		$out_obj = [["in_existence"=>$game->coins_in_existence($blockchain->last_block_id(), false), "total"=>0]];
		
		while ($game_io = $confirmed_gios->fetch()) {
			array_push($out_obj, [$game_io['game_io_index'], $game_io['io_create_block_id'], $game_io['create_block_id'], $game_io['colored_amount'], $game_io['spend_status'], $game_io['coin_rounds_created']]);
			$total_issued += $game_io['colored_amount'];
		}
		
		$unconfirmed_gios = $app->run_query("SELECT t.tx_hash, io.spend_status, io.create_block_id AS io_create_block_id, gio.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND gio.game_io_index IS NULL ORDER BY t.tx_hash ASC, gio.game_out_index ASC;", ['game_id'=>$game->db_game['game_id']]);
		
		while ($game_io = $unconfirmed_gios->fetch()) {
			array_push($out_obj, [null, $game_io['io_create_block_id'], $game_io['create_block_id'], $game_io['colored_amount'], $game_io['spend_status'], $game_io['coin_rounds_created']]);
			$total_issued += $game_io['colored_amount'];
		}
		
		$out_obj[0]['total'] = $total_issued;
		
		echo json_encode($out_obj);
	}
	else if ($mode == "game_events") {
		$check_tx_count = true;
		
		$out_obj = [];
		
		$events_by_game_params = [
			'game_id' => $game->db_game['game_id']
		];
		$events_by_game_q = "SELECT ev.event_id, gde.event_index, gde.event_name, gde.outcome_index FROM game_defined_events gde LEFT JOIN events ev ON gde.event_index=ev.event_index WHERE gde.game_id=:game_id AND ev.game_id=:game_id ORDER BY gde.event_index ASC;";
		$events_by_game = $app->run_query($events_by_game_q, $events_by_game_params);
		
		while ($db_event = $events_by_game->fetch(PDO::FETCH_ASSOC)) {
			if ($check_tx_count && !empty($db_event['event_id'])) {
				$event_tx_r = $game->blockchain->transactions_by_event($db_event['event_id']);
				$db_event['num_transactions'] = $event_tx_r->rowCount();
			}
			unset($db_event['event_id']);
			
			array_push($out_obj, $db_event);
		}
		
		echo json_encode($out_obj, JSON_PRETTY_PRINT);
	}
	else if ($mode == "blockchain") {
		$db_blockchain = $app->fetch_blockchain_by_identifier($_REQUEST['blockchain_identifier']);
		
		if ($db_blockchain) {
			$all_blocks = $app->run_query("SELECT * FROM blocks WHERE blockchain_id=:blockchain_id AND block_id>0 ORDER BY block_id ASC;", ['blockchain_id'=>$db_blockchain['blockchain_id']]);
			
			$out_obj = [];
			
			while ($block = $all_blocks->fetch()) {
				array_push($out_obj, ["block_id"=>$block['block_id'], "num_transactions"=>$block['num_transactions']]);
			}
			echo json_encode($out_obj);
		}
	}
}
else echo "Please supply the right key string\n";
?>
