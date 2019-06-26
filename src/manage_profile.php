<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');

$action = "";
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

$pagetitle = "My Profile";
$nav_tab_selected = "profile";
$nav_subtab_selected = "";
include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if ($thisuser) {
		?>
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Manage my Profile</div>
			</div>
			<div class="panel-body">
				<ul class="nav nav-tabs">
					<li><a data-toggle="tab" href="#change_password">Change my Password</a></li>
				</ul>
				
				<div class="tab-content">
					<div id="change_password" class="tab-pane fade">
						<?php
						if ($thisuser->db_user['login_method'] == "password") { ?>
							<form method="get" action="/ajax/change_password.php" onsubmit="change_password(); return false;">
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
		<script type="text/javascript">
		$(document).ready(function() {
			setTimeout(function () {
				$('#change_password_username').val("");
				$('#change_password_existing').val("");
			}, 200);
		});
		</script>
		<?php
	}
	else echo "<p>You must be logged in to access this page.</p>\n";
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>