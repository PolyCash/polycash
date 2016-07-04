<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == "9841ujoifhWR4") {
	$q = "SELECT * FROM users WHERE api_access_code='' OR api_access_code IS NULL;";
	$r = $GLOBALS['app']->run_query($q);
	$counter = 0;
	while ($user = mysql_fetch_array($r)) {
		$qq = "UPDATE users SET api_access_code='".mysql_real_escape_string($GLOBALS['app']->random_string(32))."' WHERE user_id='".$user['user_id']."';";
		$rr = $GLOBALS['app']->run_query($qq);
		$counter++;
	}
	echo "Updated ".$counter." user accounts.";
}
else echo "Incorrect key.";
?>