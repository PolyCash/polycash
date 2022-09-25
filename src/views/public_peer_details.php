<div class="modal-header">
	<b class="modal-title">Peers for <?php echo $game->db_game['name']; ?></b>
	
	<button type="button" class="close" data-dismiss="modal" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
<div class="modal-body">
	<p>
		<?php echo $game->db_game['name']; ?> is connected to <?php echo count($game_peers)." peer".(count($game_peers) == 1 ? "" : "s"); ?>.
		<?php if ($can_edit_game) { ?>
			&nbsp; <a href="/peers/<?php echo $game->db_game['url_identifier']; ?>">Manage Peers</a>
		<?php } ?>
	</p>
	
	<?php
	foreach ($game_peers as $game_peer) {
		if ($game_peer['in_sync']) $text_class = 'text-success';
		else if ($game_peer['expired']) $text_class = 'text-warning';
		else if ($game_peer['out_of_sync']) $text_class = 'text-danger';
		else $text_class = "";
		?>
		<div class="row <?php echo $text_class; ?>">
			<div class="col-sm-2">Peer #<?php echo $game_peer['game_peer_id']; ?></div>
			<div class="col-sm-8">
				<?php if ($game_peer['in_sync'] || $game_peer['expired']) { ?>
				Verified fully in sync <?php echo $game->blockchain->app->format_seconds(time()-$game_peer['last_sync_check_at']); ?> ago
				<?php } else if ($game_peer['out_of_sync']) { ?>
				Out of sync for the past <?php echo $game->blockchain->app->format_seconds(time()-$game_peer['out_of_sync_since']) ;?>, diverged on block #<?php echo $game_peer['out_of_sync_block']; ?>
				<?php } else { ?>
				Never checked sync status
				<?php } ?>
			</div>
		</div>
		<?php
	}
	?>
</div>
