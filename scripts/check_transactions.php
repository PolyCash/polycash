<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	$game_id = intval($_REQUEST['game_id']);
	$q = "SELECT * FROM games";
	if ($game_id > 0) $q .= " WHERE game_id='".$game_id."'";
	$q .= ";";
	$r = $app->run_query($q);
	
	while ($db_game = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$qq = "SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id='".$game->db_game['game_id']."' AND t.transaction_desc='transaction' GROUP BY t.transaction_id ORDER BY t.block_id ASC;";
		$rr = $app->run_query($qq);
		
		$error_count = 0;
		$first_error_block = false;
		$last_block_id = $game->last_block_id();
		
		echo "Last game block loaded was #".$last_block_id."<br/>\n";
		echo "Checking ".$rr->rowCount()." transactions for ".$game->db_game['name']."<br/>\n";
		
		while ($transaction = $rr->fetch()) {
			$coins_in = $game->transaction_coins_in($transaction['transaction_id']);
			$coins_out = $game->transaction_coins_out($transaction['transaction_id'], true);
			if (($coins_in == 0 || $coins_out == 0) || $coins_in < $coins_out || $coins_out-$coins_in > 0.5) {
				if (!$first_error_block) $first_error_block = $transaction['block_id'];
				echo 'Block '.$transaction['block_id'].' <a href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$transaction['transaction_id'].'">TX '.$transaction['transaction_id'].'</a> has '.$app->format_bignum($coins_in/pow(10,8)).' coins in and '.$app->format_bignum($coins_out/pow(10,8)).' coins out.<br/>';
				$error_count++;
			}
		}
		
		echo $error_count." errors.<br/>\n";
		
		if ($first_error_block) {
			$reset_block = min($first_error_block-1, $last_block_id+1);
			
			echo "First error was on block #".$first_error_block.", please <a href=\"/scripts/reset_game.php?game_id=".$game->db_game['game_id']."&key=".$GLOBALS['cron_key_string']."&block_id=".$reset_block."\">reset the game from block ".$reset_block."</a><br/>\n";
		}
	}
	echo "Done!";
}
else echo "Incorrect key.";
?>
