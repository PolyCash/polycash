<p>You can get <?php echo $game->db_game['coin_name_plural'].' ('.$game->db_game['coin_abbreviation'] ;?>) by depositing another currency like Bitcoin or Litecoin here. If you already have <?php echo $game->db_game['coin_name_plural']; ?> elsewhere and want to send them to this wallet, go to <a href="/wallet/<?php echo $game->db_game['url_identifier'] ;?>/?initial_tab=4">Send & Receive</a> instead.</p>

<p>If you find that there are no <?php echo $game->db_game['coin_name_plural'] ;?> available here, you may need to use an external exchange to buy <?php echo $game->db_game['coin_abbreviation']; ?>.</p>

<div class="form-group">
	<label for="buyin_currency_id">What do you want to deposit?</label>
	<select class="form-control" id="buyin_currency_id" name="buyin_currency_id" onchange="thisPageManager.change_buyin_currency(this);">
		<option value="">-- Please Select --</option>
		<?php foreach ($buyin_currencies as $a_buyin_currency) { ?>
			<option <?php
			if ($a_buyin_currency['currency_id'] == $buyin_currency['currency_id']) echo 'selected="selected" ';
			echo 'value="'.$a_buyin_currency['currency_id'].'">'.$a_buyin_currency['name']."</option>\n";
		}
		?>
	</select>
</div>
