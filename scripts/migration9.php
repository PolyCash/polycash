<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
}

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$q = "SELECT * FROM games ORDER BY game_id ASC;";
	$r = $app->run_query($q);
	echo "looping through ".$r->rowCount()." games<br/>\n";
	while ($db_game = $r->fetch()) {
		$qq = "SELECT * FROM events WHERE game_id='".$db_game['game_id']."' ORDER BY event_id ASC;";
		$rr = $app->run_query($qq);
		echo "looping through ".$rr->rowCount()." events in game #".$db_game['game_id']."<br/>\n";
		$event_i = 0;
		while ($db_event = $rr->fetch()) {
			$rrr = $app->run_query("UPDATE events SET event_index='".$event_i."' WHERE event_id='".$db_event['event_id']."';");
			$event_i++;
		}
	}
	echo "Done!\n";
}
else echo "Please supply the correct key.\n";
?>