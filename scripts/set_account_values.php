<?php
include("../includes/connect.php");

$q = "SELECT * FROM users ORDER BY user_id ASC;";
$r = run_query($q);

echo "q (".mysql_numrows($r)."): $q<br/>\n";

while ($user = mysql_fetch_array($r)) {
	$account_value = account_coin_value(get_site_constant('primary_game_id'), $user)/pow(10,8);
	$qq = "UPDATE users SET account_value='".$account_value."' WHERE user_id='".$user['user_id']."';";
	$rr = run_query($qq);
	echo number_format($account_value).", ".$user['username'].", qq: $qq<br/>\n";
}
?>