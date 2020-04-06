<?php
class BlockchainVerifier {
	public static function verifyBlock(&$app, $blockchain_id, $block_id) {
		$any_error = false;
		$any_ios_in_error = false;
		$any_ios_out_error = false;
		
		$tx_wrong_ios_in = $app->run_query("SELECT t.position_in_block, t.tx_hash, t.num_inputs, COUNT(*) FROM transactions t LEFT JOIN transaction_ios io ON io.spend_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id AND t.transaction_desc != 'coinbase' GROUP BY io.spend_transaction_id HAVING COUNT(*) != t.num_inputs;", [
			'block_id' => $block_id,
			'blockchain_id' => $blockchain_id
		]);
		
		if ($tx_wrong_ios_in->rowCount() > 0) {
			$any_error = true;
			$any_ios_in_error = true;
		}
		
		$tx_wrong_ios_out = $app->run_query("SELECT t.position_in_block, t.tx_hash, t.num_outputs, COUNT(*) FROM transactions t LEFT JOIN transaction_ios io ON io.create_transaction_id=t.transaction_id WHERE t.block_id=:block_id AND t.blockchain_id=:blockchain_id GROUP BY io.create_transaction_id HAVING COUNT(*) != t.num_outputs;", [
			'block_id' => $block_id,
			'blockchain_id' => $blockchain_id
		]);
		
		if ($tx_wrong_ios_out->rowCount() > 0) {
			$any_error = true;
			$any_ios_out_error = true;
		}
		
		return [
			$any_error,
			$any_ios_in_error,
			$any_ios_out_error
		];
	}
}
