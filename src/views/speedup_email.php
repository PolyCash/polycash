<p>Your account <a href="<?php echo $wallet_link; ?>">#<?php echo $account_id; ?></a> has unrealized gains that could be staked to increase your earn rate.</p>

<table>
	<tr>
		<td>Account:</td>
		<td><a href="<?php echo $wallet_link; ?>">#<?php echo $account_id; ?></a></td>
	</tr>
	<tr>
		<td>Balance:</td>
		<td><?php echo $this->format_bignum($balance/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']; ?></td>
	</tr>
	<tr>
		<td>Unrealized gains:</td>
		<td><?php echo $this->format_bignum($votes_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']; ?></td>
	</tr>
	<tr>
		<td style="padding-right: 20px;">Current earn rate:</td>
		<td><?php echo $this->format_bignum($gain_per_day/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']; ?> per day</td>
	</tr>
</table>

<p>Realizing your <?php echo $this->format_bignum($votes_value/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']; ?> in unrealized gains would increase your earn rate by <?php echo round($speedup_pct, 3); ?>%.</p>

<?php $button_style = 'padding: 5px 10px; font-size: 13px; line-height: 1.5; border-radius: 0; display: inline-block; margin-bottom: 0; font-weight: normal; text-align: center; vertical-align: middle; cursor: pointer; background-image: none; border: 1px solid transparent; white-space: nowrap; color: #ffffff; text-decoration: none;'; ?>

<p>
	To maximize your earn rate please log in and submit a staking transaction or click below to automatically stake your <?php echo $game->db_game['coin_name_plural']; ?>.
</p>

<p>
	<a style="<?php echo $button_style; ?>background-color: #2780e3; border-color: #2780e3;" href="<?php echo $wallet_link; ?>">Log in</a>
	<a style="<?php echo $button_style; ?>background-color: #3fb618; border-color: #3fb618;" href="<?php echo $auto_stake_link; ?>">Stake my <?php echo $game->db_game['coin_name_plural']; ?></a>
</p>
