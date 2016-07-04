<?php
if ($redirect_id > 0) {}
else if (intval($_REQUEST['redirect_id']) > 0) $redirect_id = intval($_REQUEST['redirect_id']);
else $redirect_id = FALSE;

$url_game = false;
$login_url_parts = explode("/", rtrim(ltrim($_SERVER['REQUEST_URI'], "/"), "/"));
if ($login_url_parts[0] == "wallet" && count($login_url_parts) > 1) {
	$q = "SELECT * FROM games WHERE url_identifier='".$login_url_parts[1]."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$url_game = mysql_fetch_array($r);
	}
}
?>
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
			if ($redirect_id) echo "<input type=\"hidden\" name=\"redirect_id\" value=\"".$redirect_id."\" />\n";
			
			if ($_REQUEST['invite_key'] != "") echo '<input type="hidden" name="invite_key" value="'.make_alphanumeric(strip_tags($_REQUEST['invite_key'])).'" />';
			?>
			<div class="row">
				<div class="col-sm-4 form-control-static">Alias:</div>
				<div class="col-sm-6">
					<input id="login_username" class="responsive_input form-control" name="username" type="text" size="25" maxlength="40" value="<?php echo $email; ?>" />
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
			if ($redirect_id) echo "<input type=\"hidden\" name=\"redirect_id\" value=\"".$redirect_id."\" />\n";
			
			if ($_REQUEST['invite_key'] != "") echo '<input type="hidden" name="invite_key" value="'.make_alphanumeric(strip_tags($_REQUEST['invite_key'])).'" />';
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