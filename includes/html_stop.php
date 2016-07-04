	<div style="display: none;" id="chatWindowTemplate">
		<div class="chatWindowHeader" id="chatWindowHeaderCHATID">
			<div class="chatWindowTitle" id="chatWindowTitleCHATID">test</div>
			<font class="chatWindowCloseBtn" onclick="closeChatWindow(CHATID);">&#215;</font>
			<div class="chatWindowContent" id="chatWindowContentCHATID">content</div>
			<input class="chatWindowWriter" id="chatWindowWriterCHATID" />
			<button class="btn btn-sm btn-primary" id="chatWindowSendBtnCHATID" onclick="sendChatMessage(CHATID);">Send</button>
		</div>
	</div>
	<footer class="footer" id="chatWindows"></footer>
	<?php if ($GLOBALS['signup_captcha_required']) { ?>
	<script type='text/javascript' src='https://www.google.com/recaptcha/api.js'></script>
	<?php } ?>
</body>
</html>