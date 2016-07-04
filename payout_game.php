<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = insert_pageview($thisuser);

$pagetitle = "";
$include_crypto_js = true;
include('includes/html_start.php');
echo '<div class="container" style="max-width: 1000px; padding: 10px;">';

if ($thisuser) {
	if ($GLOBALS['rsa_keyholder_email'] != "" && $GLOBALS['rsa_pub_key'] != "") {
		if ($thisuser['username'] == $GLOBALS['rsa_keyholder_email']) {
			$game_id = intval($_REQUEST['game_id']);
			
			$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
			$r = run_query($q);

			if (mysql_numrows($r) > 0) {
				$payout_game = mysql_fetch_array($r);
				?>
				<script type="text/javascript">
				var public_key = '<?php echo $GLOBALS['rsa_pub_key']; ?>';

				function rsa_decrypt(private_key, ciphertext)
				{
					var rsa = new RSAKey();
					var pri_dat = private_key.split(':');

					var n = public_key;

					var d = pri_dat[0];
					var p = pri_dat[1];
					var q = pri_dat[2];
					var dp = pri_dat[3];
					var dq = pri_dat[4];
					var c = pri_dat[5];

					rsa.setPrivateEx(n, '10001', d, p, q, dp, dq, c);

					var res = rsa.decrypt(ciphertext);

					if (res == null) {
						return "*** Invalid Ciphertext ***";
					} else {
						return res;
					}
				}

				function show_invoice_addr_WIF(invoice_id, ciphertext) {
					var private_key = prompt("Please enter your private key:");

					$('#invoice_'+invoice_id+'_decrypted').html("WIF key: "+rsa_decrypt(private_key, ciphertext));
					$('#invoice_'+invoice_id+'_decrypted').show('medium');
				}
				</script>
				<?php
				$q = "SELECT * FROM currency_invoices i JOIN invoice_addresses a ON i.invoice_address_id=a.invoice_address_id JOIN users u ON i.user_id=u.user_id JOIN currencies pc ON i.pay_currency_id=pc.currency_id WHERE i.game_id='".$payout_game['game_id']."';";
				$r = run_query($q);
				while ($invoice = mysql_fetch_array($r)) {
					echo $invoice['username']." was asked to pay ".$invoice['pay_amount']." ".$invoice['short_name']."s to <a href=\"https://blockchain.info/address/".$invoice['pub_key']."\">".$invoice['pub_key']."</a>&nbsp;&nbsp;<button onclick=\"show_invoice_addr_WIF(".$invoice['invoice_id'].", '".$invoice['priv_enc']."'); return false;\">Get WIF key</button><br/>\n";
					echo '<div id="invoice_'.$invoice['invoice_id'].'_decrypted" style="display: none;"></div>';
				}
			}
			else {
				echo "Error: a valid game_id was not specified in the URL.";
			}
		}
		else {
			echo "Sorry, only the site administrator can view this page.";
		}
	}
	else {
		echo "Error: an RSA key hasn't been configured on this server yet.";
	}
}
else {
	$redirect_url = get_redirect_url($_SERVER['REQUEST_URI']);
	$redirect_id = $redirect_url['redirect_url_id'];
	include('includes/html_login.php');
}

echo '</div>';
include('includes/html_stop.php');
?>