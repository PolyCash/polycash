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
