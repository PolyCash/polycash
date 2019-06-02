<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$event_id = (int) $_REQUEST['event_id'];
	$db_event_r = $app->run_query("SELECT * FROM events WHERE event_id='".$event_id."';");

	if ($db_event_r->rowCount() > 0) {
		$db_event = $db_event_r->fetch();
		$db_game = $app->fetch_db_game_by_id($db_event['game_id']);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			if ($app->user_can_edit_game($thisuser, $game)) {
				$user_game = $thisuser->ensure_user_in_game($game, false);
				
				if ($_REQUEST['action'] == "fetch") {
					$html = '<div class="modal-header"><h4 class="modal-title">';
					$html .= $db_event['event_name'];
					$html .= '</h2></div>'."\n";
					
					$html .= '<div class="modal-body">';
					
					$html .= '<p>To set the outcome of this event, please select one of these options.</p>';
					
					$q = "SELECT * FROM options WHERE event_id='".$db_event['event_id']."' ORDER BY option_index ASC;";
					$r = $app->run_query($q);
					
					$html .= '<select class="form-control" id="set_event_outcome_index" onchange="set_event_outcome_changed();">'."\n";
					$html .= '<option value="select">-- Please Select --</option>'."\n";
					while ($option = $r->fetch()) {
						$html .= '<option value="'.$option['event_option_index'].'">'.$option['name'].'</option>'."\n";
					}
					$html .= '<option value="-1">Cancel &amp; Refund</option>'."\n";
					$html .= '<option value="">Unset</option>'."\n";
					$html .= '</select>'."\n";
					
					$html .= '</div>';
					
					$dump_object['html'] = $html;
					
					$app->output_message(1, "", $dump_object);
				}
				else if ($_REQUEST['action'] == "set") {
					$outcome_index = $_REQUEST['outcome_index'];
					
					if ($outcome_index == "") {
						$option_ok = true;
						$outcome_index = 'NULL';
					}
					else if ($outcome_index == -1) {
						$option_ok = true;
						$outcome_index = '-1';
					}
					else {
						$outcome_index = (int)$_REQUEST['outcome_index'];
						
						$gdo_r = $app->run_query("SELECT * FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."' AND option_index='".$outcome_index."';");
						
						if ($gdo_r->rowCount() == 1) $option_ok = true;
						else $option_ok = false;
					}
					
					if ($option_ok) {
						$show_internal_params = false;
						
						$game->check_set_game_definition("defined", $show_internal_params);
						
						$q = "UPDATE game_defined_events SET outcome_index=".$outcome_index." WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$db_event['event_index']."';";
						$r = $app->run_query($q);
						
						$game->check_set_game_definition("defined", $show_internal_params);
						
						$app->output_message(2, "Changed the game definition.", false);
					}
					else $app->output_message(8, "Failed to find option by ID.", false);
				}
				else $app->output_message(7, "Please specify an action.", false);
			}
			else $app->output_message(6, "You don't have permission to set the outcome for this event.", false);
		}
		else $app->output_message(5, "Invalid game ID.", false);
	}
	else $app->output_message(4, "Invalid event ID.", false);
}
else $app->output_message(3, "Please log in.", false);
?>