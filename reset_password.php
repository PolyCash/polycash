<?php
$thispage = "account.php";
require_once("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $GLOBALS['pageview_controller']->insert_pageview($thisuser);

$pagetitle = $GLOBALS['site_name']." - Reset your password";
include("includes/html_start.php");

?>
<div class="container" style="max-width: 1000px; padding-top: 15px;">
	<?php
	if ($GLOBALS['outbound_email_enabled']) {
		if ($_REQUEST['do'] == "reset") {
			$token_id = intval($_REQUEST['tid']);
			
			$q = "SELECT * FROM user_resettokens WHERE token_id='".$token_id."';";
			$r = $GLOBALS['app']->run_query($q);
			
			if (mysql_numrows($r) == 1) {
				$reset_token = mysql_fetch_array($r);
				
				if ($reset_token['firstclick_time'] == 0 && $reset_token['expire_time'] > time()) {
					$q = "SELECT * FROM users WHERE user_id='".$reset_token['user_id']."';";
					$r = $GLOBALS['app']->run_query($q);
					$user = mysql_fetch_array($r);
					
					$q = "UPDATE user_resettokens SET firstclick_time='".time()."'";
					if ($GLOBALS['pageview_tracking_enabled']) $q .= ", firstclick_ip='".$_SERVER['REMOTE_ADDR']."'";
					$q .= ", firstclick_viewer_id='".$viewer_id."' WHERE token_id='".$reset_token['token_id']."';";
					$r = $GLOBALS['app']->run_query($q);
					?>
					Please enter a new password for your user account:<br/>
					<form action="/reset_password/" method="post" onsubmit="$('#reset_password').val(Sha256.hash($('#reset_password').val())); $('#reset_password_confirm').val(Sha256.hash($('#reset_password_confirm').val()));">
						<input type="hidden" name="tid" value="<?php echo $reset_token['token_id']; ?>" />
						<input type="hidden" name="do" value="reset_confirm" />
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
					echo "Sorry but that password reset link has expired. Please <a href=\"/reset_password/\">click here</a> to reset your password.";
				}
			}
		}
		else if ($_REQUEST['do'] == "reset_confirm") {
			$token_id = intval($_REQUEST['tid']);
			
			$q = "SELECT * FROM user_resettokens WHERE token_id='".$token_id."';";
			$r = $GLOBALS['app']->run_query($q);
			if (mysql_numrows($r) == 1) {
				$reset_token = mysql_fetch_array($r);
				
				$token2_key = $_REQUEST['token2'];
				
				if ($reset_token['token2_key'] == $token2_key && $reset_token['expire_time'] > time() && $reset_token['completed'] == 0) {
					$reset_success = false;
					
					$password = $_REQUEST['password'];
					$password_confirm = $_REQUEST['password_confirm'];
					
					if ($password == $password_confirm) {
						$q = "UPDATE users SET password='".mysql_real_escape_string($password)."' WHERE user_id='".$reset_token['user_id']."';";
						$r = $GLOBALS['app']->run_query($q);
						
						$reset_success = true;
						
						$q = "UPDATE user_resettokens SET completed=2 WHERE token_id='".$reset_token['token_id']."';";
						$r = $GLOBALS['app']->run_query($q);
					}
					
					if ($reset_success) {
						echo "Your password has successfully been changed! <a href=\"/wallet/\">Click here</a> to log in.<br/><br/>\n";
					}
					else {
						echo "The passwords that you entered didn't match, please <a href=\"/reset_password/\">click here</a> to try again.<br/><br/>\n";
						
						$q = "UPDATE user_resettokens SET completed=1 WHERE token_id='".$reset_token['token_id']."';";
						$r = $GLOBALS['app']->run_query($q);
					}
				}
				else {
					echo "Sorry but that password reset link has expired. Please <a href=\"/reset_password/\">click here</a> to reset your password.";
				}
			}
		}
		else {
			?>
			<script type="text/javascript">
			var reset_in_progress = false;
			function request_pass_reset() {
				if (reset_in_progress) {}
				else {
					var reset_email = $('#reset_email').val();
					$('#reset_button').val("Sending...");
					$.get("/ajax/reset_password.php?email="+encodeURIComponent(reset_email), function(result) {
						$('#reset_button').val("Request Password Reset");
						alert(result);
					});
					reset_in_progress = true;
					setTimeout("reset_in_progress=false;", 100);
				}
			}
			</script>
			
			<h1><?php echo $GLOBALS['site_name']; ?> - Recover your Password</h1>
			
			<form method="get" action="/reset_password/" onsubmit="request_pass_reset(); return false;">
				To reset your password, please enter your email address below and we'll send you a password reset link.<br/>
				<div class="row bootstrap_pad_row">
					<div class="col-md-6">
						<input id="reset_email" type="text" size="40" class="responsive_input form-control" placeholder="Please enter your email address" style="margin-bottom: 10px;" />
					</div>
				</div>
				<div class="row bootstrap_pad_row">
					<div class="col-md-6">
						<input id="reset_button" type="submit" class="btn btn-primary" onclick="request_pass_reset();" value="Request Password Reset" />
					</div>
				</div>
			</form>
			<br/><br/>
			<?php
		}
	}
	else {
		echo "Sorry, this function is disabled; this server cannot deliver emails.";
	}
	?>
</div>
<?php

include("includes/html_stop.php");
?>