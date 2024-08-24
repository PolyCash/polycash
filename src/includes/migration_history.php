<p>Showing <?php echo count($migrations)."/".$migrationQuantity; ?> game definition migrations.</p>

<div class="row migration-header-row">
	<div class="col-md-3 migration-header-cell">Migration Time</div>
	<div class="col-md-1 migration-header-cell">Time&nbsp;Since&nbsp;Previous</div>
	<div class="col-md-3 migration-header-cell">Migration</div>
	<div class="col-md-5 migration-header-cell" style="text-align: left;">Description</div>
</div>
<?php
foreach ($migrations as $migration) {
	if (empty($migration['cached_difference_summary']) && empty($migration['missing_game_defs_at'])) {
		$migration_summary = $app->set_migration_difference_summary($migration);
	}
	else $migration_summary = $migration['cached_difference_summary'];
	?>
	<div class="row migration-row">
		<div class="col-md-3 migration-cell">
			<?php echo $app->format_seconds(time() - $migration['migration_time'])." ago &nbsp; ".date("M j, Y g:ia", $migration['migration_time'])." UTC"; ?>
		</div>
		<div class="col-md-1 migration-cell">
			<?php
			if (!empty($migrationsByToHash[$migration['from_hash']]['migration_time'])) {
				$secSincePrev = $migration['migration_time'] - $migrationsByToHash[$migration['from_hash']]['migration_time'];
				echo str_replace(" ", "&nbsp;", $app->format_seconds($secSincePrev));
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
			echo $migration_summary;
			?>
		</div>
	</div>
	<?php
}
?>
<br/><p>Showed <?php echo count($migrations)."/".$migrationQuantity; ?> migrations for <?php echo $game->db_game['name']; ?></p>
