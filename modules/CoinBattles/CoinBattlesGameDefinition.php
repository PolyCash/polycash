<?php
class CoinBattlesGameDefinition {
	public $app;
	public $game_def;
	public $events_per_round;
	
	public function __construct(&$app) {
		$this->app = $app;
		$this->events_per_round = array(0=>array('Bitcoin','Litecoin'), 1=>array('Bitcoin','Ethereum'));
		$this->load();
	}
	
	public function load() {
		$fname = dirname(__FILE__)."/gamedef_base.txt";
		$fh = fopen($fname, 'r');
		$gamedef_txt = fread($fh, filesize($fname));

		$game_def = json_decode($gamedef_txt);

		$blockchain_r = $this->app->run_query("SELECT * FROM blockchains WHERE url_identifier='".$game_def->blockchain_identifier."';");

		if ($blockchain_r->rowCount() > 0) {
			$db_blockchain = $blockchain_r->fetch();
			
			try {
				$coin_rpc = new jsonRPCClient('http://'.$db_blockchain['rpc_username'].':'.$db_blockchain['rpc_password'].'@127.0.0.1:'.$db_blockchain['rpc_port'].'/');
			}
			catch (Exception $e) {
				echo "Error, failed to load RPC connection for ".$db_blockchain['blockchain_name'].".<br/>\n";
			}
			
			$chain_starting_block = $game_def->game_starting_block;
			$chain_last_block = $coin_rpc->getblockcount();
			$chain_events_until_block = $chain_last_block + $game_def->round_length;

			$defined_rounds = ceil(($chain_events_until_block - $chain_starting_block)/$game_def->round_length);

			$game_def->events = $this->events_between_rounds(1, $defined_rounds, $game_def->round_length, $chain_starting_block);
			
			$this->game_def = $game_def;
		}
		else echo "No blockchain found matching that identifier.";
	}
	
	public function events_between_rounds($from_round, $to_round, $round_length, $chain_starting_block) {
		$events = array();
		for ($round = $from_round; $round<=$to_round; $round++) {
			for ($e=0; $e<count($this->events_per_round); $e++) {
				$event = array(
					"event_starting_block" => $chain_starting_block+$round*$round_length,
					"event_final_block" => $chain_starting_block+($round+1)*$round_length-1,
					"event_payout_block" => $chain_starting_block+($round+1)*$round_length-1,
					"event_name" => $this->events_per_round[$e][0]." vs ".$this->events_per_round[$e][1]." Round #".$round,
					"option_name" => "outcome",
					"option_name_plural" => "outcomes",
					"outcome_index" => null,
					"possible_outcomes" => array(
						0=>array("title" => $this->events_per_round[$e][0]),
						1=>array("title" => $this->events_per_round[$e][1])
					)
				);
				array_push($events, $event);
			}
		}
		return $events;
	}
	
	public function set_event_outcome(&$game, &$coin_rpc, $db_event) {
		$start_block_hash = $coin_rpc->getblockhash((int)$db_event['event_starting_block']);
		$final_block_hash = $coin_rpc->getblockhash((int)$db_event['event_final_block']);
		
		$start_block = $coin_rpc->getblock($start_block_hash);
		$final_block = $coin_rpc->getblock($final_block_hash);
		
		$start_time = $start_block['time'];
		$final_time = $final_block['time'];
		
		$event_type = $this->events_per_round[$db_event['event_index']%count($this->events_per_round)];
		
		$poloniex_pair = "BTC_";
		if ($event_type[1] == "Litecoin") $poloniex_pair .= "LTC";
		else if ($event_type[1] == "Ethereum") $poloniex_pair .= "ETH";
		
		$poloniex_url = "https://poloniex.com/public?command=returnTradeHistory&currencyPair=".$poloniex_pair."&start=".$start_time."&end=".$final_time;
		echo "Event ".$db_event['event_index'].": ".$db_event['event_name']."<br/>\n";
		echo $poloniex_url."<br/>\n";
		$poloniex_response = file_get_contents($poloniex_url);
		
		$poloniex_trades = json_decode($poloniex_response);
		
		if (count($poloniex_trades) > 1) {
			$start_trade = $poloniex_trades[0];
			$final_trade = $poloniex_trades[count($poloniex_trades)-1];
			
			if ((string) $start_trade->rate === (string) $final_trade->rate) {
				echo "Tie<br/>\n";
			}
			else {
				$start_rate = (float) $start_trade->rate;
				$final_rate = (float) $final_trade->rate;
				
				if ($start_rate > $final_rate) {
					$option_index_offset = 1;
					echo $event_type[1]." wins<br/>\n";
				}
				else {
					$option_index_offset = 0;
					echo "Bitcoin wins<br/>\n";
				}
				
				$q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_index ASC LIMIT 1 OFFSET ".$option_index_offset.";";
				$r = $this->app->run_query($q);
				
				if ($r->rowCount() > 0) {
					$db_option = $r->fetch();
				}
				else {
					var_dump($db_event);
					die($r->rowCount().": ".$q);
				}
				
				$q = "UPDATE game_defined_events SET outcome_index=".$db_option['option_index']." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."';";
				$r = $this->app->run_query($q);
			}
		}
		else echo "Not enough data to determine winner.<br/>\n";
	}
}
?>