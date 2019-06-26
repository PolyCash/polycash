  </div>
</div>
<div style="display: none;" id="chatWindowTemplate">
	<div class="chatWindowHeader" id="chatWindowHeaderCHATID">
		<div class="chatWindowTitle" id="chatWindowTitleCHATID"></div>
		<font class="chatWindowCloseBtn" onclick="closeChatWindow(CHATID);">&#215;</font>
		<div class="chatWindowContent" id="chatWindowContentCHATID"></div>
		<input class="chatWindowWriter" id="chatWindowWriterCHATID" />
		<button class="btn btn-sm btn-primary" id="chatWindowSendBtnCHATID" onclick="sendChatMessage(CHATID);">Send</button>
	</div>
</div>
<footer class="footer" id="chatWindows"></footer>
<footer class="footer status_footer">
	<div class="status_footer_right">
		<div class="status_footer_section">
			Loaded in <?php echo round(microtime(true)-$pageload_start_time, 2); ?> sec.
		</div>
		<?php
		if (!empty($game)) { ?>
			<div class="status_footer_section">
			<?php
			echo '<a href="/'.$game->db_game['url_identifier'].'/">'.$game->db_game['name']."</a>\n";
			
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
					echo " &nbsp;&nbsp; <a id=\"apply_def_link\" href=\"\" onclick=\"apply_game_definition(".$game->db_game['game_id']."); return false;\">Apply Changes</a>";
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
			$recent_block = $blockchain->most_recently_loaded_block();
			
			if (!empty($db_blockchain['rpc_last_time_connected'])) $blockchain_last_active = $db_blockchain['rpc_last_time_connected'];
			else $blockchain_last_active = false;
			
			if (!empty($recent_block['time_loaded']) && $recent_block['time_loaded'] > $blockchain_last_active) $blockchain_last_active = $recent_block['time_loaded'];
			
			echo '<div class="status_footer_section">';
			echo '<a href="/explorer/blockchains/'.$db_blockchain['url_identifier'].'/blocks/">';
			if ($db_blockchain['default_image_id'] > 0) echo '<img class="status_footer_img" src="/images/custom/'.$db_blockchain['default_image_id'].'.'.$db_blockchain['extension'].'" />';
			else echo $db_blockchain['blockchain_name']." "; 
			if ($blockchain_last_active > time()-(60*60)) {
				echo '<font class="greentext">Online</font>';
			}
			else {
				echo '<font class="redtext">Offline</font>';
			}
			echo "</a>";
			echo '</div>';
		}
		?>
	</div>
</footer>

<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
<script type="text/javascript" src="/js/onload.js"></script>

<script type="text/javascript">
for (var game_i=0; game_i<games.length; game_i++) {
	if (games[game_i].render_events) games[game_i].game_loop_event();
}
</script>

<script type="text/javascript" src="/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/js/jquery.ui.js"></script>
<script type="text/javascript" src="/js/jquery.nouislider.js"></script>
<script type="text/javascript" src="/js/adminlte.min.js"></script>
<script type="text/javascript" src="/js/tiny.editor.js"></script>
<script type="text/javascript" src="/js/chart.js"></script>
<script type="text/javascript" src="/js/maskedinput.js"></script>
<script type="text/javascript" src="/js/qrcam.js"></script>
<?php
if (AppSettings::getParam('signup_captcha_required')) { ?>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type='text/javascript' src='https://www.google.com/recaptcha/api.js'></script>
<?php } ?>

<?php
$left_menu_open = 1;
if (AppSettings::getParam('pageview_tracking_enabled')) {
	if (empty($thisuser)) $thisuser = false;
	if (empty($viewer_id)) $viewer_id = $pageviewController->insert_pageview($thisuser);
	$viewer = $pageviewController->get_viewer($viewer_id);
	$left_menu_open = $viewer['left_menu_open'];
}
else if ($thisuser) $left_menu_open = $thisuser->db_user['left_menu_open'];

if ($left_menu_open == 0) {
	?>
	<script type="text/javascript">
	$('[data-toggle="push-menu"]').pushMenu('toggle');
	</script>
	<?php
}
?>
</body>
</html>