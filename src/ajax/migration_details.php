<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if (!empty($_REQUEST['migration_id'])) {
	$migration = $app->run_query("SELECT * FROM game_definition_migrations WHERE migration_id=:migration_id;", ['migration_id' => $_REQUEST['migration_id']])->fetch();
	$db_game = $app->fetch_game_by_id($migration['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);

	$from_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['from_hash']));
	$to_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['to_hash']));
}
else {
	$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);
	
	$from_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $_REQUEST['from_hash']));
	$to_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $_REQUEST['to_hash']));
}

$differences = GameDefinition::analyze_definition_differences($app, $from_game_def, $to_game_def);
?>
<div class="modal-header">
	<b class="modal-title"><?php echo $game->db_game['name']." migration: &nbsp; ".$migration['from_hash']." &rarr; ".$migration['to_hash']; ?></b>
	
	<button type="button" class="close" data-dismiss="modal" aria-label="Close">
		<span aria-hidden="true">&times;</span>
	</button>
</div>
<div class="modal-body">
	<?php
	$change_summary_lines = [];
	if (count($differences['base_params']) > 0) {
		array_push($change_summary_lines, count($differences['base_params'])." game parameter".(count($differences['base_params']) == 1 ? " was" : "s were")." changed.");
	}
	if (count($differences['escrow']['added']) > 0) {
		array_push($change_summary_lines, $differences['escrow']['added']." new amount".($differences['escrow']['added'] != 1 ? "s were" : " was")." added to the escrow.");
	}
	if (count($differences['escrow']['removed']) > 0) {
		array_push($change_summary_lines, count($differences['escrow']['removed'])." amount".(count($differences['escrow']['removed']) != 1 ? "s were" : " was")." removed from the escrow.");
	}
	if (count($differences['escrow']['changed']) > 0) {
		array_push($change_summary_lines, count($differences['escrow']['changed'])." escrow amount".(count($differences['escrow']['changed']) == 1 ? " was" : "s were")." changed.");
	}
	if ($differences['events']['new_events'] > 0) {
		array_push($change_summary_lines, $differences['events']['new_events']." new event".($differences['events']['new_events'] == 1 ? " was " : "s were")." added.");
	}
	if ($differences['events']['removed_events'] > 0) {
		array_push($change_summary_lines, $differences['events']['removed_events']." event".($differences['events']['removed_events'] == 1 ? " was" : "s were")." removed.");
	}
	if ($differences['events']['block_changed_events'] > 0) {
		array_push($change_summary_lines, "Blocks were changed in ".$differences['events']['block_changed_events']." event".($differences['events']['block_changed_events'] == 1 ? "" : "s").".");
	}
	if ($differences['events']['other_changed_events'] > 0) {
		array_push($change_summary_lines, $differences['events']['other_changed_events']." event".($differences['events']['other_changed_events'] != 1 ? "s were" : " was")." changed.");
	}
	echo implode("<br/>\n", $change_summary_lines);
	?>
</div>
<div class="modal-footer">
	<button type="button" class="btn btn-info" data-dismiss="modal" aria-label="Close">Close</button>
</div>
