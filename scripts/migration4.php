<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$user_r = $app->run_query("SELECT * FROM users WHERE salt='';");
	$num_users = $user_r->rowCount();
	while ($db_user = $user_r->fetch()) {
		$salt = $app->random_string(16);
		$app->run_query("UPDATE users SET salt=".$app->quote_escape($salt).", password=".$app->quote_escape($app->normalize_password($db_user['password'], $salt))." WHERE user_id=".$db_user['user_id'].";");
	}
	echo "Set passwords for ".$num_users." users.\n";
}
else {
	echo "Please supply the correct key.\n";
}
?>
