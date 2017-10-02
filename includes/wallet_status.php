<div style="overflow: hidden;">
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
