<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['key', 'blockchain_id', 'print_debug', 'force'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
		if ($blockchain->db_blockchain['p2p_mode'] == "rpc") {
			$blockchain->load_coin_rpc();
			
			if ($blockchain->coin_rpc) {
				$rpc_mining_lock_name = "rpc_mining_".$blockchain->db_blockchain['blockchain_id'];
				$rpc_mining_already = $app->check_process_running($rpc_mining_lock_name);
				
				if (!empty($_REQUEST['force']) || (!$rpc_mining_already && $app->lock_process($rpc_mining_lock_name))) {
					$new_address = $blockchain->coin_rpc->getnewaddress();
					
					if ($new_address) {
						echo "Generating block...\n";
						$generate_response = $blockchain->coin_rpc->generatetoaddress(1, $new_address);
						echo json_encode($generate_response);
					}
					else echo "Failed to generate a new address.\n";
				}
				else echo "RPC mining is already running for this blockchain.\n";
			}
			else echo "There was an error initializing the RPC client.\n";
		}
		else echo "This command only works for RPC blockchains.\n";
	}
	else echo "Please supply a blockchain_id.\n";
}
else echo "Please run this script as admin.\n";
