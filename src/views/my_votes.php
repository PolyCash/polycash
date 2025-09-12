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
				<font <?php echo isset($color) ? 'class="'.$color.'text"' : '' ;?>>
					<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_bet['tx_hash'].'/'.$my_bet['game_out_index']; ?>">
						Staked <?php echo $app->format_bignum($coin_stake/pow(10,$game->db_game['decimal_places'])); ?>
					</a> 
					on <?php echo $my_bet['name']; ?>
				</font>
			</div>
			
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text">
					<?php
					if ($max_payout > 0) echo '+';
					echo $game->display_coins($max_payout)." &nbsp; (x".$app->format_bignum($odds).")";
					?>
				</font>
			</div>
			<?php
		}
		else {
			?>
			<div class="col-sm-6">
				<div style="padding: 5px 0px;">
					<font class="<?php echo $color; ?>text"><?php echo $my_bet['name']; ?></font>
					<?php if ($bought_leverage != 1) echo ' &nbsp; (Leverage: '.$app->round_to($bought_leverage, 0, EXCHANGE_RATE_SIGFIGS, true).'X)'; ?>
					<br/>
					<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_bet['tx_hash'].'/'.$my_bet['game_out_index']; ?>">
						Paid <?php echo $game->display_coins($coin_stake, false, true, false); ?>
					</a> @ <?php echo $app->round_to($asset_price_usd, 0, EXCHANGE_RATE_SIGFIGS, true); ?> / contract
					<br/>
					<?php
					if ($my_bet['event_option_index'] != 0) echo '-';
					echo $app->format_bignum($equivalent_contracts/pow(10, $game->db_game['decimal_places'])).' '.$event->db_event['track_name_short'].' ';
					
					if ($borrow_delta != 0) {
						if ($borrow_delta > 0) echo '<font class="greentext">+ ';
						else echo '<font class="redtext">- ';
						echo $game->display_coins(abs($borrow_delta), true, false, false);
						echo "</font>\n";
					}
					?>
					<br/>
				</div>
			</div>
			
			<div class="col-sm-6">
				<div style="padding: 5px 0px;">
					<font class="<?php echo $color; ?>text">
						Now valued: 
						<?php
						echo $game->display_coins($fair_io_value-$payout_fees); ?>
					</font>
					<br/>
					<?php
					if ($net_delta < 0) echo '<font class="redtext">Net loss of ';
					else echo '<font class="greentext">Net gain of ';
					echo $game->display_coins(abs($net_delta));
					echo '</font>';
					?>
					<br/>
					<?php
					$bought_price_usd_round = $app->round_to($bought_price_usd, 0, EXCHANGE_RATE_SIGFIGS, false);
					$track_price_usd_round = $app->round_to($track_price_usd, 0, EXCHANGE_RATE_SIGFIGS, false);
					if ($event->db_event['forex_pair_shows_nonstandard']) echo $event->db_event['track_name_short']."/USD: ".$app->round_to($bought_price_usd_round, 0, EXCHANGE_RATE_SIGFIGS, true)." &rarr; ".$app->round_to($track_price_usd_round, 0, EXCHANGE_RATE_SIGFIGS, true);
					else echo "USD/".$event->db_event['track_name_short'].": ".$app->round_to(1/$bought_price_usd_round, 0, EXCHANGE_RATE_SIGFIGS, true)." &rarr; ".$app->round_to(1/$track_price_usd_round, 0, EXCHANGE_RATE_SIGFIGS, true);
					?>
				</div>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
