<?php
include("../includes/connect.php");

$q = "SELECT * FROM users ORDER BY user_id ASC;";
$r = run_query($q);
while ($user = mysql_fetch_array($r)) {
	generate_user_addresses($user['user_id']);
}
echo "All user addresses have been generated.";
?>
