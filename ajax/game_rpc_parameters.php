<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$game_id = intval($_REQUEST['game_id']);
	
	$game = new Game($app, $game_id);
	
	if ($game) {
		if ($game->db_game['creator_id'] == $thisuser->db_user['user_id']) {
			if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "save") {
				$rpc_username = $_REQUEST['rpc_username'];
				$rpc_password = $_REQUEST['rpc_password'];
				$rpc_port = $_REQUEST['rpc_port'];
				
				$q = "UPDATE games SET rpc_username=".$app->quote_escape($rpc_username).", rpc_password=".$app->quote_escape($rpc_password).", rpc_port=".$app->quote_escape($rpc_port)." WHERE game_id=".$game->db_game['game_id'].";";
				$r = $app->run_query($q);
				
				$app->output_message(1, "Your changes have been saved.", false);
			}
			else {
				$output['rpc_username'] = $game->db_game['rpc_username'];
				$output['rpc_password'] = $game->db_game['rpc_password'];
				$output['rpc_port'] = $game->db_game['rpc_port'];
				
				$app->output_message(1, "", $output);
			}
		}
		else $app->output_message(2, "Error: you're not the game administrator. Instead, try visiting /install.php", false);
	}
	else $app->output_message(3, "Error: invalide game ID", false);
}
else $app->output_message(4, "Error: you must be logged in to save game RPC parameters.", false);
?>