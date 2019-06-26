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
			$deliveries = $app->run_query("SELECT * FROM async_email_deliveries WHERE time_delivered=0 ORDER BY delivery_id ASC LIMIT 1;");
		}
		else {
			$delivery_id = (int) $_REQUEST['delivery_id'];
			
			$deliveries = $app->run_query("SELECT * FROM async_email_deliveries WHERE delivery_id=:delivery_id AND time_delivered=0;", [
				'delivery_id' => $delivery_id
			]);
		}
		
		if ($deliveries->rowCount() == 1) {
			$delivery = $deliveries->fetch();
			
			$url = 'https://api.sendgrid.com/';
			
			$params = array(
				'api_user'	=> AppSettings::getParam('sendgrid_user'),
				'api_key'	=> AppSettings::getParam('sendgrid_pass'),
				'subject'	=> $delivery['subject'],
				'html'		=> $delivery['message'],
				'from'		=> $delivery['from_email'],
				'fromname'	=> $delivery['from_name'],
				'bcc'		=> $delivery['bcc']
			);
			
			$to_list = explode(",", $delivery['to_email']);
			for ($i=0; $i<count($to_list); $i++) {
				$params["to[$i]"] = $to_list[$i];
			}
			
			if ($delivery['cc'] != "") {
				$cc_list = explode(",", $delivery['cc']);
				for ($j=0; $j<count($cc_list); $j++) {
					$params["cc[$j]"] = $cc_list[$j];
				}
			}
			
			$request =  $url.'api/mail.send.json';
			
			$session = curl_init($request);
			curl_setopt ($session, CURLOPT_POST, true);
			curl_setopt ($session, CURLOPT_POSTFIELDS, $params);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($session);
			curl_close($session);
			
			$json_response = json_decode($response);
			if ($json_response->message == "success") $successful = 1;
			else $successful = 0;
			
			$app->run_query("UPDATE async_email_deliveries SET time_delivered=:time_delivered, successful=:successful, sendgrid_response=:sendgrid_response WHERE delivery_id=:delivery_id;", [
				'time_delivered' => time(),
				'successful' => $successful,
				'sendgrid_response' => $response,
				'delivery_id' => $delivery['delivery_id']
			]);
			
			echo "response from Sendgrid was: ".$response;
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
