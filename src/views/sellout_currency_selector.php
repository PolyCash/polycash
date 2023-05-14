<p>You can use the form below to exchange your <?php echo $game->db_game['coin_name_plural']; ?> for Bitcoin or Litecoin. If instead you want send your <?php echo $game->db_game['coin_name_plural']; ?> somewhere please go to <a href="/wallet/<?php echo $game->db_game['url_identifier']; ?>/?initial_tab=4">Send & Receive</a>.</p>

<p>If you find that there are no <?php echo $sellout_blockchain->db_blockchain['coin_name_plural']; ?> available here, you may need to use an external exchange to convert your <?php echo $game->db_game['coin_name_plural']; ?>.</p>

<div class="form-group">
	<label for="buyin_currency_id">What do you want to receive?</label>
	<select class="form-control" id="sellout_currency_id" name="sellout_currency_id" onchange="thisPageManager.change_sellout_currency(this);">
		<option value="">-- Please Select --</option>
		<?php foreach ($sellout_currencies as $a_sellout_currency) { ?>
			<option <?php
			if ($a_sellout_currency['currency_id'] == $sellout_currency['currency_id']) echo 'selected="selected" ';
			echo 'value="'.$a_sellout_currency['currency_id'].'">'.$a_sellout_currency['name']; ?>
			</option>
		<?php } ?>
	</select>
</div>
