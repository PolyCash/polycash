<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) {
	$action = $_REQUEST['action'];
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$account_name = strip_tags($_REQUEST['account_name']);
	
	$db_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
	
	if ($db_blockchain) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if ($action == "by_rpc_account") {
			if ($app->user_is_admin($thisuser)) {
				if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
					$blockchain->load_coin_rpc();
					
					if ($blockchain->coin_rpc) {
						$error = false;
						try {
							$blockchain->coin_rpc->getwalletinfo();
						}
						catch (Exception $e) {
							$error = true;
						}
						
						if (!$error) {
							$account_addresses = $blockchain->coin_rpc->getaddressesbyaccount($account_name);
							
							if (count($account_addresses) > 0) {
								$account = $app->create_new_account([
									'user_id' => $thisuser->db_user['user_id'],
									'currency_id' => $blockchain->currency_id(),
									'account_name' => $blockchain->db_blockchain['blockchain_name']." account: ".$account_name
								]);
								
								for ($i=0; $i<count($account_addresses); $i++) {
									$blockchain->create_or_fetch_address($account_addresses[$i], true, $blockchain->coin_rpc, false, false, false, $account['account_id']);
								}
								$app->output_message(1, '/accounts/?account_id='.$account['account_id'], false);
							}
							else $app->output_message(8, "Error: that account doesn't exist.", false);
						}
						else $app->output_message(6, "There as an error connecting by RPC.", false);
					}
					else $app->output_message(5, "Failed to connect to RPC.", false);
				}
				else $app->output_message(4, "Action denied: this blockchain does not support RPC calls.", false);
			}
			else $app->output_message(3, "You don't have permission to perform this action.", false);
		}
		else if ($action == "for_blockchain") {
			$account = $app->create_new_account([
				'user_id' => $thisuser->db_user['user_id'],
				'currency_id' => $blockchain->currency_id(),
				'account_name' => $blockchain->db_blockchain['blockchain_name']." Account ".$app->random_string(5)
			]);
			
			$app->output_message(1, '/accounts/?account_id='.$account['account_id'], false);
		}
	}
	else $app->output_message(7, "Error: invalid blockchain ID.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>