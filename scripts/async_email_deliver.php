<?php
$host_not_required = TRUE;
require_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['delivery_id'])) $_REQUEST['delivery_id'] = $cmd_vars['delivery_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if ($_REQUEST['action'] == "send_all") {
		$keeplooping = true;
	}
	else {
		$keeplooping = false;
	}
	do {
		if ($_REQUEST['action'] == "send_all") {	
			$q = "SELECT * FROM async_email_deliveries WHERE time_delivered=0 ORDER BY delivery_id ASC LIMIT 1;";
			$r = $app->run_query($q);
		}
		else {
			$delivery_id = (int) $_REQUEST['delivery_id'];
			
			$q = "SELECT * FROM async_email_deliveries WHERE delivery_id='".$delivery_id."' AND time_delivered=0;";
			$r = $app->run_query($q);
		}
		
		if ($r->rowCount() == 1) {
			$delivery = $r->fetch();
			
			$url = 'https://api.sendgrid.com/';
			
			$params = array(
				'api_user'	=> $GLOBALS['sendgrid_user'],
				'api_key'	=> $GLOBALS['sendgrid_pass'],
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
			
			if ($cc_list != "") {
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
			
			$q = "UPDATE async_email_deliveries SET time_delivered='".time()."', successful=$successful, sendgrid_response=".$app->quote_escape($response)." WHERE delivery_id='".$delivery['delivery_id']."';";
			$r = $app->run_query($q);
			
			echo "response from Sendgrid was: ".$response;
		}
		else {
			echo "Not delivering the email, maybe it was already delivered.\n";
			$keeplooping = false;
		}
	}
	while ($keeplooping);
}
?>
