<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = (int)$_REQUEST['game_id'];
	$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
	
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	if ($game) {
		$q = "SELECT * FROM events e JOIN event_outcomes eo ON e.event_id=eo.event_id WHERE e.game_id='".$game->db_game['game_id']."' AND eo.sum_score IS NULL ORDER BY e.event_index ASC;";
		$r = $app->run_query($q);
		
		echo "Setting scores for ".$r->rowCount()." events.\n";
		
		while ($db_event = $r->fetch()) {
			$event = new Event($game, false, $db_event['event_id']);
			$score = $event->event_total_score(true);
			$qq = "UPDATE event_outcomes SET sum_score=".$score." WHERE outcome_id='".$db_event['outcome_id']."';";
			$rr = $app->run_query($qq);
			echo "$qq<br/>\n";
		}
	}
	else echo "Failed to load game #".$game_id."\n";
}
else echo "Incorrect key.\n";
?>