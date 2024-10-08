<?php
if ($game_peer['in_sync']) $text_class = 'text-success';
else if ($game_peer['expired']) $text_class = 'text-warning';
else if ($game_peer['out_of_sync']) $text_class = 'text-danger';
else $text_class = "";
?>
<font class="<?php echo $text_class; ?>">
	<?php if ($game_peer['in_sync'] || $game_peer['expired']) { ?>
	Verified fully in sync <?php echo $game->blockchain->app->format_seconds(time()-$game_peer['last_sync_check_at']); ?> ago
	<?php } else if ($game_peer['out_of_sync']) { ?>
		Out of sync for the past <?php
		echo $game->blockchain->app->format_seconds(time()-$game_peer['out_of_sync_since']);
		if ((string)$game_peer['out_of_sync_block'] !== "") echo ', diverged on block <a href="/explorer/games/'.$game->db_game['url_identifier'].'/blocks/'.$game_peer['out_of_sync_block'].'">#'.$game_peer['out_of_sync_block'].'</a>';
		if ((string)$game_peer['out_of_sync_txo_pos'] !== "") echo ', TXO <a href="/explorer/games/'.$game->db_game['url_identifier'].'/utxo/'.$game_peer['out_of_sync_txo_pos'].'">#'.$game_peer['out_of_sync_txo_pos'].'</a>';
	} else { ?>
	Never checked sync status
	<?php } ?>
</font>
