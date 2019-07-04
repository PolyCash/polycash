<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$db_event = $app->fetch_event_by_id((int)$_REQUEST['event_id']);

	if ($db_event) {
		$db_game = $app->fetch_game_by_id($db_event['game_id']);
		
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
					
					$options_by_event = $app->fetch_options_by_event($db_event['event_id']);
					
					$html .= '<select class="form-control" id="set_event_outcome_index" onchange="thisPageManager.set_event_outcome_changed();">'."\n";
					$html .= '<option value="select">-- Please Select --</option>'."\n";
					while ($option = $options_by_event->fetch()) {
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
					$outcome_index = (string) $_REQUEST['outcome_index'];
					
					if ($outcome_index == "") {
						$option_ok = true;
						$outcome_index = null;
					}
					else if ($outcome_index == "-1") {
						$option_ok = true;
						$outcome_index = -1;
					}
					else {
						$outcome_index = (int)$_REQUEST['outcome_index'];
						
						$gdo_r = $app->fetch_game_defined_options($game->db_game['game_id'], $db_event['event_index'], $outcome_index, false);
						
						if ($gdo_r->rowCount() == 1) $option_ok = true;
						else $option_ok = false;
					}
					
					if ($option_ok) {
						$show_internal_params = false;
						
						$game->check_set_game_definition("defined", $show_internal_params);
						
						$game->set_game_defined_outcome($db_event['event_index'], $outcome_index);
						
						$game->check_set_game_definition("defined", $show_internal_params);
						$game->set_cached_definition_hashes();
						
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