<div style="overflow: hidden;">
	<font style="float: right;">
		Logged in as <?php echo $thisuser->db_user['username']; ?>. <a href="/wallet/<?php if ($game) echo $game->db_game['url_identifier']."/"; ?>?do=logout">Log Out</a>
	</font>
	<?php
	if ($game) { ?>
	<div class="row">
		<div class="col-sm-2">Account&nbsp;value:</div>
		<div class="col-sm-3" style="text-align: right;" id="account_value"><?php
		echo $game->account_value_html($account_value);
		?></div>
	</div>
	<?php } ?>
</div>
