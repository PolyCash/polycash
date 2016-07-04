<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser && $game) {
	$action = $_REQUEST['action'];
	
	$voting_strategy_id = intval($_REQUEST['voting_strategy_id']);
	
	if ($voting_strategy_id > 0) {
		$q = "SELECT * FROM user_strategies WHERE user_id='".$thisuser['user_id']."' AND strategy_id='".$voting_strategy_id."';";
		$r = run_query($q);
		if (mysql_numrows($r) == 1) {
			$user_strategy = mysql_fetch_array($r);
		}
		else die("Invalid strategy ID");
	}
	else {
		$q = "INSERT INTO user_strategies SET user_id='".$thisuser['user_id']."', game_id='".$game['game_id']."';";
		$r = run_query($q);
		$voting_strategy_id = mysql_insert_id();
		
		$q = "SELECT * FROM user_strategies WHERE strategy_id='".$voting_strategy_id."';";
		$r = run_query($q);
		$user_strategy = mysql_fetch_array($r);
	}
	
	if ($action == "save") {
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		
		save_plan_allocations($user_strategy, $from_round, $to_round);
		
		output_message(1, "", false);
	}
	else if ($action == "fetch") {
		$from_round = intval($_REQUEST['from_round']);
		$to_round = intval($_REQUEST['to_round']);
		
		$html = plan_options_html($game, $from_round, $to_round);
		
		$js .= '<script type="text/javascript">';
		$js .= "$(document).ready(function() {\n";
		
		$q = "SELECT * FROM strategy_round_allocations WHERE strategy_id='".$user_strategy['strategy_id']."' AND round_id >= ".$from_round." AND round_id <= ".$to_round.";";
		$r = run_query($q);
		while ($allocation = mysql_fetch_array($r)) {
			$js .= "load_plan_option(".$allocation['round_id'].", ".($allocation['option_id']-1).", ".$allocation['points'].");\n";
		}
		$js .= "});\n";
		$js .= "</script>\n";
		
		$output_obj['html'] = $html;
		$output_obj['js'] = $js;
		
		echo json_encode($output_obj);
	}
}
else output_message(2, "Please log in", false);
?>