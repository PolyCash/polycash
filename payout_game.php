<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

if ($thisuser) {
	if ($GLOBALS['rsa_keyholder_email'] != "" && $GLOBALS['rsa_pub_key'] != "") {
		if ($thisuser['username'] == $GLOBALS['rsa_keyholder_email']) {
			$game_id = intval($_REQUEST['game_id']);
			
			$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
			$r = run_query($q);

			if (mysql_numrows($r) > 0) {
				$payout_game = mysql_fetch_array($r);
				
				if ($_REQUEST['action'] == "generate_payout") {
					include("includes/jsonRPCClient.php");
					$coin_rpc = new jsonRPCClient('http://'.$GLOBALS['coin_rpc_user'].':'.$GLOBALS['coin_rpc_password'].'@127.0.0.1:'.$GLOBALS['coin_testnet_port'].'/');

					$addresses = explode(",", $_REQUEST['addrs']);
					$privkeys = explode(",", $_REQUEST['privkeys']);
					$input_sum = floatval($_REQUEST['input_sum']);
					
					$total = 0;
					$raw_txin = array();
					$sign_arr1 = array();
					$sign_arr2 = array();

					for ($i=0; $i<count($addresses); $i++) {
						$q = "SELECT * FROM invoice_addresses WHERE pub_key='".mysql_real_escape_string($addresses[$i])."';";
						$r = run_query($q);
						if (mysql_numrows($r) == 1) {
							$address = mysql_fetch_array($r);
						}
						else die("Error, address $i was not found.");
						
						$api_url = 'https://blockchain.info/address/'.$address['pub_key'].'?format=json';
						$api_result = json_decode(file_get_contents($api_url));
						
						$total += $api_result->final_balance;
						
						for ($t=0; $t<count($api_result->txs); $t++) {
							for ($o=0; $o<count($api_result->txs[$t]->out); $o++) {
								if (!$api_result->txs[$t]->out[$o]->spent) {
									$raw_txin[count($raw_txin)] = array('txid'=>$api_result->txs[$t]->hash, 'vout'=>$api_result->txs[$t]->out[$o]->n);
									
									array_push($sign_arr1, array('txid'=>$api_result->txs[$t]->hash, 'vout'=>$api_result->txs[$t]->out[$o]->n, 'scriptPubKey'=>$api_result->txs[$t]->out[$o]->script));
								}
							}
						}
						
						$coin_rpc->importprivkey($privkeys[$i]);
					}
					
					if ((string)($total/pow(10,8)) != (string)$input_sum) {
						output_message(4, "Error, expected inputs to sum to ".$input_sum." but they only summed to ".$total/pow(10,8));
					}
					else {
						$fee_satoshis = 5000;
						
						$q = "SELECT * FROM currencies WHERE currency_id='".$payout_game['invite_currency']."';";
						$r = run_query($q);
						if (mysql_numrows($r) > 0) {
							$payout_currency = mysql_fetch_array($r);
						}
						
						$qq = "SELECT *, ug.bitcoin_address_id AS bitcoin_address_id FROM users u JOIN user_games ug ON u.user_id=ug.user_id LEFT JOIN external_addresses ea ON ug.bitcoin_address_id=ea.address_id;";
						$rr = run_query($qq);
						$output_sum = 0;
						$coins_in_existence = coins_in_existence($payout_game, false);
						while ($temp_user_game = mysql_fetch_array($rr)) {
							$payout_frac = account_coin_value($payout_game, $temp_user_game)/$coins_in_existence($game, false);
							$payout_amt = floor($payout_frac*($total-$fee_satoshis));
							if ($temp_user_game['bitcoin_address_id'] > 0) {
								$raw_txout[$temp_user_game['address']] = $payout_amt/pow(10,8);
							}
							else {
								output_message(5, "User #".$temp_user_game['user_id']." doesn't have a valid BTC address, cancelling the transaction...");
								die();
							}
						}
						
						try {
							$raw_transaction = $coin_rpc->createrawtransaction($raw_txin, $raw_txout);
							$signed_raw_transaction = $coin_rpc->signrawtransaction($raw_transaction, $sign_arr1);
							
							if ($signed_raw_transaction['complete'] == 1) {
								output_message(1, "Great, the transaction has been created and signed: ".$signed_raw_transaction['hex']);
							}
							else {
								output_message(2, $raw_transaction);
							}
						}
						catch (Exception $e) {
							output_message(3, $e);
						}
					}
				}
				else {
					$pagetitle = "";
					$include_crypto_js = true;
					include('includes/html_start.php');
					echo '<div class="container" style="max-width: 1000px; padding: 10px;">';
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
						$q = "SELECT * FROM currency_invoices i JOIN invoice_addresses a ON i.invoice_address_id=a.invoice_address_id JOIN users u ON i.user_id=u.user_id JOIN currencies pc ON i.pay_currency_id=pc.currency_id WHERE i.game_id='".$payout_game['game_id']."' AND i.status='confirmed';";
						$r = run_query($q);
						$addr_html = "";
						$input_sum = 0;
						while ($invoice = mysql_fetch_array($r)) {
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
									}
								});
							}
						}
					}
					
					$(document).ready(function() {
						load_addresses();
					});
					</script>
					<?php
					echo $addr_html;
					?>
					<button id="generate_btn" class="btn btn-default" onclick="initiate_withdrawal();">Generate Payout Transaction</button>
					<div id="generate_result" style="display: none;"></div>
					<?php
					echo '</div>';
					include('includes/html_stop.php');
					die();
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
	$redirect_id = $redirect_url['redirect_url_id'];
	include('includes/html_login.php');
	echo '</div>';
	include('includes/html_stop.php');
	die();
}
?>