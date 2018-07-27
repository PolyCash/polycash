<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	$blockchain_id = (int) $_REQUEST['blockchain_id'];
	$account_name = strip_tags($_REQUEST['account_name']);
	
	$blockchain_r = $app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';");
	
	if ($blockchain_r->rowCount() > 0) {
		$db_blockchain = $blockchain_r->fetch();
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		
		if ($action == "by_rpc_account") {
			if ($app->user_is_admin($thisuser)) {
				if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
					if (!empty($blockchain->db_blockchain['rpc_username']) && !empty($blockchain->db_blockchain['rpc_password'])) {
						$error = false;
						try {
							$coin_rpc = new jsonRPCClient('http://'.$blockchain->db_blockchain['rpc_username'].':'.$blockchain->db_blockchain['rpc_password'].'@127.0.0.1:'.$blockchain->db_blockchain['rpc_port'].'/');
							$coin_rpc->getwalletinfo();
						}
						catch (Exception $e) {
							$error = true;
						}
						
						if (!$error) {
							$account_addresses = $coin_rpc->getaddressesbyaccount($account_name);
							
							if (count($account_addresses) > 0) {
								$qq = "INSERT INTO currency_accounts SET user_id='".$thisuser->db_user['user_id']."', currency_id='".$blockchain->currency_id()."', account_name=".$app->quote_escape($blockchain->db_blockchain['blockchain_name']." account: ".$account_name).", time_created='".time()."';";
								$rr = $app->run_query($qq);
								$account_id = $app->last_insert_id();
								
								for ($i=0; $i<count($account_addresses); $i++) {
									$blockchain->create_or_fetch_address($account_addresses[$i], true, $coin_rpc, false, false, false, $account_id);
								}
								$app->output_message(1, '/accounts/?account_id='.$account_id, false);
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
			$account_name = $blockchain->db_blockchain['blockchain_name']." Account ".$app->random_string(5);
			
			$qq = "INSERT INTO currency_accounts SET user_id='".$thisuser->db_user['user_id']."', currency_id='".$blockchain->currency_id()."', account_name=".$app->quote_escape($account_name).", time_created='".time()."';";
			$rr = $app->run_query($qq);
			$account_id = $app->last_insert_id();
			
			$app->output_message(1, '/accounts/?account_id='.$account_id, false);
		}
	}
	else $app->output_message(7, "Error: invalid blockchain ID.", false);
}
else $app->output_message(2, "You must be logged in to complete this step.", false);
?>