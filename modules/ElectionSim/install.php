<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include(dirname(dirname(dirname(__FILE__)))."/includes/get_session.php");
include_once(dirname(__FILE__)."/ElectionSimGameDefinition.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (empty($argv) && empty($thisuser)) {
		$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
		header("Location: /wallet/?redirect_key=".$redirect_url['redirect_key']);
		die();
	}
	
	$public_private = "";
	if (!empty($_REQUEST['public_private'])) $public_private = $_REQUEST['public_private'];
	
	if (empty($public_private)) {
		?>
		<p><a href="/modules/ElectionSim/install.php?public_private=public&key=<?php echo $GLOBALS['cron_key_string']; ?>">Install</a></p>
		<p><a href="/modules/ElectionSim/install.php?public_private=private&key=<?php echo $GLOBALS['cron_key_string']; ?>">Install private game</a></p>
		<?php
	}
	else {
		$module = $app->check_set_module("ElectionSim");

		$db_game = false;
		$q = "SELECT * FROM games WHERE module=".$app->quote_escape($module['module_name']).";";
		$r = $app->run_query($q);
		if ($r->rowCount() > 0) $db_game = $r->fetch();
		
		$game_def = new ElectionSimGameDefinition($app);
		
		$blockchain = false;
		$db_blockchain = $app->fetch_blockchain_by_identifier($game_def->game_def->blockchain_identifier);
		
		if ($db_blockchain) {
			$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		}
		
		if ($public_private == "private") {
			$game_def->game_def->blockchain_identifier = "private";
		}
		if (!empty($db_blockchain) && $db_blockchain['p2p_mode'] == "none") {
			$game_starting_block = $blockchain->last_block_id() - ($blockchain->last_block_id()%$game_def->game_def->round_length) + 1;
			$game_def->game_def->game_starting_block = $game_starting_block;
		}
		
		$new_game_def_txt = $app->game_def_to_text($game_def->game_def);
		
		$error_message = false;
		$new_game = $app->create_game_from_definition($new_game_def_txt, $thisuser, "ElectionSim", $error_message, $db_game);
		
		if (!empty($new_game)) {
			if ($new_game->blockchain->db_blockchain['p2p_mode'] == "none") {
				if ($thisuser) {
					$user_game = $thisuser->ensure_user_in_game($new_game, false);
				}
				$log_text = "";
				$new_game->blockchain->new_block($log_text);
				$transaction_id = $new_game->add_genesis_transaction($user_game);
				if ($transaction_id < 0) $error_message = "Failed to add genesis transaction (".$transaction_id.").";
			}
			$new_game->blockchain->unset_first_required_block();
			$new_game->start_game();
			$new_game->ensure_events_until_block($new_game->db_game['game_starting_block']);
		}
		else if (empty($error_message)) $error_message = "Error: failed to create the game.";
		
		if ($error_message) echo $error_message."<br/>\n";
		?>
		Done!!<br/>
		<a href="/">Check installation</a>
		<?php
	}
}
else echo "Please supply the correct key.<br/>\n";
?>