<?php
$host_not_required = TRUE;
require_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($_REQUEST['delivery_id'] > 0) {
	$delivery_id = intval($_REQUEST['delivery_id']);
} else $delivery_id = intval($argv[1]);

$q = "SELECT * FROM async_email_deliveries WHERE delivery_id='".$delivery_id."' AND time_delivered=0;";
$r = run_query($q);

if (mysql_numrows($r) == 1) {
	$delivery = mysql_fetch_array($r);
	
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
	
	$q = "UPDATE async_email_deliveries SET time_delivered='".time()."', successful=$successful, sendgrid_response='".mysql_real_escape_string($response)."' WHERE delivery_id='".$delivery['delivery_id']."';";
	$r = run_query($q);
	
	echo "response from Sendgrid was: ".$response;
}
else {
	echo "Not delivering the email, maybe it was already delivered.\n";
}
?>
