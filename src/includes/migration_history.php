<p>Showing <?php echo count($migrations)."/".$migrationQuantity; ?> game definition migrations.</p>

<div class="row migration-header-row">
	<div class="col-md-2 migration-header-cell">Migration Time</div>
	<div class="col-md-2 migration-header-cell">Time Since Previous</div>
	<div class="col-md-3 migration-header-cell">Migration</div>
	<div class="col-md-5 migration-header-cell" style="text-align: left;">Description</div>
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
<br/><p>Showed <?php echo count($migrations)."/".$migrationQuantity; ?> migrations for <?php echo $game->db_game['name']; ?></p>
