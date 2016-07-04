<?php
if ($redirect_id > 0) {}
else if (intval($_REQUEST['redirect_id']) > 0) $redirect_id = intval($_REQUEST['redirect_id']);
else $redirect_id = FALSE;

?>
<script type="text/javascript">
function autogen_password_changed() {
	if ($('#autogen_password').val() == "1") {
		$('#signup_password_disp').hide();
	}
	else {
		$('#signup_password_disp').show();
	}
}
</script>
<div class="row">
	<div class="col-md-6">
		<b style="font-size: 17px; line-height: 24px;">Please log in to continue</b>
		<form onsubmit="$('#login_password').val(Sha256.hash($('#login_password').val()));" action="/wallet/" method="post">
			<input type="hidden" name="do" value="login" />
			<?php
			if ($redirect_id) echo "<input type=\"hidden\" name=\"redirect_id\" value=\"".$redirect_id."\" />\n";
			?>
			<div class="row">
				<div class="col-sm-4">Email Address:</div>
				<div class="col-sm-6">
					<input class="responsive_input" name="username" type="text" size="25" maxlength="40" value="<?php echo $email; ?>" />
				</div>
			</div>
			<div class="row">
				<div class="col-sm-4">Password:</div>
				<div class="col-sm-6">
					<input class="responsive_input" id="login_password" name="password" type="password" size="25" maxlength="25" />
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6 col-sm-push-4">
					<input type="submit" class="btn btn-primary" value="Log in" />
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
		Your password will be emailed to you.  This website allows password resets by email; your account here is only as secure as your email account.
		<form action="/wallet/" method="post" onsubmit="$('#signup_password').val(Sha256.hash($('#signup_password').val())); $('#signup_password2').val(Sha256.hash($('#signup_password2').val()));">
			<input type="hidden" name="do" value="signup" />
			<?php if ($redirect_id) echo "<input type=\"hidden\" name=\"redirect_id\" value=\"".$redirect_id."\" />\n"; ?>
			<?php /*
			<div class="row">
				<div class="col-sm-4">First Name: (Optional)</div>
				<div class="col-sm-6"><input type="text" name="first" size="25" class="responsive_input"></div>
			</div>
			<div class="row">
				<div class="col-sm-4">Last Name: (Optional)</div>
				<div class="col-sm-6"><input type="text" name="last" size="25" class="responsive_input"></div>
			</div> */ ?>
			<div class="row">
				<div class="col-sm-10">
					<select id="autogen_password" name="autogen_password" class="responsive_input" onchange="autogen_password_changed();">
						<option value="1">Email me a random password</option>
						<option value="0">I'll create my own password</option>
					</select>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-4">Email address:</div>
				<div class="col-sm-6"><input type="text" name="email" size="25" class="responsive_input"></div>
			</div>
			<div style="display: none;" id="signup_password_disp">
				<div class="row">
					<div class="col-sm-4">Password:</div>
					<div class="col-sm-6">
						<input class="responsive_input" id="signup_password" name="password" type="password" size="25" maxlength="25" />
					</div>
				</div>
				<div class="row">
					<div class="col-sm-4">Repeat Password:</div>
					<div class="col-sm-6">
						<input class="responsive_input" id="signup_password2" name="password2" type="password" size="25" maxlength="25" />
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-12">
					Solve a CAPTCHA:<br/>
					<div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_publickey; ?>"></div>
				</div>
			</div>
			<div class="row">
				<div class="col-sm-6">
					<input type="submit" value="Sign Up" class="btn btn-primary" />
					<br/><br/>
				</div>
			</div>
		</form>
	</div>
</div>