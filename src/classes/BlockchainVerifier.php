<?php
class BlockchainVerifier {
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
}
