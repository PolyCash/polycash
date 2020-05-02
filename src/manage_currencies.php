<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$nav_tab_selected = "manage_currencies";
$pagetitle = AppSettings::getParam('site_name')." - Manage Currencies";

AppSettings::addJsDependency("jquery.datatables.js");

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
		$reference_currency = $app->get_reference_currency();
		$display_currency = $app->fetch_currency_by_id(1);
		$all_currencies = $app->fetch_currencies([]);
		?>
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">
					Manage Currencies
				</div>
			</div>
			<div class="panel-body">
				<p><?php echo $all_currencies->rowCount(); ?> currencies are installed.</p>
				
				<table style="width: 100%;" class="table table-bordered">
					<thead style="background-color: #f6f6f6;">
						<tr>
							<th>Name</th>
							<th>Symbol</th>
							<th>Exchange Rate</th>
							<th>Exchange Rate Time</th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ($currency = $all_currencies->fetch()) {
							$price_info = $app->exchange_rate_between_currencies($display_currency['currency_id'], $currency['currency_id'], time(), $reference_currency['currency_id']);
							?>
							<tr>
								<td><?php echo $currency['name']; ?></td>
								<td><?php echo $currency['abbreviation']; ?></td>
								<td>
									<?php
									if (!empty($price_info['time'])){
										echo $app->format_bignum($price_info['exchange_rate'])." &nbsp; ".$display_currency['abbreviation']."/".$currency['abbreviation'];
									}
									?>
								</td>
								<td>
									<?php
									if (!empty($price_info['time']) && $currency['currency_id'] != $display_currency['currency_id']) {
										echo $app->format_seconds(time()-$price_info['time'])." ago";
									}
									?>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
