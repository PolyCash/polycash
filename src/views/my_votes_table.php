<?php
$last_block_id = $blockchain->last_block_id();
$html = "";
$confirmed_html = "";
$unconfirmed_html = "";

$betinfo_by_option_id = [];
$coins_per_vote = $app->coins_per_vote($game->db_game);
$unconfirmed_html = $event->my_votes_html("yellow", $coins_per_vote, $user_game, $last_block_id, $betinfo_by_option_id);
$confirmed_html = $event->my_votes_html("green", $coins_per_vote, $user_game, $last_block_id, $betinfo_by_option_id);

if (strlen($unconfirmed_html.$confirmed_html) > 0) {
	if ($user_game['net_risk_view'] && $event->db_event['payout_rule'] == "binary") {
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
							echo $app->format_bignum(abs($potential_delta)/pow(10, $game->db_game['decimal_places'])).' '.$game->db_game['coin_name_plural'];
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
			<?php echo $unconfirmed_html.$confirmed_html; ?>
		</div>
		<?php
	}
}
