<?php
if (!empty($_REQUEST['redirect_key']) && empty($redirect_url)) $redirect_url = $app->get_redirect_by_key($_REQUEST['redirect_key']);
?>
<input type="hidden" id="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
<input type="hidden" name="invite_key" value="<?php if (!empty($_REQUEST['invite_key'])) echo $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['invite_key'], "")); ?>" />

<div class="panel panel-default" style="margin-top: 15px;">
	<div class="panel-heading">
		<div class="panel-title">To continue, please register for a user account.</div>
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
		<form action="/" method="get" onsubmit="thisPageManager.check_username(); return false;">
			<div class="row">
				<div class="col-md-8">
					Please enter your username or email address. This will be kept private.<br/>
					If you enter an email address, we'll email you a link each time to log in. If you enter a username, you'll log in with a password.
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<input id="username" class="form-control" placeholder="Enter your username or email address" />
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<p>
						<button id="check_username_btn" class="btn btn-success btn-sm">Continue</button>
					</p>
					<p>
						<div id="login_message" class="greentext" style="display: none;"></div>
					</p>
				</div>
			</div>
		</form>
	</div>
</div>

<div class="panel panel-default" style="display: none;" id="password_panel">
	<div class="panel-heading">
		<div class="panel-title">Please enter your password</div>
	</div>
	<div class="panel-body">
		<form action="/" method="get" onsubmit="thisPageManager.login(); return false;">
			<p>Please enter your password.</p>
			<p><input id="login_password" type="password" required="required" class="form-control" /></p>
			<p><button id="login_btn" class="btn btn-success btn-sm">Log In</button></p>
		</form>
	</div>
</div>
