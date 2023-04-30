<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$backup = User::fetchBackupExportById($app, $_REQUEST['backup_id']);

	if ($backup) {
		if ($backup['user_id'] == $thisuser->db_user['user_id']) {
			$extra_info = json_decode($backup['extra_info'], true);
			$user = $app->fetch_user_by_id($backup['user_id']);
			$accounts = $app->run_query("SELECT * FROM currency_accounts WHERE account_id IN (".implode(",", $extra_info['account_ids']).");")->fetchAll(PDO::FETCH_ASSOC);
			$addresses_by_account_id = [];
			foreach ($accounts as $account) {
				$addresses_by_account_id[$account['account_id']] = $app->fetchAddressKeysByIdArr($account, $extra_info['address_key_ids'][$account['account_id']]);
			}
			$app->output_message(1, "", [
				'renderedContent' => $app->render_view('backup_details', [
					'backup' => $backup,
					'user' => $user,
					'accounts' => $accounts,
					'addresses_by_account_id' => $addresses_by_account_id,
				])
			]);
		}
		else $app->output_message(4, "You don't have access to that account.");
	}
	else $app->output_message(3, "Please supply a valid backup ID.");
}
else $app->output_message(2, "Please log in to use this feature.");
