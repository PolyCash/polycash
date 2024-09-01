<?php
if (count($blockchainChecks) > 0) {
	?>
	<p style="margin: 10px 0px;">
		Showing <?php echo count($blockchainChecks)." check".(count($blockchainChecks) == 1 ? "" : "s")." for ".$blockchain->db_blockchain['blockchain_name']; ?>.
	</p>
	<div style="margin: 10px 0px;">
		<?php
		foreach ($blockchainChecks as $blockchainCheck) {
			$total_blocks_to_check = null;
			$blocks_checked = null;
			$blocks_remaining = null;
			$pct_complete = null;
			
			if (empty($blockchainCheck['completed_at'])) {
				$total_blocks_to_check = $blockchain->last_block_id() - $blockchainCheck['from_block'];
				if ((string) $blockchainCheck['processed_to_block'] !== "") {
					$blocks_checked = $blockchainCheck['processed_to_block'] - $blockchainCheck['from_block'];
					$blocks_remaining = $blockchain->last_block_id() - $blockchainCheck['processed_to_block'];
					$pct_complete = round(($blocks_checked / $total_blocks_to_check)*100, 3);
				}
				else {
					$blocks_checked = 0;
					$blocks_remaining = $blockchain->last_block_id() - $blockchainCheck['from_block'];
					$pct_complete = 0;
				}
			}
			?>
			<div class="row">
				<div class="col-sm-3">
					Started <?php echo $app->format_seconds(time() - $blockchainCheck['created_at']); ?> ago
				</div>
				<div class="col-sm-9">
					<?php
					if (!empty($blockchainCheck['completed_at'])) {
						echo "Completed ".$app->format_seconds(time() - $blockchainCheck['completed_at'])." ago";
						echo ", checked blocks <a target='_blank' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$blockchainCheck['from_block']."'>#".$blockchainCheck['from_block']."</a> to <a target='_blank' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$blockchainCheck['processed_to_block']."'>#".$blockchainCheck['processed_to_block']."</a>";
						if ((string) $blockchainCheck['first_error_block'] !== "") {
							echo "<br/><font class='text-danger'>Error on block <a target='_blank' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$blockchainCheck['first_error_block']."'>#".$blockchainCheck['first_error_block']."</a>: ".$blockchainCheck['first_error_message']."</font>";
						}
						else echo ', no errors found.';
					}
					else {
						echo "In progress";
						if ((string) $blockchainCheck['processed_to_block'] !== "") echo ", checked to block <a target='_blank' href='/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/blocks/".$blockchainCheck['first_error_block']."'>#".$blockchainCheck['processed_to_block']."</a>";
						echo ", ".number_format($blocks_remaining)." blocks left, ".$pct_complete."% complete.";
					}
					?>
				</div>
			</div>
			<?php
		}
		?>
	</div>
	<?php
}
else {
	?>
	<p style="margin: 10px 0px;">
		You haven't run any checks for <?php echo $blockchain->db_blockchain['blockchain_name']; ?>.
	</p>
	<?php
}
