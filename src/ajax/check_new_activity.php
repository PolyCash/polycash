<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && !$app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) $thisuser = false;

$instance_id = (int) $_REQUEST['instance_id'];
$game_loop_index = (int) $_REQUEST['game_loop_index'];
$refresh_page = $_REQUEST['refresh_page'];

if (!$game) {
	$game_id = (int) $_REQUEST['game_id'];
	$db_game = $app->fetch_game_by_id($game_id);
	
	if ($db_game) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
	}
	else die("Invalid game ID supplied.\n");
}

if ($thisuser && $game->db_game['game_status'] == "published" && $app->user_can_edit_game($thisuser, $game) && $game->db_game['game_starting_block'] <= $game->blockchain->db_blockchain['last_complete_block']) {
	list($start_game_error, $start_game_error_message) = $game->start_game();
}

$game->load_current_events();

$net_risk_view_changed = false;

if ($thisuser) {
	$thisuser->set_user_active();
	$user_game = $thisuser->ensure_user_in_game($game, false);
	
	$net_risk_view = (int) ($_REQUEST['net_risk_view'] ?? 0);
	if ($user_game['net_risk_view'] != $net_risk_view) {
		$app->set_net_risk_view($user_game, $net_risk_view);
		$net_risk_view_changed = true;
	}
}

$blockchain_last_block_id = $game->blockchain->last_block_id();
$blockchain_current_round = $game->block_to_round($blockchain_last_block_id+1);
$blockchain_block_within_round = $game->block_id_to_round_index($blockchain_last_block_id+1);

$last_block_id = $game->last_block_id();
$current_round = $game->block_to_round($last_block_id+1);
$block_within_round = $game->block_id_to_round_index($last_block_id+1);
$coins_per_vote = $app->coins_per_vote($game->db_game);

if ($thisuser && $refresh_page == "wallet") {
	$mature_io_ids_csv = $game->mature_io_ids_csv($user_game);
}
else {
	$mature_io_ids_csv = "";
}
$mature_io_ids_hash = AppSettings::standardHash($mature_io_ids_csv);

$output = false;
$output['game_loop_index'] = $game_loop_index;

$output['game_status_explanation'] = $game->game_status_explanation($thisuser, $user_game);

if ($game->db_game['module'] == "CoinBattles") {
	if (!empty($game->current_events[0])) {
		list($html, $js) = $game->module->currency_chart($game, $game->current_events[0]->db_event['event_starting_block'], false);
		$output['chart_html'] = $html;
		$output['chart_js'] = $js;
	}
}

if ($last_block_id != (int) $_REQUEST['last_block_id']) {
	$output['new_block'] = 1;
	$output['last_block_id'] = $last_block_id;
	$db_last_block = $blockchain->fetch_block_by_id($last_block_id);
	$output['time_last_block_loaded'] = $db_last_block['time_mined'];
}
else $output['new_block'] = 0;

$filter_arr = [];
if (!empty($_REQUEST['filter_date'])) {
	$filter_time = strtotime($_REQUEST['filter_date']);
	$filter_arr['date'] = date("Y-m-d", $filter_time);
}
if (!empty($_REQUEST['filter_term'])) {
	$filter_arr['term'] = urldecode($_REQUEST['filter_term']);
}

if (isset($_REQUEST['event_hashes'])) $event_hashes = explode(",", $_REQUEST['event_hashes']);
else $event_hashes = [];

$these_events = $game->events_by_block($blockchain_last_block_id+1, $filter_arr);
$show_intro_text = false;

$set_options_js = "";

$output['rendered_events'] = [];

$display_event_ids = "";

for ($render_event_i=0; $render_event_i<count($these_events); $render_event_i++) {
	$rendered_event = $these_events[$render_event_i]->event_html($thisuser, $show_intro_text, true, $instance_id, $render_event_i);
	$rendered_event_hash = hash("sha256", $rendered_event);
	$rendered_event_hash = substr($rendered_event_hash, 0, 8);
	$display_event_ids .= $these_events[$render_event_i]->db_event['event_id'].",";
	
	if (isset($event_hashes[$render_event_i]) && $event_hashes[$render_event_i] == $rendered_event_hash && !$net_risk_view_changed) {
		$output['rendered_events'][$render_event_i] = ["hash" => false, "html" => ""];
	}
	else {
		$any_changed_events = true;
		$output['rendered_events'][$render_event_i] = ["hash" => $rendered_event_hash, "html" => $rendered_event];
		
		if ($thisuser) {
			$output['my_current_votes'][$render_event_i] = $these_events[$render_event_i]->my_votes_table($current_round, $user_game);
		}
		
		$round_stats = $these_events[$render_event_i]->round_voting_stats_all();
		$total_vote_sum = $round_stats[0];
		$option_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		
		$sum_votes = 0;
		$sum_unconfirmed_votes = 0;
		
		$sum_effective_votes = 0;
		$sum_unconfirmed_effective_votes = 0;
		
		$sum_burn_amount = 0;
		$sum_unconfirmed_burn_amount = 0;
		
		$sum_effective_burn_amount = 0;
		$sum_unconfirmed_effective_burn_amount = 0;
		
		for ($option_id=0; $option_id<count($round_stats); $option_id++) {
			$option = $round_stats[$option_id];
			
			$option_identifier = "games[".$instance_id."].events[".$render_event_i."].options[".$option['event_option_index']."]";
			$set_options_js .= $option_identifier.".votes = ".$option[$game->db_game['payout_weight'].'_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_votes = ".$option['unconfirmed_'.$game->db_game['payout_weight'].'_score'].";\n";
			$set_options_js .= $option_identifier.".effective_votes = ".$option['votes'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_effective_votes = ".$option['unconfirmed_votes'].";\n";
			$set_options_js .= $option_identifier.".burn_amount = ".$option['destroy_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_burn_amount = ".$option['unconfirmed_destroy_score'].";\n";
			$set_options_js .= $option_identifier.".effective_burn_amount = ".$option['effective_destroy_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_effective_burn_amount = ".$option['unconfirmed_effective_destroy_score'].";\n";
			
			$sum_votes += $option[$game->db_game['payout_weight'].'_score'];
			$sum_unconfirmed_votes += $option['unconfirmed_'.$game->db_game['payout_weight'].'_score'];
			
			$sum_effective_votes += $option['votes'];
			$sum_unconfirmed_effective_votes += $option['unconfirmed_votes'];
			
			$sum_burn_amount += $option['destroy_score'];
			$sum_unconfirmed_burn_amount += $option['unconfirmed_destroy_score'];
			
			$sum_effective_burn_amount += $option['effective_destroy_score'];
			$sum_unconfirmed_effective_burn_amount += $option['unconfirmed_effective_destroy_score'];
		}
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_votes = $sum_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_unconfirmed_votes = $sum_unconfirmed_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_effective_votes = $sum_effective_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_unconfirmed_effective_votes = $sum_unconfirmed_effective_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_burn_amount = $sum_burn_amount;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_unconfirmed_burn_amount = $sum_unconfirmed_burn_amount;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_effective_burn_amount = $sum_effective_burn_amount;\n";
		$set_options_js .= "games[".$instance_id."].events[".$render_event_i."].sum_unconfirmed_effective_burn_amount = $sum_unconfirmed_effective_burn_amount;\n";
	}
}

$output['set_options_js'] = $set_options_js;

if ($display_event_ids != "") $display_event_ids = substr($display_event_ids, 0, strlen($display_event_ids)-1);
$display_event_ids_hash = AppSettings::standardHash($display_event_ids);

if ($display_event_ids_hash != $_REQUEST['event_ids_hash']) {
	$js = $game->new_event_js($instance_id, $thisuser, $filter_arr, $display_event_ids, false)[0];
	$output['new_event_ids'] = 1;
	$output['new_event_js'] = $js;
	$output['event_ids'] = $display_event_ids;
}
else $output['new_event_ids'] = 0;

if ($refresh_page == "wallet" && ($mature_io_ids_hash != $_REQUEST['mature_io_ids_hash'] || !empty($output['new_block']))) {
	$output['select_input_buttons'] = $thisuser? $game->select_input_buttons($user_game) : "";
	$output['mature_io_ids_csv'] = $mature_io_ids_csv;
	$output['new_mature_ios'] = 1;
	
	if ($thisuser) {
		$unconfirmed_amount = $thisuser->unconfirmed_amount($game, $user_game);
		$immature_amount = $thisuser->immature_amount($game, $user_game);
		$mature_balance = $thisuser->mature_balance($game, $user_game);
		list($user_votes, $votes_value) = $thisuser->user_current_votes($game, $last_block_id, $current_round, $user_game);
		$user_pending_bets = $game->user_pending_bets($user_game);
		$game_pending_bets = $game->pending_bets(true);
		list($vote_supply, $vote_supply_value) = $game->vote_supply($last_block_id, $current_round, $coins_per_vote, true);
		$account_value = $mature_balance+$immature_amount+$unconfirmed_amount+$user_pending_bets;
		
		$output['wallet_text_stats'] = $thisuser->wallet_text_stats($game, $blockchain_current_round, $blockchain_last_block_id, $blockchain_block_within_round, $mature_balance, $unconfirmed_amount+$immature_amount, $user_votes, $votes_value, $user_pending_bets, $user_game);
		
		$output['account_value'] = $game->account_value_html($account_value, $user_game, $game_pending_bets, $vote_supply_value);
	}
}
else $output['new_mature_ios'] = 0;

if ($thisuser && $refresh_page == "wallet") {
	$unseen_message_threads = $app->run_query("SELECT * FROM user_messages WHERE game_id=:game_id AND to_user_id=:to_user_id AND seen=0 GROUP BY from_user_id;", [
		'game_id' => $game->db_game['game_id'],
		'to_user_id' => $thisuser->db_user['user_id']
	])->fetchAll();
	
	if (count($unseen_message_threads) > 0) {
		$output['new_messages'] = 1;
		$output['new_message_user_ids'] = "";
		
		foreach ($unseen_message_threads as $thread) {
			$output['new_message_user_ids'] .= $thread['from_user_id'].",";
		}
		$output['new_message_user_ids'] = substr($output['new_message_user_ids'], 0, strlen($output['new_message_user_ids'])-1);
	}
	else $output['new_messages'] = 0;
}
else $output['new_messages'] = 0;


$being_determined_events = $game->events_being_determined_in_block($blockchain_last_block_id+1);
$being_determined_content = $app->render_view('being_determined_events', [
	'thisuser' => $thisuser ? $thisuser : null,
	'game' => $game,
	'events' => $being_determined_events,
	'user_game' => $thisuser ? $user_game : null,
	'round_id' => $current_round,
]);
$being_determined_hash = empty($being_determined_content) ? null : hash("sha256", $being_determined_content);

$output['being_determined_hash'] = $being_determined_hash;

if (!isset($_REQUEST['being_determined_hash']) || $_REQUEST['being_determined_hash'] != $being_determined_hash) {
	$output['being_determined_content'] = $being_determined_content;
}

echo json_encode($output);
?>
