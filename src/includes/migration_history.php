<p>Showing <?php echo count($migrations)."/".$migrationQuantity; ?> game definition migrations.</p>

<div class="row migration-header-row">
	<div class="col-md-3 migration-header-cell">Migration Time</div>
	<div class="col-md-1 migration-header-cell">Time&nbsp;Since&nbsp;Previous</div>
	<div class="col-md-3 migration-header-cell">Migration</div>
	<div class="col-md-5 migration-header-cell" style="text-align: left;">Description</div>
</div>
<?php
foreach ($migrations as $migration) {
	if (empty($migration['cached_difference_summary'])) {
		$from_definition = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['from_hash']));
		$to_definition = json_decode(GameDefinition::get_game_definition_by_hash($app, $migration['to_hash']));
		
		if ($from_definition && $to_definition) {
			list($differences, $difference_summary_lines) = GameDefinition::analyze_definition_differences($app, $from_definition, $to_definition);

			$migration_summary = ucfirst(strtolower(implode(", ", $difference_summary_lines)));

			$app->run_query("UPDATE game_definition_migrations SET cached_difference_summary=:cached_difference_summary WHERE migration_id=:migration_id;", [
				'cached_difference_summary' => $migration_summary,
				'migration_id' => $migration['migration_id'],
			]);
		}
		else $migration_summary = "";
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
