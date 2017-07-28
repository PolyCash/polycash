<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "My Accounts";
$nav_tab_selected = "accounts";
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px; padding-top: 10px;">
	<?php
	if ($thisuser) {
		?>
		<script type="text/javascript">
		var selected_account_id = false;
		function toggle_account_details(account_id) {
			$('#account_details_'+account_id).toggle('fast');
			selected_account_id = account_id;
		}
		</script>
		<h1>Coin Accounts</h1>
		<?php
		$account_q = "SELECT ca.*, c.*, b.url_identifier AS blockchain_url_identifier, k.pub_key FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id JOIN addresses a ON ca.current_address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE ca.user_id='".$thisuser->db_user['user_id']."';";
		$account_r = $app->run_query($account_q);
		
		echo "<p>You have ".$account_r->rowCount()." coin account";
		if ($account_r->rowCount() != 1) echo "s";
		echo ".</p>\n";
		
		while ($account = $account_r->fetch()) {
			if ($account['game_id'] > 0) {
				$blockchain = new Blockchain($app, $account['blockchain_id']);
				$account_game = new Game($blockchain, $account['game_id']);
			}
			else $account_game = false;
			
			echo '<div class="row">';
			echo '<div class="col-sm-4">';
			if ($account['game_id'] > 0) echo ucwords($account_game->blockchain->db_blockchain['coin_name_plural'])." for ".$account_game->db_game['name'];
			else echo $account['account_name'];
			echo '</div>';
			
			$balance_q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN transactions t ON io.create_transaction_id=t.transaction_id JOIN addresses a ON io.address_id=a.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' AND io.spend_status='unspent';";
			$balance_r = $app->run_query($balance_q);
			$balance = $balance_r->fetch();
			$balance = $balance['SUM(io.amount)'];
			
			echo '<div class="col-sm-2 greentext" style="text-align: right">';
			echo $app->format_bignum($balance/pow(10,8)).' '.$account['short_name_plural'];
			echo '</div>';
			
			echo '<div class="col-sm-2">';
			if ($account['game_id'] == "") echo '<a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Deposit</a>';
			echo '</div>';
			echo '<div class="col-sm-2"><a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Transactions';
			
			$transaction_in_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC;";
			$transaction_in_r = $app->run_query($transaction_in_q);
			
			$transaction_out_q = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN addresses a ON a.address_id=io.address_id JOIN address_keys k ON a.address_id=k.address_id WHERE k.account_id='".$account['account_id']."' ORDER BY (t.block_id IS NULL) DESC, t.block_id DESC;";
			$transaction_out_r = $app->run_query($transaction_out_q);
			
			echo ' ('.($transaction_in_r->rowCount()+$transaction_out_r->rowCount()).')';
			
			echo '</a></div>';
			echo "</div>\n";
			
			echo '<div class="row" id="account_details_'.$account['account_id'].'" style="display: none;">';
			echo "<div class=\"account_details\">";
			if (empty($account['game_id'])) {
				echo "To deposit to ".$account['account_name'].", send ".$account['short_name_plural']." to: ".$account['pub_key']."<br/>\n";
				echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$account['pub_key'].'" />';
			}
			else {
				echo "This account stores your colored ".$account_game->blockchain->db_blockchain['coin_name_plural']." for ".$account_game->db_game['name'].".<br/>\n";
				echo "Do not deposit ".$account_game->blockchain->db_blockchain['coin_name_plural']." directly into this account.";
			}
			
			while ($transaction = $transaction_in_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2" style="text-align: right;"><a class="greentext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
				echo "+".$app->format_bignum($transaction['amount']/pow(10,8))."&nbsp;".$account['short_name_plural'];
				echo '</a></div>';
				echo '<div class="col-sm-3">';
				if ($transaction['block_id'] > 0) echo "Confirmed in block <a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>";
				else echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
				echo '</div>';
				echo '<div class="col-sm-3">'.ucwords($transaction['spend_status']);
				if ($transaction['spend_status'] == "unspent" && $transaction['block_id'] > 0) {
					echo "&nbsp;&nbsp;<a href=\"\" onclick=\"account_start_spend_io(".$transaction['io_id'].", ".($transaction['amount']/pow(10,8))."); return false;\">Spend</a>";
				}
				echo '</div>';
				echo '</div>';
			}
			
			while ($transaction = $transaction_out_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2" style="text-align: right;"><a class="redtext" target="_blank" href="/explorer/blockchains/'.$account['blockchain_url_identifier'].'/transactions/'.$transaction['tx_hash'].'">';
				echo "-".$app->format_bignum($transaction['amount']/pow(10,8))."&nbsp;".$account['short_name_plural'];
				echo '</a></div>';
				echo '<div class="col-sm-3">';
				if ($transaction['block_id'] > 0) echo "Confirmed in block <a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/blocks/".$transaction['block_id']."\">#".$transaction['block_id']."</a>";
				else echo "<a target=\"_blank\" href=\"/explorer/blockchains/".$account['blockchain_url_identifier']."/transactions/unconfirmed/\">Not yet confirmed</a>";
				echo '</div>';
				echo '</div>';
			}
			
			echo "</div>\n";
			echo "</div>\n";
		}
		
		$blockchains = array();
		
		echo '<h1>Colored Coin Accounts</h1>';
		
		$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."';";
		$r = $app->run_query($q);
		
		echo "<p>You have ".$r->rowCount()." colored coin account";
		if ($account_r->rowCount() != 1) echo "s";
		echo ".</p>\n";
		
		while ($user_game = $r->fetch()) {
			if (empty($blockchains[$user_game['blockchain_id']])) $blockchains[$user_game['blockchain_id']] = new Blockchain($app, $user_game['blockchain_id']);
			$coin_game = new Game($blockchains[$user_game['blockchain_id']], $user_game['game_id']);
			echo '<div class="row">';
			echo '<div class="col-sm-4"><a href="/wallet/'.$user_game['url_identifier'].'/">'.ucwords($user_game['coin_name_plural'])." for ".$user_game['name'].'</a></div>';
			echo '<div class="col-sm-2 greentext" style="text-align: right">'.$app->format_bignum($thisuser->account_coin_value($coin_game, $user_game)/pow(10,8)).' '.$user_game['coin_name_plural'].'</div>';
			
			if ($user_game['buyin_policy'] != "none") {
				$exchange_rate = $coin_game->coins_in_existence(false)/$coin_game->escrow_value(false);
				$cc_value = $thisuser->account_coin_value($coin_game, $user_game)/$exchange_rate;
				echo '<div class="col-sm-2 greentext" style="text-align: right">'.$app->format_bignum($cc_value/pow(10,8)).' '.$coin_game->blockchain->db_blockchain['coin_name_plural'].'</div>';
			}
			echo "</div>\n";
		}
		?>
		<div style="display: none;" class="modal fade" id="account_spend_modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="account_spend_modal_title">What do you want to do with these coins?</h4>
					</div>
					<div class="modal-body">
						<select class="form-control" id="account_spend_action" onchange="account_spend_action_changed();">
							<option value="">-- Please select --</option>
							<option value="buyin">Buy in to a game</option>
							<option value="withdraw">Withdraw my coins</option>
						</select>
						<div id="account_spend_buyin" style="display: none;">
							<br/>
							<p>
								Which game do you want to buy in to?
							</p>
							<select class="form-control" id="account_spend_game_id">
								<option value="">-- Please select --</option>
								<?php
								$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id WHERE ug.user_id='".$thisuser->db_user['user_id']."' GROUP BY g.game_id ORDER BY g.name ASC;";
								$r = $app->run_query($q);
								while ($db_game = $r->fetch()) {
									echo "<option value=\"".$db_game['game_id']."\">".$db_game['name']."</option>\n";
								}
								?>
							</select>
							<br/>
							<p>
								How much do you want to buy in for? <span id="account_spend_buyin_total"></span>
							</p>
							<div class="row">
								<div class="col-md-4">
									<input class="form-control" style="text-align: right;" type="text" id="account_spend_buyin_amount" placeholder="0.00" />
								</div>
								<div class="col-md-4 form-control-static">
									coins
								</div>
								<div class="col-md-4 form-control-static" id="account_spend_buyin_color_amount"></div>
							</div>
							<br/>
							<p>
								Transaction fee:
							</p>
							<div class="row">
								<div class="col-md-4">
									<input class="form-control" style="text-align: right;" type="text" id="account_spend_buyin_fee" value="0.001" />
								</div>
								<div class="col-md-4 form-control-static">
									coins
								</div>
							</div>
							<br/>
							<p>
								Which address should colored coins be sent to?
							</p>
							<select class="form-control" id="account_spend_buyin_address_choice" onchange="account_spend_buyin_address_choice_changed();">
								<option value="new">Create a new address for me</option>
								<option value="existing">Let me enter an address</option>
							</select>
							<div id="account_spend_buyin_address_existing" style="display: none;">
								<br/>
								<p>
									Please enter the address where colored coins should be deposited:
								</p>
								<input class="form-control" id="account_spend_buyin_address" />
							</div>
							<br/>
							<button class="btn btn-primary" onclick="account_spend_buyin();">Buy in</button>
						</div>
						<div id="account_spend_withdraw" style="display: none;">
						</div>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
		$(document).ready(function() {
			account_spend_refresh();
		});
		</script>
		<?php
	}
	else {
		$redirect_url = $app->get_redirect_url("/accounts/");
		$redirect_id = $redirect_url['redirect_url_id'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>