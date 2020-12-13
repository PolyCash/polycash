<?php
$migrationsPerPage = 16;
if (!empty($_REQUEST['history_pos'])) $migrationsPerPage = (int) $_REQUEST['history_pos'];
list($migrations, $definitionsByHash, $migrationsByToHash, $migrationQuantity) = $app->fetch_recent_migrations($game, $migrationsPerPage);
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
	<div id="migration_history">
		<?php
		include(dirname(__FILE__).'/includes/migration_history.php');
		?>
	</div>
	<a href="" onclick="thisPageManager.more_game_history(<?php echo $migrationsPerPage; ?>); return false;">Show More</a>
</div>

<div style="display: none;" class="modal fade" id="migration_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content" id="migration_modal_content"></div>
	</div>
</div>
