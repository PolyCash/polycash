<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser && $game) {
	$user_game = $thisuser->ensure_user_in_game($game->db_game['game_id']);
	
	$amount = floatval($_REQUEST['amount']);
	$fee = floatval($_REQUEST['fee']);
	$address_text = $_REQUEST['address'];
	
	if ($amount > 0 && $fee >= 0) {
		$amount = $amount*pow(10,8);
		$fee = $fee*pow(10,8);
		$last_block_id = $game->blockchain->last_block_id();
		$mining_block_id = $last_block_id+1;
		
		$blockchain_mature_balance = $game->blockchain->user_mature_balance($thisuser);
		$game_mature_balance = $thisuser->mature_balance($game);
		
		$remainder_address_id = $_REQUEST['remainder_address_id'];
		
		if ($remainder_address_id == "random") {
			$q = "SELECT * FROM addresses WHERE primary_blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."' AND user_id='".$thisuser->db_user['user_id']."' AND option_index IS NOT NULL AND is_mine=1 ORDER BY RAND() LIMIT 1;";
			$r = $app->run_query($q);
			$remainder_address = $r->fetch();
			$remainder_address_id = $remainder_address['address_id'];
		}
		else $remainder_address_id = intval($remainder_address_id);
		
		$user_strategy = false;
		$success = $game->get_user_strategy($thisuser->db_user['user_id'], $user_strategy);
		
		if ($success) {
			if ($amount <= $game_mature_balance && $fee < $blockchain_mature_balance) {
				$q = "SELECT SUM(gio.colored_amount), gio.io_id, io.amount FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN addresses a ON io.address_id=a.address_id WHERE gio.game_id='".$game->db_game['game_id']."' AND a.user_id='".$thisuser->db_user['user_id']."' AND io.amount > ".$fee." GROUP BY gio.io_id ORDER BY io.create_block_id DESC, SUM(gio.colored_amount) ASC;";
				$r = $app->run_query($q);
				
				$spend_gio = false;
				while ($spend_gio == false && $gio = $r->fetch()) {
					if ($gio['SUM(gio.colored_amount)'] >= $amount) {
						$spend_gio = $gio;
					}
				}
				
				if ($spend_gio) {
					$coloredcoins_per_coin = $spend_gio['SUM(gio.colored_amount)']/($spend_gio['amount']-$fee);
					$io_amount = round($amount/$coloredcoins_per_coin);
					$remainder_amount = $spend_gio['amount']-$fee-$io_amount;
					
					$address_ok = false;
					
					if ($game->blockchain->db_blockchain['p2p_mode'] == "rpc") {
						$coin_rpc = new jsonRPCClient('http://'.$game->blockchain->db_blockchain['rpc_username'].':'.$game->blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$game->blockchain->db_blockchain['rpc_port'].'/');
						$validate_address = $coin_rpc->validateaddress($address_text);
						$address_ok = $validate_address['isvalid'];
						if ($address_ok) {
							$db_address = $game->blockchain->create_or_fetch_address($address_text, TRUE, $coin_rpc, FALSE, FALSE, FALSE);
						}
					}
					else {
						$q = "SELECT * FROM addresses a LEFT JOIN users u ON a.user_id=u.user_id WHERE a.address=".$app->quote_escape($address_text)." AND a.blockchain_id='".$game->blockchain->db_blockchain['blockchain_id']."';";
						$r = $app->run_query($q);
						if ($r->rowCount() == 1) {
							$db_address = $r->fetch();
							$address_ok = true;
						}
					}
					
					if ($address_ok) {
						$address = $r->fetch();
						
						$transaction_id = $game->create_transaction(false, array($io_amount, $remainder_amount), $user_game, false, 'transaction', array($spend_gio['io_id']), array($db_address['address_id'], $remainder_address_id), false, $fee);
						
						if ($transaction_id) {
							$app->output_message(1, 'Great, your coins have been sent! <a target="_blank" href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$transaction_id.'">View Transaction</a>');
						}
						else $app->output_message(8, "There was an error creating the transaction", false);
					}
					else $app->output_message(7, "It looks like you entered an invalid address.", false);
				}
				else $app->output_message(6, "You don't have any single UTXO with that many ".$game->db_game['coin_name_plural'].". Try making several smaller transactions.", false);
			}
			else $app->output_message(5, "You don't have that many coins to spend, your transaction has been canceled.", false);
		}
		else $app->output_message(4, "It looks like you entered an invalid address.", false);
	}
	else $app->output_message(3, "Please enter a valid amount.", false);
}
else $app->output_message(2, "Please log in to withdraw coins.", false);
?>
