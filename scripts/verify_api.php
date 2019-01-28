<?php
ini_set('memory_limit', '1024M');
set_time_limit(0);
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id','mode','blockchain_identifier'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$db_game = $app->fetch_db_game_by_id((int)$_REQUEST['game_id']);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$mode = $_REQUEST['mode'];
		
		if ($mode == "game_ios") {
			$total_issued = 0;
			
			$q = "SELECT io.spend_status, io.create_block_id AS io_create_block_id, gio.* FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id='".((int)$_REQUEST['game_id'])."' AND gio.game_io_index IS NOT NULL ORDER BY gio.game_io_index ASC;";
			$r = $app->run_query($q);
			
			$out_obj = [["in_existence"=>$game->coins_in_existence($blockchain->last_block_id()), "total"=>0]];
			
			while ($game_io = $r->fetch()) {
				array_push($out_obj, [$game_io['game_io_index'], $game_io['io_create_block_id'], $game_io['create_block_id'], $game_io['colored_amount'], $game_io['spend_status'], $game_io['coin_rounds_created']]);
				$total_issued += $game_io['colored_amount'];
			}
			
			$q = "SELECT t.tx_hash, io.spend_status, io.create_block_id AS io_create_block_id, gio.* FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id='".((int)$_REQUEST['game_id'])."' AND gio.game_io_index IS NULL ORDER BY t.tx_hash ASC, gio.game_out_index ASC;";
			$r = $app->run_query($q);
			
			while ($game_io = $r->fetch()) {
				array_push($out_obj, [null, $game_io['io_create_block_id'], $game_io['create_block_id'], $game_io['colored_amount'], $game_io['spend_status'], $game_io['coin_rounds_created']]);
				$total_issued += $game_io['colored_amount'];
			}
			
			$out_obj[0]['total'] = $total_issued;
			
			echo json_encode($out_obj);
		}
		else if ($mode == "blockchain") {
			$blockchain_r = $app->run_query("SELECT * FROM blockchains WHERE url_identifier=".$app->quote_escape($_REQUEST['blockchain_identifier']).";");
			
			if ($blockchain_r->rowCount() > 0) {
				$db_blockchain = $blockchain_r->fetch();
				
				$q = "SELECT * FROM blocks WHERE blockchain_id='".$db_blockchain['blockchain_id']."' AND block_id>0 ORDER BY block_id ASC;";
				$r = $app->run_query($q);
				
				$out_obj = [];
				
				while ($block = $r->fetch()) {
					array_push($out_obj, ["block_id"=>$block['block_id'], "num_transactions"=>$block['num_transactions']]);
				}
				echo json_encode($out_obj);
			}
		}
	}
}
else echo "Syntax is: main.php?key=<CRON_KEY_STRING>\n";
?>
