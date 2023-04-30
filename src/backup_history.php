<?php
require(AppSettings::srcPath().'/includes/connect.php');
require(AppSettings::srcPath().'/includes/get_session.php');
require(AppSettings::srcPath().'/includes/must_log_in.php');

$exports = $thisuser->fetchAllBackupExportsByUser();

$pagetitle = "Backup History";

$message = null;
$message_class = null;

if (!empty($_REQUEST['action']) && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	if ($_REQUEST['action'] == "backup_all") {
		$to_email = User::getToEmailAddress($thisuser->db_user);
		
		if ($to_email === null) {
			$message = "You don't have a valid email address on file. Please visit <a href='/profile'>Account Settings</a> and enter a valid email address.";
			$message_class = "warning";
		}
		else {
			$app->run_query("UPDATE currency_accounts a JOIN address_keys k ON a.account_id=k.account_id SET k.exported_backup_at=NULL WHERE a.user_id=:user_id;", [
				'user_id' => $thisuser->db_user['user_id'],
			]);
			$message = "A backup has been scheduled and private keys for all your addresses will be emailed to ".$to_email." soon.";
			$message_class = "success";
		}
	}
}

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if (!empty($message)) echo '<div style="margin-top: 15px;">'.$app->render_error_message($message, $message_class).'</div>';
	?>
	
	<div style="margin: 10px 0px;">
		<div class="row">
			<div class="col-sm-6">
				&larr; Back to <a href="/accounts/">My Accounts</a>
			</div>
			<div class="col-sm-6">
				<div style="float: right;">
					<form method="post" action="/accounts/backups" onsubmit="return confirm('Are you sure you want to backup all addresses to your email address?');">
						<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
						<input type="hidden" name="action" value="backup_all">
						<button class="btn btn-sm btn-success">Backup all my private keys</button>
					</form>
				</div>
			</div>
		</div>
	</div>
	
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
					<div class="col-sm-2"><?php echo date("M j, Y g:ia", $export['exported_at']); ?></div>
					<div class="col-sm-2">Accounts: <?php
					foreach ($extra_info['account_ids'] as $account_id) {
						echo '<a href="/accounts/?account_id='.$account_id.'">#'.$account_id.'</a> ';
					}
					?></a></div>
					<div class="col-sm-4">Sent to: <?php echo $export['deliver_to_email']; ?></div>
					<div class="col-sm-2"><?php echo number_format($num_addresses)." new address".($num_addresses == 1 ? "" : "es"); ?></div>
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