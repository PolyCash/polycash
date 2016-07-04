<?php
include("../includes/connect.php");

$q = "SELECT * FROM user_games;";
$r = run_query($q);

echo "q (".mysql_numrows($r)."): $q<br/>\n";

while ($user_game = mysql_fetch_array($r)) {
	$account_value = account_coin_value($user_game['game_id'], $user_game);
	$qq = "UPDATE user_games SET account_value='".$account_value."' WHERE game_id='".$user_game['game_id']."' AND user_id='".$user_game['user_id']."';";
	$rr = run_query($qq);
	echo number_format($account_value).", ".$user['username'].", qq: $qq<br/>\n";
}
?>