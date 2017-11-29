<?php
class MobilePayment {
	public function __construct(&$app, $payment_id) {
		$this->app = $app;
		$this->payment_id = $payment_id;
		$this->db_payment = false;
		
		$this->card_group_id = false;
		$this->currency_id = false;
		$this->amount = false;
		$this->phone_number = false;
		$this->first_name = false;
		$this->last_name = false;
		
		$this->load();
	}
	
	public function load() {
		if ($this->payment_id) {
			$q = "SELECT * FROM mobile_payments WHERE payment_id='".$this->payment_id."';";
			$r = $this->app->run_query($q);
			$this->db_payment = $r->fetch();
		}
	}
	
	public function set_fields($currency_id, $amount, $phone_number, $first_name, $last_name) {
		$this->currency_id = $currency_id;
		$this->amount = $amount;
		$this->phone_number = $phone_number;
		$this->first_name = $first_name;
		$this->last_name = $last_name;
	}
	
	public function create() {
		$q = "INSERT INTO mobile_payments SET currency_id='".$this->currency_id."', amount=".$this->app->quote_escape($this->amount).", payment_status='pending', time_created='".time()."', phone_number=".$this->app->quote_escape($this->phone_number).", first_name=".$this->app->quote_escape($this->first_name).", last_name=".$this->app->quote_escape($this->last_name).", payment_key='".$this->app->random_string(16)."';";
		$r = $this->app->run_query($q);
		
		$this->payment_id = $this->app->last_insert_id();
		$this->load();
	}
}
?>