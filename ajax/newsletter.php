<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$email = $_REQUEST['email'];
$match_r = $app->run_query("SELECT * FROM newsletter_subscribers WHERE email_address=".$app->quote_escape($email).";");
if ($match_r->rowCount() > 0) {
	echo $app->output_message(2, "That email address has already been subscribed.", false);
}
else {
	$add_r = $app->run_query("INSERT INTO newsletter_subscribers SET email_address=".$app->quote_escape($email).", time_created=".time());
	echo $app->output_message(1, "Thanks for subscribing!", false);
}
?>
