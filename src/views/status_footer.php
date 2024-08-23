<div class="status_footer">
<?php
foreach ($display_sync_games as $display_sync_game) { ?>
	<div class="status_footer_section">
		<?php
		$game_peers = $display_sync_game->fetch_all_peers();
		
		$num_in_sync = 0;
		$num_expired = 0;
		$num_out_of_sync = 0;
		$num_never_checked = 0;
		
		foreach ($game_peers as $game_peer) {
			if ($game_peer['in_sync']) $num_in_sync++;
			if ($game_peer['expired']) $num_expired++;
			if ($game_peer['out_of_sync']) $num_out_of_sync++;
			if ($game_peer['never_checked']) $num_never_checked++;
		}
		
		if (count($game_peers) == 0) $text_class = "bg-warning";
		else if ($num_in_sync > 0 && ($num_out_of_sync+$num_expired+$num_never_checked) > 0) $text_class = "bg-warning";
		else if ($num_in_sync > 0) $text_class = "bg-success";
		else $text_class = "bg-danger";
		
		echo '<div class="'.$text_class.' footer_peer_count" title="'.count($game_peers).' peer'.(count($game_peers) == 1 ? '' : 's').'" onClick="thisPageManager.open_public_peer_details('.$display_sync_game->db_game['game_id'].'); return false;">'.count($game_peers).'</div>';
		
		echo '<a href="/'.$display_sync_game->db_game['url_identifier'].'/">'.$display_sync_game->db_game['name']."</a>\n";
		
		if ($app->user_can_edit_game($thisuser, $display_sync_game)) {
			$show_internal_params = false;
			
			if ($display_sync_game->db_game['cached_definition_hash'] != $display_sync_game->db_game['defined_cached_definition_hash']) {
				$actual_game_def_hash_3 = substr($display_sync_game->db_game['cached_definition_hash'], 0, 3);
				$defined_game_def_hash_3 = substr($display_sync_game->db_game['defined_cached_definition_hash'], 0, 3);
				
				echo "<font style=\"font-size: 75%;\">";
				echo " &nbsp;&nbsp; Pending ";
				echo '<a href="/explorer/games/'.$display_sync_game->db_game['url_identifier'].'/definition/?definition_mode=actual">'.$actual_game_def_hash_3."</a>";
				echo " &rarr; ";
				echo '<a href="/explorer/games/'.$display_sync_game->db_game['url_identifier'].'/definition/?definition_mode=defined">'.$defined_game_def_hash_3."</a>\n";
				echo " &nbsp;&nbsp; <a id=\"preview_apply_def_link\" href=\"\" onclick=\"thisPageManager.preview_apply_game_definition(".$display_sync_game->db_game['game_id']."); return false;\">Preview Changes</a>";
				echo "</font>\n";
			}
		}
		?>
	</div>
	<?php
}

$online_blockchains = $app->run_query("SELECT * FROM blockchains b LEFT JOIN images i ON b.default_image_id=i.image_id WHERE b.online=1;");

while ($db_blockchain = $online_blockchains->fetch()) {
	$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
	
	echo '<div class="status_footer_section">';
	echo '<a href="/explorer/blockchains/'.$db_blockchain['url_identifier'].'/blocks/">';
	if ($db_blockchain['default_image_id'] > 0) echo '<img class="status_footer_img" src="/images/custom/'.$db_blockchain['default_image_id'].'.'.$db_blockchain['extension'].'" />';
	
	if ($blockchain->last_active_time() > time()-(60*60)) {
		echo '<font class="text-success">'.$db_blockchain['blockchain_name'].'</font>';
	}
	else {
		echo '<font class="text-danger">'.$db_blockchain['blockchain_name'].'</font>';
	}
	echo "</a>";
	echo '</div>';
}
?>
</div>
