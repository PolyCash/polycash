<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$db_game = $app->fetch_game_by_id($_REQUEST['game_id']);

if ($db_game) {
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $db_game['game_id']);

	$app->output_message(1, "", [
		'renderedContent' => $app->render_view('public_peer_details', [
			'game' => $game,
			'game_peers' => $game->fetch_all_peers(),
			'can_edit_game' => empty($thisuser) ? false : $app->user_can_edit_game($thisuser, $game),
		])
	]);
}
