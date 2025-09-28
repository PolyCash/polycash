<p>
	<?php if (!empty($display_from_name)) { ?>
	From: <?php echo "#".$faucet_id.", ".htmlspecialchars($display_from_name); ?><br/>
	<?php } ?>
	<button id="faucet_btn" class="btn btn-sm btn-success" onclick="thisPageManager.claim_from_faucet(<?php echo $faucet_id; ?>);">
		<i class="fas fa-hand-paper"></i> &nbsp; Claim <?php echo $game->display_coins($claim_amount_int); ?>
	</button>
</p>
