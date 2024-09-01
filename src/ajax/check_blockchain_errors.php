<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->user_is_admin($thisuser)) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	
	if (empty($_REQUEST['action'])) {
		$app->output_message(1, "", [
			'rendered_content' => $app->render_view('check_blockchain_errors', [
				'app' => $app,
				'blockchain' => $blockchain,
				'blockchainChecks' => BlockchainVerifier::fetchChecksForBlockchain($app, $blockchain),
			]),
		]);
	}
	else if ($_REQUEST['action'] == "submit_new_check") {
		if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
			$blockchainCheck = BlockchainVerifier::newBlockchainCheck($app, $thisuser, $_REQUEST);
			
			if ($blockchainCheck) {
				$app->output_message(1, "Successfully initiated blockchain check.");
			}
			else $app->output_message(4, "Failed to create blockchain check.");
		}
		else $app->output_message(3, "Invalid session, please try again.");
	}
}
else $app->output_message(2, "You don't have permissions for this action.");
