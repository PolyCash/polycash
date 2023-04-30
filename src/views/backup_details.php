<div class="modal-header">
	<b class="modal-title">Details for backup #<?php echo $backup['export_id']; ?></b>
	
	<button type="button" class="close" data-dismiss="modal" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
<div class="modal-body">
	<table>
		<tr>
			<td style="min-width: 200px;">Backup exported at:</td>
			<td><?php echo date("M j, Y g:ia", strtotime($backup['exported_at'])); ?></td>
		</tr>
		<tr>
			<td>Accounts:</td>
			<td>
				<?php foreach ($accounts as $account) { ?>
					<a target="_blank" href="/accounts/?account_id=<?php echo $account['account_id']; ?>">Account #<?php echo $account['account_id']; ?></a><br/>
				<?php } ?>
			</td>
		</tr>
		<tr>
			<td>User:</td>
			<td><?php echo $user['username']; ?></td>
		</tr>
		<tr>
			<td>Delivered to:</td>
			<td><?php echo $backup['deliver_to_email']; ?></td>
		</tr>
		<tr>
			<td style="vertical-align: top;">Addresses:</td>
			<td>
				<?php foreach ($accounts as $account) { ?>
					Exported <?php echo count($addresses_by_account_id[$account['account_id']])." new address".(count($addresses_by_account_id[$account['account_id']]) == 1 ? "" : "es"); ?> in <a target="_blank" href="/accounts/?account_id=<?php echo $account['account_id']; ?>">account #<?php echo $account['account_id']; ?></a><br/>
					<div style="max-height: 300px; overflow-y: scroll; overflow-x: hidden; padding: 10px 10px 10px 0px;">
						<?php foreach ($addresses_by_account_id[$account['account_id']] as $address_key) { ?>
							<?php echo $address_key['pub_key']; ?><br/>
						<?php } ?>
					</div>
				<?php } ?>
			</td>
		</tr>
	</table>
</div>
