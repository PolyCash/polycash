<?php
$host_not_required = TRUE;
include(realpath(dirname(__FILE__))."/../includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['coins'])) $_REQUEST['coins'] = $cmd_vars['coins'];
	if (!empty($cmd_vars['to'])) $_REQUEST['to'] = $cmd_vars['to'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$user_id = false;
	$username = false;
	$to = $_REQUEST['to'];
	if (strval(intval($to)) == $to) $user_id = intval($to);
	else $username = $to;
	
	$coins_to_grant = floatval($_REQUEST['coins']);
	$coins_granted = 0;
	
	$unclaimed_coins_q = "SELECT SUM(amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.game_id=".$app->get_site_constant('primary_game_id')." AND io.spend_status='unspent' AND io.user_id IS NULL AND a.user_id IS NULL AND a.is_mine=1;";
	$unclaimed_coins_r = $app->run_query($unclaimed_coins_q);
	$unclaimed_coins = $unclaimed_coins_r->fetch(PDO::FETCH_NUM);
	$unclaimed_coins = $unclaimed_coins[0]/pow(10,8);
	echo "There are ".$unclaimed_coins." unclaimed coins on this wallet.<br/>\n";
	
	if ($coins_to_grant > 0 && $coins_to_grant <= $unclaimed_coins) {
		if ($user_id) $user_q = "SELECT * FROM users WHERE user_id='".$user_id."';";
		else $user_q = "SELECT * FROM users WHERE username=".$app->quote_escape($username).";";
		$user_r = $app->run_query($user_q);
		
		if ($user_r->rowCount() > 0) {
			$user = $user_r->fetch();
			
			$unclaimed_output_q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.game_id=".$app->get_site_constant('primary_game_id')." AND io.spend_status='unspent' AND io.user_id IS NULL AND a.user_id IS NULL AND a.is_mine=1;";
			$unclaimed_output_r = $app->run_query($unclaimed_output_q);
			echo "Looping through ".$unclaimed_output_r->rowCount()." unclaimed outputs.<br/>\n";
			while ($utxo = $unclaimed_output_r->fetch()) {
				$assign_output_q = "UPDATE transaction_ios io JOIN addresses a ON io.address_id=a.address_id SET io.user_id='".$user['user_id']."', a.user_id='".$user['user_id']."' WHERE io.io_id='".$utxo['io_id']."';";
				$assign_output_r = $app->run_query($assign_output_q);
				$coins_granted += $utxo['amount']/pow(10,8);
				if ($coins_granted >= $coins_to_grant) $unclaimed_output_r = false;
			}
			echo $coins_granted." coins have been granted to ".$user['username']."<br/>\n";
		}
		else {
			echo "Please supply a valid user ID or email address in the \"to\" parameter. $q<br/>\n";
		}
		
		$app->refresh_utxo_user_ids(false);
	}
	else {
		echo "Please specify a valid number of coins in the URL.  You specified ".$coins_to_grant." and there are ".$unclaimed_coins." unclaimed coins on this wallet.";
	}
}
else echo "Please supply the correct key. Syntax is: grant_unclaimed_coins.php?key=<key>&to=<some_username>&coins=<num_coins>";
?>
