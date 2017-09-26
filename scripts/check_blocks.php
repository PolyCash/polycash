<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");

if ($argv) $_REQUEST['key'] = $argv[1];

if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
	if (!empty($_REQUEST['blockchain_id'])) {
		$blockchain_id = (int) $_REQUEST['blockchain_id'];
	}
	else $blockchain_id = false;
	
	$q = "SELECT * FROM blockchains WHERE ";
	if ($blockchain_id) $q .= "blockchain_id='".$blockchain_id."'";
	else $q .= "online=1";
	$q .= ";";
	$r = $app->run_query($q);
	
	while ($db_blockchain = $r->fetch()) {
		$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
		echo $db_blockchain['blockchain_name']." checking ".$blockchain->db_blockchain['name']." ".$db_blockchain['first_required_block']." to ".$blockchain->last_block_id()."<br/>\n";
		
		for ($block_id=$db_blockchain['first_required_block']; $block_id<=$blockchain->last_block_id(); $block_id++) {
			$temp_block = $app->run_query("SELECT * FROM blocks WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."' AND block_id='".$block_id."';")->fetch();
			
			if ($temp_block) {
				list($num_trans, $block_sum) = $blockchain->block_stats($temp_block);
				
				if ($num_trans != $temp_block['num_transactions']) {
					$message = "Error in block ".$temp_block['block_id'].", (Should be ".$temp_block['num_transactions']." but there are only ".$num_trans.")";
					echo "$message<br/>\n";
					$app->log_message($message);
					
					$qq = "UPDATE blocks SET locally_saved=0 WHERE internal_block_id='".$temp_block['internal_block_id']."';";
					$rr = $app->run_query($qq);
					
					/*$qq = "SELECT * FROM games WHERE blockchain_id='".$blockchain->db_blockchain['blockchain_id']."';";
					$rr = $app->run_query($qq);
					
					while ($db_game = $rr->fetch()) {
						$game = new Game($blockchain, $db_game['game_id']);
						
						echo "Resetting game '".$game->db_game['name']."' from block #".$temp_block['block_id']."<br/>\n";
						$game->delete_from_block($temp_block['block_id']);
						$game->update_db_game();
						$game->ensure_events_until_block($game->blockchain->last_block_id()+1);
						$game->load_current_events();
						$game->sync(false);
					}*/
				}
				else echo $temp_block['block_id']." ";
			}
			else $block_id = $blockchain->last_block_id();
		}
	}
}
else echo "Incorrect key.";
?>