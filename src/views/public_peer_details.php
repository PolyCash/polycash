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
		?>
		<div class="row">
			<div class="col-sm-2">Peer #<?php echo $game_peer['game_peer_id']; ?></div>
			<div class="col-sm-8">
				<?php
				include(AppSettings::srcPath().'/views/peer_status_label.php');
				?>
			</div>
		</div>
		<?php
	}
	?>
</div>
