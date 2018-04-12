<?php
if (!empty($redirect_key)) {}
else if (!empty($_REQUEST['redirect_key'])) $redirect_key = strip_tags($_REQUEST['redirect_key']);
else $redirect_key = FALSE;

if (!empty($redirect_key)) $redirect_url = $app->check_fetch_redirect_url($redirect_key);
else $redirect_url = false;
?>
<script type="text/javascript">
var existing_account = false;

function generate_credentials() {
	$.get("/ajax/check_username.php?action=generate", function(result) {
		var result_obj = JSON.parse(result);
		$('#generate_display').html(result_obj['message']);
		$('#login_password').val($('#generate_password').val());
		$('#username').val($('#generate_username').val());
	});
}
function check_username() {
	var username = $('#username').val();
	$('#check_username_btn').html("Loading...");
	
	$.get("/ajax/check_username.php?username="+encodeURIComponent(username), function(result) {
		$('#check_username_btn').html("Continue");
		var result_obj = JSON.parse(result);
		
		$('#login_message').html(result_obj['message']);
		$('#login_message').show();
		
		if (result_obj['status_code'] == 3 || result_obj['status_code'] == 4) {
			toggle_to_panel('password');
			
			if (result_obj['status_code'] == 4) {
				$('#login_btn').html("Sign Up");
			}
			else $('#login_btn').html("Log In");
		}
		else if (result_obj['status_code'] == 1 || result_obj['status_code'] == 2) {
			login();
		}
	});
}
function login() {
	if ($('#login_password').val() != "") $('#login_password').val(Sha256.hash($('#login_password').val()));
	var username = $('#username').val();
	var password = $('#login_password').val();
	$('#login_btn').val("Loading...");
	$.get("/ajax/log_in.php?username="+encodeURIComponent(username)+"&password="+encodeURIComponent(password)+"&redirect_key="+$('#redirect_key').val(), function(result) {
		$('#login_btn').val("Log In");
		var result_obj = JSON.parse(result);
		
		if (result_obj['status_code'] == 1) {
			window.location = result_obj['message'];
		}
		else if (result_obj['status_code'] == 3) {
			alert(result_obj['message']);
		}
		else {
			window.location = window.location;
		}
	});
}
var selected_panel = false;

function toggle_to_panel(which_panel) {
	if (selected_panel) $('#'+selected_panel+'_panel').hide();
	
	if (which_panel == 'noemail') {
		which_panel = 'generate';
		
		$('#login_panel').hide();
	}
	
	selected_panel = which_panel;
	
	$('#'+selected_panel+'_panel').show('fast');
	
	if (which_panel == "login") setTimeout("$('#username').focus();", 500);
	else if (selected_panel == 'password') setTimeout("$('#login_password').focus();", 500);
}
</script>

<input type="hidden" id="redirect_key" value="<?php if ($redirect_url) echo $redirect_url['redirect_key']; ?>" />
<input type="hidden" name="invite_key" value="<?php if (!empty($_REQUEST['invite_key'])) echo $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['invite_key'], "")); ?>" />

<div class="panel panel-default" style="margin-top: 15px;">
	<div class="panel-heading">
		<div class="panel-title">To continue, please register for a user account.</div>
	</div>
	<div class="panel-body">
		<p>Have you already signed up?</p>
		<p>
			<button class="btn btn-primary" onclick="existing_account=1; toggle_to_panel('login');">Yes, I already have an account</button>
			<button class="btn btn-success" onclick="existing_account=0; toggle_to_panel('login');">No, I need to create an account</button>
			<a href="/redeem/<?php if ($redirect_url) echo "?redirect_key=".$redirect_url['redirect_key']; ?>" class="btn btn-danger">Log in with a gift card</a>
		</p>
	</div>
</div>

<div class="panel panel-default" style="display: none;" id="login_panel">
	<div class="panel-heading">
		<div class="panel-title">Please enter your username or email address</div>
	</div>
	<div class="panel-body">
		<form action="/" method="get" onsubmit="check_username(); return false;">
			<div class="row">
				<div class="col-md-8">
					Please enter your username or email address. This will be kept private.
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<input id="username" class="form-control" placeholder="Enter your email address" />
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<p>
						<button id="check_alias_btn" class="btn btn-success">Continue</button>
						&nbsp;&nbsp; Or &nbsp;&nbsp; <a href="" onclick="toggle_to_panel('noemail'); return false;">I prefer not to share my email address</a>
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
					<button class="btn btn-danger" onclick="generate_credentials();">Generate a username &amp; password</button>
				</p>
				<p>Or <a href="" onclick="toggle_to_panel('login'); return false;">enter my username</a></p>
				<p>Or <a href="" onclick="toggle_to_panel('password'); return false;">enter my password</a></p>
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
		<form action="/" method="get" onsubmit="login(); return false;">
			<p>Please enter your password.</p>
			<p><input id="login_password" type="password" required="required" class="form-control" /></p>
			<p><button id="login_btn" class="btn btn-success">Log In</button></p>
			<p>Or <a href="" onclick="toggle_to_panel('generate'); return false;">generate a new password</a></p>
			<p>Or <a href="" onclick="toggle_to_panel('login'); return false;">enter my username</a></p>
		</form>
	</div>
</div>