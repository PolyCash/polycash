<?php
$thispage = "register.php";
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) Router::RedirectTo("/wallet/");
else {
	if (!empty($_POST['username'])) {
		$redirect_url = null;
		if (!empty($_POST['redirect_key']) && empty($redirect_url)) $redirect_url = $app->get_redirect_by_key($_POST['redirect_key']);
		
		$old_vars_safe = [];
		$error_messages = [];
		$registration_values = [];
		
		foreach (User::registration_fields() as $field_name => $field_info) {
			if ($field_info['required'] && empty($_POST[$field_name])) {
				$error_messages[$field_name] = "The ".$field_info['display_name']." field is required.";
			}
			else {
				$safe_value = $app->strong_strip_tags($_POST[$field_name]);
				if ((string) $safe_value != (string) $_POST[$field_name]) {
					$error_messages[$field_name] = "You supplied invalid content for ".$field_info['display_name'].".";
				}
				else {
					$value_ok = true;
					
					$old_vars_safe[$field_name] = $_POST[$field_name];
					
					if (array_key_exists('max_length', $field_info)) {
						if (strlen($_POST[$field_name]) > $field_info['max_length']) {
							$error_messages[$field_name] = ucfirst($field_info['display_name'])." must be no more than ".$field_info['max_length']." characters.";
							$value_ok = false;
						}
					}
					if (array_key_exists('min_length', $field_info)) {
						if (strlen($_POST[$field_name]) < $field_info['min_length']) {
							$error_messages[$field_name] = ucfirst($field_info['display_name'])." must be at least ".$field_info['min_length']." characters.";
							$value_ok = false;
						}
					}
					
					if ($value_ok) $registration_values[$field_name] = $_POST[$field_name];
				}
			}
		}
		
		$existing_user = $app->fetch_user_by_username($_REQUEST['username']);
		
		if ($existing_user && empty($error_messages['username'])) $error_messages['username'] = "Sorry, someone has already taken that username.";
		
		if (count($error_messages) > 0) {
			include(AppSettings::srcPath()."/includes/html_start.php");
			echo '<div class="container-fluid">';
			include(__DIR__."/includes/html_register.php");
			echo '</div>';
			include(AppSettings::srcPath()."/includes/html_stop.php");
		}
		else {
			$general_error = null;
			
			$verify_code = $app->random_string(32);
			$salt = $app->random_string(16);
			
			$thisuser = $app->create_new_user($verify_code, $salt, $_POST['username'], $_POST['password'], $registration_values);
			
			if ($thisuser->db_user['login_method'] == "email") {
				$app->send_login_link($thisuser->db_user, $redirect_url, $username);
				$success = true;
			}
			else {
				$success = $thisuser->log_user_in($redirect_url, $viewer_id);
				if (!$success) $general_error = "Login failed. Please make sure you have cookies enabled.";
			}
			
			if ($success) {
				if ($redirect_url) Router::RedirectTo($redirect_url['url']);
				else Router::RedirectTo('/wallet/');
			}
			else {
				include(AppSettings::srcPath()."/includes/html_start.php");
				echo '<div class="container-fluid">';
				include(__DIR__."/includes/html_register.php");
				echo '</div>';
				include(AppSettings::srcPath()."/includes/html_stop.php");
			}
		}
		die();
	}
	
	$pagetitle = AppSettings::getParam('site_name')." - Register";
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
		<?php
		include(__DIR__.'/includes/html_register.php');
		?>
	</div>
	<?php
	include(AppSettings::srcPath()."/includes/html_stop.php");
}
