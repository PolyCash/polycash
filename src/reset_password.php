<?php
$thispage = "account.php";
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$pagetitle = AppSettings::getParam('site_name')." - Reset your password";
include(AppSettings::srcPath()."/includes/html_start.php");

?>
<div class="container-fluid">
	<?php
	die("This function is disabled.\n");
	
	$noinfo_message = "Sorry but that password reset link has expired. Please <a href=\"/reset_password/\">click here</a> to reset your password.";
	
	if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "reset") {
		$token_id = intval($_REQUEST['tid']);
		$token_key = $_REQUEST['reset_key'];
		
		$reset_token = $app->run_query("SELECT * FROM user_resettokens WHERE token_id=:token_id AND token_key=:token_key;", [
			'token_id' => $token_id,
			'token_key' => $token_key
		])->fetch();
		
		if ($reset_token) {
			if ($reset_token['firstclick_time'] == 0 && $reset_token['expire_time'] > time()) {
				$user = $app->fetch_user_by_id($reset_token['user_id']);
				
				$update_resettoken_params = [
					'firstclick_time' => time(),
					'token_id' => $reset_token['token_id']
				];
				$update_resettoken_q = "UPDATE user_resettokens SET firstclick_time=:firstclick_time";
				if (AppSettings::getParam('pageview_tracking_enabled')) {
					$update_resettoken_q .= ", firstclick_ip=:firstclick_ip, firstclick_viewer_id=:viewer_id";
					$update_resettoken_params['firstclick_ip'] = $_SERVER['REMOTE_ADDR'];
					$update_resettoken_params['viewer_id'] = $viewer_id;
				}
				$update_resettoken_q .= " WHERE token_id=:token_id;";
				$app->run_query($update_resettoken_q, $update_resettoken_params);
				?>
				Please enter a new password for your user account:<br/>
				<form action="/reset_password/" method="post" onsubmit="$('#reset_password').val(Sha256.hash($('#reset_password').val())); $('#reset_password_confirm').val(Sha256.hash($('#reset_password_confirm').val()));">
					<input type="hidden" name="tid" value="<?php echo $reset_token['token_id']; ?>" />
					<input type="hidden" name="action" value="reset_confirm" />
					<input type="hidden" name="token2" value="<?php echo $reset_token['token2_key']; ?>" />
					<div class="row">
						<div class="col-md-4 form-control-static">
							Alias:
						</div>
						<div class="col-md-4"><b><?php echo $user['username']; ?></b></div>
					</div>
					<div class="row">
						<div class="col-md-4 form-control-static">Enter a new password:</div>
						<div class="col-md-4">
							<input id="reset_password" name="password" class="form-control" type="password" size="30" required="required" />
						</div>
					</div>
					<div class="row">
						<div class="col-md-4 form-control-static">
							Please re-enter the password:
						</div>
						<div class="col-md-4">
							<input id="reset_password_confirm" name="password_confirm" class="form-control" type="password" size="30" required="required" />
						</div>
					</div>
					<div class="row">
						<div class="col-md-4 col-md-push-4">
							<input type="submit" class="btn btn-primary" value="Change my Password" />
						</div>
					</div>
				</form>
				<?php
			}
			else {
				echo $noinfo_message;
			}
		}
		else echo $noinfo_message;
	}
	else if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "reset_confirm") {
		$token_id = intval($_REQUEST['tid']);
		
		$reset_token = $app->run_query("SELECT * FROM user_resettokens WHERE token_id=:token_id;", ['token_id' => $token_id])->fetch();
		
		if ($reset_token) {
			$token2_key = $_REQUEST['token2'];
			
			if ($reset_token['token2_key'] == $token2_key && $reset_token['expire_time'] > time() && $reset_token['completed'] == 0) {
				$reset_success = false;
				
				$password = $_REQUEST['password'];
				$password_confirm = $_REQUEST['password_confirm'];
				
				if ($password == $password_confirm) {
					$db_reset_user = $app->fetch_user_by_id($reset_token['user_id']);
					
					if ($db_reset_user) {
						$app->run_query("UPDATE users SET password=:password WHERE user_id=:user_id;", [
							'password' => $app->normalize_password($password, $db_reset_user['salt']),
							'user_id' => $db_reset_user['user_id']
						]);
						
						$reset_success = true;
						
						$app->run_query("UPDATE user_resettokens SET completed=2 WHERE token_id=:token_id;", [
							'token_id' => $reset_token['token_id']
						]);
					}
				}
				
				if ($reset_success) {
					echo "Your password has successfully been changed! <a href=\"/wallet/\">Click here</a> to log in.<br/><br/>\n";
				}
				else {
					echo "The passwords that you entered didn't match, please <a href=\"/reset_password/\">click here</a> to try again.<br/><br/>\n";
					
					$app->run_query("UPDATE user_resettokens SET completed=1 WHERE token_id=:token_id;", [
						'token_id' => $reset_token['token_id']
					]);
				}
			}
			else {
				echo $noinfo_message;
			}
		}
	}
	else {
		?>
		<h1><?php echo AppSettings::getParam('site_name'); ?> - Recover your Password</h1>
		
		<form method="get" action="/reset_password/" onsubmit="thisPageManager.request_pass_reset(); return false;">
			To reset your password, please enter your email address below and we'll send you a password reset link.<br/>
			<div class="row bootstrap_pad_row">
				<div class="col-md-6">
					<input id="reset_email" type="text" size="40" class="responsive_input form-control" placeholder="Please enter your email address" style="margin-bottom: 10px;" />
				</div>
			</div>
			<div class="row bootstrap_pad_row">
				<div class="col-md-6">
					<input id="reset_button" type="submit" class="btn btn-primary" value="Request Password Reset" />
				</div>
			</div>
		</form>
		<br/><br/>
		<?php
	}
	?>
</div>
<?php

include(AppSettings::srcPath()."/includes/html_stop.php");
?>