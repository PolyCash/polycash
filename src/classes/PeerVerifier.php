<?php
class PeerVerifier {
	private $app;
	private $blockchain;
	private $game;
	private $game_or_blockchain;
	private $mode;
	private $remote_url_base;
	
	public function __construct(&$app, $mode, $game_identifier, $blockchain_identifier) {
		$this->app = $app;
		$this->mode = $mode;
		
		if (in_array($mode, ['game_ios','game_events'])) {
			$this->game_identifier = $game_identifier;
			$this->game_or_blockchain = "game";
			$db_game = $this->app->fetch_game_by_identifier($this->game_identifier);
			if (empty($db_game)) die("Invalid game identifier supplied.");
			$this->blockchain = new Blockchain($this->app, $db_game['blockchain_id']);
			$this->game = new Game($this->blockchain, $db_game['game_id']);
		}
		else {
			$this->blockchain_identifier = $blockchain_identifier;
			$this->game_or_blockchain = "blockchain";
			$db_blockchain = $this->app->fetch_blockchain_by_identifier($blockchain_identifier);
			if (empty($db_blockchain)) die("Invalid blockchain identifier supplied.");
			$this->blockchain = new Blockchain($this->app, $db_blockchain['blockchain_id']);
		}
	}
	
	public static function txosPerPartition() {
		return 1000;
	}
	
	public function remoteUrl($peer, $remote_host_url, $remote_key) {
		$this->remote_url_base = $peer ? $peer['base_url'] : $remote_host_url;
		$remote_url = $this->remote_url_base."/scripts/verify_api.php?mode=".$this->mode;
		$remote_url .= $this->mode == "blockchain" ? "&blockchain_identifier=".$this->blockchain->db_blockchain['url_identifier'] : "&game_identifier=".$this->game->db_game['url_identifier'];
		$remote_url .= "&key=".$remote_key;
		
		return $remote_url;
	}
	
	public static function fetchTxosByIndex($game, $fromIndex, $toIndex) {
		$txos = $game->blockchain->app->run_query("SELECT io.create_block_id AS io_create_block_id, gio.game_io_index, gio.create_block_id, gio.colored_amount FROM transaction_ios io JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id=:game_id AND gio.game_io_index >= :from_index AND gio.game_io_index <= :to_index ORDER BY gio.game_io_index ASC;", [
			'game_id' => $game->db_game['game_id'],
			'from_index' => $fromIndex,
			'to_index' => $toIndex,
		])->fetchAll(PDO::FETCH_ASSOC);
		
		$formattedTxos = [];
		foreach ($txos as $txo) {
			$formattedTxos[(string)$txo['game_io_index']] = [(int)$txo['game_io_index'], (int)$txo['io_create_block_id'], (int)$txo['create_block_id'], (int)$txo['colored_amount']];
		}
		return $formattedTxos;
	}
	
	public function renderOutput() {
		if ($this->mode == "game_ios") {
			$total_issued = 0;
			
			$last_block_id = $this->game->blockchain->last_block_id();
			$last_block = $this->game->fetch_game_block_by_height($last_block_id);
			
			return PeerVerifier::fetchTxosByIndex($this->game, 0, $last_block['max_game_io_index']);
		}
		else if ($this->mode == "game_events") {
			$check_tx_count = true;
			
			$out_obj = [];
			
			$events_by_game_params = [
				'game_id' => $this->game->db_game['game_id'],
				'block_id' => $this->game->last_block_id(),
			];
			$events_by_game_q = "SELECT ev.event_id, gde.event_index, gde.event_name, gde.outcome_index FROM game_defined_events gde LEFT JOIN events ev ON gde.event_index=ev.event_index WHERE gde.game_id=:game_id AND ev.game_id=:game_id AND ev.event_final_block <= :block_id ORDER BY gde.event_index ASC;";
			$events_by_game = $this->app->run_query($events_by_game_q, $events_by_game_params)->fetchAll(PDO::FETCH_ASSOC);
			
			foreach ($events_by_game as $db_event) {
				if ($check_tx_count && !empty($db_event['event_id'])) {
					$event_tx_arr = $this->game->blockchain->transactions_by_event($db_event['event_id'])->fetchAll();
					$db_event['num_transactions'] = count($event_tx_arr);
				}
				unset($db_event['event_id']);
				
				array_push($out_obj, $db_event);
			}
			
			return $out_obj;
		}
		else if ($this->mode == "blockchain") {
			$all_blocks = $this->app->run_query("SELECT block_id, num_transactions, sum_coins_in, sum_coins_out FROM blocks WHERE blockchain_id=:blockchain_id AND block_id>0 ORDER BY block_id ASC;", ['blockchain_id'=>$this->blockchain->db_blockchain['blockchain_id']])->fetchAll();
			
			$out_obj = [];
			
			foreach ($all_blocks as $block) {
				array_push($out_obj, [
					"block_id" => $block['block_id'],
					"num_transactions" => $block['num_transactions'],
					"sum_coins_in" => $block['sum_coins_in'],
					"sum_coins_out" => $block['sum_coins_out']
				]);
			}
			
			return $out_obj;
		}
	}
	
	public function checkDisplayDifferences($local_info, $remote_info) {
		if ($this->mode == "game_ios") {
			$loop_to = min(count($local_info), count($remote_info));
			$any_error = false;
			
			for ($i=0; $i<$loop_to; $i++) {
				if ($i%1000 == 0) {
					echo ". ";
					$this->app->flush_buffers();
				}
				if ($local_info[$i] != $remote_info[$i]) {
					echo "First error on line #$i<br/>\n";
					echo "<pre>local: ".json_encode($local_info[$i])."</pre><pre>remote: ".json_encode($remote_info[$i])."</pre>\n";
					if ($i > 0) $i=$loop_to;
					$any_error = true;
				}
			}
			
			if (!$any_error) echo "No errors found.\n";
		}
		else if ($this->mode == "game_events") {
			$loop_to = min(count($local_info), count($remote_info));
			$error_count = 0;
			
			for ($i=0; $i<$loop_to; $i++) {
				if ($i%1000 == 0) {
					echo ". ";
					$this->app->flush_buffers();
				}
				if ($local_info[$i] != $remote_info[$i]) {
					echo "Error on line #$i:\n";
					echo json_encode($local_info[$i], JSON_PRETTY_PRINT)."\n";
					echo json_encode($remote_info[$i], JSON_PRETTY_PRINT)."\n";
					echo "local <a href=\"/explorer/games/".$this->game_identifier."/events/".$local_info[$i]->event_index."\">".$local_info[$i]->event_name."</a> vs remote <a href=\"".$this->remote_url_base."/explorer/games/".$this->game_identifier."/events/".$remote_info[$i]->event_index."\">".$remote_info[$i]->event_name."</a><br/>\n";
					$error_count++;
				}
			}
			
			echo "Found $error_count errors.<br/>\n";
		}
		else if ($this->mode == "blockchain") {
			$loop_to = min(count($local_info), count($remote_info));
			$any_error = false;
			
			for ($i=0; $i<$loop_to; $i++) {
				if ($i%1000 == 0) {
					echo ". ";
					$this->app->flush_buffers();
				}
				if ($local_info[$i] != $remote_info[$i]) {
					echo "First error found<br/>\n";
					echo "<pre>local: ".json_encode($local_info[$i])."</pre><pre>remote: ".json_encode($remote_info[$i])."</pre>\n";
					if ($i > 0) $i = $loop_to;
					$any_error = true;
				}
			}
			
			if (!$any_error) echo "No errors found.\n";
		}
	}
}
