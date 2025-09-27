<table>
	<tr>
		<td>First name:</td>
		<td><?php echo $user['first_name']; ?></td>
	</tr>
	<tr>
		<td>Last name:</td>
		<td><?php echo $user['last_name']; ?></td>
	</tr>
	<tr>
		<td>Username:</td>
		<td><?php echo $user['username']; ?></td>
	</tr>
	<tr>
		<td>Notification email:</td>
		<td><?php echo $user['notification_email']; ?></td>
	</tr>
	<tr>
		<td>Phone number:</td>
		<td><?php echo $user['phone_number']; ?></td>
	</tr>
	<tr>
		<td>Signed up:</td>
		<td><?php echo date("Y-m-d H:i:s", $user['time_created']); ?></td>
	</tr>
</table>

<p>
	Please review this user's request and <a href="<?php echo AppSettings::getParam('base_url')."/manage_faucets/".$game->db_game['url_identifier']."/".$faucet['faucet_id']."/approve/".$join_request['request_id']; ?>">add them to this faucet</a> if everything looks ok.
</p>
