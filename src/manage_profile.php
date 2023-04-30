<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$pagetitle = "My Profile";
$nav_tab_selected = "profile";
$nav_subtab_selected = "";
include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if ($thisuser) {
		$page_tab_selected = "account_settings";
		
		$message = null;
		$message_class = null;
		
		if (!empty($_REQUEST['action']) && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
			if ($_REQUEST['action'] == "save_settings") {
				$backups_enabled = (int) $_REQUEST['backups_enabled'];
				$app->run_query("UPDATE users SET notification_email=:notification_email, backups_enabled=:backups_enabled, unsubscribed=:unsubscribed WHERE user_id=:user_id;", [
					'notification_email' => $_REQUEST['notification_email'],
					'backups_enabled' => $backups_enabled,
					'unsubscribed' => $backups_enabled ? 0 : $thisuser->db_user['unsubscribed'],
					'user_id' => $thisuser->db_user['user_id'],
				]);
				$thisuser->db_user['notification_email'] = $_REQUEST['notification_email'];
				$thisuser->db_user['backups_enabled'] = $backups_enabled;
				
				$message = "Your account settings were successfully updated.";
				$message_class = "success";
			}
		}
		
		if (!empty($message)) echo '<div style="margin-top: 15px;">'.$app->render_error_message($message, $message_class).'</div>';
		?>
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Manage my Profile</div>
			</div>
			<div class="panel-body">
				<ul class="nav nav-tabs">
					<li<?php if ($page_tab_selected == "account_settings") echo ' class="active"' ?>><a data-toggle="tab" href="#account_settings">Account Settings</a></li>
					<li<?php if ($page_tab_selected == "change_password") echo ' class="active"' ?>><a data-toggle="tab" href="#change_password">Change my Password</a></li>
				</ul>
				
				<div class="tab-content">
					<div id="account_settings" class="tab-pane <?php if ($page_tab_selected == "account_settings") echo "active"; else echo "fade"; ?>" style="padding-top: 15px;">
						<form action="/profile/" method="post">
							<input type="hidden" name="action" value="save_settings" />
							<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
							
							<div class="form-group">
								<label for="backups_enabled">Would you like to backup addresses for all of your accounts?</label>
								<select class="form-control" name="backups_enabled">
									<option value="0">Disable backups</option>
									<option value="1" <?php if ($thisuser->db_user['backups_enabled'] == 1) echo 'selected="selected"'; ?>>Enable backups</option>
								</select>
							</div>
							
							<div class="form-group">
								<label for="notification_email">What email address should address backups and other notifications be sent to?</label>
								<input class="form-control" type="text" name="notification_email" id="notification_email" placeholder="Enter your email address" value="<?php echo $thisuser->db_user['notification_email']; ?>" />
							</div>
							
							<button class="btn btn-primary">Save Settings</button>
						</form>
					</div>
					<div id="change_password" class="tab-pane <?php if ($page_tab_selected == "change_password") echo "active"; else echo "fade"; ?>" style="padding-top: 15px;">
						<?php
						if ($thisuser->db_user['login_method'] == "password") { ?>
							<form method="get" action="/ajax/change_password.php" onsubmit="thisPageManager.change_password(); return false;">
								<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
								
								<div class="form-group">
									<label for="existing_password">Please enter your username:</label>
									<input class="form-control" autocomplete="off" name="change_password_username" id="change_password_username" />
								</div>
								<div class="form-group">
									<label for="existing_password">Please enter your current password:</label>
									<input class="form-control" type="password" autocomplete="off" name="change_password_existing" id="change_password_existing" />
								</div>
								<div class="form-group">
									<label for="new_password">Please enter a new password:</label>
									<input class="form-control" type="password" autocomplete="off" name="change_password_new" id="change_password_new" />
								</div>
								<button class="btn btn-primary" id="change_password_btn">Change my Password</button>
							</form>
							<?php
						}
						else echo "<p>You don't have a password. You're set up to log in with your email address.</p>";
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	else echo "<p>You must be logged in to access this page.</p>\n";
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>