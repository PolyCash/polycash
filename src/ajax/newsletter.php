<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$email = $app->normalize_username($_REQUEST['email']);
$existing_subscriber = $app->run_query("SELECT * FROM newsletter_subscribers WHERE email_address=:email_address;", [
	'email_address' => $email
])->fetch();

if ($existing_subscriber) {
	echo $app->output_message(2, "That email address has already been subscribed.", false);
}
else {
	$app->run_insert_query("newsletter_subscribers", [
		'email_address' => $email,
		'time_created' => time()
	]);
	
	echo $app->output_message(1, "Thanks for subscribing!", false);
}
?>
