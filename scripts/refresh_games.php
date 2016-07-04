<?php
include(realpath(dirname(__FILE__))."/../includes/connect.php");

$q = "SELECT * FROM games;";
$r = run_query($q);

while ($mandatory_game = mysql_fetch_array($r)) {
	update_nation_scores($mandatory_game);
	
	if ($mandatory_game['creator_id'] > 0) {}
	else {
		$qq = "SELECT * FROM users;";
		$rr = run_query($qq);
		
		while ($user = mysql_fetch_array($rr)) {
			ensure_user_in_game($user['user_id'], $mandatory_game['game_id']);
		}
	}
}
echo "Done!";
?>