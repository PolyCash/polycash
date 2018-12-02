<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$log_text = "";
	
	$q = "SELECT * FROM blockchains b JOIN games g ON b.blockchain_id=g.blockchain_id WHERE g.game_status='running'";
	if (!empty($_REQUEST['game_id'])) $q .= " AND g.game_id=".((int)$_REQUEST['game_id']);
	$q .= " GROUP BY b.blockchain_id ORDER BY b.blockchain_id ASC;";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		$last_block_id = $blockchain->last_block_id();
		
		$game_q = "SELECT * FROM games WHERE game_status='running' AND blockchain_id='".$db_blockchain['blockchain_id']."'";
		if (!empty($_REQUEST['game_id'])) $game_q .= " AND game_id=".((int)$_REQUEST['game_id']);
		$game_q .= ";";
		$game_r = $app->run_query($game_q);
		
		while ($db_game = $game_r->fetch()) {
			$game = new Game($blockchain, $db_game['game_id']);
			$log_text .= $game->set_event_blocks(false);
		}
	}
	echo $log_text;
}
else echo "You need admin privileges to run this script.\n";
?>