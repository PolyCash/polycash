<?php
include(AppSettings::srcPath()."/includes/connect.php");
include(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game) {
	if ($game->db_game['public_players'] == 1) {
		?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<div class="panel-title">Players</div>
			</div>
			<div class="panel-body">
				<?php
				echo $game->render_game_players();
				?>
			</div>
		</div>
		<?php
	}
}
?>