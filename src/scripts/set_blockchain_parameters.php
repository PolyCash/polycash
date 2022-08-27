<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['blockchain_identifier', 'rpc_username', 'rpc_password', 'rpc_port', 'first_required_block'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!empty($_REQUEST['blockchain_identifier']) && $blockchain_arr = $app->fetch_blockchain_by_identifier($_REQUEST['blockchain_identifier'])) {
		$blockchain = new Blockchain($app, $blockchain_arr['blockchain_id']);
		$errors = [];
		
		if (empty($_REQUEST['rpc_username'])) array_push($errors, "Please specify an rpc_username");
		if (empty($_REQUEST['rpc_password'])) array_push($errors, "Please specify an rpc_password");

		if (count($errors) == 0) {
			$params = [
				'rpc_username' => $_REQUEST['rpc_username'],
				'rpc_password' => $_REQUEST['rpc_password'],
			];

			if (empty($blockchain->db_blockchain['rpc_host']) || !empty($_REQUEST['rpc_host'])) {
				$params['rpc_host'] = empty($_REQUEST['rpc_host']) ? '127.0.0.1' : $_REQUEST['rpc_host'];
			}
			
			if (empty($blockchain->db_blockchain['rpc_port']) || !empty($_REQUEST['rpc_port'])) {
				$params['rpc_port'] = empty($_REQUEST['rpc_port']) ? $blockchain->db_blockchain['default_rpc_port'] : $_REQUEST['rpc_port'];
			}

			if (array_key_exists('first_required_block', $_REQUEST)) {
				if ((string) $_REQUEST['first_required_block'] === "") $first_required_block = null;
				else $first_required_block = (int) $_REQUEST['first_required_block'];
				
				$params['first_required_block'] = $first_required_block;
				$params['last_complete_block'] = $first_required_block === null ? null : $first_required_block-1;
			}

			$blockchain->set_blockchain_parameters($params);
		}
		else echo implode("\n", $errors)."\n";
	}
	else echo "Please supply a valid blockchain_identifier.\n";
}
else echo "You need admin privileges to run this script.\n";
