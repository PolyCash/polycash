<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser || $_REQUEST['refresh_page'] == "home") {
	$game_loop_index = intval($_REQUEST['game_loop_index']);
	
	if ($thisuser) {
		set_user_active($thisuser['user_id']);
		$game_id = $thisuser['game_id'];
	}
	else $game_id = get_site_constant('primary_game_id');
	
	$q = "SELECT * FROM games WHERE game_id='".$game_id."';";
	$r = run_query($q);
	$game = mysql_fetch_array($r);
	
	if ($game['payout_weight'] == "coin") $score_field = "coins_currently_voted";
	else $score_field = "coin_block_score";
	
	if ($game['game_status'] == "running") {
		if ($game['game_type'] == "simulation" && $game['block_timing'] == "realistic") {
			$rand_max = floor($game['seconds_per_block']/get_site_constant('game_loop_seconds'))-1;
			$num = rand(0, $rand_max);
			if ($num == 0) {
				$log_text = new_block($game['game_id']);
			}
			
			$log_text = apply_user_strategies($game);
		}
	}
	
	$bet_round_range = bet_round_range($game);
	$last_block_id = last_block_id($game_id);
	$last_transaction_id = last_transaction_id($game_id);
	$my_last_transaction_id = my_last_transaction_id($thisuser['user_id'], $game_id);
	$mature_io_ids_csv = mature_io_ids_csv($thisuser['user_id'], $game);
	$current_round = block_to_round($game, $last_block_id+1);
	$block_within_round = $last_block_id%$game['round_length']+1;
	$account_value = account_coin_value($game, $thisuser);
	$immature_balance = immature_balance($game, $thisuser);
	$mature_balance = $account_value - $immature_balance;
	
	$output = false;
	$output['game_loop_index'] = $game_loop_index;
	
	$output['min_bet_round'] = $bet_round_range[0];
	
	if ($bet_round_range[0] != $_REQUEST['min_bet_round']) {
		$output['select_bet_round'] = select_bet_round($game, $current_round);
	}
	
	if ($last_block_id != $_REQUEST['last_block_id']) {
		$performance_history_sections = intval($_REQUEST['performance_history_sections']);
		$output['new_block'] = 1;
		$output['last_block_id'] = $last_block_id;
		
		$client_round = block_to_round($game, intval($_REQUEST['last_block_id'])+1);
		
		if ($_REQUEST['refresh_page'] == "wallet" && $current_round != $client_round) {
			$output['new_performance_history'] = 1;
			$output['performance_history'] = performance_history($thisuser, $current_round-(10*$performance_history_sections), $current_round-1);
			$output['performance_history_start_round'] = $current_round-(10*$performance_history_sections);
		}
		else $output['new_performance_history'] = 0;
	}
	else $output['new_block'] = 0;
	
	if ($last_transaction_id != $_REQUEST['last_transaction_id']) {
		$output['new_transaction'] = 1;
		$output['last_transaction_id'] = $last_transaction_id;
	}
	else $output['new_transaction'] = 0;
	
	if ($my_last_transaction_id != $_REQUEST['my_last_transaction_id'] && $thisuser) {
		$output['select_input_buttons'] = select_input_buttons($thisuser['user_id'], $game);
		$output['my_bets'] = my_bets($game, $thisuser);
		$output['new_my_transaction'] = 1;
		$output['my_last_transaction_id'] = $my_last_transaction_id;
	}
	else $output['new_my_transaction'] = 0;
	
	if ($mature_io_ids_csv != $_REQUEST['mature_io_ids_csv']) {
		$output['mature_io_ids_csv'] = $mature_io_ids_csv;
		$output['new_mature_ios'] = 1;
	}
	else $output['new_mature_ios'] = 0;
	
	if ($last_block_id != $_REQUEST['last_block_id'] || $last_transaction_id != $_REQUEST['last_transaction_id']) {
		$output['current_round_table'] = current_round_table($game, $current_round, $thisuser, true);
		$output['wallet_text_stats'] = wallet_text_stats($thisuser, $game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance);
		$output['my_current_votes'] = my_votes_table($game, $current_round, $thisuser);
		$output['account_value'] = number_format($account_value/pow(10,8), 2);
		$output['vote_details_general'] = vote_details_general($mature_balance);
		
		$round_stats = round_voting_stats_all($game, $current_round);
		$total_vote_sum = $round_stats[0];
		$nation_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		
		$stats_output = false;
		for ($nation_id=1; $nation_id<=16; $nation_id++) {
			$nation = $round_stats[$nation_id2rank[$nation_id]];
			$stats_output[$nation_id] = vote_nation_details($nation, $nation_id2rank[$nation['nation_id']]+1, $nation[$score_field], $total_vote_sum, $nation['losing_streak']);
		}
		$output['vote_nation_details'] = $stats_output;
	}
	
	$q = "SELECT * FROM addresses WHERE game_id='".$game['game_id']."' AND user_id='".$thisuser['user_id']."' AND nation_id > 0 GROUP BY nation_id;";
	$r = run_query($q);
	$votingaddr_count = mysql_numrows($r);
	
	if (intval($_REQUEST['votingaddr_count']) != $votingaddr_count) {
		$output['new_votingaddresses'] = 1;
		
		$nation_has_votingaddr = [];
		$votingaddr_count = 0;
		$q = "SELECT nation_id FROM addresses WHERE game_id='".$game['game_id']."' AND user_id='".$thisuser['user_id']."' AND nation_id > 0 GROUP BY nation_id ORDER BY nation_id ASC;";
		$r = run_query($q);
		while ($nation_id = mysql_fetch_row($r)) {
			$nation_has_votingaddr[$nation_id[0]] = true;
			$votingaddr_count++;
		}
		$output['nation_has_votingaddr'] = $nation_has_votingaddr;
		$output['votingaddr_count'] = $votingaddr_count;
	}
	else $output['new_votingaddresses'] = 0;
	
	echo json_encode($output);
}
else echo "0";
?>