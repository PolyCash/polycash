  </div>
</div>
<div style="display: none;" id="chatWindowTemplate">
	<div class="chatWindowHeader" id="chatWindowHeaderCHATID">
		<div class="chatWindowTitle" id="chatWindowTitleCHATID"></div>
		<font class="chatWindowCloseBtn" onclick="thisPageManager.closeChatWindow(CHATID);">&#215;</font>
		<div class="chatWindowContent" id="chatWindowContentCHATID"></div>
		<input class="chatWindowWriter" id="chatWindowWriterCHATID" />
		<button class="btn btn-sm btn-primary" id="chatWindowSendBtnCHATID" onclick="thisPageManager.sendChatMessage(CHATID);">Send</button>
	</div>
</div>
<footer class="footer" id="chatWindows"></footer>
<footer class="footer status_footer">
	<div class="status_footer_right">
		<div class="status_footer_section">
			Loaded in <?php echo round(microtime(true)-$pageload_start_time, 2); ?> sec.
		</div>
		<?php
		if (empty($thisuser)) $thisuser = false;
		
		if (!empty($game)) { ?>
			<div class="status_footer_section">
			<?php
			echo '<a href="/'.$game->db_game['url_identifier'].'/">'.$game->db_game['name']."</a>\n";
			
			$game_peers = $game->fetch_all_peers();
			
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
			
			echo '<div class="'.$text_class.' footer_peer_count" title="'.count($game_peers).' peer'.(count($game_peers) == 1 ? '' : 's').'" onClick="thisPageManager.open_public_peer_details('.$game->db_game['game_id'].'); return false;">'.count($game_peers).'</div>';
			
			if ($app->user_can_edit_game($thisuser, $game)) {
				$show_internal_params = false;
				
				if ($game->db_game['cached_definition_hash'] != $game->db_game['defined_cached_definition_hash']) {
					$actual_game_def_hash_3 = substr($game->db_game['cached_definition_hash'], 0, 3);
					$defined_game_def_hash_3 = substr($game->db_game['defined_cached_definition_hash'], 0, 3);
					
					echo "<font style=\"font-size: 75%;\">";
					echo " &nbsp;&nbsp; Pending ";
					echo '<a href="/explorer/games/'.$game->db_game['url_identifier'].'/definition/?definition_mode=actual">'.$actual_game_def_hash_3."</a>";
					echo " &rarr; ";
					echo '<a href="/explorer/games/'.$game->db_game['url_identifier'].'/definition/?definition_mode=defined">'.$defined_game_def_hash_3."</a>\n";
					echo " &nbsp;&nbsp; <a id=\"apply_def_link\" href=\"\" onclick=\"thisPageManager.apply_game_definition(".$game->db_game['game_id']."); return false;\">Apply Changes</a>";
					echo "</font>\n";
				}
			}
			?>
			</div>
		<?php
		}
		
		$online_blockchains = $app->run_query("SELECT * FROM blockchains b LEFT JOIN images i ON b.default_image_id=i.image_id WHERE b.online=1 AND b.blockchain_featured=1;");
		
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
</footer>

<?php
if (!empty($game)) {
	?>
	<div style="display: none;" class="modal fade" id="game<?php echo $game->db_game['game_id']; ?>_public_peer_details_modal">
		<div class="modal-dialog modal-lg">
			<div class="modal-content" id="game<?php echo $game->db_game['game_id']; ?>_public_peer_details_inner"></div>
		</div>
	</div>
	<?php
}
?>

<script type="text/javascript" src="/js/lodash.min.js"></script>
<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
<script type="text/javascript" src="/js/onload.js<?php if (!empty(AppSettings::getParam('cachebuster'))) echo '?v='.AppSettings::getParam('cachebuster'); ?>"></script>

<script type="text/javascript">
<?php if ($thisuser) echo "thisPageManager.synchronizer_token = '".$thisuser->get_synchronizer_token()."';\n"; ?>

for (var game_i=0; game_i<games.length; game_i++) {
	if (games[game_i].render_events) games[game_i].game_loop_event();
}
</script>

<script async type="text/javascript" src="/js/bootstrap.min.js"></script>
<script async type="text/javascript" src="/js/adminlte.min.js"></script>
<?php
$optional_js_files = ['jquery.nouislider.js', 'tiny.editor.js', 'chart.js', 'maskedinput.js', 'qrcam.js', 'jquery.datatables.js'];

foreach ($optional_js_files as $optional_js_file) {
	if (AppSettings::checkJsDependency($optional_js_file)) {
		echo '<script async type="text/javascript" src="/js/'.$optional_js_file.'"></script>'."\n";
	}
}
?>
</body>
</html>