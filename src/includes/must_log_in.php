<?php
if (empty($thisuser)) {
	$pagetitle = "Please log in.";
	
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	if ($redirect_url) $redirect_key = $redirect_url['redirect_key'];
	
	include(AppSettings::srcPath().'/includes/html_start.php');
	?>
	<div class="container-fluid">
		<?php
		include(AppSettings::srcPath()."/includes/html_register.php");
		?>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}
