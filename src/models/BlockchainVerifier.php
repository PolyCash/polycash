<?php
class BlockchainVerifier {
	public static function newBlockchainCheck($app, $thisuser, $checkParams) {
		$app->run_insert_query("blockchain_checks", [
			'blockchain_id' => $checkParams['blockchain_id'],
			'creator_id' => $thisuser->db_user['user_id'],
			'from_block' => $checkParams['from_block'],
			'check_type' => $checkParams['check_type'],
			'created_at' => time(),
		]);
		return self::fetchBlockchainCheck($app, $app->last_insert_id());
	}

	public static function fetchBlockchainCheck($app, $blockchainCheckId) {
		return $app->run_query("SELECT * FROM blockchain_checks WHERE blockchain_check_id=:blockchain_check_id;", [
			'blockchain_check_id' => $blockchainCheckId,
		])->fetch(PDO::FETCH_ASSOC);
	}

	public static function fetchChecksForBlockchain($app, $blockchain) {
		return $app->run_query("SELECT * FROM blockchain_checks WHERE blockchain_id=:blockchain_id ORDER BY created_at DESC;", [
			'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function fetchInProgressChecksForBlockchain($app, $blockchain) {
		return $app->run_query("SELECT * FROM blockchain_checks WHERE blockchain_id=:blockchain_id AND completed_at IS NULL ORDER BY created_at DESC;", [
			'blockchain_id' => $blockchain->db_blockchain['blockchain_id'],
		])->fetchAll(PDO::FETCH_ASSOC);
	}

	public static function verifyBlock(&$app, $blockchain_id, $block_id) {
		$any_error = false;
		$any_ios_in_error = false;
		$any_ios_out_error = false;
		$total_ios_error = false;
		
		$tx_wrong_ios_in = $app->run_query("SELECT t.position_in_block, t.tx_hash, t.num_inputs, COUNT(*) FROM transactions t LEFT JOIN transaction_ios io ON io.spend_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id AND t.transaction_desc != 'coinbase' AND t.has_all_inputs=1 GROUP BY t.transaction_id HAVING COUNT(*) != t.num_inputs;", [
			'block_id' => $block_id,
			'blockchain_id' => $blockchain_id
		])->fetchAll();
		
		if (count($tx_wrong_ios_in) > 0) {
			$any_error = true;
			$any_ios_in_error = true;
		}
		
		$tx_wrong_ios_out = $app->run_query("SELECT t.position_in_block, t.tx_hash, t.num_outputs, COUNT(*) FROM transactions t LEFT JOIN transaction_ios io ON io.create_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id AND t.has_all_outputs=1 GROUP BY t.transaction_id HAVING COUNT(*) != t.num_outputs;", [
			'block_id' => $block_id,
			'blockchain_id' => $blockchain_id
		])->fetchAll();
		
		if (count($tx_wrong_ios_out) > 0) {
			$any_error = true;
			$any_ios_out_error = true;
		}
		
		$txo_count = (int)($app->run_query("SELECT COUNT(*) FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id;", [
			'block_id' => $block_id,
			'blockchain_id' => $blockchain_id,
		])->fetch()['COUNT(*)']);
		if ($txo_count < 1) {
			$any_error = true;
			$total_ios_error = true;
		}
		
		return [
			$any_error,
			$any_ios_in_error,
			$any_ios_out_error,
			$total_ios_error,
		];
	}
	
	public static function setCheckComplete($app, $blockchainCheck, $block_id, $error_message) {
		$app->run_query("UPDATE blockchain_checks SET first_error_block=:first_error_block, first_error_message=:first_error_message, completed_at=:completed_at WHERE blockchain_check_id=:blockchain_check_id;", [
			'first_error_block' => $block_id,
			'first_error_message' => $error_message,
			'completed_at' => time(),
			'blockchain_check_id' => $blockchainCheck['blockchain_check_id'],
		]);
	}
	
	public static function setCheckProcessedToBlock($app, $blockchainCheck, $block_id) {
		$app->run_query("UPDATE blockchain_checks SET processed_to_block=:processed_to_block WHERE blockchain_check_id=:blockchain_check_id;", [
			'processed_to_block' => $block_id,
			'blockchain_check_id' => $blockchainCheck['blockchain_check_id'],
		]);
	}
}
