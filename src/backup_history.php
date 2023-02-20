<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');
require(AppSettings::srcPath().'/includes/must_log_in.php');

$backups = CurrencyAccount::fetchAllBackupsByUser($app, $thisuser);

$pagetitle = "Backup History";

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<p style="margin-top: 15px;">&larr; Back to <a href="/accounts/">My Accounts</a></p>
	
	<div class="panel panel-info">
		<div class="panel-heading">
			Address private keys have been exported from your account <?php echo number_format(count($backups))." time".(count($backups) == 1 ? "" : "s"); ?>.
		</div>
		<div class="panel-body">
			<?php foreach ($backups as $backup) {
				$address_key_ids = json_decode($backup['extra_info'], true)['address_key_ids'];
				?>
				<div class="row">
					<div class="col-sm-2"><?php echo date("M j, Y g:ia", strtotime($backup['exported_at'])); ?></div>
					<div class="col-sm-2"><a href="/accounts/?account_id=<?php echo $backup['account_id']; ?>">Account #<?php echo $backup['account_id']; ?></a></div>
					<div class="col-sm-2">User: <?php echo $backup['username']; ?></div>
					<div class="col-sm-2">IP: <a target="_blank" href="https://www.iplocation.net/ip-lookup/<?php echo $backup['ip_address']; ?>"><?php echo $backup['ip_address']; ?></a></div>
					<div class="col-sm-2"><?php echo count($address_key_ids)." address".(count($address_key_ids) == 1 ? "" : "es"); ?></div>
					<div class="col-sm-2"><a href="" onClick="thisPageManager.open_backup_details(<?php echo $backup['backup_id']; ?>); return false;">See Details</a></div>
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