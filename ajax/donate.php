<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$action = $_REQUEST['action'];

if ($action == "load") {
	$bitcoin_currency = $app->get_currency_by_abbreviation("btc");
	$blockchain = new Blockchain($app, $bitcoin_currency['blockchain_id']);
	$currency_account = false;
	$keygen = new bitcoin();
	$keySet = $keygen->getNewKeySet();
	$address_text = $keySet['pubAdd'];
	$address_secret = $keySet['privWIF'];
	$coin_rpc = false;
	$access_key = $app->random_string(20);
	
	$db_address = $blockchain->create_or_fetch_address($address_text, true, $coin_rpc, false, true, true, false);
	
	$q = "INSERT INTO address_keys SET access_key=".$app->quote_escape($access_key).", pub_key=".$app->quote_escape($address_text).", priv_key=".$app->quote_escape($address_secret).", save_method='db', currency_id='".$bitcoin_currency['currency_id']."', address_id='".$db_address['address_id']."';";
	$r = $app->run_query($q);
	?>
	<p>To donate bitcoins, please send BTC to the address below. This address was just generated and will remain private unless you share it. If you make a donation but don't wish to share your email address, please write down the backup code below for your records.</p>
	<center>
		<p><?php echo $address_text; ?></p>
		<p><img src="render_qr_code.php?data=<?php echo $address_text; ?>" /></p>
		<p>Backup code: <?php echo $access_key; ?></p>
	</center>
	<form action="/" method="get" onsubmit="donate_step('save_email'); return false;" id="donate_email_form">
		<input type="hidden" id="donate_access_key" value="<?php echo $access_key; ?>" />
		<div class="form-group">
			<label for="donate_email_address">Donations count towards our ICO. To receive coins proportional to your donation, please enter an email address here:</label>
			<input class="form-control" type="text" id="donate_email_address" placeholder="Please enter an email address" />
		</div>
		<div class="form-group">
			<button class="btn btn-primary" id="donate_email_save_btn">Save email address</button>
		</div>
	</form>
	<?php
}
else if ($action == "save_email") {
	$email = $_REQUEST['email'];
	$access_key = $_REQUEST['access_key'];
	
	$q = "SELECT * FROM address_keys WHERE access_key=".$app->quote_escape($access_key).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() > 0) {
		$address_key = $r->fetch();
		
		if (empty($address_key['associated_email_address'])) {
			$q = "UPDATE address_keys SET associated_email_address=".$app->quote_escape($email)." WHERE address_key_id='".$address_key['address_key_id']."';";
			$r = $app->run_query($q);
			
			$app->output_message(1, "<font class=\"greentext\">All donations to this address will be credited to <b>$email</b></font>", false);
		}
		else $app->output_message(2, "<font class=\"redtext\">An email address is already set for this address</font>", false);
	}
	else $app->output_message(3, "<font class=\"redtext\">Error: failed to find that address in the db.</font>", false);
}
?>