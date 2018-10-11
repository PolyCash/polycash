<?php
$host_not_required = TRUE;
include(realpath(dirname(dirname(__FILE__)))."/includes/connect.php");
include(realpath(dirname(dirname(__FILE__)))."/includes/get_session.php");

if ($thisuser) {
	$user_game_id = (int) $_REQUEST['user_game_id'];

	$q = "SELECT * FROM users u JOIN user_games ug ON u.user_id=ug.user_id JOIN games g ON ug.game_id=g.game_id JOIN user_strategies s ON ug.strategy_id=s.strategy_id LEFT JOIN featured_strategies fs ON s.featured_strategy_id=fs.featured_strategy_id WHERE ug.user_game_id='".$user_game['user_game_id']."';";
	$r = $this->blockchain->app->run_query($q);

	if ($r->rowCount() > 0) {
		$user_game = $r->fetch();
		
		if ($thisuser->db_user['user_id'] == $user_game['user_id']) {
			$blockchain = new Blockchain($app, $user_game['blockchain_id']);
			$game = new Game($blockchain, $user_game['game_id']);
			$mining_block_id = $blockchain->last_block_id()+1;
			$round_id = $game->block_to_round($mining_block_id);
			
			$log_text = "";
			$game->apply_user_strategy($log_text, $user_game, $mining_block_id, $round_id);
			$this->update_option_votes();
			echo "result: ".$log_text;
		}
		else echo "You don't have permission to apply this strategy.\n";
	}
	else echo "Error: invalid user_game_id.\n";
}
else echo "You must be logged in to complete this action.\n";
?>