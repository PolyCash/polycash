<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$action = $_REQUEST['action'];

if ($action == "load") {
	$donate_currency = $app->get_currency_by_abbreviation("ltc");
	$blockchain = new Blockchain($app, $donate_currency['blockchain_id']);
	$admin_user_id = (int) $app->get_site_constant("admin_user_id");
	
	if ($admin_user_id > 0) {
		$admin_user = new User($app, $admin_user_id);
		$admin_donation_account = $app->user_blockchain_account($admin_user->db_user['user_id'], $donate_currency['currency_id']);
		$new_donation_address = $app->new_normal_address_key($donate_currency['currency_id'], $admin_donation_account);
		
		if ($new_donation_address) {
			$donation_access_key = $app->random_string(20);
			$app->run_query("UPDATE address_keys SET access_key=:access_key WHERE address_key_id=:address_key_id;", [
				'access_key' => $donation_access_key,
				'address_key_id' => $new_donation_address['address_key_id']
			]);
			?>
			<p>To donate <?php echo $donate_currency['short_name_plural']; ?>, please send <?php echo $donate_currency['abbreviation']; ?> to the address below. This address was just generated and will remain private unless you share it.</p>
			<center>
				<p><?php echo $new_donation_address['address']; ?></p>
				<p><img src="render_qr_code.php?data=<?php echo $new_donation_address['address']; ?>" /></p>
			</center>
			<form action="/" method="get" onsubmit="thisPageManager.donate_step('save_email'); return false;" id="donate_email_form">
				<input type="hidden" id="donate_access_key" value="<?php echo $donation_access_key; ?>" />
				<div class="form-group">
					<label for="donate_email_address">If you'd like us to know who the donation is from, you can enter your email address here:</label>
					<input class="form-control" type="text" id="donate_email_address" placeholder="Please enter an email address" />
				</div>
				<div class="form-group">
					<button class="btn btn-primary" id="donate_email_save_btn">Save email address</button>
				</div>
			</form>
			<?php
		}
		else echo "<p>Failed to generate a donation address. Is Bitcoin running?</p>\n";
	}
}
else if ($action == "save_email") {
	$email = strip_tags($_REQUEST['email']);
	$access_key = $_REQUEST['access_key'];
	
	$address_key = $app->run_query("SELECT * FROM address_keys WHERE access_key=:access_key;", [
		'access_key' => $access_key
	])->fetch();
	
	if ($address_key) {
		if (empty($address_key['associated_email_address'])) {
			$app->run_query("UPDATE address_keys SET associated_email_address=:email_address WHERE address_key_id=:address_key_id;", [
				'email_address' => $email,
				'address_key_id' => $address_key['address_key_id']
			]);
			
			$app->output_message(1, "<font class=\"greentext\">All donations to this address will be credited to <b>$email</b></font>", false);
		}
		else $app->output_message(2, "<font class=\"redtext\">An email address is already set for this address</font>", false);
	}
	else $app->output_message(3, "<font class=\"redtext\">Error: failed to find that address in the db.</font>", false);
}
?>