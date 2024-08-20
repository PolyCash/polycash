<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$action = null;
if (!empty($_REQUEST['action'])) $action = $_REQUEST['action'];

$migration = null;

if (!empty($_REQUEST['migration_id'])) {
	$migration = $app->run_query("SELECT * FROM game_definition_migrations WHERE migration_id=:migration_id;", ['migration_id' => $_REQUEST['migration_id']])->fetch();
	$db_game = $app->fetch_game_by_id($migration['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
}
else {
	$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
}

if ($action == "see_history") {
	$history_pos = (int) $_REQUEST['history_pos'];
	
	list($migrations, $migrationsByToHash, $migrationQuantity) = $app->fetch_recent_migrations($game, $history_pos);
	
	include(dirname(__DIR__).'/includes/migration_history.php');
	
}
else {
	if (empty($migration)) {
		$from_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $_REQUEST['from_hash']));
		$to_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $_REQUEST['to_hash']));
	}
	else {
		$from_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['from_hash']));
		$to_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['to_hash']));
	}
	
	if ($from_game_def && $to_game_def) {
		list($differences, $difference_summary_lines) = GameDefinition::analyze_definition_differences($app, $from_game_def, $to_game_def);
	}
	else $difference_summary_lines = [];
	?>
	<div class="modal-header">
		<b class="modal-title"><?php echo $game->db_game['name']; ?> migration: &nbsp; 
		<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/<?php echo $migration['from_hash']; ?>"> <?php echo $migration['from_hash']; ?></a> &rarr; <a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/<?php echo $migration['to_hash']; ?>"><?php echo $migration['to_hash']; ?></a></b>
		
		<button type="button" class="close" data-dismiss="modal" aria-label="Close">
			<span aria-hidden="true">&times;</span>
		</button>
	</div>
	<div class="modal-body">
		<?php
		if ($migration) {
			if ((string)$migration['extra_info'] != "" && $extra_info = json_decode($migration['extra_info'], true)) {
				if (isset($extra_info['reset_from_block'])) {
					$reset_from_block = $game->blockchain->fetch_block_by_id($extra_info['reset_from_block']);
					echo "Reset from block #".$extra_info['reset_from_block'];
					if ($reset_from_block) echo " (".date("Y-m-d H:i:s", $reset_from_block['time_mined']).")";
					echo "<br/>\n";
				}
				
				if (isset($extra_info['reset_from_event_index'])) {
					echo "Reset from event #".$extra_info['reset_from_event_index']."<br/>\n";
				}
			}
		}
		echo implode(".<br/>\n", $difference_summary_lines);
		?>
	</div>
	<div class="modal-footer">
		<button type="button" class="btn btn-info" data-dismiss="modal" aria-label="Close">Close</button>
	</div>
	<?php
}
?>