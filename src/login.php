<?php
$thispage = "register.php";
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser) Router::RedirectTo("/wallet/");
else {
	$pagetitle = "Log in - ".AppSettings::getParam('site_name');
	
	$message = null;
	$message_class = "warning";
	
	$old_vars_safe = [];
	
	if (!empty($_REQUEST['redirect_key'])) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
	else $redirect_url = null;
	
	if (!empty($_POST['username'])) {
		if (!empty($_POST['username']) && !empty($_POST['password'])) {
			$username = $app->normalize_username($_POST['username']);
			$password = $app->strong_strip_tags($_POST['password']);
			
			$old_vars_safe['username'] = $username;
			
			if ($password == hash("sha256", "")) $password = "";
			
			$noinfo_message = "Incorrect username or password, please try again.";
			
			$existing_user = $app->fetch_user_by_username($username);
			
			if (!$existing_user) {
				$message = $noinfo_message;
			}
			else {
				if ($existing_user['login_method'] == "password") {
					if ($existing_user['password'] == $app->normalize_password($password, $existing_user['salt'])) {
						$thisuser = new User($app, $existing_user['user_id']);
						
						$success = $thisuser->log_user_in($redirect_url, $viewer_id);
						
						if ($success) {
							if ($redirect_url) Router::RedirectTo($redirect_url['url']);
							else Router::RedirectTo('/wallet/');
						}
						else $message = "Login failed. Please make sure you have cookies enabled.";
					}
					else $message = $noinfo_message;
				}
				else {
					$app->send_login_link($existing_user, $redirect_url, $username);
					$message = User::email_login_message();
					$message_class = "success";
				}
			}
		}
		else $message = "Please include a valid username and password.";
	}
	
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Please log in to your account.</div>
			</div>
			<div class="panel-body">
				<p>
					Don't have an account yet? Please <a href="/register<?php if ($redirect_url) echo '/?redirect_key='.$redirect_url['redirect_key']; ?> ">sign up</a> first.
				</p>
				<form method="post" action="/login">
					<input type="hidden" name="redirect_key" id="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
					
					<?php if (isset($message)) { ?>
						<p class="text-<?php echo $message_class; ?>"><?php echo $message; ?></p>
					<?php } ?>
					
					<div class="form-group">
						<label for="username">Please enter your username:</label>
						<input id="username" name="username" class="form-control" placeholder="Username" required <?php if (!empty($old_vars_safe['username'])) echo ' value="'.$old_vars_safe['username'].'" '; ?> onChange="thisPageManager.check_username();" onKeyUp="thisLoginManager.usernameChanged = true;" />
						<?php
						if (!empty($error_messages['username'])) {
							echo '<p class="text-warning">'.$error_messages['username'].'</p>';
						}
						?>
					</div>
					
					<div id="password_logins">
						<div class="form-group">
							<label for="password">Please enter your password:</label>
							<input name="password" type="password" class="form-control" required placeholder="Password" />
						</div>
						
						<button class="btn btn-success btn-sm">Log in</button>
					</div>
				</form>
				<div id="email_logins" style="display: none;">
					<button class="btn btn-sm btn-success" onClick="thisLoginManager.login_by_email();">Log in by email</button>
					<p class="text-success" id="send_link_message" style="display: none; margin-top: 15px;"></p>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
	var thisLoginManager;
	window.onload = function() {
		thisLoginManager = new LoginManager();
		thisLoginManager.regularly_check_username();
	};
	</script>
	<?php
	include(AppSettings::srcPath()."/includes/html_stop.php");
}
