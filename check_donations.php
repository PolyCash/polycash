<?php
include('includes/connect.php');
include('includes/get_session.php');
$pagetitle = "Checking donations";
include('includes/html_start.php');

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	?>
	<div class="container-fluid">
		<div class="panel panel-info" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Checking for Donations</div>
			</div>
			<div class="panel-body">
				<?php
				$q = "SELECT * FROM address_keys WHERE priv_key IS NOT NULL ORDER BY address_key_id ASC;";
				$r = $app->run_query($q);
				
				while ($address_key = $r->fetch()) {
					$api_url = "https://blockchain.info/rawaddr/".$address_key['pub_key'];
					$api_data = json_decode(file_get_contents($api_url));
					
					echo '<div class="row">';
					echo '<div class="col-sm-4">'.$address_key['pub_key']."</div>\n";
					echo '<div class="col-sm-2">'.$api_data->n_tx." transactions</div>\n";
					echo '<div class="col-sm-2">'.$api_data->final_balance." BTC</div>\n";
					echo "</div>\n";
				}
				?>
			</div>
		</div>
	</div>
	<?php
}
else echo "Incorrect key.";

include('includes/html_stop.php');
?>