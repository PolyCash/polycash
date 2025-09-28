<?php
set_time_limit(0);
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
	$message = null;
	$from_hash = null;
	$to_hash = null;

	if ($action == "preview_apply") {
		if (!empty($game->db_game['cached_definition_hash']) && !empty($game->db_game['defined_cached_definition_hash']) && $game->db_game['cached_definition_hash'] != $game->db_game['defined_cached_definition_hash']) {
			$from_hash = $game->db_game['cached_definition_hash'];
			$to_hash = $game->db_game['defined_cached_definition_hash'];
		}
		else $message = "No changes need to be applied right now.";
	}
	else {
		if (empty($migration)) {
			$from_hash = $_REQUEST['from_hash'];
			$to_hash = $_REQUEST['to_hash'];
		}
		else {
			$from_hash = $migration['from_hash'];
			$to_hash = $migration['to_hash'];
		}
	}
	
	$intended_reset_block = null;
	$intended_reset_event_index = null;
	
	if ($message) {}
	else if (!empty($migration['cached_difference_summary'])) $difference_summary_txt = $migration['cached_difference_summary'];
	else {
		$from_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $from_hash));
		$to_game_def = json_decode(GameDefinition::get_game_definition_by_hash($app, $to_hash));
		
		if (!empty($from_game_def) && !empty($to_game_def)) {
			list($differences, $difference_summary_lines) = GameDefinition::analyze_definition_differences($app, $from_game_def, $to_game_def);
			$difference_summary_txt = implode(".<br/>\n", $difference_summary_lines);
			[$intended_reset_block, $intended_reset_event_index] = GameDefinition::find_reset_block_and_event_index_from_defs($app, $game, $from_game_def, $to_game_def);
		}
		else $message = "Failed to load differences; game definitions are missing.";
	}
	?>
	<div class="modal-header">
		<b class="modal-title"><?php echo $game->db_game['name']; ?> migration: &nbsp; 
		<?php if (!$message) { ?>
		<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/<?php echo $from_hash; ?>"> <?php echo $from_hash; ?></a> &rarr; <a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/definition/<?php echo $to_hash; ?>"><?php echo $to_hash; ?></a></b>
		<?php } ?>

		<button type="button" class="close" data-dismiss="modal" aria-label="Close">
			<span aria-hidden="true">&times;</span>
		</button>
	</div>
	<div class="modal-body">
		<?php
		if ($message) echo $message;
		else {
			if ($migration) {
				$migrationTypeInfo = empty(GameDefinition::migration_types()[$migration['migration_type']]) ? null : GameDefinition::migration_types()[$migration['migration_type']];
				
				echo "<table>";
				echo "<tr><td style='min-width: 170px;'>Migration time:</td><td>".date("Y-m-d H:i:s", $migration['migration_time'])." UTC</td></tr>\n";
				echo "<tr><td>Migration type:</td><td>".(empty($migrationTypeInfo) ? $migration['migration_type'] : $migrationTypeInfo['label'])."</td></tr>\n";

				if ((string)$migration['extra_info'] != "" && $extra_info = json_decode($migration['extra_info'], true)) {
					if (isset($extra_info['reset_from_block'])) {
						$reset_from_block = $game->blockchain->fetch_block_by_id($extra_info['reset_from_block']);
						echo "<tr><td>Reset from block:</td><td>#".$extra_info['reset_from_block'];
						if ($reset_from_block) echo " (".date("Y-m-d H:i:s", $reset_from_block['time_mined']).")";
						echo "</td></tr>\n";
					}
					
					if (isset($extra_info['reset_from_event_index'])) {
						echo "<tr><td>Reset from event:</td><td>#".$extra_info['reset_from_event_index']."</td></tr>\n";
					}
				}
				if (isset($intended_reset_block)) {
					echo '<tr><td>Should reset from block:</td><td>#'.$intended_reset_block.'</td></tr>';
				}
				if (isset($intended_reset_event_index)) {
					echo '<tr><td>Should reset from event:</td><td>#'.$intended_reset_event_index.'</td></tr>';
				}
				
				echo "</table><br/>\n";
			}
			echo $difference_summary_txt;
			
			if ($action == "preview_apply") {
				echo '<br/><br/><a id="apply_def_link" href="" onClick="thisPageManager.apply_game_definition('.$game->db_game['game_id'].', \''.$from_hash.'\', \''.$to_hash.'\'); return false;">Apply Migration</a>';
			}
		}
		?>
	</div>
	<div class="modal-footer">
		<button type="button" class="btn btn-info" data-dismiss="modal" aria-label="Close">Close</button>
	</div>
	<?php
}
?>