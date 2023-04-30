<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');
require(AppSettings::srcPath().'/includes/must_log_in.php');

$exports = $thisuser->fetchAllBackupExportsByUser();

$pagetitle = "Backup History";

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<p style="margin-top: 15px;">&larr; Back to <a href="/accounts/">My Accounts</a></p>
	
	<div class="panel panel-info">
		<div class="panel-heading">
			Address private keys have been exported from your account <?php echo number_format(count($exports))." time".(count($exports) == 1 ? "" : "s"); ?>.
		</div>
		<div class="panel-body">
			<?php foreach ($exports as $export) {
				$extra_info = json_decode($export['extra_info'], true);
				$num_addresses = 0;
				foreach ($extra_info['address_key_ids'] as $account_id => $address_key_ids) {
					$num_addresses += count($address_key_ids);
				}
				?>
				<div class="row">
					<div class="col-sm-2"><?php echo date("M j, Y g:ia", strtotime($export['exported_at'])); ?></div>
					<div class="col-sm-2">Accounts <?php echo implode(", ", $extra_info['account_ids']); ?></a></div>
					<div class="col-sm-2">User: <?php echo $export['username']; ?></div>
					<div class="col-sm-2"><?php echo $num_addresses." new address".($num_addresses == 1 ? "" : "es"); ?></div>
					<div class="col-sm-2"><a href="" onClick="thisPageManager.open_backup_details(<?php echo $export['export_id']; ?>); return false;">See Details</a></div>
				</div>
			<?php } ?>
		</div>
	</div>
</div>

<div style="display: none;" class="modal fade" id="backup_details_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" id="backup_details_inner"></div>
	</div>
</div>
<?php
if (!empty($_REQUEST['view_backup_id']) && (string)((int) $_REQUEST['view_backup_id']) === (string) $_REQUEST['view_backup_id']) {
	?>
	<script type="text/javascript">
	window.onload = function() {
		thisPageManager.open_backup_details(<?php echo $_REQUEST['view_backup_id']; ?>);
	};
	</script>
	<?php
}

include(AppSettings::srcPath().'/includes/html_stop.php');
?>