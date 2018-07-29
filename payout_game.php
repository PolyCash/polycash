<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	if ($GLOBALS['rsa_keyholder_email'] != "" && $GLOBALS['rsa_pub_key'] != "") {
		if ($thisuser->db_user['username'] == $GLOBALS['rsa_keyholder_email']) {
			$game_id = intval($_REQUEST['game_id']);
			$fee_satoshis = 50000;
			
			$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
			$r = $app->run_query($q);

			if ($r->rowCount() > 0) {
				$payout_game = $r->fetch();
				
				if (empty($GLOBALS['profit_btc_address'])) {
					echo 'You need to specify a Bitcoin address where profits from the game will be sent. ';
					echo ' Add $GLOBALS[\'profit_btc_address\'] = "yourbtcaddress"; to your includes/config.php';
					echo "<br/>\n";
				}
				else {
					if ($_REQUEST['action'] == "generate_payout") {
						$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['bitcoin_rpc_user'].':'.$GLOBALS['bitcoin_rpc_password'].'@127.0.0.1:'.$GLOBALS['bitcoin_port'].'/');

						$addresses = explode(",", $_REQUEST['addrs']);
						$privkeys = explode(",", $_REQUEST['privkeys']);
						$input_sum = floatval($_REQUEST['input_sum']);
						
						$total = 0;
						$raw_txin = array();
						$sign_arr1 = array();
						$sign_arr2 = array();

						for ($i=0; $i<count($addresses); $i++) {
							$q = "SELECT * FROM currency_addresses WHERE pub_key=".$app->quote_escape($addresses[$i]).";";
							$r = $app->run_query($q);
							if ($r->rowCount() == 1) {
								$address = $r->fetch();
							}
							else die("Error, address $i was not found.");
							
							$api_url = 'https://blockchain.info/address/'.$address['pub_key'].'?format=json';
							$api_result = json_decode(file_get_contents($api_url));
							
							$total += $api_result->final_balance;
							
							for ($t=0; $t<count($api_result->txs); $t++) {
								for ($o=0; $o<count($api_result->txs[$t]->out); $o++) {
									if (!$api_result->txs[$t]->out[$o]->spent && $api_result->txs[$t]->out[$o]->addr == $addresses[$i]) {
										$raw_txin[count($raw_txin)] = array('txid'=>$api_result->txs[$t]->hash, 'vout'=>$api_result->txs[$t]->out[$o]->n);
										
										array_push($sign_arr1, array('txid'=>$api_result->txs[$t]->hash, 'vout'=>$api_result->txs[$t]->out[$o]->n, 'scriptPubKey'=>$api_result->txs[$t]->out[$o]->script));
									}
								}
							}
							try {
								$coin_rpc->importprivkey($privkeys[$i]);
							}
							catch (Exception $e) {
								$app->output_message(7, "Error importing private key. Is bitcoind running on port ".$GLOBALS['bitcoin_port']."?");
								die();
							}
						}
						
						if ((string)($total/pow(10,8)) != (string)$input_sum) {
							$app->output_message(4, "Error, expected inputs to sum to ".$input_sum." but they summed to ".($total/pow(10,8)));
						}
						else {
							$q = "SELECT * FROM currencies WHERE currency_id='".$payout_game['invite_currency']."';";
							$r = $app->run_query($q);
							if ($r->rowCount() > 0) {
								$payout_currency = $r->fetch();
							}
							
							$qq = "SELECT *, ea.address AS address, ug.payout_address_id AS payout_address_id, u.user_id AS user_id FROM users u JOIN user_games ug ON u.user_id=ug.user_id LEFT JOIN external_addresses ea ON ug.payout_address_id=ea.address_id WHERE ug.game_id='".$payout_game['game_id']."' AND ug.payment_required=0;";
							$rr = $app->run_query($qq);
							
							$output_sum = 0;
							$payout_game_obj = new game($app, $payout_game['game_id']);
							$coins_in_existence = $payout_game_obj->coins_in_existence(false);
							$raw_txout[$GLOBALS['profit_btc_address']] = 0;
							
							while ($temp_user_game = $rr->fetch()) {
								$payout_frac = $payout_game_obj->account_balance($temp_user_game['account_id'])/$coins_in_existence;
								$payout_amt = floor($payout_frac*($total-$fee_satoshis));
								
								if ($temp_user_game['payout_address_id'] > 0 && strlen($temp_user_game['address']) >= 26) {
									if (empty($raw_txout[$temp_user_game['address']])) $raw_txout[$temp_user_game['address']] = $payout_amt/pow(10,8);
									else $raw_txout[$temp_user_game['address']] += $payout_amt/pow(10,8);
									$output_sum += $payout_amt;
								}
							}
							
							$profit_satoshis = $total - $fee_satoshis - $output_sum;
							if ($profit_satoshis > 0) {
								if (!$GLOBALS['profit_btc_address']) {
									$app->output_message(6, 'You made a profit but no BTC profit address was specified. Please set $GLOBALS[\'profit_btc_address\'] to a BTC address in your includes/config.php');
									die();
								}
								else {
									$raw_txout[$GLOBALS['profit_btc_address']] += $profit_satoshis/pow(10,8);
									$output_sum += $profit_satoshis;
								}
							}
							
							try {
								$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
								try {
									$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction, $sign_arr1);
							
									if ($signed_raw_transaction['complete'] == 1) {
										$app->output_message(1, "Great, the transaction has been created and signed: ".$signed_raw_transaction['hex']);
									}
									else {
										$app->output_message(2, $raw_transaction);
									}
								}
								catch (Exception $e) {
									$app->output_message(8, $raw_transaction);
								}
							}
							catch (Exception $e) {
								$output_obj['error'] = $e;
								$output_obj['raw_txin'] = $raw_txin;
								$output_obj['raw_txout'] = $raw_txout;
								$app->output_message(3, $output_obj);
							}
						}
					}
					else if ($_REQUEST['action'] == "submit_tx_hash") {
						$tx_hash = $_REQUEST['tx_hash'];
						
						$pagetitle = "";
						$include_crypto_js = true;
						include('includes/html_start.php');
						echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
						
						if ($payout_game['payout_tx_hash'] == "") {
							$q = "UPDATE games SET payout_complete=1, payout_tx_hash=".$app->quote_escape($tx_hash)." WHERE game_id='".$payout_game['game_id']."';";
							$r = $app->run_query($q);
							echo "Great, the tx hash has been saved!";
						}
						else echo "Error, a payout tx hash has already been set for this game.";
						
						echo '</div>';
						include('includes/html_stop.php');
						die();
					}
					else {
						$pagetitle = "";
						$include_crypto_js = true;
						include('includes/html_start.php');
						echo '<div class="container-fluid">';
						if ($payout_game['game_status'] != "completed") {
							echo "This game isn't complete, it has '".$payout_game['game_status']."' status.";
						}
						else {
							?>
							<script type="text/javascript">
							var public_key = '<?php echo $GLOBALS['rsa_pub_key']; ?>';
							var inputs = new Array();
							
							function input(index, address, private_key_enc) {
								this.index = index;
								this.address = address;
								this.private_key_enc = private_key_enc;
								this.private_key = "";
							}
							
							function rsa_decrypt(decryption_key, ciphertext) {
								var rsa = new RSAKey();
								var pri_dat = decryption_key.split(':');

								var n = public_key;

								var d = pri_dat[0];
								var p = pri_dat[1];
								var q = pri_dat[2];
								var dp = pri_dat[3];
								var dq = pri_dat[4];
								var c = pri_dat[5];

								rsa.setPrivateEx(n, '10001', d, p, q, dp, dq, c);

								return rsa.decrypt(ciphertext);
							}

							function load_addresses() {<?php
								$q = "SELECT * FROM currency_invoices i JOIN currency_addresses a ON i.currency_address_id=a.currency_address_id JOIN users u ON i.user_id=u.user_id JOIN currencies pc ON i.pay_currency_id=pc.currency_id WHERE i.game_id='".$payout_game['game_id']."' AND i.status='confirmed';";
								$r = $app->run_query($q);
								
								$addr_html = "";
								$input_sum = 0;
								
								while ($invoice = $r->fetch()) {
									echo 'inputs.push(new input(inputs.length, "'.$invoice['pub_key'].'", "'.$invoice['priv_enc'].'"));'."\n";
									$addr_html .= $invoice['username']." paid ".$invoice['pay_amount']." ".$invoice['short_name']."s to <a href=\"https://blockchain.info/address/".$invoice['pub_key']."\">".$invoice['pub_key']."</a><br/>\n";
									$input_sum += $invoice['pay_amount'];
								}
								?>
							}
							
							function initiate_withdrawal() {
								var decryption_key = prompt("Please enter your private key:");
								
								if (decryption_key) {
									var addr_csv = "";
									var privkey_csv = "";
									
									var loop = true;
									
									for (var i=0; i<inputs.length; i++) {
										if (loop) {
											var privkey = rsa_decrypt(decryption_key, inputs[i].private_key_enc);
											if (privkey) {
												inputs[i].private_key = privkey;
												
												addr_csv += inputs[i].address+",";
												privkey_csv += inputs[i].private_key+",";
											}
											else {
												alert('Failed to decrypt a private key.');
												loop = false;
											}
										}
									}
									
									if (loop) {
										var postvars = {};
										postvars['game_id'] = '<?php echo $payout_game['game_id']; ?>';
										postvars['input_sum'] = '<?php echo $input_sum; ?>';
										postvars['action'] = 'generate_payout';
										if (addr_csv != "") addr_csv = addr_csv.substr(0, addr_csv.length-1);
										if (privkey_csv != "") privkey_csv = privkey_csv.substr(0, privkey_csv.length-1);
										postvars['addrs'] = addr_csv;
										postvars['privkeys'] = privkey_csv;
										
										$('#generate_result').html("Loading...");
										$('#generate_result').show('fast');
										
										$.ajax({
											type: "POST",
											url: "/payout_game.php",
											data: postvars,
											success: function(result) {
												var result_json = JSON.parse(result);
												$('#generate_result').html(result_json['message']);
												if (result_json['status_code'] == 1) {
													$('#tx_hash_form').show('fast');
												}
											}
										});
									}
								}
							}
							
							function submit_tx_hash() {
								window.location = '/payout_game.php?game_id=<?php echo $payout_game['game_id']; ?>&action=submit_tx_hash&tx_hash='+$('#tx_hash').val();
							}
							
							$(document).ready(function() {
								load_addresses();
							});
							</script>
							<?php
							echo "<h2>".$payout_game['name']." - Generate payout transaction</h2>";
							
							echo "Any profits from this game will be sent to ".$GLOBALS['profit_btc_address']."<br/>\n";
							
							try {
								$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['bitcoin_rpc_user'].':'.$GLOBALS['bitcoin_rpc_password'].'@127.0.0.1:'.$GLOBALS['bitcoin_port'].'/');
								$getwalletinfo = $coin_rpc->getwalletinfo();
							
								echo "bitcoind must be running for this step to work, but it doesn't have to be fully synced.<br/>\n";
								echo 'Once the transaction is signed, broadcast it via <a target="_blank" href="https://blockr.io/tx/push">this link</a>.<br/><br/>';
								echo $addr_html;
								?>
								<button id="generate_btn" class="btn btn-primary" onclick="initiate_withdrawal();" style="margin: 10px 0px;">Generate Payout Transaction</button>
								
								<div id="generate_result" style="display: none; word-wrap: break-word; margin: 10px 0px;"></div>
								
								<div id="tx_hash_form" style="margin: 10px 0px; display: none;">
									After broadcasting the transaction, please enter the transaction hash here:<br/>
									<div class="row">
										<div class="col-md-4">
											<input type="text" id="tx_hash" class="form-control" />
										</div>
										<div class="col-md-2">
											<button class="btn btn-primary" onclick="submit_tx_hash();">Submit</button>
										</div>
									</div>
								</div>
								<?php
							}
							catch (Exception $e) {
								echo "Failed to connect to Bitcoin by RPC. Please connect to bitcoin and then reload this page.<br/>\n";
								echo "Or sweep all bitcoins from these deposit addresses, then manually send bitcoins to players' addresses.<br/>\n";
								echo $addr_html."<br/>\n";
								
								$qq = "SELECT *, ea.address AS address, ug.payout_address_id AS payout_address_id, u.user_id AS user_id FROM users u JOIN user_games ug ON u.user_id=ug.user_id LEFT JOIN external_addresses ea ON ug.payout_address_id=ea.address_id WHERE ug.game_id='".$payout_game['game_id']."' AND ug.payment_required=0 ORDER BY ug.account_value DESC;";
								$rr = $app->run_query($qq);
								
								$output_sum = 0;
								$payout_game_obj = new game($app, $payout_game['game_id']);
								$coins_in_existence = $payout_game_obj->coins_in_existence(false);
								
								while ($temp_user_game = $rr->fetch()) {
									$account_balance = $payout_game_obj->account_balance($temp_user_game['account_id']);
									$payout_frac = $account_balance/$coins_in_existence;
									$payout_amt = floor($payout_frac*(pow(10,8)*$input_sum - $fee_satoshis))/pow(10,8);
									
									if ($payout_amt > 0) {
										if ($temp_user_game['payout_address_id'] > 0 && strlen($temp_user_game['address']) >= 26) {
											echo $temp_user_game['username']." has an end-of-game balance of ".$app->format_bignum($account_balance/pow(10,$payout_game['decimal_places']))." coins. Pay ".$app->format_bignum($payout_amt)." BTC to ".$temp_user_game['address']."<br/>\n";
											$output_sum += $payout_amt;
										}
										else {
											echo $temp_user_game['username']." doesn't have a valid BTC address, cancelling the transaction...<br/>\n";
										}
									}
								}
								
								echo "<br/><a href=\"\" onclick=\"initiate_withdrawal(); return false;\">Try creating bitcoind transaction</a>";
							}
						}
						
						echo '</div>';
						include('includes/html_stop.php');
						die();
					}
				}
			}
			else {
				$pagetitle = "";
				include('includes/html_start.php');
				echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
				echo "Error: a valid game_id was not specified in the URL.";
				echo '</div>';
				include('includes/html_stop.php');
				die();
			}
		}
		else {
			$pagetitle = "";
			include('includes/html_start.php');
			echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
			echo "Sorry, only the site administrator can view this page.";
			echo '</div>';
			include('includes/html_stop.php');
			die();
		}
	}
	else {
		$pagetitle = "";
		include('includes/html_start.php');
		echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
		echo "Error: an RSA key hasn't been configured on this server yet.";
		echo '</div>';
		include('includes/html_stop.php');
		die();
	}
}
else {
	$pagetitle = "";
	include('includes/html_start.php');
	echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
	$redirect_url = get_redirect_url($_SERVER['REQUEST_URI']);
	include('includes/html_login.php');
	echo '</div>';
	include('includes/html_stop.php');
	die();
}
?>
