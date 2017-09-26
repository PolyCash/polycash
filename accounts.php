<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && !empty($_REQUEST['action']) && $_REQUEST['action'] == "donate_to_faucet") {
	$io_id = (int) $_REQUEST['account_io_id'];
	$amount_each = (float) $_REQUEST['donate_amount_each'];
	$satoshis_each = pow(10,8)*$amount_each;
	$quantity = (int) $_REQUEST['donate_quantity'];
	$game_id = (int) $_REQUEST['donate_game_id'];
	$fee_amount = 0.001*pow(10,8);
	
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$db_game = $r->fetch();
		$donate_blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$donate_game = new Game($donate_blockchain, $db_game['game_id']);
		
		if ($quantity > 0 && $satoshis_each > 0) {
			$total_cost_satoshis = $quantity*$satoshis_each;
			
			$q = "SELECT * FROM transaction_ios WHERE io_id='".$io_id."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$db_io = $r->fetch();
				
				$q = "SELECT * FROM transaction_game_ios gio JOIN transaction_ios io ON io.io_id=gio.io_id WHERE io.io_id='".$io_id."' AND gio.game_id='".$game_id."';";
				$r = $app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$faucet_account = $donate_game->check_set_faucet_account();
					
					$game_ios = array();
					$colored_coin_sum = 0;
					
					while ($game_io = $r->fetch()) {
						array_push($game_ios, $game_io);
						$colored_coin_sum += $game_io['colored_amount'];
					}
					
					$coin_sum = $game_ios[0]['amount'];
					$coins_per_chain_coin = (float) $colored_coin_sum/($coin_sum-$fee_amount);
					$chain_coins_each = ceil($satoshis_each/$coins_per_chain_coin);
					
					if ($game_ios[0]['spend_status'] == "unspent") {
						$address_ids = array();
						$addresses_needed = $quantity;
						$loop_count = 0;
						do {
							$addr_q = "SELECT * FROM addresses a WHERE a.primary_blockchain_id='".$donate_blockchain->db_blockchain['blockchain_id']."' AND a.is_mine=1 AND a.user_id IS NULL AND NOT EXISTS (SELECT * FROM transaction_ios io WHERE io.address_id=a.address_id) ORDER BY RAND() LIMIT 1;";
							$addr_r = $app->run_query($addr_q);
							
							if ($addr_r->rowCount() > 0) {
								$db_address = $addr_r->fetch();
								
								if (empty($db_address['user_id'])) {
									$update_addr_q = "UPDATE addresses SET user_id='".$thisuser->db_user['user_id']."' WHERE address_id='".$db_address['address_id']."';";
									$update_addr_r = $app->run_query($update_addr_q);
									
									$addr_key_q = "INSERT INTO address_keys SET address_id='".$db_address['address_id']."', account_id='".$faucet_account['account_id']."', save_method='wallet.dat', pub_key=".$app->quote_escape($db_address['address']).";";
									$addr_key_r = $app->run_query($addr_key_q);
									
									$addresses_needed--;
									
									array_push($address_ids, $db_address['address_id']);
								}
								else echo "Error, ".$address['address_id']." is already owned by someone.<br/>\n";
							}
							$loop_count++;
						}
						while ($addresses_needed > 0 && $loop_count < $quantity*2);
						
						if ($addresses_needed > 0) die("Not enough free addresses (still need $addresses_needed/$quantity).");
						
						$account_q = "SELECT ca.* FROM currency_accounts ca JOIN games g ON g.game_id=ca.game_id JOIN address_keys k ON k.account_id=ca.account_id WHERE ca.user_id='".$thisuser->db_user['user_id']."' AND k.address_id='".$game_ios[0]['address_id']."';";
						$account_r = $app->run_query($account_q);
						
						if ($account_r->rowCount() > 0) {
							$donate_account = $account_r->fetch();
							
							if ($total_cost_satoshis < $colored_coin_sum && $coin_sum > $chain_coins_each*$quantity - $fee_amount) {
								$remainder_satoshis = $coin_sum - ($chain_coins_each*$quantity) - $fee_amount;
								
								$amounts = array();
								
								for ($i=0; $i<$quantity; $i++) {
									array_push($amounts, $chain_coins_each);
								}
								if ($remainder_satoshis > 0) {
									array_push($amounts, $remainder_satoshis);
									array_push($address_ids, $game_ios[0]['address_id']);
								}
								
								$transaction_id = $donate_game->blockchain->create_transaction('transaction', $amounts, false, array($game_ios[0]['io_id']), $address_ids, $fee_amount);
								
								if ($transaction_id) {
									header("Location: /explorer/games/".$db_game['url_identifier']."/transactions/".$transaction_id."/");
									die();
								}
								else echo "Error: failed to create the transaction.<br/>\n";
							}
							else {
								echo "UTXO is only ".$app->format_bignum($colored_coin_sum/pow(10,8))." ".$donate_game->db_game['coin_name_plural']." but you tried to spend ".$app->format_bignum($total_cost_satoshis/pow(10,8))."<br/>\n";
							}
						}
						else echo "You don't own this UTXO.<br/>\n";
					}
					else echo "Invalid UTXO.<br/>\n";
				}
				else echo "Invalid UTXO ID.<br/>\n";
			}
			else echo "Invalid UTXO ID.<br/>\n";
		}
		else echo "Invalid quantity.<br/>\n";
	}
	else echo "Invalid game ID.<br/>\n";
	die();
}

$pagetitle = "My Accounts";
$nav_tab_selected = "accounts";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<?php
	if ($thisuser) {
		?>
		<script type="text/javascript">
		var selected_account_id = false;
		function toggle_account_details(account_id) {
			$('#account_details_'+account_id).toggle('fast');
			selected_account_id = account_id;
		}
		</script>
		<h1>Coin Accounts</h1>
		<?php
		$account_q = "SELECT ca.*, c.*, b.url_identifier AS blockchain_url_identifier, k.pub_key FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id JOIN addresses a ON ca.current_address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE ca.user_id='".$thisuser->db_user['user_id']."';";
		$account_r = $app->run_query($account_q);
		
		echo "<p>You have ".$account_r->rowCount()." coin account";
		if ($account_r->rowCount() != 1) echo "s";
		echo ".</p>\n";
		
		while ($account = $account_r->fetch()) {
			if ($account['game_id'] > 0) {
				$blockchain = new Blockchain($app, $account['blockchain_id']);
				$account_game = new Game($blockchain, $account['game_id']);
			}
			else $account_game = false;
			
			echo '<div class="row">';
			echo '<div class="col-sm-4">';
			if ($account['game_id'] > 0) echo ucwords($account_game->blockchain->db_blockchain['coin_name_plural'])." for ".$account_game->db_game['name'];
			else echo $account['account_name'];
			echo '</div>';
			
			$balance_q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' AND io.spend_status='unspent';";
			$balance_r = $app->run_query($balance_q);
			$balance = $balance_r->fetch();
			$balance = $balance['SUM(io.amount)'];
			
			echo '<div class="col-sm-2 greentext" style="text-align: right">';
			echo $app->format_bignum($balance/pow(10,8)).' '.$account['short_name_plural'];
			echo '</div>';
			
			echo '<div class="col-sm-2">';
			if ($account['game_id'] == "") echo '<a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Deposit</a>';
			echo '</div>';
			echo '<div class="col-sm-2"><a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Transactions';
			
			$transaction_in_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."'";
			if ($account['game_id'] > 0) $transaction_in_q .= " AND t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."'";
			$transaction_in_q .= " ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC;";
			$transaction_in_r = $app->run_query($transaction_in_q);
			
			$transaction_out_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."'";
			if ($account['game_id'] > 0) $transaction_out_q .= " AND t.blockchain_id='".$blockchain->db_blockchain['blockchain_id']."'";
			$transaction_out_q .= " ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC;";
			$transaction_out_r = $app->run_query($transaction_out_q);
			
			echo ' ('.($transaction_in_r->rowCount()+$transaction_out_r->rowCount()).')';
			
			echo '</a></div>';
			echo "</div>\n";
			
			echo '<div class="row" id="account_details_'.$account['account_id'].'" style="display: none;">';
			echo "<div class=\"account_details\">";
			if (empty($account['game_id'])) {
				echo "To deposit to ".$account['account_name'].", send ".$account['short_name_plural']." to: ".$account['pub_key']."<br/>\n";
				echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$account['pub_key'].'" />';
			}
			else {
				echo "This account stores your colored ".$account_game->blockchain->db_blockchain['coin_name_plural']." for ".$account_game->db_game['name'].".<br/>\n";
				echo "Do not deposit ".$account_game->blockchain->db_blockchain['coin_name_plural']." directly into this account.";
			}
			
			while ($transaction = $transaction_in_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2" style="text-align: right;"><a class="greentext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
				echo "+".$app->format_bignum($transaction['amount']/pow(10,8))."&nbsp;".$account['short_name_plural'];
				echo '</a></div>';
				echo '<div class="col-sm-3">';
				if ($transaction['block_id'] == "") echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
				else echo "Confirmed in block <a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>";
				echo "</div>\n";
				echo '<div class="col-sm-3">'.ucwords($transaction['spend_status']);
				if ($transaction['spend_status'] != "spent" && $transaction['block_id'] !== "") {
					echo "&nbsp;&nbsp;<a href=\"\" onclick=\"account_start_spend_io(";
					if ($account_game) echo $account_game->db_game['game_id'];
					else echo 'false';
					
					echo ', '.$transaction['io_id'].", ".($transaction['amount']/pow(10,8))."); return false;\">Spend</a>";
				}
				echo '</div>';
				echo "</div>\n";
			}
			
			while ($transaction = $transaction_out_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2" style="text-align: right;"><a class="redtext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
				echo "-".$app->format_bignum($transaction['amount']/pow(10,8))."&nbsp;".$account['short_name_plural'];
				echo '</a></div>';
				echo '<div class="col-sm-3">';
				if ($transaction['block_id'] == "") echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
				else echo "Confirmed in block <a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>";
				echo '</div>';
				echo '</div>';
			}
			
			echo "</div>\n";
			echo "</div>\n";
		}
		
		$blockchains = array();
		
		echo '<h1>Colored Coin Accounts</h1>';
		
		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."';";
		$r = $app->run_query($q);
		
		echo "<p>You have ".$r->rowCount()." colored coin account";
		if ($account_r->rowCount() != 1) echo "s";
		echo ".</p>\n";
		
		while ($user_game = $r->fetch()) {
			if (empty($blockchains[$user_game['blockchain_id']])) $blockchains[$user_game['blockchain_id']] = new Blockchain($app, $user_game['blockchain_id']);
			$coin_game = new Game($blockchains[$user_game['blockchain_id']], $user_game['game_id']);
			echo '<div class="row">';
			echo '<div class="col-sm-4"><a href="/wallet/'.$user_game['url_identifier'].'/">'.ucwords($user_game['coin_name_plural'])." for ".$user_game['name'].'</a></div>';
			echo '<div class="col-sm-2 greentext" style="text-align: right">'.$app->format_bignum($thisuser->account_coin_value($coin_game, $user_game)/pow(10,8)).' '.$user_game['coin_name_plural'].'</div>';
			
			if ($user_game['buyin_policy'] != "none") {
				$exchange_rate = $coin_game->coins_in_existence(false)/$coin_game->escrow_value(false);
				$cc_value = $thisuser->account_coin_value($coin_game, $user_game)/$exchange_rate;
				echo '<div class="col-sm-2 greentext" style="text-align: right">'.$app->format_bignum($cc_value/pow(10,8)).' '.$coin_game->blockchain->db_blockchain['coin_name_plural'].'</div>';
			}
			echo "</div>\n";
		}
		?>
		<div style="display: none;" class="modal fade" id="account_spend_modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="account_spend_modal_title">What do you want to do with these coins?</h4>
					</div>
					<div class="modal-body">
						<select class="form-control" id="account_spend_action" onchange="account_spend_action_changed();">
							<option value="">-- Please select --</option>
							<option value="buyin">Buy in to a game</option>
							<option value="withdraw">Withdraw my coins</option>
							<option value="faucet">Donate to a faucet</option>
							<option value="join_tx">Join with another UTXO</option>
						</select>
						<div id="account_spend_join_tx" style="display: none; padding-top: 20px;">
							Loading...
						</div>
						<div id="account_spend_faucet" style="display: none; padding-top: 20px;">
							<form action="/accounts/" method="get">
								<input type="hidden" name="donate_game_id" id="donate_game_id" value="" />
								<input type="hidden" name="account_io_id" id="account_io_id" value="" />
								<input type="hidden" name="action" value="donate_to_faucet" />
								
								<div class="form-group">
									<label for="donate_amount_each">How many in-game coins should each person receive?</label>
									<input class="form-control" name="donate_amount_each" />
								</div>
								<div class="form-group">
									<label for="donate_quantity">How many faucet contributions do you want to make?</label>
									<input class="form-control" name="donate_quantity" />
								</div>
								<div class="form-group">
									<button class="btn btn-primary">Donate to Faucet</button>
								</div>
							</form>
						</div>
						<div id="account_spend_buyin" style="display: none;">
							<br/>
							<p>
								Which game do you want to buy in to?
							</p>
							<select class="form-control" id="account_spend_game_id">
								<option value="">-- Please select --</option>
								<?php
								$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' GROUP BY g.game_id ORDER BY g.name ASC;";
								$r = $app->run_query($q);
								while ($db_game = $r->fetch()) {
									echo "<option value=\"".$db_game['game_id']."\">".$db_game['name']."</option>\n";
								}
								?>
							</select>
							<br/>
							<p>
								How much do you want to buy in for? <span id="account_spend_buyin_total"></span>
							</p>
							<div class="row">
								<div class="col-md-4">
									<input class="form-control" style="text-align: right;" type="text" id="account_spend_buyin_amount" placeholder="0.00" />
								</div>
								<div class="col-md-4 form-control-static">
									coins
								</div>
								<div class="col-md-4 form-control-static" id="account_spend_buyin_color_amount"></div>
							</div>
							<br/>
							<p>
								Transaction fee:
							</p>
							<div class="row">
								<div class="col-md-4">
									<input class="form-control" style="text-align: right;" type="text" id="account_spend_buyin_fee" value="0.001" />
								</div>
								<div class="col-md-4 form-control-static">
									coins
								</div>
							</div>
							<br/>
							<p>
								Which address should colored coins be sent to?
							</p>
							<select class="form-control" id="account_spend_buyin_address_choice" onchange="account_spend_buyin_address_choice_changed();">
								<option value="new">Create a new address for me</option>
								<option value="existing">Let me enter an address</option>
							</select>
							<div id="account_spend_buyin_address_existing" style="display: none;">
								<br/>
								<p>
									Please enter the address where colored coins should be deposited:
								</p>
								<input class="form-control" id="account_spend_buyin_address" />
							</div>
							<br/>
							<button class="btn btn-primary" onclick="account_spend_buyin();">Buy in</button>
						</div>
						<div id="account_spend_withdraw" style="display: none;">
						</div>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
		$(document).ready(function() {
			account_spend_refresh();
		});
		</script>
		<?php
	}
	else {
		$redirect_url = $app->get_redirect_url("/accounts/");
		$redirect_id = $redirect_url['redirect_url_id'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>