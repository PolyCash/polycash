<?php
require_once(dirname(__FILE__)."/classes/AppSettings.php");
/*
To use custom logic, install this PHP script on a webserver
Then enter it's URL into your user account under the Strategy tab.
To keep your recommendations private, it's recommended that you set a secure $client_access_key below.
Then your API url will be something like:
http://mywebserver.com/APIClient.php?key=AppSettings::getParam('operator_key')
*/

// Log into your web wallet -> Settings to find your server access key, then enter it below
$server_access_key = "";
$server_host = "https://poly.cash";
$client_access_key = AppSettings::getParam('operator_key');

$event_id = intval($_REQUEST['event_id']);
if (!$event_id) die("Please provide a valid event_id");

if ($_REQUEST['key'] == $client_access_key) {
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
		public $rank2option_id;
		public $recommendation_unit;
		public $server_host;
		public $server_access_key;
		public $input_utxo_ids;
		
		public function __construct($event_id, $server_host, $server_access_key) {
			$this->event_id = $event_id;
			$this->server_host = $server_host;
			$this->server_access_key = $server_access_key;
			
			$this->server_result = FALSE;
			$this->event = FALSE;
			$this->event_votes = FALSE;
			$this->user_info = FALSE;
			
			$this->error_code = FALSE;
			$this->error_message = "";
			$this->total_vote_amount = FALSE;
			$this->rank2option_id = FALSE;
			$this->recommendation_unit = FALSE;
			$this->input_utxo_ids = [];
			
			$this->recommendations = [];
		}
		
		// Fetch information about the current status of the game
		public function getCurrentStatus() {
			$fetch_url = $this->server_host."/api/".$this->event_id."/current_events/";
			if ($this->server_access_key && $this->server_access_key != "") {
				$fetch_url .= "?api_access_code=".$this->server_access_key;
			}
			
			$fetch_result = file_get_contents($fetch_url);
			
			if ($fetch_result) {
				// Store the result of the API call
				$this->server_result = json_decode($fetch_result);
				$this->event = $this->server_result->event;
				$this->event_votes = $this->server_result->event_votes;
				$this->user_info = $this->server_result->user_info;
				
				if (!$this->event) {
					$this->error_code = 2;
					$this->error_message = "Error: the server didn't return a valid event.";
					$this->outputJSON();
					die();
				}
				else {
					// Define the rank2option_id map so that we can reference nations by rank in setOutputs()
					foreach ($this->event_votes as $event_votes) {
						$this->rank2option_id[$event_votes->rank] = $event_votes->option_id;
						$this->recommendations[$event_votes->option_id] = new VotingRecommendation($event_votes->option_id, $event_votes->name);
					}
					$this->setname2option_id();
				}
			}
		}
		
		// Only recommendations with amounts greater than 0 need to be sent back to the server
		public function nonzeroRecommendations() {
			$nonzeroRecommendations = [];
			
			foreach ($this->recommendations as $recommendation) {
				if ($recommendation->recommended_amount > 0) {
					$nonzeroRecommendations[count($nonzeroRecommendations)] = $recommendation;
				}
			}
			
			return $nonzeroRecommendations;
		}
		
		// Output our response to JSON
		public function outputJSON() {
			$output_obj = [];
			
			if ($this->error_code) {
				$output_obj['errors'] = array(0 => $this->error_message);
				$output_obj['recommendations'] = [];
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
				$input_utxo_ids = [];
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
					if ($this->event->block_within_round >= 2) {
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
	
	$recommendations = new VotingRecommendations($event_id, $server_host, $server_access_key);
	$recommendations->getCurrentStatus();
	$recommendations->setInputs();
	$recommendations->setOutputs();
	$recommendations->outputJSON();
}
else echo "Error, please supply the correct key.";
?>