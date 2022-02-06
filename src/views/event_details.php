<div class="modal-header">
	<b class="modal-title">Event details: <?php echo $event->db_event['event_name']; ?></b>
	
	<button type="button" class="close" data-dismiss="modal" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
<div class="modal-body">
	<p>
		<table style="width: 100%;">
			<tr>
				<td style="width: 200px;">
					Event ID:
				</td>
				<td>
					<a target="_blank" href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/<?php echo $event->db_event['event_index']; ?>">
						<?php echo $event->db_event['event_index']; ?>
					</a>
				</td>
			</tr>
			<tr>
				<td>Season:</td><td>#<?php echo $event->db_event['season_index']+1; ?></td>
			</tr>
			<tr>
				<td>Home team:</td><td><?php echo $options[0]['name']; ?></td>
			</tr>
			<tr>
				<td>Away team:</td><td><?php echo $options[1]['name']; ?></td>
			</tr>
		</table>
	</p>
	
	<p>
		<?php echo $options[0]['name']; ?> had a target score of <?php echo $options[0]['target_score']; ?> points for this game.<br/>
		<?php echo $options[1]['name']; ?> had a target score of <?php echo $options[1]['target_score']; ?> points for this game.<br/>
		These teams averaged <?php echo $event_past_avg; ?> in prior games, so each team's target was set
		<?php
		if ($event_score_boost >= 0) echo '<font class="text-success">'.$event_score_boost.'</font> points above';
		else echo '<font class="text-danger">'.abs($event_score_boost).'</font> points below';
		?>
		their prior average.
	</p>
	
	<p>
		<table>
			<tr>
				<?php foreach ($options as $option) { ?>
					<td style="vertical-align: top; padding-right: 20px;">
						<p><b><?php echo $option['name']; ?></b></p>
						<?php if (!empty($past_events[$option['option_id']])) { ?>
							<p>Won <?php echo $option['past_wins']."/".count($past_events[$option['option_id']]); ?> prior games, averaging <?php echo round($option['past_average_score'], 8); ?> points per game.</p>
							<?php foreach ($past_events[$option['option_id']] as $past_event_id => $past_event) { ?>
								<?php
								if ($past_event['winning_entity_id'] == $option['entity_id']) echo '<font class="text-success">Won</font>';
								else echo '<font class="text-danger">Lost</font>';
								?>
								&nbsp;
								<?php
								$score_disp = "";
								foreach ($past_event['options'] as $past_option_id => $past_option) {
									if ($past_option['entity_id'] == $option['entity_id']) $score_disp .= "<b>";
									$score_disp .= $past_option['option_block_score'];
									if ($past_option['entity_id'] == $option['entity_id']) $score_disp .= "</b>";
									$score_disp .= "-";
								}
								$score_disp = substr($score_disp, 0, -1);
								echo $score_disp;
								?>
								&nbsp;
								<a target="_blank" style="color: #333;" href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/<?php echo $past_event['event_index']; ?>"><?php echo $past_event['event_name']; ?></a>
								<br/>
							<?php } ?>
						<?php } else { ?>
							<p>No prior games.</p>
						<?php } ?>
					</td>
				<?php } ?>
			</tr>
		</table>
	</p>
</div>
