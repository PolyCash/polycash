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
<div style="display: none;" class="modal fade" id="migration_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" id="migration_modal_content"></div>
	</div>
</div>
<footer class="footer" id="chatWindows"></footer>
<?php
$display_sync_games = $app->fetch_display_sync_games();

foreach ($display_sync_games as $display_sync_game) {
	?>
	<div style="display: none;" class="modal fade" id="game<?php echo $display_sync_game->db_game['game_id']; ?>_public_peer_details_modal">
		<div class="modal-dialog modal-lg">
			<div class="modal-content" id="game<?php echo $display_sync_game->db_game['game_id']; ?>_public_peer_details_inner"></div>
		</div>
	</div>
	<?php
}
?>
<div id="status_footer">
	<?php
	echo $app->render_view('status_footer', [
		'app' => $app,
		'thisuser' => empty($thisuser) ? null : $thisuser,
		'display_sync_games' => $display_sync_games,
	]);
	?>
</div>

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