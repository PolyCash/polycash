<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$action = $_REQUEST['action'];
	
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	
	if ($voting_strategy_id > 0) {
		$user_strategy = $app->run_query("SELECT * FROM user_strategies WHERE user_id='".$thisuser->db_user['user_id']."' AND strategy_id='".$voting_strategy_id."';")->fetch();
		
		if (!$user_strategy) die("Invalid strategy ID");
	}
	else {
		$app->run_query("INSERT INTO user_strategies SET user_id='".$thisuser->db_user['user_id']."', game_id='".$game->db_game['game_id']."';");
		$voting_strategy_id = $app->last_insert_id();
		
		$user_strategy = $app->fetch_strategy_by_id($voting_strategy_id);
	}
	
	if ($action == "save") {
		$from_display_round = (int)$_REQUEST['from_round'];
		$to_display_round = (int)$_REQUEST['to_round'];
		
		$from_round = $game->display_round_to_round($from_display_round);
		$to_round = $game->display_round_to_round($to_display_round);
		
		$thisuser->save_plan_allocations($game, $user_strategy, $from_round, $to_round);
		
		$app->output_message(1, "", false);
	}
	else if ($action == "fetch") {
		$from_display_round = (int)$_REQUEST['from_round'];
		$to_display_round = (int)$_REQUEST['to_round'];
		
		if ($game->db_game['final_round'] > 0 && $to_display_round > $game->db_game['final_round']) $to_display_round = $game->db_game['final_round'];
		
		$from_round = $game->display_round_to_round($from_display_round);
		$to_round = $game->display_round_to_round($to_display_round);
		
		$html = $game->plan_options_html($from_round, $to_round, $user_strategy);
		
		$output_obj['html'] = $html;
		
		echo json_encode($output_obj);
	}
	else if ($action == "scramble") {
		$from_display_round = (int)$_REQUEST['from_round'];
		$to_display_round = (int)$_REQUEST['to_round'];
		
		$from_round = $game->display_round_to_round($from_display_round);
		$to_round = $game->display_round_to_round($to_display_round);
		
		$game->scramble_plan_allocations($user_strategy, array(0=>1, 1=>0.5), $from_round, $to_round);
		$app->output_message(1, "Scrambled it!", false);
	}
	
	if ($action == "save" || $action == "scramble") {
		$app->run_query("UPDATE user_strategies SET voting_strategy='by_plan' WHERE strategy_id=".$user_strategy['strategy_id'].";");
	}
}
else $app->output_message(2, "Please log in", false);
?>