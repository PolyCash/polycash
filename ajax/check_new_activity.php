<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser) {
	set_user_active($thisuser['user_id']);
	
	$last_block_id = last_block_id($thisuser['currency_mode']);
	$last_transaction_id = last_voting_transaction_id();
	$current_round = block_to_round($last_block_id+1);
	$block_within_round = $last_block_id%get_site_constant('round_length')+1;
	$account_value = account_coin_value($thisuser);
	$immature_balance = immature_balance($thisuser);
	$mature_balance = $account_value - $immature_balance;
	
	$output = false;
	
	if ($last_block_id != $_REQUEST['last_block_id']) {
		$performance_history_sections = intval($_REQUEST['performance_history_sections']);
		$output['new_block'] = 1;
		$output['last_block_id'] = $last_block_id;
		$output['account_value'] = number_format($account_value, 3);
		
		if ($last_block_id%10 == 0) {
			$output['performance_history'] = performance_history($thisuser, $current_round-(10*$performance_history_sections), $current_round-1);
			$output['performance_history_start_round'] = $current_round-(10*$performance_history_sections);
		}
	}
	else $output['new_block'] = 0;
	
	if ($last_transaction_id != $_REQUEST['last_transaction_id']) {
		$output['new_transaction'] = 1;
		$output['last_transaction_id'] = $last_transaction_id;
		$output['vote_details_general'] = vote_details_general($mature_balance);
	}
	else $output['new_transaction'] = 0;
	
	if ($last_block_id != $_REQUEST['last_block_id'] || $last_transaction_id != $_REQUEST['last_transaction_id']) {
		$output['current_round_table'] = current_round_table($current_round, $thisuser, true, true);
		$output['wallet_text_stats'] = wallet_text_stats($thisuser, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance);
		$output['my_current_votes'] = my_votes_table($current_round, $thisuser);
		
		$round_stats = round_voting_stats_all($current_round);
		$total_vote_sum = $round_stats[0];
		$nation_id_to_rank = $round_stats[3];
		$round_stats = $round_stats[2];
		
		$stats_output = false;
		for ($nation_id=1; $nation_id<=16; $nation_id++) {
			$nation = $round_stats[$nation_id_to_rank[$nation_id]];
			$stats_output[$nation_id] = vote_nation_details($nation, $nation_id_to_rank[$nation['nation_id']]+1, $nation['coins_currently_voted'], $total_vote_sum, $nation['losing_streak']);
		}
		$output['vote_nation_details'] = $stats_output;
	}
	
	echo json_encode($output);
}
else echo "0";
?>