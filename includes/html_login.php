<?php
if (!empty($redirect_key)) {}
else if (!empty($_REQUEST['redirect_key'])) $redirect_key = strip_tags($_REQUEST['redirect_key']);
else $redirect_key = FALSE;

if (empty($redirect_key)) {
	$redir_game = $app->fetch_game_from_url();
	if ($redir_game) {
		$redirect_url = $app->get_redirect_url("/wallet/".$redir_game['url_identifier']."/");
		$redirect_key = $redirect_url['redirect_key'];
	}
}
?>
<script type="text/javascript">
function check_alias() {
	var alias = $('#alias').val();
	$('#check_alias_btn').html("Loading...");
	$.get("/ajax/check_alias.php?alias="+encodeURIComponent(alias), function(result) {
		$('#check_alias_btn').html("Continue");
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) {
			$('#alias_form').hide();
			$('#registration_options').show();
			$('#registration_options_message').html(result_obj['message']);
			login_method_changed();
		}
		else if (result_obj['status_code'] == 5) {
			$('#alias_form').hide();
			$('#login_password').show();
			
			<?php if (empty($GLOBALS['login_by_email_enabled'])) { ?>
			$('#login_method').val("password");
			login_method_changed();
			$('#login_password_password').focus();
			<?php } ?>
		}
		else alert(result_obj['message']);
	});
}
function register() {
	if ($('#registration_options_password').val() != "") $('#registration_options_password').val(Sha256.hash($('#registration_options_password').val()));
	var alias = $('#alias').val();
	var password = $('#registration_options_password').val();
	var email = $('#registration_options_email').val();
	$('#register_btn').val("Loading...");
	
	$.get("/ajax/register.php?alias="+encodeURIComponent(alias)+"&password="+encodeURIComponent(password)+"&email="+encodeURIComponent(email)+"&redirect_key="+$('#redirect_key').val()+"&invite_key="+$('#invite_key').val(), function(result) {
		$('#register_btn').val("Sign Up");
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 2) {
			window.location = '/wallet/';
		}
		else if (result_obj['status_code'] == 1) {
			window.location = result_obj['message'];
		}
		else alert(result_obj['message']);
	});
}
function login_method_changed() {
	var login_method = $('#login_method').val();
	$('#registration_options_step2').show();
	if (login_method == "email") {
		$('#registration_options_password').hide();
		$('#registration_options_email').attr('placeholder', 'Enter your email address');
		$('#registration_options_email').focus();
		$('#registration_options_email').attr('required', 'required');
		$('#registration_options_password').attr('required', false);
	}
	else {
		$('#registration_options_password').show();
		$('#registration_options_email').attr('placeholder', 'Enter your email address (optional)');
		$('#registration_options_email').attr('required', false);
		$('#registration_options_password').attr('required', 'required');
		$('#registration_options_password').focus();
	}
}
function login() {
	if ($('#login_password_password').val() != "") $('#login_password_password').val(Sha256.hash($('#login_password_password').val()));
	var alias = $('#alias').val();
	var password = $('#login_password_password').val();
	$('#login_btn').val("Loading...");
	$.get("/ajax/log_in.php?alias="+encodeURIComponent(alias)+"&password="+encodeURIComponent(password)+"&redirect_key="+$('#redirect_key').val(), function(result) {
		$('#login_btn').val("Log In");
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) {
			window.location = result_obj['message'];
		}
		else {
			alert(result_obj['message']);
			$('#login_password_password').val("");
		}
	});
}
</script>

<input type="hidden" id="redirect_key" value="<?php if ($redirect_key) echo $redirect_key; ?>" />
<input type="hidden" name="invite_key" value="<?php if (!empty($_REQUEST['invite_key'])) echo $app->strong_strip_tags($app->make_alphanumeric($_REQUEST['invite_key'], "")); ?>" />

<div class="panel panel-default" style="margin-top: 15px;">
	<div class="panel-heading">
		<div class="panel-title">To continue, please register for a user account.</div>
	</div>
	<div class="panel-body">
		<h3>Log in by username</h3>
		<form id="alias_form" action="/" method="get" onsubmit="check_alias(); return false;">
			<div class="row">
				<div class="col-md-8">
					Please sign up or log in by entering a username. Your username will be shared publicly.
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<input id="alias" class="form-control" placeholder="Enter your username / alias." />
					<script type="text/javascript">
					$('#alias').focus();
					</script>
				</div>
			</div>
			<div class="row" style="padding-top: 10px;">
				<div class="col-md-8">
					<button id="check_alias_btn" class="btn btn-success">Continue</button>
				</div>
			</div>
		</form>
		<div style="display: none;" id="registration_options">
			<form id="registration_form" action="/" method="get" onsubmit="register(); return false;">
				<div class="row">
					<div id="registration_options_step1" class="col-md-8">
						<div id="registration_options_message" style="margin-bottom: 10px;" class="greentext"></div>
					</div>
					<div id="registration_options_step1" class="col-md-8"<?php if (empty($GLOBALS['login_by_email_enabled'])) { ?> style="display: none;"<?php } ?>>
						How would you like to log in?<br/>
						<select style="margin-top: 10px;" id="login_method" class="form-control" onchange="login_method_changed();">
							<option value="">-- Please Select --</option>
							<option value="password">I'll create a secure password</option>
							<option value="email">Email me a link whenever I want to sign in</option>
						</select>
					</div>
					<div id="registration_options_step2"<?php if (!empty($GLOBALS['login_by_email_enabled'])) { ?> style="display: none;"<?php } ?>>
						<div class="col-md-8">
							<input id="registration_options_email" type="email" class="form-control" placeholder="Enter your email address" />
						</div>
						<div class="col-md-8">
							<input id="registration_options_password" type="password" class="form-control" placeholder="Enter your password" />
						</div>
						<div class="col-md-8">
							<input type="submit" class="btn btn-success" value="Sign Up" id="register_btn" />
						</div>
					</div>
				</div>
			</form>
		</div>
		<div style="display: none;" id="login_password">
			<form id="login_form" action="/" method="get" onsubmit="login(); return false;">
				<div class="row">
					<div class="col-md-8">
						Please enter your password:<br/>
						<input id="login_password_password" style="margin-top: 10px;" type="password" required="required" class="form-control" />
						<input id="login_btn" type="submit" style="margin-top: 10px;" class="btn btn-success" value="Log In" />
					</div>
				</div>
			</form>
		</div>
		
		<h3>Log in via card</h3>
		<p>If you have previously used this site, but do not have a user account, you may be able to log in via your scratch off card. To log in via card, please follow <a href="/redeem/<?php if ($redirect_key) echo "?redirect_key=".$redirect_key; ?>">this link</a>.</p>
	</div>
</div>
