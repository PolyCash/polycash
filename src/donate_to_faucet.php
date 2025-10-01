<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$pagetitle = "Donate to Faucet";
$nav_tab_selected = "donate_to_faucet";

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	if ($redirect_url) $redirect_key = $redirect_url['redirect_key'];
	
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
	<?php
	include(AppSettings::srcPath()."/includes/html_register.php");
	?>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}

$db_game = $app->fetch_game_from_url();

if (!$db_game) {
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
		You've reached an invalid URL. Please try again.
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
	die();
}

$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $db_game['game_id']);

$faucet = null;
if (!empty($_REQUEST['faucet_id']) && ctype_digit($_REQUEST['faucet_id'])) {
	$faucet = Faucet::fetchById($app, $_REQUEST['faucet_id']);
	if (! $faucet || $faucet['user_id'] != $thisuser->db_user['user_id']) {
		die("You don't have permission to donate to that faucet.");
	}
}

$from_account = null;
if (!empty($_REQUEST['account_id']) && ctype_digit($_REQUEST['account_id'])) {
	$from_account = $app->fetch_account_by_id($_REQUEST['account_id']);
	
	if (! $from_account || $from_account['user_id'] != $thisuser->db_user['user_id']) {
		die("You don't have permission to donate from that account.");
	}
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_REQUEST['action']) && $_REQUEST['action'] == "donate") {
	$message = null;
	
	if (!$faucet) {
		$message = "Please select a valid faucet.";
	} else if (!$from_account) {
		$message = "Please select a valid account.";
	} else if (!isset($_REQUEST['donation_amount'])) {
		$message = "Please specify a valid donation amount.";
	} else if (!isset($_REQUEST['donation_tx_fee'])) {
		$message = "Please specify a valid transaction fee.";
	} else {
		$spendable_ios = $app->spendable_ios_in_account($from_account['account_id'], $game->db_game['game_id'], false, false);
		$spendable_balance = 0;
		foreach ($spendable_ios as $spendable_io) {
			$spendable_balance += (int) $spendable_io['coins'];
		}
		
		$donation_amount = (float) $_REQUEST['donation_amount'];
		$donation_amount_int = (int) $donation_amount*pow(10, $db_game['decimal_places']);
		
		if ($donation_amount_int > $spendable_balance) {
			$message = "You don't have enough ".$game->db_game['coin_name_plural']." to make that donation.";
		} else {
			$tx_fee_float = (float) $_REQUEST['donation_tx_fee'];
			$tx_fee_int = (int) ($tx_fee_float*pow(10, $game->blockchain->db_blockchain['decimal_places']));

			$txo_size_int = (int) ($faucet['txo_size']*pow(10, $game->db_game['decimal_places']));

			$num_donation_txos_to_create = floor($donation_amount_int/$txo_size_int);
			
			if ($num_donation_txos_to_create == 0) {
				$message = "Please donate at least ".$faucet['txo_size']." ".$game->db_game['coin_name_plural']." for this faucet.";
			} else {
				$io_amount_in = 0;
				$game_amount_in = 0;
				$io_ids = [];
				$keep_looping = true;
				$loop_pos = 0;

				while ($keep_looping && $loop_pos < count($spendable_ios)) {
					$io = $spendable_ios[$loop_pos];

					$game_amount_in += $io['coins'];
					$io_amount_in += $io['amount'];
					array_push($io_ids, $io['io_id']);

					if ($game_amount_in >= $donation_amount_int) $keep_looping = false;

					$loop_pos++;
				}
				
				if ($io_amount_in <= $tx_fee_int) {
					$message = "You can't afford this transaction after fees.";
				}
				else {
					$coins_per_chain_coin = (float) $game_amount_in/($io_amount_in-$tx_fee_int);
					$chain_coins_each = ceil($txo_size_int/$coins_per_chain_coin);
					
					$address_ids = [];
					$address_key_ids = [];
					$addresses_needed = $num_donation_txos_to_create;
					$loop_count = 0;
					
					do {
						$address_key = $app->new_normal_address_key($faucet['currency_id'], $faucet);
						
						array_push($address_ids, $address_key['address_id']);
						array_push($address_key_ids, $address_key['address_key_id']);
						
						$addresses_needed--;
						$loop_count++;
					}
					while ($addresses_needed > 0);
					
					if ($addresses_needed > 0) {
						if (count($address_ids) > 0) {
							$app->run_query("UPDATE addresses SET user_id=NULL WHERE address_id IN (".implode(",", array_map("intval", $address_ids)).");");
							$app->run_query("UPDATE address_keys SET account_id=NULL WHERE address_key_id IN (".implode(",", array_map("intval", $address_key_ids)).");");
						}
						$message = "Couldn't generate enough addresses for this transaction.";
					} else {
						$chain_remainder_int = $io_amount_in - ($chain_coins_each*$num_donation_txos_to_create) - $tx_fee_int;
						
						$send_address_ids = [];
						$amounts = [];

						for ($i=0; $i<$num_donation_txos_to_create; $i++) {
							array_push($amounts, $chain_coins_each);
							array_push($send_address_ids, $address_ids[$i]);
						}
						if ($chain_remainder_int > 0) {
							$remainder_address_key = $app->new_normal_address_key($from_account['currency_id'], $from_account);
							array_push($amounts, $chain_remainder_int);
							array_push($send_address_ids, $remainder_address_key['address_id']);
						}

						$error_message = false;
						$transaction_id = $game->blockchain->create_transaction('transaction', $amounts, false, $io_ids, $send_address_ids, $tx_fee_int, $error_message);

						if ($transaction_id) {
							$transaction = $app->fetch_transaction_by_id($transaction_id);
							$message = "Successfully donated to the faucet: <a href=\"/explorer/games/".$db_game['url_identifier']."/transactions/".$transaction['tx_hash']."/\">".$transaction['tx_hash']."</a>";
						}
						else $message = "TX Error: ".$error_message;
					}
				}
			}
		}
	}

	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid" style="padding-top: 15px;">
		<?php
		echo $app->render_view('game_links', [
			'explore_mode' => 'manage_faucets',
			'game' => $game,
			'blockchain' => $game->blockchain,
			'block' => null,
			'io' => null,
			'transaction' => null,
			'address' => null,
			'account' => null,
			'my_games' => [],
		]);
		?>
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">Donate to Faucet</div>
			</div>
			<div class="panel-body">
				<?php echo $message; ?>
			</div>
		</div>
	</div>
	<?php
	die();
}

include(AppSettings::srcPath()."/includes/html_start.php");
?>
<div class="container-fluid" style="padding-top: 15px;">
	<?php
	echo $app->render_view('game_links', [
		'explore_mode' => 'manage_faucets',
		'game' => $game,
		'blockchain' => $game->blockchain,
		'block' => null,
		'io' => null,
		'transaction' => null,
		'address' => null,
		'account' => null,
		'my_games' => [],
	]);
	
	$my_faucets = Faucet::fetchFaucetsManagedByUser($app, $thisuser, $game->db_game['game_id']);
	
	$my_accounts = $app->run_query("SELECT * FROM currency_accounts WHERE user_id=:user_id AND game_id=:game_id ORDER BY account_id ASC;", [
		'user_id' => $thisuser->db_user['user_id'],
		'game_id' => $game->db_game['game_id'],
	])->fetchAll(PDO::FETCH_ASSOC);
	?>
	<div class="panel panel-default" style="margin-top: 15px;">
		<div class="panel-heading">
			<div class="panel-title">Donate to Faucet</div>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-lg-6">
					<?php
					if (count($my_faucets) == 0) {
						?>
						<p>You haven't set up any faucets yet. Do you want to <a href="/manage_faucets/<?php echo $game->db_game['url_identifier']; ?>">create a faucet</a>?</p>
						<?php
					} else {
						?>
						<p><a href="/manage_faucets/<?php echo $game->db_game['url_identifier']; ?>">Manage Faucets</a></p>
						
						<form method="post" action="/donate_to_faucet/<?php echo $game->db_game['url_identifier']; ?>">
							<input type="hidden" name="action" value="donate" />
							
							<div class="form-group">
								<label for="faucet_id">Which faucet do you want to donate to?</label>
								<select class="form-control" name="faucet_id" id="faucet_id">
									<option value="">-- Please Select --</option>
									<?php
									foreach ($my_faucets as $my_faucet) {
										echo '<option value="'.$my_faucet['faucet_id'].'"'.($faucet && $my_faucet['faucet_id'] == $faucet['faucet_id'] ? ' selected="selected"' : '').'>#'.$my_faucet['faucet_id'].', '.$my_faucet['display_from_name'].'</option>';
									}
									?>
								</select>
							</div>
							
							<div class="form-group">
								<label for="account_id">Which account do you want to spend from?</label>
								<select class="form-control" name="account_id" id="account_id">
									<option value="">-- Please Select --</option>
									<?php
									foreach ($my_accounts as $my_account) {
										echo '<option value="'.$my_account['account_id'].'"'.($from_account && $from_account['account_id'] == $my_account['account_id'] ? ' selected="selected"' : '').'>#'.$my_account['account_id'].', '.$my_account['account_name'].'</option>';
									}
									?>
								</select>
							</div>
							
							<div class="form-group">
								<label for="donation_amount">How many <?php echo $game->db_game['coin_name_plural']; ?> do you want to donate?</label>
								<input type="text" class="form-control" placeholder="0" id="donation_amount" name="donation_amount" />
							</div>
							
							<div class="form-group">
								<label for="donation_tx_fee">How many <?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?> do you want to pay in fees to get this transaction confirmed?</label>
								<input type="text" class="form-control" placeholder="0.0001" id="donation_tx_fee" name="donation_tx_fee" />
							</div>
							
							<button class="btn btn-sm btn-success">Donate</button>
						</form>
						<?php
					}
					?>
				</div>
			</div>
		</div>
	</div>
</div>
