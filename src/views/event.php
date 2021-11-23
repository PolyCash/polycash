<?php
$score_field = $game->db_game['payout_weight']."_score";

$last_block_id = $blockchain->last_block_id();
$max_block_id = min($event->db_event['event_final_block'], $last_block_id);

$coins_per_vote = $app->coins_per_vote($game->db_game);

$display_mode = "slim";

$option_max_width = $event->db_event['option_max_width'];
if ($display_mode == "slim") $option_max_width = min(100, $option_max_width);

$sq_px_per_pct_point = pow($option_max_width, 2)/100;
$min_px_diam = 20;

$round_stats_all = false;
$winner = false;

list($winning_option_id, $winning_votes, $winning_effective_destroy_score) = $event->determine_winning_option($round_stats_all);

if ((string)$event->db_event['outcome_index'] !== "") {
	$expected_winner = $app->fetch_option_by_event_option_index($event->db_event['event_id'], $event->db_event['outcome_index']);
}
else $expected_winner = false;

$game_defined_winner = false;
$gde = $app->fetch_game_defined_event_by_index($game->db_game['game_id'], $event->db_event['event_index']);

if ($gde) {
	if ((string)$gde['outcome_index'] !== "") {
		$game_defined_winner = $app->fetch_option_by_event_option_index($event->db_event['event_id'], $gde['outcome_index']);
	}
}

$sum_votes = $round_stats_all[0];
$max_sum_votes = $round_stats_all[1];
$round_stats = $round_stats_all[2];
$option_id_to_rank = $round_stats_all[3];
$confirmed_sum_votes = $round_stats_all[4];
$unconfirmed_sum_votes = $round_stats_all[5];
$confirmed_score = $round_stats_all[6];
$unconfirmed_score = $round_stats_all[7];
$destroy_score = $round_stats_all[8];
$unconfirmed_destroy_score = $round_stats_all[9];
$effective_destroy_score = $round_stats_all[10];
$unconfirmed_effective_destroy_score = $round_stats_all[11];

$event_effective_coins = $sum_votes*$coins_per_vote + $effective_destroy_score + $unconfirmed_effective_destroy_score;

list($inflationary_reward, $destroy_reward, $total_reward) = $event->event_rewards();

$blocks_left = $event->db_event['event_final_block'] - $max_block_id;
?>
<p><div class="event_timer_slim">
<font style="font-size: 88%">
<?php
if (!empty($event->db_event['event_final_time']) && $blocks_left > 0) {
	$sec_left = strtotime($event->db_event['event_final_time'])-time();
	if ($sec_left <= 0) {
		?>
		<font class="redtext">Expired <?php echo $app->format_seconds(-1*$sec_left); ?> ago</font>
		<br/>
		<?php
	}
}

if ($event->db_event['event_starting_block'] > $last_block_id+1) {
	$blocks_to_start = $event->db_event['event_starting_block'] - $last_block_id;
	$sec_to_start = $blockchain->seconds_per_block('average')*$blocks_to_start;
	?>
	Betting starts in <?php echo number_format($blocks_to_start); ?> block<?php echo $blocks_to_start=="1" ? "" : "s"; ?> (<?php echo $app->format_seconds($sec_to_start); ?>)
	<br/>
	<?php
}
else if ($blocks_left > 0) {
	$sec_left = $blockchain->seconds_per_block('average')*$blocks_left;
	echo $app->format_bignum($blocks_left); ?> betting block<?php echo $blocks_left=="1" ? "" : "s"; ?> left (<?php echo $app->format_seconds($sec_left); ?>)<br/>
	<?php
}

if ($last_block_id < $event->db_event['event_payout_block']) {
	$payout_blocks_left = $event->db_event['event_payout_block'] - $last_block_id;
	
	if (!empty($event->db_event['event_payout_time'])) {
		?>
		Pays out at <?php echo $event->db_event['event_payout_time']; ?> UTC (<?php echo $app->format_seconds(strtotime($event->db_event['event_payout_time'])-time()); ?>)
		<?php
	}
	else {
		?>
		Pays out in <?php echo $app->format_seconds($blockchain->seconds_per_block('average')*$payout_blocks_left);
	}
}
else {
	$payout_block = $blockchain->fetch_block_by_id($event->db_event['event_payout_block']);
	?>
	Paid <?php echo $app->format_seconds(time()-$payout_block['time_mined']); ?> ago<br/>
	<?php echo date("Y-m-d H:m:s", $payout_block['time_mined']); ?> UTC
	<?php
}
if ($event->db_event['payout_rate'] != 1) {
	?>
	<br/>
	<?php echo $app->format_percentage((1-$event->db_event['payout_rate'])*100); ?>% fee
	<?php
}
?>
</font>

</div>
</p>

<strong>
<a style="color: #000; text-decoration: underline; display: inline-block;" target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier']."/events/".$event->db_event['event_index']; ?>"><?php echo $event->db_event['event_name']; ?></a></strong>

<?php
if (!empty($event->db_event['option_block_rule'])) {
	$option_ids = [];
	$scores = [];
	
	if ($display_mode == "default") {
		if ($game->last_block_id()+1 >= $event->db_event['event_determined_from_block']) {
			?>
			<div class="event_score_box">
				Current Scores:<br/>
				<?php
				for ($i=0; $i<count($round_stats); $i++) {
					?>
					<div class="row">
						<div class="col-sm-6 boldtext"><?php echo $round_stats[$i]['entity_name']; ?></div>
						<div class="col-sm-6"><?php echo $round_stats[$i]['option_block_score']; ?></div>
					</div>
					<?php
				}
				?>
			</div>
			<?php
		}
	}
	else {
		if ($game->last_block_id()+1 >= $event->db_event['event_determined_from_block']) {
			list($options_by_score, $options_by_index, $is_tie, $score_disp, $in_progress_summary) = $event->option_block_info();
			
			echo " &nbsp;&nbsp; ".$score_disp." &nbsp; ".$in_progress_summary;
		}
	}
}
?>
</p>
<?php
if (!empty($event->db_event['sport_name']) || !empty($event->db_event['league_name'])) {
	echo "<p>".$event->db_event['sport_name']." &nbsp;&nbsp; ".$event->db_event['league_name']."</p>\n";
}

if ($last_block_id >= $event->db_event['event_final_block']) {
	$clickable = false;
}
else if ($last_block_id >= $event->db_event['event_final_block']-$event->avoid_bet_buffer_blocks) {
	?>
	<p class="text-warning">Betting is about to end</p>
	<?php
	$clickable = false;
}

if ($event->db_event['outcome_index'] == "-1") {
	?>
	<p class="redtext">This event has been canceled</p>
	<?php
}
else if ($expected_winner || $game_defined_winner) {
	?>
	<p class="greentext">Winner: 
		<?php
		if ($expected_winner) echo $expected_winner['name'];
		if ($expected_winner && $game_defined_winner && $expected_winner['option_id'] != $game_defined_winner['option_id'] || ($expected_winner && !$game_defined_winner)) echo " &rarr; ";
		if ($game_defined_winner && (!$expected_winner || ($expected_winner && $expected_winner['option_id'] != $game_defined_winner['option_id']))) echo $game_defined_winner['name'];
		if ($expected_winner && !$game_defined_winner) echo "Unset";
		?>
	</p>
	<?php
}

if (!empty($event->db_event['option_block_rule'])) {
	$target_score_disp = "";
	$is_tie = true;
	$last_target_score = null;
	$best_target_score = null;
	$predicted_winner = null;
	foreach ($round_stats as $option) {
		if ((string)$option['target_score'] !== "") {
			$rounded_score = round($option['target_score']);
			if ($last_target_score === null) $last_target_score = $rounded_score;
			if ($rounded_score !== $last_target_score) $is_tie = false;
			if ($best_target_score === null || $rounded_score > $best_target_score) {
				$best_target_score = $rounded_score;
				$predicted_winner = $option;
			}
			$target_score_disp .= $rounded_score."-";
		}
	}
	if ($target_score_disp !== "") {
		$was_is = $game->last_block_id() >= $event->db_event['event_final_block'] ? "was" : "is";
		echo "<p>";
		if ($is_tie) echo "A tie ".$was_is." predicted";
		else echo $predicted_winner['name']." ".$was_is." predicted to win ".substr($target_score_disp, 0, strlen($target_score_disp)-1);
		echo "</p>\n";
	}
}

if ($game->db_game['inflation'] == "exponential") {
	$confirmed_coins = $destroy_score + $confirmed_score*$coins_per_vote;
	$unconfirmed_coins = $total_reward - $confirmed_coins;
	
	if ($event->db_event['payout_rule'] == "binary") {
		echo "<p>".$game->display_coins($confirmed_coins)." in confirmed bets";
		if ($unconfirmed_coins > 0) echo ", ".$app->format_bignum($unconfirmed_coins/pow(10,$game->db_game['decimal_places']))." unconfirmed";
		echo "</p>\n";
	}
	else {
		$two_sided_contract_price = $event->db_event['track_max_price']-$event->db_event['track_min_price'];
		$confirmed_equivalent_contracts = $confirmed_coins/$two_sided_contract_price/pow(10,$game->db_game['decimal_places']);
		$unconfirmed_equivalent_contracts = $unconfirmed_coins/$two_sided_contract_price/pow(10,$game->db_game['decimal_places']);
		
		echo "<p>".$app->format_bignum($confirmed_equivalent_contracts)." ".$event->db_event['track_name_short']." issued at $".$app->format_bignum($two_sided_contract_price)." per contract";
		if ($unconfirmed_coins > 0) echo " +&nbsp;".$app->format_bignum($unconfirmed_equivalent_contracts)."&nbsp;unconfirmed&nbsp;".$event->db_event['track_name_short']."<br/>\n";
		echo " (".str_replace(" ", "&nbsp;", $game->display_coins($confirmed_coins+$unconfirmed_coins)).")";
		echo "</p>\n";
	}
}

if ($game->db_game['module'] == "CryptoDuels") {
	$btc_currency = $app->get_currency_by_abbreviation("BTC");
	$event_starting_block = $blockchain->fetch_block_by_id($event->db_event['event_starting_block']);
	$event_final_block = $blockchain->fetch_block_by_id($event->db_event['event_final_block']);
	if ($event_final_block && !empty($event_final_block['time_mined'])) $event_to_time = $event_final_block['time_mined'];
	else $event_to_time = time();
}

if ($event->db_event['payout_rule'] == "linear") {
	$track_entity = $app->fetch_entity_by_id($round_stats[0]['entity_id']);
	
	$track_price_info = $app->exchange_rate_between_currencies(1, $track_entity['currency_id'], time(), 6);
	$track_price_usd = max($event->db_event['track_min_price'], min($event->db_event['track_max_price'], $track_price_info['exchange_rate']));
	
	// For tracked asset events, the buy position is always the first option (min option ID)
	$min_option_id = min(array_keys($option_id_to_rank));
	$min_option_index = $option_id_to_rank[$min_option_id];
	
	$buy_pos_votes = $round_stats[$min_option_index]['votes'] + $round_stats[$min_option_index]['unconfirmed_votes'];
	$buy_pos_effective_coins = $buy_pos_votes*$coins_per_vote + $round_stats[$min_option_index]['effective_destroy_score'] + $round_stats[$min_option_index]['unconfirmed_effective_destroy_score'];
	
	if ($last_block_id < $event->db_event['event_payout_block']) {
		echo "Market price: &nbsp; $".$app->round_to($track_price_usd, 2, 4, true);
		if (time()-$track_price_info['time'] >= 60*30) echo ' &nbsp; <font class="redtext">'.$app->format_seconds(time()-$track_price_info['time'])." ago</font>";
		echo "<br/>\n";
	}
	
	$buy_pos_payout_frac = false;
	$our_buy_price = false;
	
	if ($event_effective_coins > 0) {
		$buy_pos_payout_frac = $buy_pos_effective_coins/$event_effective_coins;
		$our_buy_price = $event->db_event['track_min_price'] + $buy_pos_payout_frac*($event->db_event['track_max_price']-$event->db_event['track_min_price']);
		
		if ($last_block_id < $event->db_event['event_final_block']) {
			?>Buy here for: &nbsp; $<?php echo $app->round_to($our_buy_price, 2, 4, true); ?><br/><?php
		}
		else {
			?>Bought at: &nbsp; $<?php echo $app->round_to($our_buy_price, 2, 4, true); ?><br/><?php
		}
	}
	
	if ((string)$event->db_event['track_payout_price'] != "") {
		if ($our_buy_price) $pct_gain = 100*($event->db_event['track_payout_price']/$our_buy_price-1);
		else $pct_gain = 0;
		echo "Paid out at: &nbsp; $".$app->format_bignum($event->db_event['track_payout_price'])."<br/>\n";
	}
	else if ($our_buy_price > 0) {
		$pct_gain = 100*($track_price_usd/$our_buy_price-1);
	}
	else $pct_gain = 0;
	
	$pct_gain = round($pct_gain, 2);
	
	echo $event-> db_event['track_name_short'];
	
	if ($pct_gain >= 0) {
		?> up <font class="greentext"><?php echo $pct_gain; ?>%</font><?php
	}
	else {
		?> down <font class="redtext"><?php echo abs($pct_gain); ?>%</font><?php
	}
	?>
	<br/>
	<?php
}

for ($i=0; $i<count($round_stats); $i++) {
	$option_votes = $round_stats[$i]['votes'] + $round_stats[$i]['unconfirmed_votes'];
	$option_effective_coins = $option_votes*$coins_per_vote + $round_stats[$i]['effective_destroy_score'] + $round_stats[$i]['unconfirmed_effective_destroy_score'];
	
	if ($event->db_event['event_winning_rule'] == "max_below_cap" && !$winning_option_id && $option_votes <= $max_sum_votes && $option_votes > 0) $winning_option_id = $round_stats[$i]['option_id'];
	
	if ($option_effective_coins > 0 && $event_effective_coins > 0) {
		$pct_votes = 100*(floor(1000*$option_effective_coins/$event_effective_coins)/1000);
		$odds = $event->db_event['payout_rate']*$event_effective_coins/$option_effective_coins;
		$odds_disp = "x".$app->round_to($odds, 2, 4, true);
	}
	else {
		$pct_votes = 0;
		$odds_disp = "";
	}
	
	$sq_px = $pct_votes*$sq_px_per_pct_point;
	$box_diam = round(sqrt($sq_px));
	if ($box_diam < $min_px_diam) $box_diam = $min_px_diam;
	
	$holder_width = $box_diam;
	
	$show_boundbox = false;
	if (!empty($event->db_event['max_voting_fraction']) && $event->db_event['max_voting_fraction'] != 1 && ($i == 0 || $option_votes > $max_sum_votes)) {
		$show_boundbox = true;
		$boundbox_sq_px = $event->db_event['max_voting_fraction']*100*$sq_px_per_pct_point;
		$boundbox_diam = round(sqrt($boundbox_sq_px));
		if ($boundbox_diam > $holder_width) $holder_width = $boundbox_diam;
	}
	?>
	<div class="vote_option_box_container">
	<?php
	if ($game->db_game['view_mode'] == "simple") {
		$onclick_html = 'if (!thisPageManager.transaction_in_progress) {thisPageManager.add_utxo_to_vote(utxo_spend_offset); games['.$game_instance_id.'].add_option_to_vote('.$game_event_index.', '.$round_stats[$i]['option_id'].'); thisPageManager.confirm_compose_bets(); setTimeout(function() {games[0].show_next_event()}, 1200);}';
		echo '<img id="option'.$round_stats[$i]['option_id'].'_image" src="" style="cursor: pointer; max-width: 400px; max-height: 400px; border: 1px solid black; margin-bottom: 5px;" onclick="'.$onclick_html.'" />';
	}
	else $onclick_html = 'games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');';
	
	if ($game->db_game['module'] == "CryptoDuels") {
		$db_currency = $app->fetch_currency_by_name($round_stats[$i]['name']);
		$initial_price = $app->currency_price_after_time($db_currency['currency_id'], $btc_currency['currency_id'], $event_starting_block['time_mined'], $event_to_time);
		
		if ($round_stats[$i]['name'] == "Bitcoin") {
			$final_price = 0;
			$final_performance = 1;
		}
		else {
			$final_price = $app->currency_price_at_time($db_currency['currency_id'], $btc_currency['currency_id'], $event_to_time);
			if (empty($initial_price['price'])) $final_performance = 1;
			else $final_performance = $final_price['price']/$initial_price['price'];
		}
	}
	?>
	<div class="vote_option_label<?php
		if ($event->db_event['event_winning_rule'] == "max_below_cap") {
			if ($option_votes > $max_sum_votes) $html .=  " redtext";
			else if ($winning_option_id == $round_stats[$i]['option_id']) $html .=  " greentext";
		}
		?>"
		<?php
		if ($clickable) {
			?> style="cursor: pointer;" onclick="<?php echo $onclick_html; ?>"
			<?php
		}
		?>>
		<?php echo $round_stats[$i]['name']; ?>
		<?php
		if ($event->db_event['payout_rule'] == "binary") {
			if (!empty($odds_disp)) echo ' &nbsp; '.$odds_disp;
		}
		else {
			if ($our_buy_price) {
				if ($round_stats[$i]['event_option_index'] == 0) $position_price = $our_buy_price-$event->db_event['track_min_price'];
				else $position_price = $event->db_event['track_max_price']-$our_buy_price;
				
				?><font class="greentext">$<?php echo number_format($position_price, 2); ?></font><?php
			}
		}
		?> &nbsp; (<?php echo $pct_votes; ?>%)<?php
		
		if ($game->db_game['module'] == "CryptoDuels") {
			?><br/><?php
			if ($final_performance >= 1) { ?><font class="greentext">Up <?php echo round(($final_performance-1)*100, 3); ?>%</font><?php }
			else {?><font class="redtext">Down <?php echo round((1-$final_performance)*100, 3); ?>%</font><?php }
		}
		?>
	</div>
	<?php
	if ($game->db_game['view_mode'] == "simple") {}
	else {
		?>
		<div class="stage vote_option_box_holder" style="height: <?php echo $holder_width; ?>px; width: <?php echo $holder_width; ?>px;">
		<?php
		if ($show_boundbox) {
			?>
			<div onclick="games[<?php echo $game_instance_id; ?>].events[<?php echo $game_event_index; ?>].start_vote(<?php echo $round_stats[$i]['option_id']; ?>);" class="vote_option_boundbox" style="cursor: pointer; height: <?php echo $boundbox_diam; ?>px; width: <?php echo $boundbox_diam; ?>px;<?php
				if ($holder_width != $boundbox_diam) echo 'left: '.(($holder_width-$boundbox_diam)/2).'px; top: '.(($holder_width-$boundbox_diam)/2).'px;';
			?>">
			</div>
			<?php
		}
		?>
		<div class="ball vote_option_box" style="width: <?php echo $box_diam; ?>px; height: <?php echo $box_diam; ?>px;<?php
			if ($holder_width != $box_diam) echo 'left: '.(($holder_width-$box_diam)/2).'px; top: '.(($holder_width-$box_diam)/2).'px;';
			
			if ($round_stats[$i]['image_id'] > 0) $bg_im_url = $app->image_url($round_stats[$i]);
			else if (!empty($round_stats[$i]['content_url'])) $bg_im_url = $round_stats[$i]['content_url'];
			else $bg_im_url = "";
			if ($bg_im_url != "") echo 'background-image: url('.$app->quote_escape($bg_im_url).');';
			
			if ($clickable) echo 'cursor: pointer;';
			if ($event->db_event['event_winning_rule'] == "max_below_cap" && $option_votes > $max_sum_votes) echo 'opacity: 0.5;';
			echo '" id="game'.$game_instance_id.'_event'.$game_event_index.'_vote_option_'.$i.'"';
			if ($clickable) echo ' onclick="games['.$game_instance_id.'].events['.$game_event_index.'].start_vote('.$round_stats[$i]['option_id'].');"';
			?>>
				<input type="hidden" id="game<?php echo $game_instance_id; ?>_event<?php echo $game_event_index; ?>_option_id2rank_<?php echo $round_stats[$i]['option_id']; ?>" value="<?php echo $i; ?>" />
				<input type="hidden" id="game<?php echo $game_instance_id; ?>_event<?php echo $game_event_index; ?>_rank2option_id_<?php echo $i; ?>" value="<?php echo $round_stats[$i]['option_id']; ?>" />
			</div>
		</div>
		<?php
	}
	?>
	</div>
	<?php
}
