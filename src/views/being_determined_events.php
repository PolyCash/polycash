<?php if ($as_panel) { ?>
<div class="panel panel-default">
	<div class="panel-heading">
		<div class="panel-title">
			There are <?php echo count($events)." ".$game->db_game['event_type_name_plural']; ?> in progress
		</div>
	</div>
	<div class="panel-body">
		<div class="row">
			<?php
}
			foreach ($just_ended_events as $just_ended_event) {
				list($options_by_score, $options_by_index, $is_tie, $score_disp, $in_progress_summary) = $just_ended_event->option_block_info();
				
				?>
				<div class="col-sm-12">
					<p>
						<?php
						if ($is_tie) {
							$winner = $game->blockchain->app->fetch_option_by_event_option_index($just_ended_event->db_event['event_id'], $just_ended_event->db_event['outcome_index']);
							echo "Tied ".$score_disp.", ".$winner['name']." won in overtime";
						}
						else {
							echo $options_by_score[0]['name']." beat ".$options_by_score[1]['name']." ".$score_disp;
						}
						?>
						&nbsp;&nbsp; <a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/<?php echo $just_ended_event->db_event['event_index']; ?>">See details</a>
					</p>
				</div>
				<?php
			}
			
			$render_event_i = 0;
			foreach ($events as $event) {
				?>
				<div class="col-sm-6">
					<div style="width: 100%; padding: 0px 8px; border: 1px solid #aaa; background-color: #fff;">
						<?php echo $event->event_html($thisuser, false, true, null, $render_event_i); ?>
						<?php if ($user_game) echo $event->my_votes_table($round_id, $user_game); ?>
					</div>
				</div>
				<?php
				$render_event_i++;
			}
if ($as_panel) {
			?>
		</div>
	</div>
</div>
<?php
}
