<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->user_is_admin($thisuser) && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	$block_id = (int) $_REQUEST['block_id'];

	if ($block_id >= $blockchain->db_blockchain['first_required_block']) {
		$block = $blockchain->fetch_block_by_id($block_id);
		
		if ($block) {
			$blockchain->delete_blocks_from_height($block['block_id'], "blockchain_manager");
			$app->output_message(1, "Successfully deleted ".$blockchain->db_blockchain['blockchain_name']." from height #".$block['block_id']);
		}
		else $app->output_message(4, "Failed to fetch block #".$block_id.".");
	}
	else $app->output_message(3, "Please reset this blockchain from block #".$blockchain->db_blockchain['first_required_block']." or higher.");
}
else $app->output_message(2, "You don't have permissions for this action.");
