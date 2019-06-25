<?php
include(AppSettings::srcPath().'/includes/connect.php');
include(AppSettings::srcPath().'/includes/get_session.php');

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

if ($thisuser) {
	if ($action == "set_for_sale") {
		$io_id = (int) $_REQUEST['set_for_sale_io_id'];
		$amount_each = (float) $_REQUEST['set_for_sale_amount_each'];
		$quantity = (int) $_REQUEST['set_for_sale_quantity'];
		$game_id = (int) $_REQUEST['set_for_sale_game_id'];
		
		$db_game = $app->fetch_game_by_id($game_id);
		
		if ($db_game) {
			$sale_blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$sale_game = new Game($sale_blockchain, $db_game['game_id']);
			
			$satoshis_each = pow(10,$db_game['decimal_places'])*$amount_each;
			$fee_amount = (int) (0.0001*pow(10,$sale_blockchain->db_blockchain['decimal_places']));
			
			if ($quantity > 0 && $satoshis_each > 0) {
				$total_cost_satoshis = $quantity*$satoshis_each;
				
				$db_io = $app->fetch_io_by_id($io_id);
				
				if ($db_io) {
					$gios_by_io = $sale_game->fetch_game_ios_by_io($io_id);
					
					if ($gios_by_io->rowCount() > 0) {
						$game_sale_account = $sale_game->check_set_game_sale_account($thisuser);
						
						$game_ios = array();
						$colored_coin_sum = 0;
						
						while ($game_io = $gios_by_io->fetch()) {
							array_push($game_ios, $game_io);
							$colored_coin_sum += $game_io['colored_amount'];
						}
						
						$coin_sum = $game_ios[0]['amount'];
						$coins_per_chain_coin = (float) $colored_coin_sum/($coin_sum-$fee_amount);
						$chain_coins_each = ceil($satoshis_each/$coins_per_chain_coin);
						
						if (in_array($game_ios[0]['spend_status'], array("unspent", "unconfirmed"))) {
							$address_ids = array();
							$address_key_ids = array();
							$addresses_needed = $quantity;
							$loop_count = 0;
							do {
								$addr_r = $app->run_query("SELECT * FROM addresses a WHERE a.primary_blockchain_id=:blockchain_id AND a.is_mine=1 AND a.user_id IS NULL AND a.is_destroy_address=0 AND a.is_separator_address=0 ORDER BY RAND() LIMIT 1;", [
									'blockchain_id' => $sale_blockchain->db_blockchain['blockchain_id']
								]);
								
								if ($addr_r->rowCount() > 0) {
									$db_address = $addr_r->fetch();
									
									if (empty($db_address['user_id'])) {
										$update_addr_q = "UPDATE addresses SET user_id=:user_id WHERE address_id=:address_id;";
										$update_addr_r = $app->run_query($update_addr_q, [
											'user_id' => $thisuser->db_user['user_id'],
											'address_id' => $db_address['address_id']
										]);
										
										$addr_key_q = "SELECT * FROM address_keys WHERE address_id=:address_id;";
										$addr_key_r = $app->run_query($addr_key_q, ['address_id' => $db_address['address_id']]);
										
										if ($addr_key_r->rowCount() > 0) {
											$addr_key = $addr_key_r->fetch();
											$addr_key_q = "UPDATE address_keys SET account_id=:account_id WHERE address_key_id=:address_key_id;";
											$addr_key_r = $app->run_query($addr_key_q, [
												'account_id' => $game_sale_account['account_id'],
												'address_key_id' => $addr_key['address_key_id']
											]);
											$address_key_id = $addr_key['address_key_id'];
										}
										else {
											$addr_key_q = "INSERT INTO address_keys SET address_id=:address_id, account_id=:account_id, save_method='wallet.dat', pub_key=:pub_key;";
											$addr_key_r = $app->run_query($addr_key_q, [
												'address_id' => $db_address['address_id'],
												'account_id' => $faucet_account['account_id'],
												'pub_key' => $db_address['address']
											]);
											$address_key_id = $app->last_insert_id();
										}
										
										$addresses_needed--;
										
										array_push($address_ids, $db_address['address_id']);
										array_push($address_key_ids, $address_key_id);
									}
									else echo "Error, ".$address['address_id']." is already owned by someone.<br/>\n";
								}
								$loop_count++;
							}
							while ($addresses_needed > 0 && $loop_count < $quantity*2);
							
							if ($addresses_needed > 0) {
								if (count($address_ids) > 0) {
									$app->run_query("UPDATE addresses SET user_id=NULL WHERE address_id IN (:address_ids);", [
										'address_ids' => implode(",", $address_ids)
									]);
									$app->run_query("UPDATE address_keys SET account_id=NULL WHERE address_key_id IN (:address_key_ids);", [
										'address_key_ids' => implode(",", $address_key_ids)
									]);
								}
								die("Not enough free addresses (still need $addresses_needed/$quantity).");
							}
							
							$account_q = "SELECT ca.* FROM currency_accounts ca JOIN games g ON g.game_id=ca.game_id JOIN address_keys k ON k.account_id=ca.account_id WHERE ca.user_id=:user_id AND k.address_id=:address_id;";
							$account_r = $app->run_query($account_q, [
								'user_id' => $thisuser->db_user['user_id'],
								'address_id' => $game_ios[0]['address_id']
							]);
							
							if ($account_r->rowCount() > 0) {
								$donate_account = $account_r->fetch();
								
								if ($total_cost_satoshis < $colored_coin_sum && $coin_sum > ($chain_coins_each*$quantity) - $fee_amount) {
									$remainder_satoshis = $coin_sum - ($chain_coins_each*$quantity) - $fee_amount;
									
									$send_address_ids = array();
									$amounts = array();
									
									for ($i=0; $i<$quantity; $i++) {
										array_push($amounts, $chain_coins_each);
										array_push($send_address_ids, $address_ids[$i]);
									}
									if ($remainder_satoshis > 0) {
										array_push($amounts, $remainder_satoshis);
										array_push($send_address_ids, $game_ios[0]['address_id']);
									}
									
									$error_message = false;
									$transaction_id = $sale_game->blockchain->create_transaction('transaction', $amounts, false, array($game_ios[0]['io_id']), $send_address_ids, $fee_amount, $error_message);
									
									if ($transaction_id) {
										$transaction = $app->fetch_transaction_by_id($transaction_id);
										header("Location: /explorer/games/".$db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/");
										die();
									}
									else echo "TX Error: ".$error_message.".<br/>\n";
								}
								else {
									echo "UTXO is only ".$app->format_bignum($colored_coin_sum/pow(10,$sale_game->db_game['decimal_places']))." ".$sale_game->db_game['coin_name_plural']." but you tried to spend ".$app->format_bignum($total_cost_satoshis/pow(10,$sale_game->db_game['decimal_places']))."<br/>\n";
								}
							}
							else echo "You don't own this UTXO.<br/>\n";
						}
						else echo "Invalid UTXO.";
					}
					else echo "Invalid UTXO.";
				}
				else echo "Invalid UTXO ID.";
			}
			else echo "Invalid quantity or amount per UTXO.";
		}
		else echo "Invalid game ID.";
	}
	else if ($action == "donate_to_faucet") {
		$io_id = (int) $_REQUEST['account_io_id'];
		$amount_each = (float) $_REQUEST['donate_amount_each'];
		$utxos_each = (int) $_REQUEST['donate_utxos_each'];
		$quantity = (int) $_REQUEST['donate_quantity'];
		$game_id = (int) $_REQUEST['donate_game_id'];
		
		$db_game = $app->fetch_game_by_id($game_id);
		
		if ($db_game) {
			$donate_blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$donate_game = new Game($donate_blockchain, $db_game['game_id']);
			
			$satoshis_each = pow(10,$db_game['decimal_places'])*$amount_each;
			$satoshis_each_utxo = ceil($satoshis_each/$utxos_each);
			$satoshis_each = $satoshis_each_utxo*$utxos_each;
			$fee_amount = (int)(0.0001*pow(10,$db_game['decimal_places']));
			
			if ($quantity > 0 && $satoshis_each > 0) {
				$total_cost_satoshis = $quantity*$satoshis_each;
				
				$db_io = $app->fetch_io_by_id($io_id);
				
				if ($db_io) {
					$gios_by_io = $donate_game->fetch_game_ios_by_io($io_id);
					
					if ($gios_by_io->rowCount() > 0) {
						$faucet_account = $donate_game->check_set_faucet_account();
						
						$game_ios = array();
						$colored_coin_sum = 0;
						
						while ($game_io = $gios_by_io->fetch()) {
							array_push($game_ios, $game_io);
							$colored_coin_sum += $game_io['colored_amount'];
						}
						
						$coin_sum = $game_ios[0]['amount'];
						$coins_per_chain_coin = (float) $colored_coin_sum/($coin_sum-$fee_amount);
						$chain_coins_each = ceil($satoshis_each_utxo/$coins_per_chain_coin);
						
						if (in_array($game_ios[0]['spend_status'], array("unspent", "unconfirmed"))) {
							$address_ids = array();
							$address_key_ids = array();
							$addresses_needed = $quantity;
							$loop_count = 0;
							
							do {
								$address_key = $app->new_address_key($faucet_account['currency_id'], $faucet_account);
								
								if ($address_key['is_destroy_address'] == 0) {
									array_push($address_ids, $address_key['address_id']);
									array_push($address_key_ids, $address_key_id);
									$addresses_needed--;
								}
								$loop_count++;
							}
							while ($addresses_needed > 0);
							
							if ($addresses_needed > 0) {
								if (count($address_ids) > 0) {
									$app->run_query("UPDATE addresses SET user_id=NULL WHERE address_id IN (:address_ids);", [
										'address_ids' => implode(",", $address_ids)
									]);
									$app->run_query("UPDATE address_keys SET account_id=NULL WHERE address_key_id IN (:address_key_ids);", [
										'address_key_ids' => implode(",", $address_key_ids)
									]);
								}
								die("Not enough free addresses (still need $addresses_needed/$quantity).");
							}
							
							$account_q = "SELECT ca.* FROM currency_accounts ca JOIN games g ON g.game_id=ca.game_id JOIN address_keys k ON k.account_id=ca.account_id WHERE ca.user_id=:user_id AND k.address_id=:address_id;";
							$account_r = $app->run_query($account_q, [
								'user_id' => $thisuser->db_user['user_id'],
								'address_id' => $game_ios[0]['address_id']
							]);
							
							if ($account_r->rowCount() > 0) {
								$donate_account = $account_r->fetch();
								
								if ($total_cost_satoshis < $colored_coin_sum && $coin_sum > ($chain_coins_each*$quantity*$utxos_each) - $fee_amount) {
									$remainder_satoshis = $coin_sum - ($chain_coins_each*$quantity*$utxos_each) - $fee_amount;
									
									$send_address_ids = array();
									$amounts = array();
									
									for ($i=0; $i<$quantity; $i++) {
										for ($j=0; $j<$utxos_each; $j++) {
											array_push($amounts, $chain_coins_each);
											array_push($send_address_ids, $address_ids[$i]);
										}
									}
									if ($remainder_satoshis > 0) {
										array_push($amounts, $remainder_satoshis);
										array_push($send_address_ids, $game_ios[0]['address_id']);
									}
									
									$error_message = false;
									$transaction_id = $donate_game->blockchain->create_transaction('transaction', $amounts, false, array($game_ios[0]['io_id']), $send_address_ids, $fee_amount, $error_message);
									
									if ($transaction_id) {
										header("Location: /explorer/games/".$db_game['url_identifier']."/transactions/".$transaction_id."/");
										die();
									}
									else echo "TX Error: ".$error_message."<br/>\n";
								}
								else {
									echo "UTXO is only ".$app->format_bignum($colored_coin_sum/pow(10,$donate_game->db_game['decimal_places']))." ".$donate_game->db_game['coin_name_plural']." but you tried to spend ".$app->format_bignum($total_cost_satoshis/pow(10,$donate_game->db_game['decimal_places']))."<br/>\n";
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
}

$pagetitle = "My Accounts";
$nav_tab_selected = "accounts";
$nav_subtab_selected = "";

if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if ($thisuser) {
		if (!empty($_REQUEST['account_id'])) $selected_account_id = (int) $_REQUEST['account_id'];
		else $selected_account_id = false;
		?>
		<script type="text/javascript">
		var selected_account_id = <?php if ($selected_account_id) echo $selected_account_id; else echo 'false'; ?>;
		</script>
		
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<?php
				$account_params = [
					'user_id' => $thisuser->db_user['user_id']
				];
				$account_q = "SELECT ca.*, c.*, b.url_identifier AS blockchain_url_identifier, k.pub_key, ug.user_game_id FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id LEFT JOIN addresses a ON ca.current_address_id=a.address_id LEFT JOIN address_keys k ON a.address_id=k.address_id LEFT JOIN user_games ug ON ug.account_id=ca.account_id WHERE ca.user_id=:user_id";
				if ($selected_account_id) {
					$account_q .= " AND ca.account_id=:account_id";
					$account_params['account_id'] = $selected_account_id;
				}
				$account_r = $app->run_query($account_q, $account_params);
				
				if ($selected_account_id) {
					$selected_account = $account_r->fetch();
					$account_r = $app->run_query($account_q, $account_params);
					echo '
						<div class="panel-title">Account: '.$selected_account['account_name'].'</div>
					</div>
					<div class="panel-body">';
					
					echo '<p><a href="/accounts/">&larr; My Accounts</a></p>';
				}
				else {
					echo '
						<div class="panel-title">Coin Accounts</div>
					</div>
					<div class="panel-body">';
					
					echo "<p>You have ".$account_r->rowCount()." coin account";
					if ($account_r->rowCount() != 1) echo "s";
					echo ".</p>\n";
				}
				
				while ($account = $account_r->fetch()) {
					$blockchain = new Blockchain($app, $account['blockchain_id']);
					
					if ($account['game_id'] > 0) {
						$account_game = new Game($blockchain, $account['game_id']);
						$account_value = $account_game->account_balance($account['account_id']);
					}
					else $account_game = false;
					
					if ($selected_account_id && $account_game) {
						echo '<p><a href="/wallet/'.$account_game->db_game['url_identifier'].'/?action=change_user_game&user_game_id='.$account['user_game_id'].'" class="btn btn-sm btn-success">Play Now</a></p>';
					}
					
					echo '<div class="row">';
					echo '<div class="col-sm-4">';
					if (!$selected_account_id) echo '<a href="/accounts/?account_id='.$account['account_id'].'">';
					echo $account['account_name'];
					if (!$selected_account_id) echo '</a>';
					echo '</div>';
					
					$balance = $app->account_balance($account['account_id']);
					
					echo '<div class="col-sm-2 greentext" style="text-align: right">';
					if ($account['game_id'] > 0) echo $app->format_bignum($account_value/pow(10,$account_game->db_game['decimal_places'])).' '.$account_game->db_game['coin_name_plural'];
					else echo "&nbsp;";
					echo '</div>';
					
					echo '<div class="col-sm-2 greentext" style="text-align: right">';
					echo $app->format_bignum($balance/pow(10,$blockchain->db_blockchain['decimal_places'])).' '.$account['short_name_plural'];
					echo '</div>';
					
					echo '<div class="col-sm-2">';
					if (empty($account['game_id'])) {
						echo '<a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Deposit</a>';
						echo ' &nbsp;&nbsp; <a href="" onclick="withdraw_from_account('.$account['account_id'].', 1); return false;">Withdraw</a>';
					}
					echo '</div>';
					
					echo '<div class="col-sm-2"><a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Transactions';
					
					$transaction_in_params = [
						'account_id' => $account['account_id']
					];
					$transaction_in_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id";
					if ($account['game_id'] > 0) {
						$transaction_in_q .= " AND t.blockchain_id=:blockchain_id";
						$transaction_in_params['blockchain_id'] = $blockchain->db_blockchain['blockchain_id'];
					}
					$transaction_in_q .= " ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC LIMIT 200;";
					$transaction_in_r = $app->run_query($transaction_in_q, $transaction_in_params);
					
					$transaction_out_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id";
					if ($account['game_id'] > 0) $transaction_out_q .= " AND t.blockchain_id=:blockchain_id";
					$transaction_out_q .= " ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC LIMIT 200;";
					$transaction_out_r = $app->run_query($transaction_out_q, $transaction_in_params);
					
					echo ' ('.($transaction_in_r->rowCount()+$transaction_out_r->rowCount()).')';
					
					echo '</a></div>';
					echo "</div>\n";
					
					echo '<div class="row" id="account_details_'.$account['account_id'].'"';
					if ($selected_account_id == $account['account_id']) {}
					else echo ' style="display: none;"';
					echo '>';

					echo "<div class=\"account_details\">";
					
					echo '
					<ul class="nav nav-tabs">
						<li><a data-toggle="tab" href="#primary_address_'.$account['account_id'].'">Deposit Address</a></li>
						<li><a data-toggle="tab" href="#transactions_'.$account['account_id'].'">Transactions</a></li>
						<li><a data-toggle="tab" href="#addresses_'.$account['account_id'].'">Addresses</a></li>
						<li><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/utxos/?account_id='.$account['account_id'].'">UTXOs</a></li>
					</ul>';
					
					echo '
					<div class="tab-content">
						<div id="primary_address_'.$account['account_id'].'" class="tab-pane fade pad-this-pane">';
					
					echo "<p>You can deposit ".$account['short_name_plural'];
					if ($account_game) echo " or ".$account_game->db_game['coin_name_plural'];
					echo " to this account by sending to:</p>";
					echo '<a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$account['pub_key'].'">'.$account['pub_key']."</a><br/>\n";
					echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$account['pub_key'].'" />';
					
					echo '
						</div>
						<div id="transactions_'.$account['account_id'].'" class="tab-pane fade pad-this-pane">';
					
					echo "<p>Rendering ".($transaction_in_r->rowCount() + $transaction_out_r->rowCount())." transactions.</p>";
					
					while ($transaction = $transaction_in_r->fetch()) {
						if ($account_game) $colored_coin_amount = $account_game->game_amount_by_io($transaction['io_id']);
						
						echo '<div class="row">';
						echo '<div class="col-sm-4"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$transaction['pub_key'].'">';
						echo $transaction['pub_key'];
						echo '</a></div>';
						
						if ($account_game) {
							echo '<div class="col-sm-2" style="text-align: right;"><a class="greentext" target="_blank" href="/explorer/games/'.$account_game->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'/">';
							echo "+".$app->format_bignum($colored_coin_amount/pow(10,$account_game->db_game['decimal_places']))."&nbsp;".$account_game->db_game['coin_name_plural'];
							echo '</a></div>';
						}
						echo '<div class="col-sm-2" style="text-align: right;"><a class="greentext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/utxo/'.$transaction['tx_hash'].'/'.$transaction['out_index'].'">';
						echo "+".$app->format_bignum($transaction['amount']/pow(10,$blockchain->db_blockchain['decimal_places']))."&nbsp;".$account['short_name_plural'];
						echo '</a></div>';
						
						echo '<div class="col-sm-2">';
						if ($transaction['block_id'] == "") echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
						else echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">Block&nbsp;#".$transaction['block_id']."</a>";
						echo "</div>\n";
						
						echo '<div class="col-sm-2">'.ucwords($transaction['spend_status']);
						if ($transaction['spend_status'] != "spent" && $transaction['block_id'] !== "") {
							echo "&nbsp;&nbsp;<a href=\"\" onclick=\"account_start_spend_io(";
							if ($account_game) echo $account_game->db_game['game_id'];
							else echo 'false';
							
							echo ', '.$transaction['io_id'].", ".($transaction['amount']/pow(10,$blockchain->db_blockchain['decimal_places'])).", '".$blockchain->db_blockchain['coin_name_plural']."', '";
							if ($account_game) echo $account_game->db_game['coin_name_plural'];
							echo "'); return false;\">Spend</a>";
						}
						echo '</div>';
						
						echo "</div>\n";
					}
					
					while ($transaction = $transaction_out_r->fetch()) {
						if ($account_game) $colored_coin_amount = $account_game->game_amount_by_io($transaction['io_id']);
						
						echo '<div class="row">';
						echo '<div class="col-sm-4"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$transaction['pub_key'].'">';
						echo $transaction['pub_key'];
						echo '</a></div>';
						
						if ($account_game) {
							echo '<div class="col-sm-2" style="text-align: right;"><a class="redtext" target="_blank" href="/explorer/games/'.$account_game->db_game['url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
							echo "-".$app->format_bignum($colored_coin_amount/pow(10,$account_game->db_game['decimal_places']))."&nbsp;".$account_game->db_game['coin_name_plural'];
							echo '</a></div>';
						}
						
						echo '<div class="col-sm-2" style="text-align: right;"><a class="redtext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
						echo "-".$app->format_bignum($transaction['amount']/pow(10,$blockchain->db_blockchain['decimal_places']))."&nbsp;".$account['short_name_plural'];
						echo '</a></div>';
						
						echo '<div class="col-sm-2">';
						if ($transaction['block_id'] == "") echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
						else echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">Block&nbsp;#".$transaction['block_id']."</a>";
						echo '</div>';
						
						echo '</div>';
					}
					
					echo '
						</div>
						<div id="addresses_'.$account['account_id'].'" class="tab-pane fade pad-this-pane">';
					$addr_r = $app->run_query("SELECT * FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id=:account_id ORDER BY a.option_index ASC LIMIT 500;", [
						'account_id' => $account['account_id']
					]);
					echo "<p>This account has ".$addr_r->rowCount()." addresses.</p>";
					
					while ($address = $addr_r->fetch()) {
						$address_balance = $blockchain->address_balance_at_block($address, false);
						if ($account_game) $game_balance = $account_game->address_balance_at_block($address, false);
						
						echo '<div class="row">';
						
						echo '<div class="col-sm-2">'.$app->format_bignum($address_balance/pow(10, $blockchain->db_blockchain['decimal_places'])).' '.$blockchain->db_blockchain['coin_name_plural'].'</div>';
						
						if ($account_game) {
							echo '<div class="col-sm-2">'.$app->format_bignum($game_balance/pow(10, $account_game->db_game['decimal_places'])).' '.$account_game->db_game['coin_name_plural'].'</div>';
						}
						
						echo '<div class="col-sm-2">'.$address['vote_identifier'].' (#'.$address['option_index'].')';
						if ($address['is_destroy_address'] == 1) echo ' <font class="redtext">Destroy Address</font>';
						if ($address['is_separator_address'] == 1) echo ' <font class="yellowtext">Separator Address</font>';
						echo '</div>';
						
						echo '<div class="col-sm-4"><a href="/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/addresses/'.$address['address'].'">'.$address['address'].'</a></div>';
						
						echo '<div class="col-sm-2">';
						if ($address['is_separator_address'] == 0 && $address['is_destroy_address'] == 0) {
							echo '<a href="" onclick="manage_addresses('.$account['account_id'].', \'set_primary\', '.$address['address_id'].');">Set as Primary</a>';
						}
						echo '</div>';
						
						echo "</div>\n";
					}
					
					echo '<br/><p><button class="btn btn-sm btn-primary" onclick="manage_addresses('.$account['account_id'].', \'new\', false);">New Address</button></p>';
					echo '
						</div>
					</div>';
					
					echo "</div>\n";
					echo "</div>\n";
				}
				?>
				<p style="margin-top: 10px;">
					<a href="" onclick="$('#create_account_dialog').toggle('fast'); return false;">Create a new account</a>
				</p>
				<div id="withdraw_dialog" class="modal fade" style="display: none;">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h4 class="modal-title">Withdraw Coins</h4>
							</div>
							<div class="modal-body">
								<div class="form-group">
									<label for="withdraw_amount">Amount:</label>
									<input class="form-control" type="tel" placeholder="0.000" id="withdraw_amount" style="text-align: right;" />
								</div>
								<div class="form-group">
									<label for="withdraw_fee">Fee:</label>
									<input class="form-control" type="tel" placeholder="0.0001" id="withdraw_fee" style="text-align: right;" />
								</div>
								<div class="form-group">
									<label for="withdraw_address">Address:</label>
									<input class="form-control" type="text" id="withdraw_address" />
								</div>
								
								<div class="greentext" style="display: none;" id="withdraw_message"></div>
								
								<button id="withdraw_btn" class="btn btn-success" onclick="withdraw_from_account(false, 2);">Withdraw</button>
							</div>
						</div>
					</div>
				</div>
				<div id="create_account_dialog" style="display: none;">
					<div class="form-group">
						<label for="create_account_action">Create a new account:</label>
						<select class="form-control" id="create_account_action" onchange="create_account_step(1);">
							<option value="">-- Please Select --</option>
							<option value="for_blockchain">Create a new blockchain account</option>
							<option value="by_rpc_account">Import an existing account by RPC</option>
						</select>
					</div>
					<div class="form-group" id="create_account_step2" style="display: none;">
						<label for="create_account_blockchain_id">Please select a blockchain:</label>
						<select class="form-control" id="create_account_blockchain_id" onchange="create_account_step(2);">
							<option value="">-- Please Select --</option>
							<?php
							$all_blockchains = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_name ASC;");
							while ($db_blockchain = $all_blockchains->fetch()) {
								echo '<option value="'.$db_blockchain['blockchain_id'].'">'.$db_blockchain['blockchain_name'].'</option>'."\n";
							}
							?>
						</select>
					</div>
					<div class="form-group" id="create_account_step3" style="display: none;">
						<label for="create_account_rpc_name">Please enter the account name as used by the coin daemon:</label>
						<input type="text" class="form-control" id="create_account_rpc_name" value="" />
					</div>
					<div class="form-group" id="create_account_submit" style="display: none;">
						<button class="btn btn-primary" onclick="create_account_step('submit');">Create Account</button>
					</div>
				</div>
			</div>
		</div>
		
		<div style="display: none;" class="modal fade" id="account_spend_modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="account_spend_modal_title">What do you want to do with these coins?</h4>
					</div>
					<div class="modal-body">
						<div class="form-group">
							<select class="form-control" id="account_spend_action" onchange="account_spend_action_changed();">
								<option value="">-- Please select --</option>
								<option value="withdraw">Spend</option>
								<option value="split">Split into pieces</option>
								<option value="buyin">Buy in to a game</option>
								<option value="faucet">Donate to a faucet</option>
								<option value="set_for_sale">Set as for sale</option>
								<option value="join_tx">Join with another UTXO</option>
							</select>
						</div>
						<div id="account_spend_join_tx" style="display: none;">
							Loading...
						</div>
						<div id="account_spend_withdraw" style="display: none;">
							<form method="get" action="/ajax/account_spend.php" onsubmit="account_spend_withdraw(); return false;">
								<div class="form-group">
									<label for="spend_withdraw_address">Address:</label>
									<input type="text" class="form-control" id="spend_withdraw_address" />
								</div>
								<div class="form-group">
									<label for="spend_withdraw_amount">Amount:</label>
									<div class="row">
										<div class="col-sm-8"><input type="text" class="form-control" id="spend_withdraw_amount" style="text-align: right;" /></div>
										<div class="col-sm-4">
											<select class="form-control" id="spend_withdraw_coin_type">
											</select>
										</div>
									</div>
								</div>
								<div class="form-group">
									<label for="spend_withdraw_fee">Fee:</label>
									<div class="row">
										<div class="col-sm-8"><input type="text" class="form-control" id="spend_withdraw_fee" placeholder="0.0001" style="text-align: right;" /></div>
										<div class="col-sm-4 form-control-static" id="spend_withdraw_fee_label"></div>
									</div>
								</div>
								<div class="form-group">
									<button class="btn btn-primary">Withdraw</button>
								</div>
							</form>
						</div>
						<div id="account_spend_faucet" style="display: none;">
							<form action="/accounts/" method="get">
								<input type="hidden" name="action" value="donate_to_faucet" />
								<input type="hidden" name="donate_game_id" id="donate_game_id" value="" />
								<input type="hidden" name="account_io_id" id="account_io_id" value="" />
								
								<div class="form-group">
									<label for="donate_amount_each">How many in-game coins should each person receive?</label>
									<input type="text" class="form-control" name="donate_amount_each" />
								</div>
								<div class="form-group">
									<label for="donate_amount_each">How many UTXOs should each person's coins be divided into?</label>
									<input type="text" class="form-control" name="donate_utxos_each" />
								</div>
								<div class="form-group">
									<label for="donate_quantity">How many faucet contributions do you want to make?</label>
									<input type="text" class="form-control" name="donate_quantity" />
								</div>
								<div class="form-group">
									<button class="btn btn-primary">Donate to Faucet</button>
								</div>
							</form>
						</div>
						<div id="account_spend_set_for_sale" style="display: none;">
							<form action="/accounts/" method="get">
								<input type="hidden" name="action" value="set_for_sale" />
								<input type="hidden" name="set_for_sale_game_id" id="set_for_sale_game_id" value="" />
								<input type="hidden" name="set_for_sale_io_id" id="set_for_sale_io_id" value="" />
								
								<div class="form-group">
									<label for="donate_amount_each">How many in-game coins should be in each UTXO?</label>
									<input type="text" class="form-control" name="set_for_sale_amount_each" />
								</div>
								<div class="form-group">
									<label for="donate_quantity">How many UTXOs do you want to make?</label>
									<input type="text" class="form-control" name="set_for_sale_quantity" />
								</div>
								<div class="form-group">
									<button class="btn btn-primary">Set for sale</button>
								</div>
							</form>
						</div>
						<div id="account_spend_split" style="display: none;" onsubmit="account_spend_split(); return false;">
							<form action="/accounts/" method="get">
								<input type="hidden" name="action" value="split" />
								
								<div class="form-group">
									<label for="split_amount_each">How many coins should be in each UTXO?</label>
									<input type="text" class="form-control" name="split_amount_each" id="split_amount_each" />
								</div>
								<div class="form-group">
									<label for="split_quantity">How many UTXOs do you want to make?</label>
									<input type="text" class="form-control" name="split_quantity" id="split_quantity" />
								</div>
								<div class="form-group">
									<label for="split_quantity">Transaction fee:</label>
									<div class="row">
										<div class="col-sm-8">
											<input type="text" class="form-control" name="split_fee" id="split_fee" placeholder="0.0001" />
										</div>
										<div class="col-sm-4 form-control-static">
											<?php echo $blockchain->db_blockchain['coin_name_plural']; ?>
										</div>
									</div>
								</div>
								<div class="form-group">
									<button class="btn btn-primary">Split my coins</button>
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
								$my_games = $app->run_query("SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id=:user_id GROUP BY g.game_id ORDER BY g.name ASC;", [
									'user_id' => $thisuser->db_user['user_id']
								]);
								while ($db_game = $my_games->fetch()) {
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
									<input class="form-control" style="text-align: right;" type="text" id="account_spend_buyin_fee" value="0.0001" />
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
					</div>
				</div>
			</div>
		</div>
		
		<script type="text/javascript">
		$(document).ready(function() {
			account_spend_refresh();
			<?php
			if ($action == "prompt_game_buyin") {
				?>
				account_start_spend_io(false, <?php echo ((int) $_REQUEST['io_id']); ?>, <?php echo ((float) $_REQUEST['amount']); ?>, '', '');
				$('#account_spend_action').val('buyin');
				account_spend_action_changed();
				<?php
			}
			?>
		});
		</script>
		<?php
	}
	else {
		include(AppSettings::srcPath()."/includes/html_login.php");
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>