<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$pagetitle = "Unsubscribe";

if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "unsubscribe") {
	$email = $_REQUEST['unsubscribe_email'];
	
	$app->run_query("UPDATE newsletter_subscribers SET subscribed=0 WHERE email_address=:email_address;", ['email_address' => $email]);
	
	$error_message = "You have been unsubscribed";
}
else $error_message = false;

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid" style="padding-top: 10px;">
	<?php
	if (!empty($error_message)) echo $app->render_error_message($error_message, "error");
	?>
	<form method="post" action="/unsubscribe/">
		<input type="hidden" name="action" value="unsubscribe" />
		<div class="form-group">
			<label for="unsubscribe_email">To unsubscribe from our newsletter, please enter your email address:</label>
			<input class="form-control" type="text" id="unsubscribe_email" name="unsubscribe_email" />
		</div>
		<button class="btn btn-success">Unsubscribe Me</button>
	</form>
</div>
<?php
include(AppSettings::srcPath()."/includes/html_stop.php");
?>