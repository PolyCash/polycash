<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$action = $_REQUEST['action'];
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$account_name = strip_tags($_REQUEST['account_name']);
	
	$db_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
	
	if ($db_blockchain) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if ($action == "for_blockchain") {
			$account = $app->create_new_account([
				'user_id' => $thisuser->db_user['user_id'],
				'currency_id' => $blockchain->currency_id(),
				'account_name' => $blockchain->db_blockchain['blockchain_name']." Account ".$app->random_string(5)
			]);
			
			$app->output_message(1, '/accounts/?account_id='.$account['account_id'], false);
		}
		else $app->output_message(4, "Error: invalid blockchain ID.", false);
	}
	else $app->output_message(3, "Error: invalid blockchain ID.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>