<?php
foreach ($my_bets as $my_bet) {
	$unconfirmed_votes = 0;
	list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees, $coin_stake) = $game->get_payout_info($my_bet, $coins_per_vote, $last_block_id);
	
	?>
	<div class="row">
		<?php
		if ($event->db_event['payout_rule'] == "binary") {
			?>
			<div class="col-sm-6">
				<font class="<?php echo $color ;?>text">
					<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_bet['tx_hash'].'/'.$my_bet['game_out_index']; ?>">
						Staked <?php echo $app->format_bignum($coin_stake/pow(10,$game->db_game['decimal_places'])); ?>
					</a> 
					on <?php echo $my_bet['name']; ?>
				</font>
			</div>
			
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text">
					<?php
					if ($max_payout-$coin_stake > 0) echo '+';
					echo $game->display_coins($max_payout-$coin_stake)." &nbsp; (x".$app->format_bignum($odds).")";
					?>
				</font>
			</div>
			<?php
		}
		else {
			?>
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text"><?php echo $my_bet['name']; ?></font><br/>
				<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_bet['tx_hash'].'/'.$my_bet['game_out_index']; ?>">
					Paid <?php echo $game->display_coins($coin_stake); ?>
				</a> @ $<?php echo $app->format_bignum($asset_price_usd); ?>
				<br/>
				<?php
				echo $app->format_bignum($equivalent_contracts/pow(10, $game->db_game['decimal_places'])).' '.$event->db_event['track_name_short'].' @ $'.$app->format_bignum($bought_price_usd);
				if ($bought_leverage != 1) echo ' &nbsp; ('.$app->format_bignum($bought_leverage).'X)';
				?>
			</div>
			
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text">
					<?php
					echo $game->display_coins($fair_io_value-$payout_fees); ?>
				</font>
				@ $<?php echo $app->format_bignum($track_pay_price);
				if ($track_pay_price != $track_price_usd) echo " ($".$app->format_bignum($track_price_usd).")";
				?>
				<br/>
				<?php
				if ($my_bet['event_option_index'] != 0) echo '-';
				echo $app->format_bignum($equivalent_contracts/pow(10, $game->db_game['decimal_places'])).' '.$event->db_event['track_name_short'].' ';
				
				if ($borrow_delta != 0) {
					if ($borrow_delta > 0) echo '<font class="greentext">+ ';
					else echo '<font class="redtext">- ';
					echo $app->format_bignum(abs($borrow_delta/pow(10, $game->db_game['decimal_places'])));
					echo "</font>\n";
				}
				if ($current_leverage && $current_leverage != 1) echo " &nbsp; (".$app->format_bignum($current_leverage)."X)\n";
				?>
				<br/>
				<?php
				if ($net_delta < 0) echo '<font class="redtext">Net loss of ';
				else echo '<font class="greentext">Net gain of ';
				echo $game->display_coins(abs($net_delta));
				echo '</font>';
				?>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
