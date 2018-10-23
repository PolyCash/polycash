<?php
$host_not_required = TRUE;
include_once(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	if (!empty($cmd_vars['game_id'])) $_REQUEST['game_id'] = $cmd_vars['game_id'];
	if (!empty($cmd_vars['print_debug'])) $_REQUEST['print_debug'] = 1;
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	else $print_debug = false;
	
	$game_r = $app->run_query("SELECT * FROM games WHERE game_status='running';");
	if ($print_debug) echo "Looping through ".$game_r->rowCount()." games.<br/>\n";
	
	while ($db_game = $game_r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$q = "SELECT * FROM user_games WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		if ($print_debug) echo "Looping through ".$r->rowCount()." users.<br/>\n";
		
		while ($user_game = $r->fetch()) {
			$user = new User($app, $user_game['user_id']);
			$user->generate_user_addresses($game, $user_game);
			if ($print_debug) echo ". ";
		}
	}
	if ($print_debug) echo "Done!\n";
}
else echo "Invalid key supplied.\n";
?>