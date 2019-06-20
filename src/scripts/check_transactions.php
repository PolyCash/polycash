<?php
ini_set('memory_limit', '1024M');
$host_not_required = TRUE;
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$game_id = intval($_REQUEST['game_id']);
	$check_games_q = "SELECT * FROM games";
	if ($game_id > 0) $check_games_q .= " WHERE game_id='".$game_id."'";
	$check_games_q .= ";";
	$check_games = $app->run_query($check_games_q);
	
	while ($db_game = $check_games->fetch()) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		$error_count = 0;
		$first_error_block = false;
		$first_severe_error_block = false;
		$last_block_id = $game->last_block_id();
		
		echo "Last game block loaded was #".$last_block_id."<br/>\n";
		
		$check_unconfirmed_transactions = $app->run_query("SELECT * FROM transactions WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND transaction_desc='transaction' AND block_id IS NULL;");
		
		echo "Checking ".$check_unconfirmed_transactions->rowCount()." unconfirmed transactions in ".$blockchain->db_blockchain['blockchain_name']."<br/>\n";
		
		while ($transaction = $check_unconfirmed_transactions->fetch()) {
			$coins_in = $app->transaction_coins_in($transaction['transaction_id']);
			$coins_out = $app->transaction_coins_out($transaction['transaction_id']);
			
			if ((string) $coins_in !== (string) $coins_out) {
				echo "TX ".$transaction['tx_hash']." has ".$coins_in." coins in, ".$coins_out." coins out.<br/>\n";
				
				if ($coins_in == 0) {
					echo "Deleting ".$transaction['tx_hash']." ...<br/>\n";
					$app->run_query("DELETE FROM transaction_ios WHERE create_transaction_id='".$transaction['transaction_id']."';");
					$app->run_query("DELETE FROM transactions WHERE transaction_id='".$transaction['transaction_id']."';");
				}
			}
		}
		
		$check_game_transactions = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.spend_transaction_id JOIN transaction_game_ios gio ON io.io_id=gio.io_id WHERE gio.game_id='".$game->db_game['game_id']."' AND t.transaction_desc='transaction' AND io.spend_block_id<=".$game->last_block_id()." GROUP BY t.transaction_id ORDER BY t.block_id ASC;");
		
		echo "Checking ".$check_game_transactions->rowCount()." transactions for ".$game->db_game['name']."<br/>\n";
		$severe_threshold = 50;
		
		while ($transaction = $check_game_transactions->fetch()) {
			$coins_in = $game->transaction_coins_in($transaction['transaction_id']);
			$coins_out = $game->transaction_coins_out($transaction['transaction_id'], true);
			
			if (($coins_in == 0 || $coins_out == 0) || $coins_in < $coins_out || $coins_in-$coins_out > $severe_threshold) {
				if (!$first_error_block) $first_error_block = $transaction['block_id'];
				echo 'Block '.$transaction['block_id'].' <a href="/explorer/games/'.$game->db_game['url_identifier'].'/transactions/'.$transaction['transaction_id'].'">TX '.$transaction['transaction_id'].'</a> has '.((string) $coins_in).' coins in and '.((string) $coins_out).' coins out.';
				if (abs($coins_in-$coins_out) > $severe_threshold) {
					if ($first_severe_error_block === false) $first_severe_error_block = $transaction['block_id'];
					echo "<b>Severe</b>";
				}
				echo '<br/>';
				$error_count++;
			}
		}
		
		echo $error_count." errors.<br/>\n";
		
		if ($first_error_block) {
			$reset_block = min($first_error_block, $last_block_id+1);
			
			echo "First error was on block #".$first_error_block.", please <a href=\"/scripts/reset_game.php?game_id=".$game->db_game['game_id']."&key=".AppSettings::getParam('cron_key_string')."&block_id=".$reset_block."\">reset the game from block ".$reset_block."</a>";
			if ($first_severe_error_block !== false) echo " or <a href=\"/scripts/reset_game.php?game_id=".$game->db_game['game_id']."&key=".AppSettings::getParam('cron_key_string')."&block_id=".$first_severe_error_block."\">reset the game from block ".$first_severe_error_block."</a>";
			echo "<br/>\n";
		}
	}
	echo "Done!\n";
}
else echo "You need admin privileges to run this script.\n";
?>
