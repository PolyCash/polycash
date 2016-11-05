<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['from_block'] = $cmd_vars['from_block'];
	$_REQUEST['to_block'] = $cmd_vars['to_block'];
}

$game_id = (int) $_REQUEST['game_id'];
$from_block = (int) $_REQUEST['from_block'];
$to_block = (int) $_REQUEST['to_block'];

$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $game_id);

for ($block=$from_block; $block<=$to_block; $block++) {
	$coins_in_existence = $game->coins_in_existence($block);
	echo $app->format_bignum($coins_in_existence/pow(10,8))." ".$game->db_game['coin_name_plural']." at block #".$block."<br/>\n";
}
?>