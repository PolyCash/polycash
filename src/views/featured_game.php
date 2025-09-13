<?php
$last_block_id = $game->last_block_id();
$blockchain_last_block_id = $blockchain->last_block_id();
$db_last_block = $blockchain->fetch_block_by_id($blockchain_last_block_id);
$current_round_id = $game->block_to_round($blockchain_last_block_id+1);

$filter_arr = ["order_by" => $game->db_game['order_events_by']];
$event_ids = "";
list($new_event_js, $new_event_html) = $game->new_event_js($counter, $user, $filter_arr, $event_ids, true, "featured_games");

if ($user) $user_game = $blockchain->app->fetch_user_game($user->db_user['user_id'], $game->db_game['game_id']);
else $user_game = null;

$claim_amount_int = $game->get_faucet_claim_amount($user_game);

$play_now_url = '/wallet/'.$game->db_game['url_identifier'].'/';

$just_ended_events = $game->events_by_outcome_block($last_block_id);
$being_determined_events = $game->events_being_determined_in_block($last_block_id+1);

$include_being_determined_events = count($being_determined_events) > 0;
$include_betting_events = true;
?>
<script type="text/javascript">
games.push(new Game(thisPageManager, <?php
	echo $game->db_game['game_id'];
	echo ', '.$last_block_id;
	echo ', false';
	echo ', ""';
	echo ', "'.$game->db_game['payout_weight'].'"';
	echo ', '.$game->db_game['round_length'];
	echo ', 0';
	echo ', "'.$game->db_game['url_identifier'].'"';
	echo ', "'.$game->db_game['coin_name'].'"';
	echo ', "'.$game->db_game['coin_name_plural'].'"';
	echo ', "'.$game->db_game['coin_abbreviation'].'"';
	echo ', "'.$blockchain->db_blockchain['coin_name'].'"';
	echo ', "'.$blockchain->db_blockchain['coin_name_plural'].'"';
	echo ', "home", "'.$event_ids.'"';
	echo ', "'.$game->logo_image_url().'"';
	echo ', "'.$game->vote_effectiveness_function().'"';
	echo ', "'.$game->effectiveness_param1().'"';
	echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
	echo ', "'.$game->db_game['inflation'].'"';
	echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
	echo ', "'.$db_last_block['time_mined'].'"';
	echo ', "'.$game->db_game['decimal_places'].'"';
	echo ', "'.$game->blockchain->db_blockchain['decimal_places'].'"';
	echo ', "'.$game->db_game['view_mode'].'"';
	echo ', 0';
	echo ', false';
	echo ', "'.$game->db_game['default_betting_mode'].'"';
	echo ', true, '.json_encode($include_betting_events).', '.json_encode($include_being_determined_events);
?>));
</script>

<div class="col-md-12">
	<center>
		<h2 style="display: inline-block"><?php echo $game->db_game['name']; ?></h2>
		<?php
		if ($game->db_game['short_description'] != "") echo "<p>".$game->db_game['short_description']."</p>";
		?>
		<p>
			<a href="<?php echo $play_now_url; ?>" class="btn btn-sm btn-success"><i class="fas fa-play-circle"></i> &nbsp; 
			<?php if ($claim_amount_int > 0) { ?>
				Join now & receive <?php echo $game->display_coins($claim_amount_int); ?>
			<?php } else {?>
				Play Now
			<?php } ?>
			</a>
			<a href="/<?php echo $game->db_game['url_identifier']; ?>/" class="btn btn-sm btn-danger"><i class="fas fa-info"></i> &nbsp; About</a>
			<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/" class="btn btn-sm btn-primary"><i class="fas fa-list"></i> &nbsp; <?php echo ucwords($game->db_game['event_type_name']); ?> Results</a>
			<?php
			if ($game->db_game['finite_events']) {
				?>
				<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/history/" class="btn btn-sm btn-warning"><i class="fas fa-book"></i> &nbsp; Migration History</a>
				<?php
			}
			?>
		</p>
		<?php
		if ($game->db_game['module'] == "CoinBattles") {
			$game->load_current_events();
			$event = $game->current_events[0];
			list($html, $js) = $game->module->currency_chart($game, $event->db_event['event_starting_block'], false);
			echo '<div style="margin-bottom: 15px;" id="game'.$counter.'_chart_html">'.$html."</div>\n";
			echo '<div id="game'.$counter.'_chart_js"><script type="text/javascript">'.$js.'</script></div>'."\n";
		}
		
		if ($include_betting_events) {
			echo '<div style="margin-bottom: 15px;">'.$game->event_filter_html($game_instance_id, null).'</div>';
		}
		?>
	</center>
	
	<div id="game<?php echo $counter; ?>_events" class="game_events game_events_short"><?php echo $new_event_html; ?></div>
	<script type="text/javascript" id="game<?php echo $counter; ?>_new_event_js">
	<?php echo $new_event_js; ?>
	</script>
	<br/>

	<?php
	if (count($being_determined_events) > 0) {
		?>
		<div class="row" id="game<?php echo $counter; ?>_events_being_determined">
			<?php
			echo $this->render_view('being_determined_events', [
				'thisuser' => $user,
				'game' => $game,
				'events' => $being_determined_events,
				'just_ended_events' => $just_ended_events,
				'user_game' => $user_game,
				'round_id' => $current_round_id,
				'as_panel' => false,
				'game_instance_id' => $game_instance_id,
			]);
			?>
		</div>
		<br/>
		<?php
	}
	?>
</div>
