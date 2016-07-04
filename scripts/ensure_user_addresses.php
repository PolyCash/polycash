<?php
include("../includes/connect.php");

$q = "SELECT * FROM users;";
$r = run_query($q);

while ($user = mysql_fetch_array($r)) {
	ensure_user_in_game($user['user_id'], get_site_constant('primary_game_id'));
}

$q = "SELECT * FROM user_games;";
$r = run_query($q);
while ($user_game = mysql_fetch_array($r)) {
	generate_user_addresses($user_game['game_id'], $user_game['user_id']);
}
echo "All user addresses have been generated.";
?>
