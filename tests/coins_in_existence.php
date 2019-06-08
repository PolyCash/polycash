<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id', 'from_block', 'to_block'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = (int) $_REQUEST['game_id'];
	$from_block = (int) $_REQUEST['from_block'];
	$to_block = (int) $_REQUEST['to_block'];

	$db_game = $app->fetch_db_game_by_id($game_id);
	$blockchain = new Blockchain($app, $db_game['blockchain_id']);
	$game = new Game($blockchain, $game_id);

	for ($block=$from_block; $block<=$to_block; $block++) {
		$coins_in_existence = $game->coins_in_existence($block, false);
		echo $app->format_bignum($coins_in_existence/pow(10,$game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']." at block #".$block."<br/>\n";
	}
}
else echo "You need admin privileges to run this script.\n";
?>