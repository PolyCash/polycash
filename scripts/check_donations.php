<?php
include(dirname(dirname(__FILE__)).'/includes/connect.php');
include(dirname(dirname(__FILE__)).'/includes/get_session.php');
$pagetitle = "Checking donations";
include(dirname(dirname(__FILE__)).'/includes/html_start.php');

if ($app->running_as_admin()) {
	?>
	<div class="container-fluid">
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Checking for Donations</div>
			</div>
			<div class="panel-body">
				<?php
				$donate_addresses = $app->run_query("SELECT * FROM address_keys WHERE priv_key IS NOT NULL ORDER BY address_key_id ASC;");
				
				while ($address_key = $donate_addresses->fetch()) {
					$api_url = "https://blockchain.info/rawaddr/".$address_key['pub_key'];
					$api_data = json_decode(file_get_contents($api_url));
					
					echo '<div class="row">';
					echo '<div class="col-sm-4">'.$address_key['pub_key']."</div>\n";
					echo '<div class="col-sm-2">'.number_format($api_data->n_tx)." transactions</div>\n";
					echo '<div class="col-sm-2">'.$api_data->final_balance/pow(10, 8)." BTC</div>\n";
					echo "</div>\n";
				}
				?>
			</div>
		</div>
	</div>
	<?php
}
else echo "You need admin privileges to run this script.\n";

include(dirname(dirname(__FILE__)).'/includes/html_stop.php');
?>