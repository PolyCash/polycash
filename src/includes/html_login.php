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
		<p>Have you already signed up?</p>
		<p>
			<button class="btn btn-primary" onclick="thisPageManager.existing_account=1; thisPageManager.toggle_to_panel('login');">Yes, I already have an account</button>
			<button class="btn btn-success" onclick="thisPageManager.existing_account=0; thisPageManager.toggle_to_panel('login');">No, I need to create an account</button>
			<a href="/redeem/<?php if ($redirect_url) echo "?redirect_key=".$redirect_url['redirect_key']; ?>" class="btn btn-danger">Log in with a gift card</a>
		</p>
	</div>
</div>

<div class="panel panel-default" style="display: none;" id="login_panel">
	<div class="panel-heading">
		<div class="panel-title">Please enter your username or email address</div>
	</div>
	<div class="panel-body">
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
						<button id="check_username_btn" class="btn btn-success">Continue</button>
						&nbsp;&nbsp; Or &nbsp;&nbsp; <a href="" onclick="thisPageManager.toggle_to_panel('noemail'); return false;">Generate a username and password for me</a>
					</p>
					<p>
						<div id="login_message" class="greentext" style="display: none;"></div>
					</p>
				</div>
			</div>
		</form>
	</div>
</div>

<div class="panel panel-default" style="display: none;" id="generate_panel">
	<div class="panel-heading">
		<div class="panel-title">Please generate a username/password pair.</div>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-sm-6">
				<p>
					<button class="btn btn-danger" onclick="thisPageManager.generate_credentials();">Generate a username &amp; password</button>
				</p>
				<p>Or <a href="" onclick="thisPageManager.toggle_to_panel('login'); return false;">enter my username</a></p>
				<p>Or <a href="" onclick="thisPageManager.toggle_to_panel('password'); return false;">enter my password</a></p>
			</div>
			<div class="col-sm-6">
				<div id="generate_display"></div>
			</div>
		</div>
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
			<p><button id="login_btn" class="btn btn-success">Log In</button></p>
			<p>Or <a href="" onclick="thisPageManager.toggle_to_panel('generate'); return false;">generate a new password</a></p>
			<p>Or <a href="" onclick="thisPageManager.toggle_to_panel('login'); return false;">enter my username</a></p>
		</form>
	</div>
</div>