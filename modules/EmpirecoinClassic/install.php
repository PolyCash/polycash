<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");
include_once(dirname(__FILE__)."/EmpirecoinClassicGameDefinition.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$public_private = "";
	if (!empty($_REQUEST['public_private'])) $public_private = $_REQUEST['public_private'];
	
	if (empty($public_private)) {
		?>
		<p><a href="/modules/EmpirecoinClassic/install.php?public_private=public&key=<?php echo $GLOBALS['cron_key_string']; ?>">Install</a></p>
		<p><a href="/modules/EmpirecoinClassic/install.php?public_private=private&key=<?php echo $GLOBALS['cron_key_string']; ?>">Install private game</a></p>
		<?php
	}
	else {
		$module = $app->check_set_module("EmpirecoinClassic");

		$db_game = false;
		
		$q = "SELECT * FROM games WHERE module=".$app->quote_escape($module['module_name']).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() > 0) $db_game = $r->fetch();
		
		$game_def = new EmpirecoinClassicGameDefinition($app);
		if ($public_private == "private") {
			$game_def->game_def->blockchain_identifier = "private";
		}
		$new_game_def_txt = $app->game_def_to_text($game_def->game_def);
		
		$error_message = false;
		$new_game = $app->create_game_from_definition($new_game_def_txt, $thisuser, "EmpirecoinClassic", $error_message, $db_game);
		$new_game->blockchain->unset_first_required_block();
		$new_game->start_game();
		$new_game->ensure_events_until_block($new_game->db_game['game_starting_block']);
		
		if ($error_message) echo $error_message."<br/>\n";
		?>
		Done!!<br/>
		<?php
	}
}
else echo "Please supply the correct key.<br/>\n";
?>