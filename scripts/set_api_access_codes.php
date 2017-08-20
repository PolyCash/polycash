<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM user_games WHERE api_access_code='' OR api_access_code IS NULL;";
	$r = $app->run_query($q);
	$counter = 0;
	while ($user_game = $r->fetch()) {
		$qq = "UPDATE user_games SET api_access_code=".$app->quote_escape($app->random_string(32))." WHERE user_game_id='".$user_game['user_game_id']."';";
		$rr = $app->run_query($qq);
		$counter++;
	}
	echo "Updated ".$counter." accounts.";
}
else echo "Incorrect key.";
?>
