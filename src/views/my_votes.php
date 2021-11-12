<?php
$my_votes_q = "SELECT p.*, p.contract_parts AS total_contract_parts, gio.contract_parts, gio.is_game_coinbase, gio.game_out_index AS game_out_index, op.*, ev.*, p.votes, op.votes AS option_votes, op.effective_destroy_score AS option_effective_destroy_score, ev.destroy_score AS sum_destroy_score, ev.effective_destroy_score AS sum_effective_destroy_score, t.transaction_id, t.tx_hash, t.fee_amount, io.spend_status FROM transaction_game_ios gio JOIN transaction_ios io ON gio.io_id=io.io_id JOIN transaction_game_ios p ON gio.parent_io_id=p.game_io_id JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN options op ON gio.option_id=op.option_id JOIN events ev ON op.event_id=ev.event_id JOIN address_keys k ON io.address_id=k.address_id WHERE gio.event_id=:event_id AND k.account_id=:account_id AND gio.resolved_before_spent=1";
if ($color == "green") $my_votes_q .= " AND io.create_block_id IS NOT NULL";
else $my_votes_q .= " AND io.create_block_id IS NULL";
$my_votes_q .= " ORDER BY op.event_option_index ASC;";
$my_votes = $app->run_query($my_votes_q, [
	'event_id' => $event->db_event['event_id'],
	'account_id' => $user_game['account_id']
]);

while ($my_vote = $my_votes->fetch()) {
	$unconfirmed_votes = 0;
	$temp_html = "";
	list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees) = $game->get_payout_info($my_vote, $coins_per_vote, $last_block_id, $temp_html);
	
	?>
	<div class="row">
		<?php echo $temp_html; ?>
		<?php
		$coin_stake = (($my_vote['contract_parts']/$my_vote['total_contract_parts'])*$my_vote['destroy_amount']) + $inflation_stake;
		
		if ($event->db_event['payout_rule'] == "binary") {
			$payout_disp = $app->format_bignum(($max_payout-$coin_stake)/pow(10,$game->db_game['decimal_places']));
			?>
			<div class="col-sm-6">
				<font class="<?php echo $color ;?>text">
					<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_vote['tx_hash'].'/'.$my_vote['game_out_index']; ?>">
						Staked <?php echo $app->format_bignum($coin_stake/pow(10,$game->db_game['decimal_places'])); ?>
					</a> 
					on <?php echo $my_vote['name']; ?>
				</font>
			</div>
			
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text">
					<?php
					if ($max_payout-$coin_stake > 0) echo '+'.$payout_disp;
					else echo $payout_disp;
					
					echo ' '.$game->db_game['coin_name_plural']." &nbsp; (x".$app->format_bignum($odds).")";
					?>
				</font>
			</div>
			<?php
			if (empty($betinfo_by_option_id[$my_vote['option_id']])) $betinfo_by_option_id[$my_vote['option_id']] = ['spent' => 0, 'payout' => 0];
			$betinfo_by_option_id[$my_vote['option_id']]['spent'] += $coin_stake;
			$betinfo_by_option_id[$my_vote['option_id']]['payout'] += $max_payout;
		}
		else {
			?>
			<div class="col-sm-6">
				<font class="<?php echo $color; ?>text"><?php echo $my_vote['name']; ?></font><br/>
				<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier'].'/utxo/'.$my_vote['tx_hash'].'/'.$my_vote['game_out_index']; ?>">
					Paid <?php echo $app->format_bignum($coin_stake/pow(10,$game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural']; ?>
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
					echo $app->format_bignum(($fair_io_value-$payout_fees)/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']; ?>
				</font>
				@ $<?php echo $app->format_bignum($track_pay_price);
				if ($track_pay_price != $track_price_usd) echo " ($".$app->format_bignum($track_price_usd).")";
				?>
				<br/>
				<?php
				if ($my_vote['event_option_index'] != 0) echo '-';
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
				echo $app->format_bignum(abs($net_delta)/pow(10, $game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'];
				$html .= '</font>';
				?>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
