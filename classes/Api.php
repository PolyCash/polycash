<?php
class block {
	public $block_id;
	public $hash;
	public $json_obj;
	
	public function __construct($json_obj, $block_id, $hash) {
		$this->json_obj = $json_obj;
		$this->block_id = $block_id;
		$this->hash = $hash;
	}
}

class transaction {
	public $hash;
	public $raw;
	public $json_obj;
	public $block_id;
	public $is_coinbase;
	public $db_id;
	public $output_sum;
	
	public function __construct($hash, $raw, $json_obj, $block_id) {
		$this->hash = $hash;
		$this->raw = $raw;
		$this->json_obj = $json_obj;
		$this->block_id = $block_id;
		$this->is_coinbase = false;
		$this->db_id = false;
		$this->output_sum = 0;
	}
}
?>