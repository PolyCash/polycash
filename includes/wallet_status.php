<div style="overflow: hidden;">
	<font style="float: right;">
		Logged in as <?php echo $thisuser['username']; ?>
	</font>
	<div class="row"><div class="col-sm-2">Account&nbsp;value:</div><div class="col-sm-3" style="text-align: right;"><font class="greentext" id="account_value"><?php echo format_bignum($account_value/pow(10,8), 2); ?></font> EmpireCoins<br/>(<?php echo format_bignum(100*$account_value/coins_in_existence($game, false)); ?>%)</div></div>
</div>