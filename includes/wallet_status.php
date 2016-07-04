<div style="overflow: hidden;">
	<font style="float: right;">
		Logged in as <?php echo $thisuser['username']; ?>. <a href="/wallet/?do=logout">Log Out</a>
	</font>
	<div class="row"><div class="col-sm-2">Account&nbsp;value:</div><div class="col-sm-3" style="text-align: right;"><font class="greentext" id="account_value"><?php echo number_format($account_value, 3); ?></font> EmpireCoins</div></div>
</div>