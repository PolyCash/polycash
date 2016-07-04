<?php
include("../includes/connect.php");

$q = "SELECT * FROM games WHERE creator_id IS NULL;";
$r = run_query($q);

while ($game = mysql_fetch_array($r)) {
	$qq = "SELECT u.* FROM users u JOIN user_games ug ON u.user_id=ug.user_id WHERE ug.game_id='".$game['game_id']."' ORDER BY u.user_id ASC;";
	$rr = run_query($qq);
	
	while ($user = mysql_fetch_array($rr)) {
		$account_value = account_coin_value($game, $user)/pow(10,8);
		$qqq = "UPDATE users SET account_value='".$account_value."' WHERE user_id='".$user['user_id']."';";
		$rrr = run_query($qqq);
		echo $game['name']." &rarr; ".number_format($account_value).", ".$user['username']."<br/>\n";
	}
}
?>