<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$game_id = (int) $_REQUEST['game_id'];
	$io_id = (int) $_REQUEST['io_id'];
	
	if ($action == "buyin") {
		$buyin_amount = (int) ($_REQUEST['buyin_amount']*pow(10,8));
		$fee_amount = (int) ($_REQUEST['fee_amount']*pow(10,8));
		
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			$db_currency = $app->run_query("SELECT * FROM currencies WHERE currency_id='".$game->blockchain->currency_id()."';")->fetch();
			
			$db_io = $app->run_query("SELECT * FROM transaction_ios WHERE io_id='".$io_id."';")->fetch();
			
			if ($db_io) {
				$color_amount = (int) ($db_io['amount'] - $buyin_amount - $fee_amount);
				
				if ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0) {
					$address_text = $_REQUEST['address'];
					
					$thisuser->ensure_user_in_game($game->db_game['game_id']);
					$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
					
					if ($address_text == "new") {
						$game_currency_account = $thisuser->create_or_fetch_game_currency_account($game);
						$color_address = $app->new_address_key($game->blockchain->currency_id(), $game_currency_account);
					}
					else {
						$coin_rpc = new jsonRPCClient('http://'.$game->blockchain->db_blockchain['rpc_username'].':'.$game->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$game->blockchain->db_blockchain['rpc_port'].'/');
						$color_address = $game->blockchain->create_or_fetch_address($address_text, true, $coin_rpc, false, false, false);
					}
					
					$transaction_id = $game->create_transaction(false, array($buyin_amount, $color_amount), $thisuser->db_user['user_id'], $thisuser->db_user['user_id'], false, 'transaction', array($io_id), array($escrow_address['address_id'], $color_address['address_id']), false, $fee_amount);
					
					if ($transaction_id) {
						$app->output_message(1, "Great, your transaction has been submitted (#".$transaction_id.")", false);
					}
					else {
						$app->output_message(7, "Failed to create the transaction.", false);
					}
				}
				else $app->output_message(6, "Error, one of the amounts you entered is invalid ($fee_amount > 0 && $buyin_amount > 0 && $color_amount > 0).", false);
			}
			else $app->output_message(5, "Error, incorrect io_id.", false);
		}
		else $app->output_message(4, "Error, incorrect game_id", false);
	}
	else $app->output_message(3, "This action is not yet implemented.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>