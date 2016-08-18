<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$output_obj['result_code'] = 0;
$output_obj['message'] = "";

if ($thisuser && $game) {
	$amount = floatval($_REQUEST['amount']);
	$fee = floatval($_REQUEST['fee']);
	$address_text = $_REQUEST['address'];
	
	if ($amount > 0) {
		$amount = $amount*pow(10,8);
		$fee = $fee*pow(10,8);
		$last_block_id = $game->last_block_id();
		$mining_block_id = $last_block_id+1;
		$account_value = $thisuser->account_coin_value($game);
		$immature_balance = $thisuser->immature_balance($game);
		$mature_balance = $thisuser->mature_balance($game);
		
		$remainder_address_id = $_REQUEST['remainder_address_id'];
		
		if ($remainder_address_id == "random") {
			$q = "SELECT * FROM addresses WHERE game_id='".$game->db_game['game_id']."' AND user_id='".$thisuser->db_user['user_id']."' AND option_index IS NOT NULL AND is_mine=1 ORDER BY RAND() LIMIT 1;";
			$r = $app->run_query($q);
			$remainder_address = $r->fetch();
			$remainder_address_id = $remainder_address['address_id'];
		}
		else $remainder_address_id = intval($remainder_address_id);
		
		$user_strategy = false;
		$success = $game->get_user_strategy($thisuser->db_user['user_id'], $user_strategy);
		if ($success) {
			if ($amount+$fee <= $mature_balance) {
				$address_ok = false;
				
				if ($game->db_game['game_type'] == "real") {
					$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');
					$validate_address = $coin_rpc->validateaddress($address_text);
					$address_ok = $validate_address['isvalid'];
					if ($address_ok) {
						$db_address = $game->create_or_fetch_address($address_text, TRUE, $coin_rpc, FALSE, FALSE, FALSE);
					}
				}
				else {
					$q = "SELECT * FROM addresses a LEFT JOIN users u ON a.user_id=u.user_id WHERE a.address=".$app->quote_escape($address_text)." AND a.game_id='".$game->db_game['game_id']."';";
					$r = $app->run_query($q);
					if ($r->rowCount() == 1) {
						$db_address = $r->fetch();
						$address_ok = true;
					}
				}
				
				if ($address_ok) {
					$address = $r->fetch();
					
					$transaction_id = $game->create_transaction(false, array($amount), $thisuser->db_user['user_id'], $db_address['user_id'], false, 'transaction', false, array($db_address['address_id']), $remainder_address_id, $fee);
					
					if ($transaction_id) {
						$app->output_message(1, 'Great, your coins have been sent! <a target="_blank" href="/explorer/'.$game->db_game['url_identifier'].'/transactions/'.$transaction_id.'">View Transaction</a>');
					}
					else $app->output_message(7, "There was an error creating the transaction");
				}
				else $app->output_message(6, "It looks like you entered an invalid address.");
			}
			else $app->output_message(5, "You don't have that many coins to spend, your transaction has been canceled.");
		}
		else $app->output_message(4, "It looks like you entered an invalid address.");
	}
	else $app->output_message(3, "Please enter a valid amount.");
}
else $app->output_message(2, "Please log in to withdraw coins.");
?>
