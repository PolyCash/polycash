<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);

	$q = "SELECT payment_required FROM user_games WHERE user_id='".$thisuser['user_id']."' AND game_id='".$game_id."';";
	$r = run_query($q);

	if (mysql_numrows($r) > 0) {
		$user_game = mysql_fetch_array($r);
		
		if ($user_game['payment_required'] == 0) $status_code = 1;
		else $status_code = 2;

		output_message($status_code, "", $user_game);
	}
	else output_message(2, "", array('payment_required'=>1));
}
else output_message(2, "Please log in", false);
?>
