<?php
if (!empty($redirect_id)) {}
else if (!empty($_REQUEST['redirect_id']) > 0) $redirect_id = intval($_REQUEST['redirect_id']);
else $redirect_id = FALSE;

$url_game = false;
$login_url_parts = explode("/", rtrim(ltrim($_SERVER['REQUEST_URI'], "/"), "/"));
if ($login_url_parts[0] == "wallet" && count($login_url_parts) > 1) {
	$q = "SELECT * FROM games WHERE url_identifier='".$login_url_parts[1]."';";
	$r = $app->run_query($q);
	if ($r->rowCount() == 1) {
		$url_game = $r->fetch();
		if (empty($redirect_id)) {
			$redirect_url = $app->get_redirect_url("/wallet/".$url_game['url_identifier']);
			$redirect_id = $redirect_url['redirect_url_id'];
		}
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
		else if (result_obj['status_code'] == 5) { // login by password
			$('#alias_form').hide();
			$('#login_password').show();
			
			<?php if (empty($GLOBALS['login_by_email_enabled'])) { ?>
			$('#login_method').val("password");
			login_method_changed();
			$('#login_password_password').focus();
			<?php } ?>
		}
		else alert(result_obj['message']);
		/*else if (result_obj['status_code'] == 6) { // login by email
		}*/
	});
}
function register() {
	if ($('#registration_options_password').val() != "") $('#registration_options_password').val(Sha256.hash($('#registration_options_password').val()));
	var alias = $('#alias').val();
	var password = $('#registration_options_password').val();
	var email = $('#registration_options_email').val();
	$.get("/ajax/register.php?alias="+encodeURIComponent(alias)+"&password="+encodeURIComponent(password)+"&email="+encodeURIComponent(email)+"&redirect_id="+parseInt($('#redirect_id').val()), function(result) {
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
	$.get("/ajax/log_in.php?alias="+encodeURIComponent(alias)+"&password="+encodeURIComponent(password)+"&redirect_id="+parseInt($('#redirect_id').val()), function(result) {
		var result_obj = JSON.parse(result);
		if (result_obj['status_code'] == 1) {
			window.location = result_obj['message'];
		}
		else alert(result_obj['message']);
	});
}
</script>
<br/>
<input type="hidden" id="redirect_id" value="<?php if ($redirect_id) echo $redirect_id; ?>" />
<form id="alias_form" action="/" method="get" onsubmit="check_alias(); return false;">
	<div class="row">
		<div class="col-md-8 col-md-push-2">
			<h3>You must be logged in to view this page.</h3>
			Please create an account. The alias you enter here is public and may be shown to other players.
		</div>
	</div>
	<div class="row" style="padding-top: 10px;">
		<div class="col-md-8 col-md-push-2">
			<input id="alias" class="form-control" placeholder="Please enter your username / alias." />
			<script type="text/javascript">
			$('#alias').focus();
			</script>
		</div>
	</div>
	<div class="row" style="padding-top: 10px;">
		<div class="col-md-8 col-md-push-2">
			<button id="check_alias_btn" class="btn btn-success">Continue</button>
		</div>
	</div>
</form>
<div style="display: none;" id="registration_options">
	<form id="registration_form" action="/" method="get" onsubmit="register(); return false;">
		<div class="row">
			<div id="registration_options_step1" class="col-md-8 col-md-push-2">
				<div id="registration_options_message" style="margin-bottom: 10px;" class="greentext"></div>
			</div>
			<div id="registration_options_step1" class="col-md-8 col-md-push-2"<?php if (empty($GLOBALS['login_by_email_enabled'])) { ?> style="display: none;"<?php } ?>>
				How would you like to log in?<br/>
				<select style="margin-top: 10px;" id="login_method" class="form-control" onchange="login_method_changed();">
					<option value="">-- Please Select --</option>
					<option value="password">I'll create a secure password</option>
					<option value="email">Email me a link whenever I want to sign in</option>
				</select>
			</div>
			<div id="registration_options_step2"<?php if ($GLOBALS['login_by_email_enabled']) { ?> style="display: none;"<?php } ?>>
				<div class="col-md-8 col-md-push-2">
					<input id="registration_options_email" type="email" class="form-control" placeholder="Enter your email address" />
				</div>
				<div class="col-md-8 col-md-push-2">
					<input id="registration_options_password" type="password" class="form-control" placeholder="Enter your password" />
				</div>
				<div class="col-md-8 col-md-push-2">
					<input type="submit" class="btn btn-success" value="Sign Up" />
				</div>
			</div>
		</div>
	</form>
</div>
<div style="display: none;" id="login_password">
	<form id="login_form" action="/" method="get" onsubmit="login(); return false;">
		<div class="row">
			<div class="col-md-8 col-md-push-2">
				Please enter your password:<br/>
				<input id="login_password_password" style="margin-top: 10px;" type="password" required="required" class="form-control" />
				<input type="submit" style="margin-top: 10px;" class="btn btn-success" value="Log In" />
			</div>
		</div>
	</form>
</div>
<?php
/*
<script type="text/javascript">
function autogen_password_changed() {
	if ($('#autogen_password').val() == "0") {
		$('#signup_password_disp').show();
	}
	else {
		$('#signup_password_disp').hide();
	}
}
$(document).ready(function() {
	$('#autogen_password').val(0);
	autogen_password_changed();
});
</script>
<div class="row" style="padding-top: 20px;">
	<div class="col-md-6">
		<b style="font-size: 17px; line-height: 24px;">Please log in to continue</b>
		<form onsubmit="$('#login_password').val(Sha256.hash($('#login_password').val()));" action="/wallet/<?php if ($url_game) echo $url_game['url_identifier']."/"; ?>" method="post">
			<input type="hidden" name="do" value="login" />
			<?php
			if ($redirect_id) echo '<input type="hidden" name="redirect_id" value="'.$redirect_id.'" />'."\n";
			
			if (!empty($_REQUEST['invite_key'])) echo '<input type="hidden" name="invite_key" value="'.make_alphanumeric(strip_tags($_REQUEST['invite_key'])).'" />';
			?>
			<div class="row">
				<div class="col-sm-4 form-control-static">Alias:</div>
				<div class="col-sm-6">
					<input id="login_username" class="responsive_input form-control" name="username" type="text" size="25" maxlength="40" value="<?php if (!empty($email)) echo $email; ?>" />
				</div>
			</div>
			<div class="row">
				<div class="col-sm-4 form-control-static">Password:</div>
				<div class="col-sm-6">
					<input id="login_password" class="responsive_input form-control" name="password" type="password" size="25" maxlength="25" />
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6 col-sm-push-4">
					<input type="submit" class="btn btn-primary" value="Log In" />
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6 col-sm-push-4" style="padding-top: 10px;">
					<a href="/reset_password/">I forgot my password</a>
					<br/><br/>
				</div>
			</div>
		</form>
	</div>
	<div class="col-md-6">
		<b style="font-size: 17px; line-height: 24px;">Or sign up for an account</b><br/>
		
		<form action="/wallet/<?php if ($url_game) echo $url_game['url_identifier']."/"; ?>" method="post" onsubmit="$('#signup_password').val(Sha256.hash($('#signup_password').val())); $('#signup_password2').val(Sha256.hash($('#signup_password2').val()));">
			<input type="hidden" name="do" value="signup" />
			<?php
			
			if (!empty($_REQUEST['invite_key'])) echo '<input type="hidden" name="invite_key" value="'.make_alphanumeric(strip_tags($_REQUEST['invite_key'])).'" />';
			?>
			<div class="row">
				<div class="col-sm-10">
					<select id="autogen_password" name="autogen_password" class="responsive_input form-control" onchange="autogen_password_changed();" style="margin-bottom: 5px;">
						<option value="0">I'll create my own password</option>
						<option value="1">Generate a random password for me</option>
					</select>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-4 form-control-static">Alias:</div>
				<div class="col-sm-6"><input type="text" name="username" size="25" class="responsive_input form-control" required="required"></div>
			</div>
			<div class="row">
				<div class="col-sm-4 form-control-static">Email address:</div>
				<div class="col-sm-6"><input type="text" name="notification_email" size="25" class="responsive_input form-control"></div>
			</div>
			<div class="row" title="Please enter a bitcoin address where you can receive payments.">
				<div class="col-sm-4 form-control-static">Bitcoin address:</div>
				<div class="col-sm-6"><input type="text" name="bitcoin_address" size="25" class="responsive_input form-control"></div>
			</div>
			<div style="display: none;" id="signup_password_disp">
				<div class="row">
					<div class="col-sm-4 form-control-static">Password:</div>
					<div class="col-sm-6">
						<input class="responsive_input form-control" id="signup_password" name="password" type="password" size="25" maxlength="25" required="required" />
					</div>
				</div>
				<div class="row">
					<div class="col-sm-4 form-control-static">Repeat Password:</div>
					<div class="col-sm-6">
						<input class="responsive_input form-control" id="signup_password2" name="password2" type="password" size="25" maxlength="25" required="required" />
					</div>
				</div>
			</div>
			<?php if ($GLOBALS['signup_captcha_required']) { ?>
			<div class="row">
				<div class="col-sm-12">
					Solve a CAPTCHA:<br/>
					<div class="g-recaptcha" data-sitekey="<?php echo $GLOBALS['recaptcha_publickey']; ?>"></div>
				</div>
			</div>
			<?php } ?>
			<div class="row">
				<div class="col-sm-6 col-sm-push-4">
					<input type="submit" value="Sign Up" class="btn btn-primary" />
					<br/><br/>
				</div>
			</div>
		</form>
	</div>
</div>
*/ ?>