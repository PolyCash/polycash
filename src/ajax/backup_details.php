<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$backup = CurrencyAccount::fetchBackupById($app, $_REQUEST['backup_id']);

	if ($backup) {
		$account = $app->fetch_account_by_id($backup['account_id']);
		
		if ($account['user_id'] == $thisuser->db_user['user_id']) {
			$user = $app->fetch_user_by_id($backup['user_id']);
			
			$app->output_message(1, "", [
				'renderedContent' => $app->render_view('backup_details', [
					'backup' => $backup,
					'account' => $account,
					'user' => $user,
					'address_keys' => CurrencyAccount::fetchAddressKeysByIdArr($app, $account, json_decode($backup['extra_info'], true)['address_key_ids']),
				])
			]);
		}
		else $app->output_message(4, "You don't have access to that account.");
	}
	else $app->output_message(3, "Please supply a valid backup ID.");
}
else $app->output_message(2, "Please log in to use this feature.");
