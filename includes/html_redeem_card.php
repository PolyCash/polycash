<script type="text/javascript">
$('#redeem_options').on('hidden.bs.modal', function () {
	$('#step1').show();
});
</script>

<div class="modal-dialog">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			<h4 class="modal-title greentext">You entered the correct code!</h4>
		</div>
		<div class="modal-body">
			<h3 style="margin-top: 0px;">How do you want to get your money?</h3>
			<?php
			$option_i = 1;

			if (FALSE && $currency['blockchain_id'] > 0) {
				?>
				<script type="text/javascript">
				var v=null;
				var qrCanvasId = 'qrCanvas';

				function QRFound(string) {
					$('#withdraw_address').val(string.replace('bitcoin:', ''));
					$('#qrCam').hide('fast');
					$('#qrUpload').hide('fast');
					$('#os_options').hide('fast');
				}
				
				var QRC = new QRCam('qrCam', 'qrUpload', qrCanvasId, QRFound);
				</script>
				
				<div class="form-group">
					<label for="withdraw_address"><h4><?php echo $option_i; $option_i++; ?>. Send my <?php echo $currency['short_name_plural']; ?> to this <?php
					if ($currency['currency_id'] == 9) echo "phone number:";
					else echo "address:";
					?></h4></label>
					
					<div class="form-group">
						<label for="withdraw_name">Please enter your full name.  The name you enter here should match the registration with your phone company.</label>
						<input class="form-control" id="withdraw_name" name="withdraw_name" />
					</div>
					<div class="form-group">
						<label for="withdraw_address">Please enter your phone number:</label>
						<input class="form-control" name="withdraw_address" id="withdraw_address" />
					</div>
					
					<button id="card_withdrawal_btn" class="btn btn-success" onclick="card_withdrawal(<?php echo $card['card_id']; ?>);">Send <?php echo $currency['short_name_plural']; ?></button>
				</div>
				
				<a href="" onclick="$('#os_options').toggle('fast'); return false;">Scan a QR code address</a>
				
				<div style="display: none; padding-top: 10px;" id="os_options">
					<p>Which are you using?</p>
					<button class="btn btn-default" onclick="QRC.selectOS('iphone');">Mobile Phone</button>
					<button class="btn btn-default" onclick="QRC.selectOS('pc');">Computer</button>
				</div>
				
				<div id="qrUpload" style="display: none; border: 1px solid #ccc; padding: 10px;">
					Please upload a picture of the QR code.
					<div id="qrfile"><canvas id="out-canvas" width="320" height="240"></canvas>
						<div id="imghelp">
							<input type="file" onchange="QRC.handleFiles(this.files)"/>
						</div>
					</div>
				</div>
				<div id="qrCam" style="display: none; border: 1px solid #ccc; padding: 10px;">
					To scan an address, please share your web cam with the browser, then hold the QR code up to your web cam.
					
					<div id="outdiv"></div>
					
					<canvas id="qrCanvas" width="800" height="600"></canvas>
				</div>
				<br/>
				<?php
			}
			?>
			<div>
				<h4><?php if ($option_i > 0) echo $option_i.". "; $option_i++; ?>Create a secure account to hold this money:</h4>
				<p>
					To store your <?php echo $currency['short_name_plural']; ?> in a secure wallet account, please enter a password below.  After creating the account, you can easily convert your <?php echo $currency['short_name_plural']; ?> to other currencies or withdraw your money to Bitcoin or Mobile Money at any time. The 16 digit code from your card will function as your username so please remember to keep your card somewhere safe.
				</p>
				
				<form action="#" onsubmit="card_login(true); return false;" method="get">
					<div class="form-group">
						<label for="card_account_password">Please enter a new password:</label>
						<input class="form-control" id="card_account_password" name="password" type="password" />
					</div>
					<div class="form-group">
						<label for="card_account_password2">Please repeat your password to avoid making a mistake.</label>
						<input class="form-control" id="card_account_password2" name="password2" type="password" />
					</div>
					<input type="submit" class="btn btn-success" value="Create an account" onclick="card_login(true, card_id); return false;" />
				</form>
			</div>
			<br/>
		</div>
	</div>
</div>