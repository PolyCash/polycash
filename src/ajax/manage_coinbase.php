<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->user_is_admin($thisuser)) {
	$blockchain = new Blockchain($app, $_REQUEST['blockchain_id']);
	
	$mature_unclaimed_coinbase_ios = $blockchain->fetch_coinbase_ios(true, true, true, null);
	$mature_unclaimed_coinbase_sum = array_sum(array_column($mature_unclaimed_coinbase_ios, 'amount'));
	
	if ($_REQUEST['action'] == "view") {
		$display_coinbase_amt = $app->format_bignum($mature_unclaimed_coinbase_sum/pow(10, $blockchain->db_blockchain['decimal_places']));
		?>
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">
					There are <?php echo count($mature_unclaimed_coinbase_ios); ?> mature coinbase outputs available totalling <?php echo $display_coinbase_amt." ".($display_coinbase_amt=="1" ? $blockchain->db_blockchain['coin_name'] : $blockchain->db_blockchain['coin_name_plural']); ?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary">Save changes</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
		<?php
	}
	else if ($_REQUEST['action'] == "transfer") {
		if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
			$coinbase_quantity = (int) $_REQUEST['quantity'];
			$db_address = $blockchain->create_or_fetch_address($_REQUEST['to_address'], false, null);
			
			if (count($coinbase_ios) == $coinbase_quantity) {
				$in_sum = 0;
				$io_ids = [];
				
				foreach ($coinbase_ios as $coinbase_io) {
					array_push($io_ids, $coinbase_io['io_id']);
					$in_sum += $coinbase_io['amount'];
				}
				
				$error_message = "";
				$transaction_id = $blockchain->create_transaction("transaction", [$in_sum], false, $io_ids, [$db_address['address_id']], 0, $error_message);
				
				if ($transaction_id) {
					$transaction = $app->fetch_transaction_by_id($transaction_id);
					$app->output_message(1, '/explorer/blockchains/'.$blockchain->db_blockchain['url_identifier'].'/transactions/'.$transaction['tx_hash']);
				}
				else $app->output_message(5, "Failed: ".$error_message);
			}
			else $app->output_message(4, "Couldn't find that many available coinbase outputs.");
		}
		else $app->output_message(3, "Permission denied.");
	}
}
else $app->output_message(2, "Permission denied.");
