<?php
$host_not_required = TRUE;
require_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	// Find orphaned address_keys (associated currency_account has been deleted)
	$q = "SELECT COUNT(*), a.user_id, k.account_id FROM addresses a JOIN address_keys k ON a.address_id=k.address_id WHERE a.user_id IS NOT NULL AND NOT EXISTS (SELECT * FROM currency_accounts acc WHERE k.account_id=acc.account_id) GROUP BY k.account_id, a.user_id;";
	$r = $app->run_query($q);
	
	while ($user_to_account = $r->fetch()) {
		echo "give account ".$user_to_account['account_id']." to user #".$user_to_account['user_id']."<br/>\n";
		
		// Find the account ID used on the most up to date user_game for this user
		$qq = "SELECT k.*, ug.account_id AS correct_account_id FROM user_games ug JOIN transaction_game_ios gio ON ug.game_id=gio.game_id JOIN transaction_ios io ON gio.io_id=io.io_id JOIN games g ON ug.game_id=g.game_id JOIN address_keys k ON io.address_id=k.address_id WHERE ug.user_id=".$user_to_account['user_id']." AND ug.account_id != '".$user_to_account['account_id']."' GROUP BY k.address_key_id;";
		$rr = $app->run_query($qq);
		echo "qq: $qq<br/>\n";
		
		while ($address_key = $rr->fetch()) {
			$qqq = "UPDATE address_keys SET account_id='".$address_key['correct_account_id']."' WHERE account_id='".$address_key['account_id']."';";
			$rrr = $app->run_query($qqq);
			echo "qqq: $qqq<br/>\n";
		}
	}
}
?>