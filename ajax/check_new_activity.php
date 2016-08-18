<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser || $_REQUEST['refresh_page'] != "wallet") {
	$instance_id = (int) $_REQUEST['instance_id'];
	$game_event_index = (int) $_REQUEST['game_event_index'];
	$event_loop_index = (int) $_REQUEST['event_loop_index'];
	$event_id = (int) $_REQUEST['event_id'];
	$event_ids = $_REQUEST['event_ids'];
	
	if (!$game) {
		$game_id = intval($_REQUEST['game_id']);
		$game = new Game($app, $game_id);
	}
	
	$event = new Event($game, false, $event_id);
	
	if ($thisuser) $thisuser->set_user_active();
	
	$bet_round_range = $game->bet_round_range();
	$last_block_id = $game->last_block_id();
	$last_transaction_id = $game->last_transaction_id();
	$current_round = $game->block_to_round($last_block_id+1);
	$block_within_round = $game->block_id_to_round_index($last_block_id+1);
	if ($thisuser) {
		$my_last_transaction_id = $thisuser->my_last_transaction_id($game->db_game['game_id']);
		$account_value = $thisuser->account_coin_value($game);
		$immature_balance = $thisuser->immature_balance($game);
		$mature_balance = $thisuser->mature_balance($game);
		$mature_io_ids_csv = $game->mature_io_ids_csv($thisuser->db_user['user_id']);
	}
	else {
		$my_last_transaction_id = false;
		$account_value = 0;
		$immature_balance = 0;
		$mature_balance = 0;
		$mature_io_ids_csv = "";
	}
	
	$output = false;
	$output['event_loop_index'] = $event_loop_index;
	
	$output['min_bet_round'] = $bet_round_range[0];
	$output['game_status_explanation'] = $game->game_status_explanation($thisuser);
	
	if ($bet_round_range[0] != $_REQUEST['min_bet_round']) {
		$output['select_bet_round'] = $game->select_bet_round($current_round);
	}
	
	if ($last_block_id != $_REQUEST['last_block_id']) {
		//$performance_history_sections = intval($_REQUEST['performance_history_sections']);
		$output['new_block'] = 1;
		$output['last_block_id'] = $last_block_id;
		
		$client_round = $game->block_to_round(intval($_REQUEST['last_block_id'])+1);
		
		/*if ($_REQUEST['refresh_page'] == "wallet" && $current_round != $client_round) {
			$output['new_performance_history'] = 1;
			$output['performance_history'] = $thisuser->performance_history($game, $current_round-(10*$performance_history_sections), $current_round-1);
			$output['performance_history_start_round'] = $current_round-(10*$performance_history_sections);
		}
		else */
		$output['new_performance_history'] = 0;
	}
	else $output['new_block'] = 0;
	
	if ($last_transaction_id != $_REQUEST['last_transaction_id']) {
		$output['new_transaction'] = 1;
		$output['last_transaction_id'] = $last_transaction_id;
	}
	else $output['new_transaction'] = 0;
	
	if ($my_last_transaction_id != $_REQUEST['my_last_transaction_id'] && $thisuser) {
		$output['my_bets'] = $game->my_bets($thisuser);
		$output['new_my_transaction'] = 1;
		$output['my_last_transaction_id'] = $my_last_transaction_id;
	}
	else $output['new_my_transaction'] = 0;
	
	if ($output['new_my_transaction'] == 1 || $mature_io_ids_csv != $_REQUEST['mature_io_ids_csv'] || $output['new_block'] == 1) {
		$output['select_input_buttons'] = $thisuser? $game->select_input_buttons($thisuser->db_user['user_id']) : "";
		$output['mature_io_ids_csv'] = $mature_io_ids_csv;
		$output['new_mature_ios'] = 1;
	}
	else $output['new_mature_ios'] = 0;
	
	if ($last_block_id != $_REQUEST['last_block_id'] || $last_transaction_id != $_REQUEST['last_transaction_id']) {
		//if ($_REQUEST['refresh_page'] == "wallet") $show_intro_text = true;
		$show_intro_text = false;
		$output['current_round_table'] = $event->current_round_table($current_round, $thisuser, $show_intro_text, true, $instance_id, $game_event_index);
		
		if ($thisuser) $output['wallet_text_stats'] = $thisuser->wallet_text_stats($game, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance);
		if ($thisuser) $output['my_current_votes'] = $event->my_votes_table($current_round, $thisuser);
		$output['account_value'] = $game->account_value_html($account_value);
		$output['vote_details_general'] = $app->vote_details_general($mature_balance);
		
		$round_stats = $event->round_voting_stats_all($current_round);
		$total_vote_sum = $round_stats[0];
		$option_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		
		$stats_output = false;
		for ($option_id=0; $option_id<count($round_stats); $option_id++) {
			$option = $round_stats[$option_id];
			if (!$option['last_win_round']) $losing_streak = false;
			else $losing_streak = $current_round - $option['last_win_round'] - 1;
			$stats_output[$option['option_id']] = '<div class=\'modal-dialog\'><div class=\'modal-content\'><div class=\'modal-header\'><h2 class=\'modal-title\'>Vote for '.$option['name'].'</h2></div><div class=\'modal-body\'><div id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_option_details_'.$option['option_id'].'\'></div><div id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_details_'.$option['option_id'].'\'>'.$app->vote_option_details($option, $option_id+1, $option[$game->db_game['payout_weight'].'_score'], $option['unconfirmed_'.$game->db_game['payout_weight'].'_score'], $total_vote_sum, 0).'</div><div class=\'redtext\' id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_error_'.$option['option_id'].'\'></div></div><div class=\'modal-footer\'><button class=\'btn btn-primary\' id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_confirm_btn_'.$option['option_id'].'\' onclick=\'games['.$instance_id.'].add_option_to_vote('.$option['option_id'].', "'.$option['name'].'");\'>Add '.$option['name'].' to my vote</button><button type=\'button\' class=\'btn btn-default\' data-dismiss=\'modal\'>Close</button></div></div></div>';
		}
		$output['vote_option_details'] = $stats_output;
	}
	
	if ($game->event_ids() != $event_ids) {
		$output['new_event_ids'] = 1;
		$js = $game->new_event_js($instance_id, $thisuser);
		$output['new_event_js'] = $js;
		$output['event_ids'] = $game->event_ids();
	}
	else $output['new_event_ids'] = 0;
	
	if ($thisuser) {
		$q = "SELECT * FROM addresses WHERE game_id='".$game->db_game['game_id']."' AND user_id='".$thisuser->db_user['user_id']."' AND option_index IS NOT NULL GROUP BY option_index;";
		$r = $app->run_query($q);
		$votingaddr_count = $r->rowCount();
	}
	else $votingaddr_count = 0;
	
	/*if ($thisuser && intval($_REQUEST['votingaddr_count']) != $votingaddr_count) {
		$output['new_votingaddresses'] = 1;
		
		$option_has_votingaddr = [];
		$votingaddr_count = 0;
		$q = "SELECT option_id FROM addresses WHERE game_id='".$game->db_game['game_id']."' AND user_id='".$thisuser->db_user['user_id']."' AND option_index IS NOT NULL GROUP BY option_index ORDER BY option_index ASC;";
		$r = $app->run_query($q);
		while ($option_id = $r->fetch(PDO::FETCH_NUM)) {
			$option_has_votingaddr[$option_id[0]] = true;
			$votingaddr_count++;
		}
		$output['option_has_votingaddr'] = $option_has_votingaddr;
		$output['votingaddr_count'] = $votingaddr_count;
	}
	else*/
	$output['new_votingaddresses'] = 0;
	
	if ($thisuser) {
		$q = "SELECT * FROM user_messages WHERE game_id='".$game->db_game['game_id']."' AND to_user_id='".$thisuser->db_user['user_id']."' AND seen=0 GROUP BY from_user_id;";
		$r = $app->run_query($q);
		if ($r->rowCount() > 0) {
			$output['new_messages'] = 1;
			$output['new_message_user_ids'] = "";
			while ($thread = $r->fetch()) {
				$output['new_message_user_ids'] .= $thread['from_user_id'].",";
			}
			$output['new_message_user_ids'] = substr($output['new_message_user_ids'], 0, strlen($output['new_message_user_ids'])-1);
		}
		else $output['new_messages'] = 0;
	}
	else $output['new_messages'] = 0;
	
	echo json_encode($output);
}
else echo "0";
?>
