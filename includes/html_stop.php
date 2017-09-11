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
				IP & pageview tracking is: 
				<?php
				if ($GLOBALS['pageview_tracking_enabled']) echo "<font class='redtext'>Enabled</font>";
				else echo "<font class='greentext'>Disabled</font>";
				?>
			</div>
			<?php
			$q = "SELECT * FROM blockchains b JOIN images i ON b.default_image_id=i.image_id WHERE b.p2p_mode='rpc';";
			$r = $app->run_query($q);
			
			while ($db_blockchain = $r->fetch()) {
				echo '<div class="status_footer_section">';
				echo '<img class="status_footer_img" src="/images/custom/'.$db_blockchain['default_image_id'].'.'.$db_blockchain['extension'].'" />';
				
				if ($db_blockchain['rpc_last_time_connected'] > time()-(60*2)) {
					echo '<font class="greentext">Online</font>';
				}
				else {
					echo '<font class="redtext">Offline</font>';
				}
				echo '</div>';
			}
			?>
		</div>
	</footer>
	
	<?php if ($GLOBALS['signup_captcha_required']) { ?>
	<script type='text/javascript' src='https://www.google.com/recaptcha/api.js'></script>
	<?php } ?>
</body>
</html>