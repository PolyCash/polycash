<?php
include("../includes/connect.php");

if ($_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);

	$game = new Game($game_id);
	
	if ($game) {
		if ($game->db_game['game_type'] == "simulation" && $game->db_game['game_status'] != "completed") {
			$quantity = intval($_REQUEST['quantity']);
			if (!$quantity) $quantity = 1;

			for ($i=0; $i<$quantity; $i++) {
				echo $game->new_block();
				if ($_REQUEST['apply_user_strategies'] == "1") echo $game->apply_user_strategies();
			}
			echo "Done!<br/>\n";
		}
		else echo "A block can't be added for this game.";
	}
	else echo "Please supply a valid game ID.";
}
else echo "Incorrect key.";
?>