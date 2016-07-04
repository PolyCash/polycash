<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);

	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);

	if (mysql_numrows($r) == 1) {
		$game = mysql_fetch_array($r);

		if ($game['game_type'] == "simulation") {
			$quantity = intval($_REQUEST['quantity']);
			if (!$quantity) $quantity = 1;

			for ($i=0; $i<$quantity; $i++) {
				echo new_block($game['game_id']);
				if ($_REQUEST['apply_user_strategies'] == "1") echo apply_user_strategies($game);
			}
			echo "Done!<br/>\n";
		}
		else echo "A block can't be added for this game.";
	}
	else echo "Please supply a valid game ID.";
}
else echo "Incorrect key.";
?>