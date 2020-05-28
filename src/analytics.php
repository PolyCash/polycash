<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
require(AppSettings::srcPath()."/classes/Analytics.php");
$nav_tab_selected = "analytics";
$pagetitle = AppSettings::getParam('site_name')." - Key Performance Indicators";

include(AppSettings::srcPath()."/includes/html_start.php");
?>
<div class="container-fluid">
	<?php
	if (!$thisuser) {
		$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
		$redirect_key = $redirect_url['redirect_key'];
		
		include(AppSettings::srcPath()."/includes/html_login.php");
	}
	else if (!$app->user_is_admin($thisuser)) {
		?>
		You must be logged in as admin to manage currencies.
		<?php
	}
	else {
		$from_date = date("Y-m-d", strtotime("-21 days"));
		if (!empty($_REQUEST['from_date'])) $from_date = date("Y-m-d", strtotime($_REQUEST['from_date']));
		
		$to_date = date("Y-m-d", time());
		if (!empty($_REQUEST['to_date'])) $to_date = date("Y-m-d", strtotime($_REQUEST['to_date']));
		
		$signup_chart_url = Analytics::get_chart_url($app, "signups", $from_date, $to_date, 1000, 120, "ffff00");
		$login_chart_url = Analytics::get_chart_url($app, "logins", $from_date, $to_date, 1000, 120, "ff9900");
		$conversion_chart_url = Analytics::get_chart_url($app, "currency_conversions", $from_date, $to_date, 1000, 120, "ff5500");
		$viewer_chart_url = Analytics::get_chart_url($app, "viewers", $from_date, $to_date, 1000, 120, "cc0077");
		$email_chart_url = Analytics::get_chart_url($app, "emails", $from_date, $to_date, 1000, 120, "7700ff");
		?>
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">
					Key Performance Indicators
				</div>
			</div>
			<div class="panel-body">
				<p>Showing analytics from <b><?php echo $from_date; ?></b> to <b><?php echo $to_date; ?></b> &nbsp;&nbsp; <a href="" onclick="$('#change_dates').toggle('fast'); return false;">Change</a></p>
				<div style="display: none;" id="change_dates">
					<form action="/analytics/" method="get">
						<div class="form-group">
							<label for="from_date">From date:</label>
							<input type="text" class="form-control datepicker input-sm" name="from_date" id="from_date" value="<?php echo $from_date; ?>" />
						</div>
						<div class="form-group">
							<label for="to_date">To date:</label>
							<input type="text" class="form-control datepicker input-sm" name="to_date" id="to_date" value="<?php echo $to_date; ?>" /> 
						</div>
						<div class="form-group">
							<input type="submit" class="btn btn-success btn-sm" value="Go" />
						</div>
					</form>
				</div>
				
				<h4>User registrations</h4>
				<img src="<?php echo $signup_chart_url; ?>" style="max-width: 100%; margin: 8px 0px;" />
				
				<h4>Unique user logins</h4>
				<img src="<?php echo $login_chart_url; ?>" style="max-width: 100%; margin: 8px 0px;" />
				
				<h4>Currency conversions</h4>
				<img src="<?php echo $conversion_chart_url; ?>" style="max-width: 100%; margin: 8px 0px;" />
				
				<h4>Unique viewers</h4>
				<img src="<?php echo $viewer_chart_url; ?>" style="max-width: 100%; margin: 8px 0px;" />
				
				<h4>Emails delivered</h4>
				<img src="<?php echo $email_chart_url; ?>" style="max-width: 100%; margin: 8px 0px;" />
			</div>
		</div>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
