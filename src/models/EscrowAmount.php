<?php
class EscrowAmount {
	public static function insert_escrow_amount(&$app, $game_id, $currency_id, $defined_or_actual, $escrow_position, $escrow_amount_arr) {
		if ($defined_or_actual == "defined") $table_name = "game_defined_escrow_amounts";
		else $table_name = "game_escrow_amounts";
		
		$insert_params = [
			'game_id' => $game_id,
			'currency_id' => $currency_id,
			'escrow_type' => $escrow_amount_arr['type'],
			'escrow_position' => $escrow_position
		];
		
		if ($escrow_amount_arr['type'] == "dynamic") $insert_params['relative_amount'] = $escrow_amount_arr['relative_amount'];
		else $insert_params['amount'] = $escrow_amount_arr['amount'];
		
		$app->run_insert_query($table_name, $insert_params);
	}
	
	public static function fetch_escrow_amounts_in_game(&$game, $defined_or_actual) {
		if ($defined_or_actual == "defined") $table_name = "game_defined_escrow_amounts";
		else $table_name = "game_escrow_amounts";
		
		return $game->blockchain->app->run_query("SELECT ea.*, c.abbreviation FROM ".$table_name." ea JOIN currencies c ON ea.currency_id=c.currency_id WHERE ea.game_id=:game_id;", [
			'game_id' => $game->db_game['game_id']
		]);
	}
}
