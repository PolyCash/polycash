<?php
include('includes/connect.php');
include('includes/get_session.php');
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$thisuser->refresh_currency_accounts();
}

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
		<h1>My Accounts</h1>
		<?php
		$account_q = "SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id JOIN currency_addresses a ON ca.current_address_id=a.currency_address_id WHERE ca.user_id='".$thisuser->db_user['user_id']."';";
		$account_r = $app->run_query($account_q);
		
		echo "<p>You have ".$account_r->rowCount()." currency account";
		if ($account_r->rowCount() != 1) echo "s";
		echo ".</p>\n";
		
		while ($account = $account_r->fetch()) {
			echo '<div class="row">';
			echo '<div class="col-sm-4">'.$account['account_name'].'</div>';
			
			$balance_q = "SELECT SUM(amount) FROM currency_ios io JOIN currency_addresses a ON io.currency_address_id=a.currency_address_id WHERE a.account_id='".$account['account_id']."' AND io.spend_status='unspent';";
			$balance_r = $app->run_query($balance_q);
			$balance = $balance_r->fetch();
			$balance = (int) $balance['SUM(amount)'];
			
			echo '<div class="col-sm-2 greentext">';
			echo $app->format_bignum($balance/pow(10,8)).' '.$account['short_name_plural'];
			echo '</div>';
			
			echo '<div class="col-sm-2"><a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Deposit</a></div>';
			echo '<div class="col-sm-2"><a href="" onclick="toggle_account_details('.$account['account_id'].'); return false;">Transactions';
			
			$transaction_in_q = "SELECT * FROM currency_transactions t JOIN currency_ios io ON t.transaction_id=io.create_transaction_id JOIN currency_addresses ca ON ca.currency_address_id=io.currency_address_id WHERE ca.account_id='".$account['account_id']."';";
			$transaction_in_r = $app->run_query($transaction_in_q);
			
			$transaction_out_q = "SELECT * FROM currency_transactions t JOIN currency_ios io ON t.transaction_id=io.spend_transaction_id JOIN currency_addresses ca ON ca.currency_address_id=io.currency_address_id WHERE ca.account_id='".$account['account_id']."';";
			$transaction_out_r = $app->run_query($transaction_out_q);
			
			echo ' ('.($transaction_in_r->rowCount()+$transaction_out_r->rowCount()).')';
			
			echo '</a></div>';
			echo "</div>\n";
			
			echo '<div class="row" id="account_details_'.$account['account_id'].'" style="display: none;">';
			echo "<div class=\"account_details\">";
			echo "To deposit to ".$account['account_name'].", send ".$account['short_name_plural']." to: ".$account['pub_key']."<br/>\n";
			echo '<img style="margin: 10px;" src="/render_qr_code.php?data='.$account['pub_key'].'" />';
			
			while ($transaction = $transaction_in_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2 greentext">';
				echo "+".$app->format_bignum($transaction['amount']/pow(10,8))." ".$account['short_name_plural'];
				echo '</div>';
				echo '<div class="col-sm-3">';
				if ($transaction['block_id'] > 0) echo "Confirmed in block #".$transaction['block_id'];
				else echo "Not yet confirmed";
				echo '</div>';
				echo '</div>';
			}
			
			while ($transaction = $transaction_out_r->fetch()) {
				echo '<div class="row">';
				echo '<div class="col-sm-4">';
				echo $transaction['pub_key'];
				echo '</div>';
				echo '<div class="col-sm-2 redtext">';
				echo "-".$app->format_bignum($transaction['amount']/pow(10,8))." ".$account['short_name_plural'];
				echo '</div>';
				echo '<div class="col-sm-3">';
				echo "Confirmed in block #".$transaction['block_id'];
				echo '</div>';
				echo '</div>';
			}
			
			echo "</div>\n";
			echo "</div>\n";
		}
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