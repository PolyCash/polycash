<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$event_id = (int) $_REQUEST['event_id'];

	$db_event_r = $app->run_query("SELECT * FROM events WHERE event_id='".$event_id."';");

	if ($db_event_r->rowCount() > 0) {
		$db_event = $db_event_r->fetch();
		
		$db_game_r = $app->run_query("SELECT * FROM games WHERE game_id='".$db_event['game_id']."';");
		
		if ($db_game_r->rowCount() > 0) {
			$db_game = $db_game_r->fetch();
			
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			if (empty($GLOBALS['prevent_changes_to_history']) || $game->db_game['creator_id'] == $thisuser->db_user['user_id']) {
				$user_game = $thisuser->ensure_user_in_game($game, false);
				
				if ($_REQUEST['action'] == "fetch") {
					$html = '<div class="modal-header"><h4 class="modal-title">';
					$html .= $db_event['event_name'];
					$html .= '</h2></div>'."\n";
					
					$html .= '<div class="modal-body">';
					
					$html .= '<p>To set the outcome of this event, please select one of these options.</p>';
					
					$q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_index ASC;";
					$r = $app->run_query($q);
					
					$html .= '<select class="form-control" id="set_event_outcome_option_id" onchange="set_event_outcome_selected();">'."\n";
					$html .= '<option value="">-- Please Select --</option>'."\n";
					while ($option = $r->fetch()) {
						$html .= '<option value="'.$option['option_id'].'">'.$option['name'].'</option>'."\n";
					}
					$html .= '</select>'."\n";
					
					$html .= '</div>';
					
					$dump_object['html'] = $html;
					
					$app->output_message(1, "", $dump_object);
				}
				else if ($_REQUEST['action'] == "set") {
					$option_id = (int) $_REQUEST['option_id'];
					
					$db_option_r = $app->run_query("SELECT * FROM options WHERE option_id='".$option_id."';");
					
					if ($db_option_r->rowCount() > 0) {
						$db_option = $db_option_r->fetch();
						
						if ($db_option['event_id'] == $db_event['event_id']) {
							$initial_game_def = $app->fetch_game_definition($game);
							$initial_game_def_hash = $app->game_definition_hash($game);
							
							$q = "UPDATE game_defined_events SET outcome_index=".$db_option['option_index']." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."';";
							$r = $app->run_query($q);
							
							$game->check_set_game_definition();
							
							$new_game_def = $app->fetch_game_definition($game);
							$new_game_def_hash = $app->game_definition_hash($game);
							
							$log_message = $app->migrate_game_definitions($game, $initial_game_def_hash, $new_game_def_hash);
							
							$app->output_message(2, $log_message, false);
						}
					}
				}
			}
			else $app->output_message(6, "You don't have permission to set the outcome for this event.", false);
		}
		else $app->output_message(5, "Invalid game ID.", false);
	}
	else $app->output_message(4, "Invalid event ID.", false);
}
else $app->output_message(3, "Please log in.", false);
?>