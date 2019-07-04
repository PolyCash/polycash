<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $game && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token']) {
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