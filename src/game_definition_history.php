<?php
$migrationsPerPage = 30;
$migrationQueryBase = "game_definition_migrations WHERE game_id=:game_id AND migration_type = 'apply_defined_to_actual' ORDER BY migration_time DESC";
$migrationQuantity = $app->run_query("SELECT COUNT(*) FROM ".$migrationQueryBase, [
	'game_id' => $game->db_game['game_id']
])->fetch()['COUNT(*)'];
$migrations = $app->run_query("SELECT * FROM ".$migrationQueryBase." LIMIT ".($migrationsPerPage+1)."", [
	'game_id' => $game->db_game['game_id']
])->fetchAll();
$migrationsByToHash = [];
$definitionsByHash = [];
foreach ($migrations as $migration) {
	$migrationsByToHash[$migration['to_hash']] = $migration;
	if (empty($definitionsByHash[$migration['from_hash']])) $definitionsByHash[$migration['from_hash']] = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['from_hash']));
	if (empty($definitionsByHash[$migration['to_hash']])) $definitionsByHash[$migration['to_hash']] = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['to_hash']));
}
	
$migrations = array_slice($migrations, 0, $migrationsPerPage);
?>
<style>
.migration-header-cell {
	font-weight: bold;
}
.migration-header-cell, .migration-cell {
	text-align: center;
	line-height: 30px;
}
.migration-row:hover {
	background-color: #f6f6f6;
}
</style>
<div class="panel-heading">
	<div class="panel-title">Game definition history for <?php echo $game->db_game['name']; ?></div>
</div>
<div class="panel-body">
	<p>Showing <?php echo count($migrations)."/".$migrationQuantity; ?> game definition migrations.</p>
	
	<div class="row migration-header-row">
		<div class="col-md-2 migration-header-cell">Migration Time</div>
		<div class="col-md-2 migration-header-cell">Time Since Previous</div>
		<div class="col-md-3 migration-header-cell">Migration</div>
	</div>
	<?php
	foreach ($migrations as $migration) {
		list($differences, $difference_summary_lines) = GameDefinition::analyze_definition_differences($app, $definitionsByHash[$migration['from_hash']], $definitionsByHash[$migration['to_hash']]);
		?>
		<div class="row migration-row">
			<div class="col-md-2 migration-cell">
				<?php echo date("M j, Y g:ia", $migration['migration_time']); ?>
			</div>
			<div class="col-md-2 migration-cell">
				<?php
				if (!empty($migrationsByToHash[$migration['from_hash']]['migration_time'])) {
					$secSincePrev = $migration['migration_time'] - $migrationsByToHash[$migration['from_hash']]['migration_time'];
					echo $app->format_seconds($secSincePrev);
				}
				?>
			</div>
			<div class="col-md-3 migration-cell" style="cursor: pointer;" onclick="thisPageManager.view_game_migration(<?php echo $migration['migration_id']; ?>);">
				<?php
				echo GameDefinition::shorten_game_def_hash($migration['from_hash'])." &rarr; ".GameDefinition::shorten_game_def_hash($migration['to_hash']);
				?>
			</div>
			<div class="col-md-5 migration-cell" style="text-align: left;">
				<?php
				echo ucfirst(strtolower(implode(", ", $difference_summary_lines)));
				?>
			</div>
		</div>
		<?php
	}
	?>
</div>

<div style="display: none;" class="modal fade" id="migration_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" id="migration_modal_content"></div>
	</div>
</div>

