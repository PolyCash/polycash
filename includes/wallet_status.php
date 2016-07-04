<div style="overflow: hidden;">
	<font style="float: right;">
		Logged in as <?php echo $thisuser['username']; ?>. <a href="/wallet/<?php if ($game) echo $game['url_identifier']."/"; ?>?do=logout">Log Out</a>
	</font>
	<div class="row">
		<div class="col-sm-2">Account&nbsp;value:</div>
		<div class="col-sm-3" style="text-align: right;" id="account_value"><?php
		echo account_value_html($game, $account_value);
		?></div>
	</div>
</div>