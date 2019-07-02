<div id="card_login">
	<h3><?php echo $login_title; ?></h3>
	
	<div class="form-group">
		<label for="peer_id">Card peer:</label>
		<select class="form-control" name="peer_id" id="peer_id">
			<option value="">-- Please Select --</option>
			<?php
			$visible_peers = $app->run_query("SELECT * FROM peers WHERE visible=1 ORDER BY peer_name ASC;");
			while ($db_peer = $visible_peers->fetch()) {
				echo "<option value=\"".$db_peer['peer_id']."\">".$db_peer['peer_name']."</option>\n";
			}
			?>
		</select>
	</div>
	
	<div class="form-group">
		<label for="peer_card_id">Card ID:</label>
		<?php if ($ask4nameid) { ?>
		<input class="form-control" type="tel" size="6" value="" placeholder="" id="peer_card_id" name="peer_card_id" />
		<?php } else {
			echo $card['peer_card_id'];
		} ?>
	</div>
	
	<div class="form-group">
		<label for="redeem_code">16 digit code:</label>
		<input type="tel" size="20" maxlength="19" class="form-control" id="redeem_code" />
	</div>
	
	<div class="form-group">
		<label for="card_account_password">Password:</label>
		<input id="card_account_password" name="password" type="password" size="25" class="form-control" maxlength="100" />
	</div>
	
	<button class="btn btn-success" onclick="thisPageManager.card_login(false, <?php echo $card_login_card_id; ?>, <?php echo $card_login_peer_id; ?>);">Log in</button>
</div>