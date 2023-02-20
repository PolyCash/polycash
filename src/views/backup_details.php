<div class="modal-header">
	<b class="modal-title">Details for backup #<?php echo $backup['backup_id']; ?></b>
	
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
			<td>Account:</td>
			<td><a target="_blank" href="/accounts/?account_id=<?php echo $account['account_id']; ?>">Account #<?php echo $account['account_id']; ?></a></td>
		</tr>
		<tr>
			<td>User:</td>
			<td><?php echo $user['username']; ?></td>
		</tr>
		<tr>
			<td>IP Address:</td>
			<td>
				<a target="_blank" href="https://www.iplocation.net/ip-lookup/<?php echo $backup['ip_address']; ?>"><?php echo $backup['ip_address']; ?></a>
			</td>
		</tr>
		<tr>
			<td style="vertical-align: top;">Addresses:</td>
			<td>
				<?php echo count($address_keys)." address".(count($address_keys) == 1 ? "" : "es"); ?><br/>
				<div style="max-height: 300px; overflow-y: scroll; overflow-x: hidden; padding: 10px 10px 10px 0px;">
					<?php foreach ($address_keys as $address_key) { ?>
						<?php echo $address_key['pub_key']; ?><br/>
					<?php } ?>
				</div>
			</td>
		</tr>
	</table>
</div>
