<?php
$host_not_required = true;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if (!empty($argv)) {
	$cmd_vars = $app->argv_to_array($argv);
	if (!empty($cmd_vars['key'])) $_REQUEST['key'] = $cmd_vars['key'];
	else if (!empty($cmd_vars[0])) $_REQUEST['key'] = $cmd_vars[0];
	$_REQUEST['game_id'] = $cmd_vars['game_id'];
	$_REQUEST['block_height'] = $cmd_vars['block_height'];
}

$game_id = (int) $_REQUEST['game_id'];
$game = new Game($app, $game_id);

$block_height = (int) $_REQUEST['block_height'];

$game->delete_blocks_from_height($block_height);
$coin_rpc = new jsonRPCClient('http://'.$game->db_game['rpc_username'].':'.$game->db_game['rpc_password'].'@127.0.0.1:'.$game->db_game['rpc_port'].'/');
$block_hash = $coin_rpc->getblockhash($block_height);

$start_time = microtime(true);
$game->coind_add_block($coin_rpc, $block_hash, $block_height, false);
$message = "Took ".(microtime(true)-$start_time)." sec to add block #".$block_height."<br/>\n";
$app->log($message);
echo "<br/>".$message;
?>