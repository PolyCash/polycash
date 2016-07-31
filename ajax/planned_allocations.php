<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$action = $_REQUEST['action'];
	
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	
	if ($voting_strategy_id > 0) {
		$q = "SELECT * FROM user_strategies WHERE user_id='".$thisuser->db_user['user_id']."' AND strategy_id='".$voting_strategy_id."';";
		$r = $app->run_query($q);
		if ($r->rowCount() == 1) {
			$user_strategy = $r->fetch();
		}
		else die("Invalid strategy ID");
	}
	else {
		$q = "INSERT INTO user_strategies SET user_id='".$thisuser->db_user['user_id']."', game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		$voting_strategy_id = $app->last_insert_id();
		
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$voting_strategy_id."';";
		$r = $app->run_query($q);
		$user_strategy = $r->fetch();
	}
	
	if ($action == "save") {
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		
		$thisuser->save_plan_allocations($user_strategy, $from_round, $to_round);
		
		$app->output_message(1, "", false);
	}
	else if ($action == "fetch") {
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		if ($game->db_game['final_round'] > 0 && $to_round > $game->db_game['final_round']) $to_round = $game->db_game['final_round'];
		
		$html = $game->plan_options_html($from_round, $to_round);
		
		$js .= '<script type="text/javascript">';
		$js .= "$(document).ready(function() {\n";
		
		$q = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
		$r = $app->run_query($q);
		while ($allocation = $r->fetch()) {
			$js .= "load_plan_option(".$allocation['round_id'].", option_id2option_index[".$allocation['option_id']."], ".$allocation['points'].");\n";
		}
		$js .= "load_plan_option_game_types();\n";
		$js .= "});\n";
		$js .= "</script>\n";
		
		$output_obj['html'] = $html;
		$output_obj['js'] = $js;
		
		echo json_encode($output_obj);
	}
	else if ($action == "scramble") {
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		$game->scramble_plan_allocations($user_strategy, array(0=>1, 1=>0.5), $from_round, $to_round);
		$app->output_message(1, "Scrambled it!", false);
	}
}
else $app->output_message(2, "Please log in", false);
?>