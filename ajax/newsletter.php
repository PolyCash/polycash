<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$email = $app->normalize_username($_REQUEST['email']);
$existing_subscriber = $app->run_query("SELECT * FROM newsletter_subscribers WHERE email_address=".$app->quote_escape($email).";")->fetch();

if ($existing_subscriber) {
	echo $app->output_message(2, "That email address has already been subscribed.", false);
}
else {
	$app->run_query("INSERT INTO newsletter_subscribers SET email_address=".$app->quote_escape($email).", time_created=".time());
	
	echo $app->output_message(1, "Thanks for subscribing!", false);
}
?>
