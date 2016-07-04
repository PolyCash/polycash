<div style="overflow: hidden;">
	<font style="float: right;">
		Logged in as <?php echo $thisuser['username']; ?>. <a href="/wallet/<?php if ($game) echo $game['url_identifier']."/"; ?>?do=logout">Log Out</a>
	</font>
	<div class="row"><div class="col-sm-2">Account&nbsp;value:</div><div class="col-sm-3" style="text-align: right;"><font class="greentext" id="account_value"><?php echo format_bignum($account_value/pow(10,8), 2); ?></font> <?php echo $game['coin_name_plural']; ?> (<?php echo format_bignum(100*$account_value/coins_in_existence($game, false)); ?>%)</div></div>
</div>