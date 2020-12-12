<?php
$migrationsPerPage = 50;
$migrationQueryBase = "game_definition_migrations WHERE game_id=:game_id AND migration_type = 'apply_defined_to_actual' ORDER BY migration_time DESC";
$migrationQuantity = $app->run_query("SELECT COUNT(*) FROM ".$migrationQueryBase, [
	'game_id' => $game->db_game['game_id']
])->fetch()['COUNT(*)'];
$migrations = $app->run_query("SELECT * FROM ".$migrationQueryBase." LIMIT ".($migrationsPerPage+1)."", [
	'game_id' => $game->db_game['game_id']
])->fetchAll();
$migrationsByToHash = [];
foreach ($migrations as $migration) {
	$migrationsByToHash[$migration['to_hash']] = $migration;
}
$migrations = array_slice($migrations, 0, $migrationsPerPage);
?>
<div class="panel-heading">
	<div class="panel-title">Game definition history for <?php echo $game->db_game['name']; ?></div>
</div>
<div class="panel-body">
	<p>Showing <?php echo count($migrations)."/".$migrationQuantity; ?> game definition migrations.</p>
	
	<div class="row">
		<div class="col-md-2 text-bold" style="text-align: center;">Migration Time</div>
		<div class="col-md-2 text-bold" style="text-align: center;">Time Since Previous Migration</div>
		<div class="col-md-3 text-bold" style="text-align: center;">Migration</div>
	</div>
	<?php
	foreach ($migrations as $migration) {
		?>
		<div class="row">
			<div class="col-md-2" style="text-align: center;">
				<?php echo date("M j, Y g:ia", $migration['migration_time']); ?>
			</div>
			<div class="col-md-2" style="text-align: center;">
				<?php
				$secSincePrev = $migration['migration_time'] - $migrationsByToHash[$migration['from_hash']]['migration_time'];
				echo $app->format_seconds($secSincePrev);
				?>
			</div>
			<div class="col-md-3" style="text-align: center;">
				<?php
				echo GameDefinition::shorten_game_def_hash($migration['from_hash'])." &rarr; ".GameDefinition::shorten_game_def_hash($migration['to_hash']);
				?>
			</div>
		</div>
		<?php
	}
	?>
</div>
