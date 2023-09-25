<?php
if (!empty($_REQUEST['redirect_key']) && empty($redirect_url)) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
?>
<input type="hidden" name="invite_key" value="<?php if (!empty($_REQUEST['invite_key'])) echo $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['invite_key'], "")); ?>" />

<div class="panel panel-default" style="margin-top: 15px;">
	<div class="panel-heading">
		<div class="panel-title">To continue, please sign up or log in.</div>
	</div>
	<div class="panel-body">
		<?php
		if (empty($app->get_site_constant('admin_user_id'))) {
			?>
			<p>
				<font class="text-success">
					Thanks for installing PolyCash.<br/>
					The user account you create now will have special privileges that allow you to install blockchains and games.<br/>
					Be sure to remember or write down your username and password.<br/>
					If you're installing PolyCash on a public server, please use a secure username & password.
				</font>
			</p>
			<?php
		}
		?>
		<form action="/register" method="post">
			<input type="hidden" name="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
			
			<p>
				Please register for a user account by filling out the form below.<br/>
				If you already have an account you can <a href="/login<?php if ($redirect_url) echo '/?redirect_key='.$redirect_url['redirect_key']; ?> ">log in</a>.
			</p>
			
			<?php if (isset($general_error)) { ?>
				<p class="text-warning"><?php echo $general_error; ?></p>
			<?php } ?>
			
			<div class="form-group">
				<label for="username">Please enter a username or email address for your account:</label>
				<input id="username" name="username" class="form-control" placeholder="Username" required <?php if (!empty($old_vars_safe['username'])) echo ' value="'.$old_vars_safe['username'].'" '; ?>/>
				<?php
				if (!empty($error_messages['username'])) {
					echo '<p class="text-warning">'.$error_messages['username'].'</p>';
				}
				?>
			</div>
			<div class="form-group">
				<label for="first_name">What is your first name?</label>
				<input name="first_name" class="form-control" placeholder="First name" autocomplete="off" <?php if (!empty($old_vars_safe['first_name'])) echo ' value="'.$old_vars_safe['first_name'].'" '; ?>/>
				<?php
				if (!empty($error_messages['first_name'])) {
					echo '<p class="text-warning">'.$error_messages['first_name'].'</p>';
				}
				?>
			</div>
			<div class="form-group">
				<label for="last_name">What is your last name?</label>
				<input name="last_name" class="form-control" placeholder="Last name" autocomplete="off" <?php if (!empty($old_vars_safe['last_name'])) echo ' value="'.$old_vars_safe['last_name'].'" '; ?>/>
				<?php
				if (!empty($error_messages['last_name'])) {
					echo '<p class="text-warning">'.$error_messages['last_name'].'</p>';
				}
				?>
			</div>
			<div class="form-group">
				<label for="phone_number">What is your phone number? (Optional)</label>
				<input name="phone_number" class="form-control" placeholder="+1" autocomplete="off" <?php if (!empty($old_vars_safe['phone_number'])) echo ' value="'.$old_vars_safe['phone_number'].'" '; ?>/>
				<?php
				if (!empty($error_messages['phone_number'])) {
					echo '<p class="text-warning">'.$error_messages['phone_number'].'</p>';
				}
				?>
			</div>
			<div class="form-group">
				<label for="password">Please set a secure password:</label>
				<input name="password" type="password" class="form-control" placeholder="Password" />
				<?php
				if (!empty($error_messages['password'])) {
					echo '<p class="text-warning">'.$error_messages['password'].'</p>';
				}
				?>
			</div>
			
			<p>
				<button id="check_username_btn" class="btn btn-success btn-sm">Register</button>
			</p>
		</form>
	</div>
</div>
