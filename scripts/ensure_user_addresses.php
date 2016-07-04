<?php
include("../includes/connect.php");

$q = "SELECT * FROM users;";
$r = run_query($q);

while ($user = mysql_fetch_array($r)) {
	ensure_user_in_game($user, get_site_constant('primary_game_id'));
}

$q = "SELECT * FROM user_games ug JOIN games g ON ug.game_id=g.game_id;";
$r = run_query($q);
while ($user_game = mysql_fetch_array($r)) {
	generate_user_addresses($user_game);
}
echo "All user addresses have been generated.";
?>
