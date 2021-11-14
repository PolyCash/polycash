<?php
$last_block_id = $blockchain->last_block_id();
$html = "";
$confirmed_html = "";
$unconfirmed_html = "";

$coins_per_vote = $app->coins_per_vote($game->db_game);

$unconfirmed_bets = $game->my_bets_in_event($event->db_event['event_id'], $user_game['account_id'], false);
$confirmed_bets = $game->my_bets_in_event($event->db_event['event_id'], $user_game['account_id'], true);

if (count($confirmed_bets)+count($unconfirmed_bets) > 0) {
	if ($user_game['net_risk_view'] && $event->db_event['payout_rule'] == "binary") {
		$betinfo_by_option_id = [];
		
		foreach ($unconfirmed_bets as $my_bet) {
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees, $coin_stake) = $game->get_payout_info($my_bet, $coins_per_vote, $last_block_id);
			
			if (empty($betinfo_by_option_id[$my_bet['option_id']])) $betinfo_by_option_id[$my_bet['option_id']] = ['spent' => 0, 'payout' => 0];
			$betinfo_by_option_id[$my_bet['option_id']]['spent'] += $coin_stake;
			$betinfo_by_option_id[$my_bet['option_id']]['payout'] += $max_payout;
		}
		
		foreach ($confirmed_bets as $my_bet) {
			list($track_entity, $track_price_usd, $track_pay_price, $asset_price_usd, $bought_price_usd, $fair_io_value, $inflation_stake, $effective_stake, $unconfirmed_votes, $max_payout, $odds, $effective_paid, $equivalent_contracts, $event_equivalent_contracts, $track_position_price, $bought_leverage, $current_leverage, $borrow_delta, $net_delta, $payout_fees, $coin_stake) = $game->get_payout_info($my_bet, $coins_per_vote, $last_block_id);
			
			if (empty($betinfo_by_option_id[$my_bet['option_id']])) $betinfo_by_option_id[$my_bet['option_id']] = ['spent' => 0, 'payout' => 0];
			$betinfo_by_option_id[$my_bet['option_id']]['spent'] += $coin_stake;
			$betinfo_by_option_id[$my_bet['option_id']]['payout'] += $max_payout;
		}
		
		$options = $app->fetch_options_by_event($event->db_event['event_id'], $require_entities=false)->fetchAll(PDO::FETCH_ASSOC);
		$options = AppSettings::arrayToMapOnKey($options, "option_id");
		$sum_spent = 0;
		foreach ($betinfo_by_option_id as $option_id => $betinfo) {
			$sum_spent += $betinfo['spent'];
		}
		?>
		<div class="my_votes_table">
			<div class="row my_votes_header">
				<div class="col-sm-6">Amount Bet</div>
				<div class="col-sm-6">Net Win</div>
			</div>
			<?php
			foreach ($options as $option_id => $db_option) {
				$payout_this_op = $betinfo_by_option_id[$option_id]['payout'] ?? 0;
				$spent_this_op = $betinfo_by_option_id[$option_id]['spent'] ?? 0;
				$potential_delta = $payout_this_op - $sum_spent;
				?>
				<font class="<?php echo ($potential_delta >= 0 ? 'green' : 'red'); ?>text">
					<div class="row">
						<div class="col-sm-6">Staked <?php echo $app->format_bignum($spent_this_op/pow(10, $game->db_game['decimal_places'])).' on '.$db_option->name; ?>
						</div>
						<div class="col-sm-6">
							<?php
							if ($potential_delta >= 0) echo '+';
							else echo '-';
							echo $game->display_coins(abs($potential_delta));
							?>
						</div>
					</div>
				</font>
				<?php
			}
			?>
		</div>
		<?php
	}
	else {
		?>
		<div class="my_votes_table">
			<div class="row my_votes_header">
				<div class="col-sm-6">Amount Bet</div>
				<div class="col-sm-6">To Win</div>
			</div>
			<?php
			echo $app->render_view('my_votes', [
				'app' => $app,
				'game' => $game,
				'event' => $event,
				'my_bets' => $unconfirmed_bets,
				'color' => 'yellow',
				'coins_per_vote' => $coins_per_vote,
				'user_game' => $user_game,
				'last_block_id' => $last_block_id,
			]);
			
			echo $app->render_view('my_votes', [
				'app' => $app,
				'game' => $game,
				'event' => $event,
				'my_bets' => $confirmed_bets,
				'color' => 'green',
				'coins_per_vote' => $coins_per_vote,
				'user_game' => $user_game,
				'last_block_id' => $last_block_id,
			]);
			?>
		</div>
		<?php
	}
}
