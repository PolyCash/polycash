<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
require(AppSettings::srcPath()."/includes/must_log_in.php");

require(AppSettings::srcPath()."/models/Analytics.php");

$nav_tab_selected = "analytics";
$pagetitle = AppSettings::getParam('site_name')." - Key Performance Indicators";

include(AppSettings::srcPath()."/includes/html_start.php");
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid">
	<?php
	if (!$app->user_is_admin($thisuser)) {
		?>
		You must be logged in as admin to manage currencies.
		<?php
	}
	else {
		$from_date = date("Y-m-d", strtotime("-21 days"));
		if (!empty($_REQUEST['from_date'])) $from_date = date("Y-m-d", strtotime($_REQUEST['from_date']));
		
		$to_date = date("Y-m-d", time());
		if (!empty($_REQUEST['to_date'])) $to_date = date("Y-m-d", strtotime($_REQUEST['to_date']));
		
		$chart_height_px = 250;
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
				
				<div>
					<canvas id="chart_signups" style="height: <?php echo $chart_height_px; ?>px;"></canvas>
				</div>
				<script>
				<?php echo Analytics::get_chart_js($app, "signups", $from_date, $to_date, "User Registrations"); ?>
				</script>
				
				<div>
					<canvas id="chart_logins" style="height: <?php echo $chart_height_px; ?>px;"></canvas>
				</div>
				<script>
				<?php echo Analytics::get_chart_js($app, "logins", $from_date, $to_date, "Unique User Logins"); ?>
				</script>
				
				<div>
					<canvas id="chart_currency_conversions" style="height: <?php echo $chart_height_px; ?>px;"></canvas>
				</div>
				<script>
				<?php echo Analytics::get_chart_js($app, "currency_conversions", $from_date, $to_date, "Currency Conversions"); ?>
				</script>
				
				<div>
					<canvas id="chart_viewers" style="height: <?php echo $chart_height_px; ?>px;"></canvas>
				</div>
				<script>
				<?php echo Analytics::get_chart_js($app, "viewers", $from_date, $to_date, "Unique Viewers"); ?>
				</script>
				
				<div>
					<canvas id="chart_emails" style="height: <?php echo $chart_height_px; ?>px;"></canvas>
				</div>
				<script>
				<?php echo Analytics::get_chart_js($app, "emails", $from_date, $to_date, "Emails Delivered"); ?>
				</script>
			</div>
		</div>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
