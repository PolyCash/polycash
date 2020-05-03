<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	if ($user_game) {
		if (!empty($_REQUEST['change_to_currency_id'])) {
			$change_to_currency_id = (int) $_REQUEST['change_to_currency_id'];
			$user_game = $thisuser->set_buyin_currency($user_game, $change_to_currency_id);
		}
		
		$coins_in_existence = ($game->coins_in_existence(false, true)+$game->pending_bets(true))/pow(10, $game->db_game['decimal_places']);
		
		if ($game->db_game['buyin_policy'] == "for_sale") {
			$buyin_currency = $app->fetch_currency_by_id($user_game['buyin_currency_id']);
			$escrow_value = $game->escrow_value_in_currency($user_game['buyin_currency_id'], $coins_in_existence);
			$ref_user = false;
			$pay_to_account = $game->check_set_blockchain_sale_account($ref_user, $buyin_currency);
			$game_sale_account = $game->check_set_game_sale_account($ref_user);
			$game_sale_amount = $game->account_balance($game_sale_account['account_id']);
		}
		else {
			$buyin_currency = $app->fetch_currency_by_id($game->blockchain->currency_id());
			$escrow_address = $game->blockchain->create_or_fetch_address($game->db_game['escrow_address'], true, false, false, false, false);
			$escrow_value = $game->escrow_value(false)/pow(10, $game->db_game['decimal_places']);
			$pay_to_account = $thisuser->fetch_currency_account($buyin_currency['currency_id']);
		}
		
		$buyin_blockchain = new Blockchain($app, $buyin_currency['blockchain_id']);
		
		if ($escrow_value > 0) {
			$exchange_rate = $coins_in_existence/$escrow_value;
		}
		else $exchange_rate = 0;
		
		$output_obj = [];
		
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "check_amount") {
			$content_html = "";
			
			if (!empty($_REQUEST['invoice_id'])) $invoice_id = (int) $_REQUEST['invoice_id'];
			else {
				if ($pay_to_account) {
					$invoice_type = "buyin";
					if ($game->db_game['buyin_policy'] == "for_sale") $invoice_type = "sale_buyin";
					
					$invoice = $app->new_currency_invoice($pay_to_account, $pay_to_account['currency_id'], false, $thisuser, $user_game, $invoice_type);
					$invoice_id = $invoice['invoice_id'];
				}
				else $content_html .= "Failed to generate a deposit address.";
			}
			
			$buyin_amount = floatval(str_replace(",", "", urldecode($_REQUEST['buyin_amount'])));
			$color_amount = floatval(str_replace(",", "", urldecode($_REQUEST['color_amount'])));
			$pay_amount = $buyin_amount+$color_amount;
			$receive_amount = $buyin_amount*$exchange_rate;
			
			$invoice = $app->run_query("SELECT * FROM currency_invoices ci JOIN user_games ug ON ci.user_game_id=ug.user_game_id WHERE ci.invoice_id=:invoice_id AND ci.user_game_id=:user_game_id;", [
				'invoice_id' => $invoice_id,
				'user_game_id' => $user_game['user_game_id']
			])->fetch();
			
			if ($invoice) {
				$invoice_address = $app->fetch_address_by_id($invoice['address_id']);
				
				$app->run_query("UPDATE currency_invoices SET buyin_amount=:buyin_amount, color_amount=:color_amount, pay_amount=:pay_amount WHERE invoice_id=:invoice_id;", [
					'buyin_amount' => $buyin_amount,
					'color_amount' => $color_amount,
					'pay_amount' => $pay_amount,
					'invoice_id' => $invoice['invoice_id']
				]);
				
				if ($game->db_game['buyin_policy'] == "for_sale") {
					$max_buyin_amount = $game_sale_amount/pow(10, $game->db_game['decimal_places'])/$exchange_rate;
					if ($buyin_amount > $max_buyin_amount) {
						$content_html .= '<p class="redtext">Don\'t send that many '.$buyin_blockchain->db_blockchain['coin_name_plural'].'. There are only '.$app->format_bignum($game_sale_amount/pow(10, $game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'].' for sale ('.$app->format_bignum($max_buyin_amount)." ".$buyin_currency['abbreviation'].")</p>\n";
					}
				}
				
				$content_html .= '<p>
					For '.$buyin_amount.' '.$buyin_currency['short_name_plural'].', you\'ll receive approximately '.$app->format_bignum($receive_amount).' '.$game->db_game['coin_name_plural'].'. Send '.$buyin_currency['short_name_plural'].' to <a target="_blank" href="/explorer/blockchains/'.$buyin_blockchain->db_blockchain['url_identifier'].'/addresses/'.$invoice_address['address'].'">'.$invoice_address['address'].'</a>
				</p>
				<p>
					<center><img style="margin: 10px;" src="/render_qr_code.php?data='.$invoice_address['address'].'" /></center>
				</p>
				<p>
					'.ucfirst($game->db_game['coin_name_plural']).' will automatically be credited to this account when your payment is received.
				</p>';
				
				$output_obj['invoice_id'] = $invoice['invoice_id'];
			}
			else $content_html .= "There was an error loading this invoice.";
		}
		else {
			$content_html = "<p>Which currency would you like to pay with?<br/>\n";
			$content_html .= '<select class="form-control" id="buyin_currency_id" name="buyin_currency_id" onchange="thisPageManager.change_buyin_currency(this);">';
			$content_html .= "<option value=\"\">-- Please Select --</option>\n";
			$buyin_currencies = $app->run_query("SELECT * FROM currencies c JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE b.p2p_mode='rpc' ORDER BY c.name ASC;");
			while ($a_buyin_currency = $buyin_currencies->fetch()) {
				$content_html .= "<option ";
				if ($a_buyin_currency['currency_id'] == $buyin_currency['currency_id']) $content_html .= "selected=\"selected\" ";
				$content_html .= "value=\"".$a_buyin_currency['currency_id']."\">".$a_buyin_currency['name']."</option>\n";
			}
			$content_html .= "</select>\n";
			$content_html .= "</p>\n";
			
			$content_html .= "<p>The exchange rate is ".$app->format_bignum($exchange_rate)." ".$game->db_game['coin_name_plural']." per ".$buyin_currency['short_name'].".</p>\n";
			
			$content_html .= '<p>';
			$buyin_limit = 0;
			if ($game->db_game['buyin_policy'] == "none") {
				$content_html .= "Sorry, buy-ins are not allowed in this game.";
			}
			else if ($game->db_game['buyin_policy'] == "unlimited") {
				$content_html .= "You can buy in for as many coins as you want in this game. ";
			}
			else if ($game->db_game['buyin_policy'] == "game_cap") {
				$content_html .= "This game has a game-wide buy-in cap of ".$app->format_bignum($game->db_game['game_buyin_cap'])." ".$game->blockchain->db_blockchain['coin_name_plural'].". ";
			}
			else if ($game->db_game['buyin_policy'] == "for_sale") {
				$content_html .= "There are ".$app->format_bignum($game_sale_amount/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." for sale. ";
			}
			else $content_html .= "Invalid buy-in policy.";
			
			$content_html .= "</p>\n";
			
			if ($game->db_game['buyin_policy'] == "for_sale") {
				if ($buyin_blockchain->db_blockchain['online'] == 1) {
					$content_html .= '
					<p>
						How many '.$buyin_currency['short_name_plural'].' do you want to spend?
					</p>
					<p>
						<div class="row">
							<div class="col-sm-12">
								<input type="text" class="form-control" id="buyin_amount">
							</div>
						</div>
					</p>';
					
					$content_html .= '<button class="btn btn-primary" onclick="thisPageManager.manage_buyin(\'check_amount\');">Check</button>'."\n";
				}
				else {
					$content_html .= '<p class="redtext">You can\'t buy '.$game->db_game['coin_name_plural'].' with '.$buyin_currency['abbreviation'].' here right now. '.$buyin_blockchain->db_blockchain['blockchain_name']." is not running on this node.</p>\n";
				}
			}
			else {
				$content_html .= '
				<p>
					How many '.$game->blockchain->db_blockchain['coin_name_plural'].' do you want to spend?
				</p>
				<p>
					<div class="row">
						<div class="col-sm-12">
							<input type="text" class="form-control" id="buyin_amount">
						</div>
					</div>
				</p>
				<p>
					How many '.$game->blockchain->db_blockchain['coin_name_plural'].' do you want to color?
				</p>
				<p>
					<div class="row">
						<div class="col-sm-12">
							<input type="text" class="form-control" id="color_amount">
						</div>
					</div>
				</p>';
				
				$content_html .= '<button class="btn btn-primary" onclick="thisPageManager.manage_buyin(\'check_amount\');">Check</button>'."\n";
			}
		}
		
		list($num_buyin_invoices, $buyin_invoices_html) = $game->display_buyins_by_user_game($user_game['user_game_id']);
		
		$invoices_html = "";
		if ($num_buyin_invoices > 0) {
			$invoices_html .= '<p style="margin-top: 10px;">You have '.$num_buyin_invoices.' buyin address';
			if ($num_buyin_invoices != 1) $invoices_html .= 'es';
			$invoices_html .= '. <div class="buyin_sellout_list">'.$buyin_invoices_html."</div></p>\n";
		}
		
		$output_obj['content_html'] = $content_html;
		$output_obj['invoices_html'] = $invoices_html;
		
		$app->output_message(1, "", $output_obj);
	}
	else $app->output_message(3, "You're not logged in to this game.", false);;
}
else $app->output_message(2, "Error: it looks like you're not logged into this game.", false);
?>
