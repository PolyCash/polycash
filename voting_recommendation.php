<?php
include("includes/connect.php");

if ($_REQUEST['key'] == $site_access_key) {
	class VotingRecommendation {
		public $empire_id;
		public $empire_name;
		public $recommended_amount;
		
		public function __construct($empire_id, $empire_name, $recommendation_amount) {
			$this->empire_id = $empire_id;
			$this->empire_name = $empire_name;
			$this->recommended_amount = 0;
		}
	}

	class VotingRecommendations {
		public $recommendations;
		public $error_code;
		public $error_message;
		public $total_vote_amount;
		public $name2option;
		
		public function __construct() {
			$this->error_code = FALSE;
			$this->error_message = "";
			$this->total_vote_amount = FALSE;
			$this->name2option = FALSE;
			
			$this->recommendations = array();
			$option_pos = 0;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'China'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'United States'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'India'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Japan'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Germany'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Russia'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Brazil'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Indonesia'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'France'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'United Kingdom'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Mexico'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Italy'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'South Korea'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Saudi Arabia'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Canada'); $option_pos++;
			$this->recommendations[$option_pos] = new VotingRecommendation($option_pos, 'Spain'); $option_pos++;
			
			$this->setName2Option();
		}
		
		public function setName2Option() {
			for ($empire_id=0; $empire_id<count($this->recommendations); $empire_id++) {
				$this->name2option[$this->recommendations[$empire_id]->empire_name] = $empire_id;
			}
		}
		
		public function nonzeroRecommendations() {
			$option_pos = 0;
			$nonzeroRecommendations = array();
			
			for ($option_pos=0; $option_pos<count($this->recommendations); $option_pos++) {
				if ($this->recommendations[$option_pos]->recommended_amount > 0) {
					$nonzeroRecommendations[count($nonzeroRecommendations)] = $this->recommendations[$option_pos];
				}
			}
			
			return $nonzeroRecommendations;
		}
		
		public function setVoteAmount($total_vote_amount, $request_amount_str) {
			if ((string)$total_vote_amount == $request_amount_str && $total_vote_amount > 0) {
				$this->total_vote_amount = $total_vote_amount;
			}
			else {
				$this->error_code = 1;
				$this->error_message = "Please specify a valid 'amount2vote' in the URL.";
			}
		}
		
		public function outputJSON() {
			$output_obj = array();
			
			if ($this->error_code) {
				$output_obj['errors'] = array(0 => $this->error_message);
				$output_obj['recommendations'] = array();
			}
			else {
				$output_obj['recommendations'] = $this->nonzeroRecommendations();
			}
			
			echo json_encode($output_obj);
		}
		
		public function setRecommendations() {
			if ($this->total_vote_amount) {
				$this->recommendations[$this->name2option['United States']]->recommended_amount = $this->total_vote_amount;
			}
			else if (!$this->error_code) {
				$this->error_code = 2;
				$this->error_message = "Please run setVoteAmount before calling setRecommendations.";
			}
		}
	}

	$amount2vote = floatval($_REQUEST['amount2vote']);

	$recommendations = new VotingRecommendations();
	$recommendations->setVoteAmount($amount2vote, $_REQUEST['amount2vote']);
	$recommendations->setRecommendations();
	$recommendations->outputJSON();
}
?>