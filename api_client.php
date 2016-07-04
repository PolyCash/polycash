<?php
include("includes/config.php");
ini_set('display_errors', 'Off');
/*
EmpireCoin Voting Recommendations API Client
To use custom logic for voting your empirecoins, install this PHP script on a webserver
Then enter it's URL into your EmpireCoin user account under the Voting Strategies -> Vote by API section.
To keep your recommendations private, it's recommended that you set a secure $client_access_key below.
Then your API url will be something like:
http://mywebserver.com/EmpirecoinAPIClient.php?key=$GLOBALS['cron_key_string']
*/

// Log into your web wallet -> Settings to find your server access key, then enter it below
$server_access_key = $GLOBALS['default_server_api_access_key'];
$server_host = "http://empirecoin.org";
$client_access_key = $GLOBALS['cron_key_string'];

$game_id = intval($_REQUEST['game_id']);
if (!$game_id) die("Please provide a valid game_id");

if ($_REQUEST['key'] == $client_access_key) {
	// Many VotingRecommendations can be sent at once; each consists of an empire and a number of votes.
	class VotingRecommendation {
		public $option_id;
		public $name;
		public $recommended_amount;
		
		public function __construct($option_id, $name, $recommendation_amount) {
			$this->option_id = $option_id;
			$this->name = $name;
			$this->recommended_amount = 0;
		}
	}
	
	// Voting logic & API behavior are contained in the VotingRecommendations class
	class VotingRecommendations {
		public $recommendations;
		public $error_code;
		public $error_message;
		public $total_vote_amount;
		public $server_result;
		public $name2option_id;
		public $rank2option_id;
		public $recommendation_unit;
		public $server_host;
		public $server_access_key;
		public $input_utxo_ids;
		
		// Initialize variables and define the Empires aka "Voting Options"
		public function __construct($game_id, $server_host, $server_access_key) {
			$this->game_id = $game_id;
			$this->server_host = $server_host;
			$this->server_access_key = $server_access_key;
			
			$this->server_result = FALSE;
			$this->game = FALSE;
			$this->game_scores = FALSE;
			$this->user_info = FALSE;
			
			$this->error_code = FALSE;
			$this->error_message = "";
			$this->total_vote_amount = FALSE;
			$this->name2option_id = FALSE;
			$this->rank2option_id = FALSE;
			$this->recommendation_unit = FALSE;
			$this->input_utxo_ids = array();
			
			$this->recommendations = array();
		}
		
		// Define a map from empire names to empire IDs so that we can reference empires by name
		public function setname2option_id() {
			for ($option_id=0; $option_id<count($this->recommendations); $option_id++) {
				$this->name2option_id[$this->recommendations[$option_id]->name] = $option_id;
			}
		}
		
		// Fetch information about the current status of the EmpireCoin blockchain
		// If a valid EmpireCoin.org api_access_code has been specified, 
		// information about the corresponding user account will also be returned
		public function getCurrentStatus() {
			$fetch_url = $this->server_host."/api/".$this->game_id."/status/";
			if ($this->server_access_key && $this->server_access_key != "") {
				$fetch_url .= "?api_access_code=".$this->server_access_key;
			}
			
			$fetch_result = file_get_contents($fetch_url);
			
			if ($fetch_result) {
				// Store the result of the API call
				$this->server_result = json_decode($fetch_result);
				$this->game = $this->server_result->game;
				$this->game_scores = $this->server_result->game_scores;
				$this->user_info = $this->server_result->user_info;
				
				if (!$this->game) {
					$this->error_code = 2;
					$this->error_message = "Error: the server didn't return a valid game.";
					$this->outputJSON();
					die();
				}
				else {
					// Define the rank2option_id map so that we can reference nations by rank in setOutputs()
					foreach ($this->game_scores as $game_score) {
						$this->rank2option_id[$game_score->rank] = $game_score->option_id;
						$this->recommendations[$game_score->option_id] = new VotingRecommendation($game_score->option_id, $game_score->name);
					}
					$this->setname2option_id();
				}
			}
		}
		
		// Only recommendations with amounts greater than 0 need to be sent back to the server
		public function nonzeroRecommendations() {
			$nonzeroRecommendations = array();
			
			foreach ($this->recommendations as $recommendation) {
				if ($recommendation->recommended_amount > 0) {
					$nonzeroRecommendations[count($nonzeroRecommendations)] = $recommendation;
				}
			}
			
			return $nonzeroRecommendations;
		}
		
		// Output our response to JSON
		public function outputJSON() {
			$output_obj = array();
			
			if ($this->error_code) {
				$output_obj['errors'] = array(0 => $this->error_message);
				$output_obj['recommendations'] = array();
			}
			else {
				if (count($this->input_utxo_ids) > 0) $output_obj['input_utxo_ids'] = $this->input_utxo_ids;
				$output_obj['recommendation_unit'] = $this->recommendation_unit;
				$output_obj['recommendations'] = $this->nonzeroRecommendations();
			}
			
			echo json_encode($output_obj);
		}
		
		
		// If a valid server access code has been specified, recommendations are denominated in satoshis
		// and should sum up to the user's mature balance.  If no user account is specified, recommendations are 
		// denominated in percentage points; the server will convert percentages based on the user's mature balance.
		public function setInputs() {
			if ($this->user_info) {
				$input_coins = 0;
				$input_utxo_ids = array();
				for ($i=0; $i<count($this->user_info->my_utxos); $i++) {
					$input_utxo_ids[count($input_utxo_ids)] = $this->user_info->my_utxos[$i]->utxo_id;
					$input_coins += $this->user_info->my_utxos[$i]->coins;
				}
				$this->input_utxo_ids = $input_utxo_ids;
				
				$total_vote_amount = $input_coins;
				$this->recommendation_unit = "coin";
			}
			else {
				$total_vote_amount = 100;
				$this->recommendation_unit = "percent";
			}
			
			$this->total_vote_amount = $total_vote_amount;
		}
		
		public function setOutputs() {
			if ($this->total_vote_amount) {
				if ($this->server_result) {
					// Only cast votes late in the round
					if ($this->game->block_within_round >= 2) {
						if ($this->user_info) { // Recommendations specified in coins
							if ($this->user_info->votes_available >= $this->user_info->mature_balance) {
								$coins_out = 0;
								
								$rec_amount = floor($this->total_vote_amount*0.15); $coins_out += $rec_amount;
								$this->recommendations[$this->rank2option_id[1]]->recommended_amount = $rec_amount;
								
								$rec_amount = floor($this->total_vote_amount*0.25); $coins_out += $rec_amount;
								$this->recommendations[$this->rank2option_id[2]]->recommended_amount = $rec_amount;
								
								$rec_amount = floor($this->total_vote_amount*0.30); $coins_out += $rec_amount;
								$this->recommendations[$this->rank2option_id[3]]->recommended_amount = $rec_amount;
								
								$this->recommendations[$this->rank2option_id[4]]->recommended_amount = $this->total_vote_amount-$coins_out;
							}
						}
						else { // Recommendations specified in percent of user's mature balance
							$this->recommendations[$this->rank2option_id[1]]->recommended_amount = 15;
							$this->recommendations[$this->rank2option_id[2]]->recommended_amount = 25;
							$this->recommendations[$this->rank2option_id[3]]->recommended_amount = 30;
							$this->recommendations[$this->rank2option_id[4]]->recommended_amount = 30;
						}
					}
				}
			}
			else if (!$this->error_code) {
				$this->error_code = 2;
				$this->error_message = "Please run setInputs before calling setOutputs.";
			}
		}
	}
	
	$recommendations = new VotingRecommendations($game_id, $server_host, $server_access_key);
	$recommendations->getCurrentStatus();
	$recommendations->setInputs();
	$recommendations->setOutputs();
	$recommendations->outputJSON();
}
else echo "Error, please supply the correct key.";
?>