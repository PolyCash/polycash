<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$app->output_message(1, "Successfully returned status footer", [
	'renderedContent' => $app->render_view('status_footer', [
		'app' => $app,
		'thisuser' => empty($thisuser) ? null : $thisuser,
	])
]);
