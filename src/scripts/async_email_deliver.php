<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['delivery_id', 'action'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	if (!isset($_REQUEST['action'])) $_REQUEST['action'] = "";
	if (!isset($_REQUEST['delivery_id'])) $_REQUEST['delivery_id'] = "";
	
	if ($_REQUEST['action'] == "send_all") $keeplooping = true;
	else $keeplooping = false;
	
	do {
		if ($_REQUEST['action'] == "send_all") {
			$deliveries = $app->run_query("SELECT * FROM async_email_deliveries WHERE time_delivered=0 ORDER BY delivery_id ASC LIMIT 1;")->fetchAll();
		}
		else {
			$delivery_id = (int) $_REQUEST['delivery_id'];
			
			$deliveries = $app->run_query("SELECT * FROM async_email_deliveries WHERE delivery_id=:delivery_id AND time_delivered=0;", [
				'delivery_id' => $delivery_id
			])->fetchAll();
		}
		
		if (count($deliveries) == 1) {
			$delivery = $deliveries[0];
			
			$sendgrid_api_url = 'https://api.sendgrid.com/v3/mail/send';
			
			$to_list = explode(",", $delivery['to_email']);
			$to_list_formatted = [];
			foreach ($to_list as $to_email) {
				array_push($to_list_formatted, [
					'email' => $to_email
				]);
			}
			
			$sendgrid_personalizations = [
				'to' => $to_list_formatted,
				'subject'	=> $delivery['subject']
			];
			
			if (!empty($delivery['cc'])) {
				$cc_list = explode(",", $delivery['cc']);
				$sendgrid_personalizations['cc'] = [];
				
				foreach ($cc_list as $cc_email) {
					array_push($sendgrid_personalizations['cc'], [
						'email' => $cc_email
					]);
				}
			}
			
			if (!empty($delivery['bcc'])) {
				$bcc_list = explode(",", $delivery['bcc']);
				$sendgrid_personalizations['bcc'] = [];
				
				foreach ($bcc_list as $bcc_email) {
					array_push($sendgrid_personalizations['bcc'], [
						'email' => $bcc_email
					]);
				}
			}
			
			$sendgrid_payload = [
				'personalizations' => [$sendgrid_personalizations],
				'from' => [
					'email' => $delivery['from_email'],
					'name' => $delivery['from_name']
				],
				'content' => [[
					'type' => 'text/html',
					'value' => $delivery['message']
				]]
			];
			
			$curl_handle = curl_init($sendgrid_api_url);
			
			curl_setopt_array($curl_handle, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode($sendgrid_payload),
				CURLOPT_HTTPHEADER => [
					"content-type: application/json",
					"Authorization: Bearer ".AppSettings::getParam('sendgrid_api_key'),
				],
				CURLOPT_RETURNTRANSFER => true
			]);
			
			$sendgrid_response_raw = curl_exec($curl_handle);
			$sendgrid_response_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
			curl_close($curl_handle);
			
			if ($sendgrid_response_code == 202) $successful = 1;
			else $successful = 0;
			
			$sendgrid_response = json_decode($sendgrid_response_raw);
			$save_sendgrid_response = json_encode([
				'http_code' => $sendgrid_response_code,
				'response' => $sendgrid_response
			], JSON_PRETTY_PRINT);
			
			$app->run_query("UPDATE async_email_deliveries SET time_delivered=:time_delivered, successful=:successful, sendgrid_response=:sendgrid_response WHERE delivery_id=:delivery_id;", [
				'time_delivered' => time(),
				'successful' => $successful,
				'sendgrid_response' => $save_sendgrid_response,
				'delivery_id' => $delivery['delivery_id']
			]);
			
			echo $save_sendgrid_response."\n";
		}
		else {
			echo "Not delivering the email, maybe it was already delivered.\n";
			$keeplooping = false;
		}
	}
	while ($keeplooping);
}
else echo "You need admin privileges to run this script.\n";
?>
