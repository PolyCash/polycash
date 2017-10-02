<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser && $game) {
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
?>