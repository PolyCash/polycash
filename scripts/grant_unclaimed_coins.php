<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['coins'])) $_REQUEST['coins'] = $cmd_vars['coins'];
	if (!empty($cmd_vars['to'])) $_REQUEST['to'] = $cmd_vars['to'];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['game_id'])) {
		$game_id = (int) $_REQUEST['game_id'];
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
	}
	else if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
		$db_blockchain = $app->run_query("SELECT * FROM blockchains WHERE blockchain_id='".$blockchain_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$game = false;
	}
	
	$user_id = false;
	$username = false;
	$to = $_REQUEST['to'];
	if (strval(intval($to)) == $to) $user_id = intval($to);
	else $username = $to;
	
	$coins_to_grant = floatval($_REQUEST['coins']);
	$coins_granted = 0;
	
	$unclaimed_coins_q = "SELECT SUM(io.amount) FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.blockchain_id=".$blockchain->db_blockchain['blockchain_id']." AND io.spend_status='unspent' AND a.user_id IS NULL AND a.is_mine=1;";
	$unclaimed_coins_r = $app->run_query($unclaimed_coins_q);
	$unclaimed_coins = $unclaimed_coins_r->fetch(PDO::FETCH_NUM);
	$unclaimed_coins = $unclaimed_coins[0]/pow(10,$blockchain->db_blockchain['decimal_places']);
	echo "There are ".$unclaimed_coins." unclaimed coins on this wallet.<br/>\n";
	
	if ($coins_to_grant > 0 && $coins_to_grant <= $unclaimed_coins) {
		if ($user_id) $user_q = "SELECT * FROM users WHERE user_id='".$user_id."';";
		else $user_q = "SELECT * FROM users WHERE username=".$app->quote_escape($username).";";
		$user_r = $app->run_query($user_q);
		
		if ($user_r->rowCount() > 0) {
			$db_user = $user_r->fetch();
			$user = new User($app, $db_user['user_id']);
			
			$unclaimed_output_q = "SELECT * FROM transaction_ios io JOIN addresses a ON io.address_id=a.address_id WHERE io.blockchain_id=".$blockchain->db_blockchain['blockchain_id']." AND io.spend_status='unspent' AND a.user_id IS NULL AND a.is_mine=1;";
			$unclaimed_output_r = $app->run_query($unclaimed_output_q);
			
			echo "Looping through ".$unclaimed_output_r->rowCount()." unclaimed outputs.<br/>\n";
			$keeplooping = true;
			
			while ($keeplooping && $utxo = $unclaimed_output_r->fetch()) {
				$success = $app->give_address_to_user($game, $user, $utxo);
				
				if ($success) {
					$coins_granted += $utxo['amount']/pow(10,$blockchain->db_blockchain['decimal_places']);
					if ($coins_granted >= $coins_to_grant) $keeplooping = false;
				}
			}
			echo $coins_granted." ".$blockchain->db_blockchain['coin_name_plural']." have been granted to ".$user->db_user['username']."<br/>\n";
		}
		else {
			echo "Please supply a valid user ID or email address in the \"to\" parameter.<br/>\n";
		}
		
		$app->refresh_utxo_user_ids(false);
	}
	else {
		echo "Please specify a valid number of coins in the URL.  You specified ".$coins_to_grant." and there are ".$unclaimed_coins." unclaimed coins on this wallet.";
	}
}
else echo "Please supply the correct key. Syntax is: grant_unclaimed_coins.php?key=<key>&to=<some_username>&coins=<num_coins>";
?>
