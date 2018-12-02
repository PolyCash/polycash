<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

if ($thisuser) {
	$action = $_REQUEST['action'];
	if ($action != "new") $game_id = intval($_REQUEST['game_id']);
	
	if ($action == "switch") {
		$game = new Game($app, $game_id);
		
		if ($game) {
			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$game->db_game['game_id']."';";
			$r = $app->run_query($q);
			
			if ($r->rowCount() == 1) {
				$user_game = $r->fetch();
				
				$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
			}
			else {
				if ($game->db_game['creator_id'] > 0) {
					$app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
				}
				else {
					$user_game = $thisuser->ensure_user_in_game($game, false);
					
					$q = "UPDATE users SET game_id='".$game->db_game['game_id']."' WHERE user_id='".$thisuser->db_user['user_id']."';";
					$r = $app->run_query($q);
					
					$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
				}
			}
		}
		else $app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
	}
	else if ($action == "new") {
		$new_game_perm = $thisuser->new_game_permission();
		
		if ($new_game_perm) {
			$default_game_def_txt = '{
				"protocol_version": 0,
				"name": "",
				"url_identifier": "",
				"module": null,
				"decimal_places": 8,
				"category_id": null,
				"event_type_name": "event",
				"event_type_name_plural": "events",
				"event_rule": "game_definition",
				"event_winning_rule": "game_definition",
				"event_entity_type_id": null,
				"option_group_id": null,
				"events_per_round": 1,
				"inflation": "exponential",
				"exponential_inflation_rate": 0,
				"pos_reward": 0,
				"round_length": 1,
				"maturity": 0,
				"payout_weight": "coin_round",
				"final_round": null,
				"buyin_policy": "none",
				"game_buyin_cap": 0,
				"sellout_policy": "off",
				"sellout_confirmations": 0,
				"coin_name": "coin",
				"coin_name_plural": "coins",
				"coin_abbreviation": "COIN",
				"escrow_address": "",
				"genesis_tx_hash": "",
				"genesis_amount": 100000000000000,
				"game_starting_block": 1,
				"game_winning_rule": "none",
				"game_winning_field": "",
				"game_winning_inflation": 0,
				"default_vote_effectiveness_function": "constant",
				"default_effectiveness_param1": 1,
				"default_max_voting_fraction": 1,
				"default_option_max_width": 200,
				"default_payout_block_delay": 0,
				"view_mode": "default"
			}';
			
			$setup_error = false;
			$setup_error_message = "";
			
			$q = "SELECT MAX(creator_game_index) FROM games WHERE creator_id='".$thisuser->db_user['user_id']."';";
			$r = $app->run_query($q);
			if ($r->rowCount() > 0) {
				$game_index = $r->fetch(PDO::FETCH_NUM);
				$game_index = $game_index[0]+1;
			}
			else $game_index = 1;
			
			$blockchain_id = (int) $_REQUEST['blockchain_id'];
			$blockchain = new Blockchain($app, $blockchain_id);
			
			$game_module = $_REQUEST['module'];
			
			if (empty($game_module)) {
				$initial_game_def = json_decode($default_game_def_txt);
			}
			else {
				eval('$module = new '.$game_module.'GameDefinition($app);');
				$initial_game_def = json_decode($module->game_def_base_txt);
			}
			
			$initial_game_def->name = urldecode($_REQUEST['name']);
			$initial_game_def->url_identifier = $app->game_url_identifier($initial_game_def->name);
			$initial_game_def->module = $game_module;
			$initial_game_def->game_starting_block = floor($blockchain->last_block_id()/$initial_game_def->round_length)*$initial_game_def->round_length+1;
			
			$db_group = false;
			if (!empty($initial_game_def->option_group)) {
				$db_group = $app->select_group_by_description($initial_game_def->option_group);
				
				if (!$db_group) {
					$import_error = "";
					$app->import_group_from_file($initial_game_def->option_group, $import_error);
					
					$db_group = $app->select_group_by_description($initial_game_def->option_group);
					
					if (!$db_group) {
						$setup_error = true;
						$setup_error_message = "Error: the \"".$initial_game_def->option_group."\" group does not exist. Please visit /groups/ to add this group and then try again.";
					}
				}
			}
			
			if (!$setup_error) {
				$verbatim_vars = $app->game_definition_verbatim_vars();
				
				$q = "INSERT INTO games SET creator_id='".$thisuser->db_user['user_id']."', blockchain_id='".$blockchain->db_blockchain['blockchain_id']."', game_status='editable', featured=1";
				if ($db_group) $q .= ", option_group_id=".$db_group['group_id'];
				
				for ($i=0; $i<count($verbatim_vars); $i++) {
					$var_type = $verbatim_vars[$i][0];
					$var_name = $verbatim_vars[$i][1];
					
					if ($initial_game_def->$var_name != "") {
						$q .= ", ".$var_name."=".$app->quote_escape($initial_game_def->$var_name);
					}
				}
				$q .= ";";
				$r = $app->run_query($q);
				$game_id = $app->last_insert_id();
				
				$game = new Game($blockchain, $game_id);
				
				$user_game = $thisuser->ensure_user_in_game($game, false);
				$user_strategy = $app->run_query("SELECT * FROM user_strategies WHERE strategy_id='".$user_game['strategy_id']."';")->fetch();
				
				$genesis_tx_hash = "";
				$escrow_address = "";
				
				if ($_REQUEST['genesis_type'] == "existing") {
					$genesis_tx_hash = $_REQUEST['genesis_tx_hash'];
					
					$genesis_first_address_r = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON io.address_id=a.address_id WHERE t.tx_hash=".$app->quote_escape($genesis_tx_hash)." AND io.out_index=0;");
					if ($genesis_first_address_r->rowCount() == 1) {
						$genesis_first_address = $genesis_first_address_r->fetch();
						$escrow_address = $genesis_first_address['address'];
					}
				}
				else {
					$io_id = (int) $_REQUEST['genesis_io_id'];
					$escrow_amount = (float) $_REQUEST['escrow_amount'];
					
					$io_q = "SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id JOIN currency_accounts ca ON k.account_id=ca.account_id WHERE io.io_id='".$io_id."' AND ca.user_id='".$thisuser->db_user['user_id']."';";
					$io_r = $app->run_query($io_q);
					
					if ($io_r->rowCount() > 0) {
						$io = $io_r->fetch();
						$escrow_amount = $escrow_amount*pow(10, $blockchain->db_blockchain['decimal_places']);
						$fee_amount = $user_strategy['transaction_fee']*pow(10, $blockchain->db_blockchain['decimal_places']);
						$genesis_remainder = $io['amount']-$escrow_amount-$fee_amount;
						
						if ($escrow_amount > 0) {
							if ($genesis_remainder > 0) {
								$account_name = "Escrow account for ".$game_name;
								
								$genesis_account_q = "INSERT INTO currency_accounts SET currency_id='".$blockchain->currency_id()."', user_id='".$thisuser->db_user['user_id']."', is_escrow_account=1, account_name=".$app->quote_escape($account_name).", time_created='".time()."';";
								$genesis_account_r = $app->run_query($genesis_account_q);
								$account_id = $app->last_insert_id();
								$genesis_account = $app->run_query("SELECT * FROM currency_accounts WHERE account_id='".$account_id."';")->fetch();
								
								$db_genesis_address = $app->new_address_key($blockchain->currency_id(), $genesis_account);
								$escrow_address = $db_genesis_address['pub_key'];
								
								$address_q = "SELECT * FROM address_keys WHERE account_id='".$user_game['account_id']."';";
								$address_r = $app->run_query($address_q);
								$my_address = $address_r->fetch();
								
								$error_message = false;
								$transaction_id = $blockchain->create_transaction('transaction', array($escrow_amount, $genesis_remainder), false, array($io['io_id']), array($db_genesis_address['address_id'], $my_address['address_id']), $fee_amount, $error_message);
								
								$transaction = $app->run_query("SELECT * FROM transactions WHERE transaction_id='".$transaction_id."';")->fetch();
								$genesis_tx_hash = $transaction['tx_hash'];
							}
							else {
								$app->output_message(2, "The escrow amount must be smaller than the amount of your UTXO.", false);
								die();
							}
						}
						else {
							$app->output_message(3, "The escrow amount must be greater than zero.", false);
							die();
						}
					}
					else {
						$app->output_message(4, "Error, you don't have permission to spend those coins.", false);
						die();
					}
				}
				
				$q = "UPDATE games SET escrow_address=".$app->quote_escape($escrow_address).", genesis_tx_hash=".$app->quote_escape($genesis_tx_hash)." WHERE game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				
				$app->output_message(1, $game->db_game['url_identifier'], false);
			}
			else {
				$app->output_message(5, $setup_error_message, false);
			}
		}
		else {
			$app->output_message(6, "You don't have permission to create a new game.", false);
		}
	}
	else if ($action == "fetch") {
		$db_game = $app->fetch_db_game_by_id($game_id);
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
		
		$q = "SELECT game_id, blockchain_id, creator_id, event_rule, option_group_id, event_entity_type_id, events_per_round, event_type_name, game_status, block_timing, giveaway_status, giveaway_amount, maturity, name, payout_weight, round_length, pos_reward, pow_reward, inflation, exponential_inflation_rate, exponential_inflation_minershare, final_round, invite_cost, invite_currency, coin_name, coin_name_plural, coin_abbreviation, start_condition, start_datetime, buyin_policy, game_buyin_cap, default_vote_effectiveness_function, default_effectiveness_param1, default_max_voting_fraction, game_starting_block, escrow_address, genesis_tx_hash, genesis_amount, default_betting_mode FROM games WHERE game_id='".$game->db_game['game_id']."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$switch_game = $r->fetch(PDO::FETCH_ASSOC);

			$q = "SELECT * FROM user_games WHERE user_id='".$thisuser->db_user['user_id']."' AND game_id='".$switch_game['game_id']."';";
			$r = $app->run_query($q);
			$user_game = $r->fetch();
			
			if ($switch_game['creator_id'] == $thisuser->db_user['user_id'] || $user_game) {
				if ($switch_game['creator_id'] == $thisuser->db_user['user_id']) $switch_game['my_game'] = true;
				else $switch_game['my_game'] = false;

				$switch_game['creator_id'] = false;

				$switch_game['name_disp'] = '<a target="_blank" href="/'.$game->db_game['url_identifier'].'">'.$switch_game['name'].'</a>';

				$switch_game['start_date'] = date("n/j/Y", strtotime($switch_game['start_datetime']));
				$switch_game['start_time'] = date("G", strtotime($switch_game['start_datetime']));
				
				$app->output_message(1, "", $switch_game);
			}
			else $app->output_message(2, "Access denied", false);
		}
		else $app->output_message(2, "Access denied", false);
	}
	else if ($action == "load_gde" || $action == "save_gde" || $action == "manage_gdos" || $action == "add_new_gdo" || $action == "delete_gdo") {
		$db_game = $app->fetch_db_game_by_id($game_id);
		
		if ($db_game) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
			
			if ($app->user_can_edit_game($thisuser, $game)) {
				if ($action == "load_gde") {
					$gde_id = $_REQUEST['gde_id'];
					$gde = false;
					
					if ($gde_id == "new") {
						$q = "SELECT COUNT(*), MAX(event_index) FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."';";
						$r = $app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$row = $r->fetch();
							if ($row['COUNT(*)'] > 0) $new_event_index = $row['MAX(event_index)']+1;
							else $new_event_index = 0;
						}
						else {
							$new_event_index = 0;
						}
						$gde['event_index'] = $new_event_index;
					}
					else {
						$gde_id = (int) $gde_id;
						
						$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND game_defined_event_id='".$gde_id."';";
						$r = $app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$gde = $r->fetch(PDO::FETCH_ASSOC);
						}
					}
					
					$verbatim_vars = $app->event_verbatim_vars();
					
					$form_data = array();
					
					for ($i=0; $i<count($verbatim_vars); $i++) {
						if (isset($gde[$verbatim_vars[$i][1]])) $form_data[$verbatim_vars[$i][1]] = $gde[$verbatim_vars[$i][1]];
					}
					
					$output_obj['form_data'] = $form_data;
					
					$app->output_message(1, "", $output_obj);
				}
				else if ($action == "save_gde") {
					$game->check_set_game_definition("defined");
					
					$verbatim_vars = $app->event_verbatim_vars();
					
					$gde_id = $_REQUEST['gde_id'];
					
					if ($gde_id == "new") $q = "INSERT INTO game_defined_events SET game_id='".$game->db_game['game_id']."', ";
					else {
						$gde_id = (int) $gde_id;
						$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND game_defined_event_id='".$gde_id."';";
						$r = $app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$gde = $r->fetch();
							$q = "UPDATE game_defined_events SET ";
						}
						else {
							$app->output_message(8, "Failed to load that event.", false);
							die();
						}
					}
					
					for ($i=0; $i<count($verbatim_vars); $i++) {
						$var = $verbatim_vars[$i][1];
						if (isset($_REQUEST[$var])) $val = $_REQUEST[$var];
						else $val = "";
						if (isset($val) && $val !== "" && in_array($var, array('event_starting_time', 'event_final_time'))) {
							$val = date("Y-m-d G:i:s", strtotime($val));
						}
						$q .= $var."=";
						if (!isset($val) || $val === "") $q .= "NULL";
						else $q .= $app->quote_escape($val);
						$q .= ", ";
					}
					$q = substr($q, 0, strlen($q)-2);
					
					if ($gde_id == "new") $q .= ";";
					else $q .= " WHERE game_defined_event_id='".$gde['game_defined_event_id']."';";
					$r = $app->run_query($q);
					
					if ($gde_id == "new") {
						$gde_id = $app->last_insert_id();
					}
					
					$game->check_set_game_definition("defined");
					
					$app->output_message(1, "Changed the game definition.", false);
				}
				else if ($action == "manage_gdos") {
					$gde_id = (int) $_REQUEST['gde_id'];
					
					$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND game_defined_event_id='".$gde_id."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$gde = $r->fetch();
						
						$html = '<div class="modal-body"><p><b>'.$gde['event_name'].':</b></p>'."\n";
						
						$q = "SELECT * FROM game_defined_options gdo LEFT JOIN entities e ON gdo.entity_id=e.entity_id LEFT JOIN entity_types et ON e.entity_type_id=et.entity_type_id WHERE gdo.game_id='".$game->db_game['game_id']."' AND gdo.event_index='".$gde['event_index']."' ORDER BY gdo.option_index ASC;";
						$r = $app->run_query($q);
						
						while ($gdo = $r->fetch()) {
							$html .= '<div class="row">';
							
							$html .= '<div class="col-md-6">';
							$html .= $gdo['option_index'].". ".$gdo['name'];
							$html .= '</div>'."\n";
							
							$html .= '<div class="col-md-3">';
							$html .= ucfirst($gdo['entity_name']);
							$html .= '</div>'."\n";
							
							$html .= '<div class="col-md-1">';
							$html .= '<i class="fa fa-times redtext" aria-hidden="true" style="cursor: pointer;" title="Delete this option" onclick="delete_game_defined_option('.$gde['game_defined_event_id'].', '.$gdo['game_defined_option_id'].');"></i>';
							$html .= '</div>'."\n";
							
							$html .= '</div>'."\n";
						}
						
						$html .= '<p style="margin-top: 10px;"><a href="" onclick="$(\'#new_gdo_form\').toggle(\'fast\'); return false;">Add another option</a></p>'."\n";
						
						$html .= '<div id="new_gdo_form" style="display: none">';
						
						$html .= '<div class="form-group">';
						$html .= '<label for="new_gdo_name">Name:</label>'."\n";
						$html .= '<input type="text" class="form-control" id="new_gdo_name" value="" />'."\n";
						$html .= "</div>\n";
						
						$html .= '<div class="form-group">';
						$html .= '<label for="new_gdo_entity_type_id">Entity type:</label>'."\n";
						$html .= '<select class="form-control" id="new_gdo_entity_type_id">'."\n";
						
						$q = "SELECT * FROM entity_types ORDER BY entity_name='general entity' DESC, entity_name ASC;";
						$r = $app->run_query($q);
						
						while ($entity_type = $r->fetch()) {
							$html .= '<option value="'.$entity_type['entity_type_id'].'">'.$entity_type['entity_name'].'</option>'."\n";
						}
						$html .= '</select>'."\n";
						$html .= "</div>\n";
						
						$html .= '<button type="button" class="btn btn-primary" onclick="add_game_defined_option(\''.$gde_id.'\');">Add option</button>'."\n";
						
						$html .= "</div>\n";
						
						$html .= "</div>\n";
						
						$output_obj = array();
						$output_obj['html'] = $html;
						
						$app->output_message(1, "", $output_obj);
					}
					else $app->output_message(7, "Invalid game defined event ID.", false);
				}
				else if ($action == "add_new_gdo") {
					$gde_id = (int) $_REQUEST['gde_id'];
					
					$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' AND game_defined_event_id='".$gde_id."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$gde = $r->fetch();
						
						$name = $_REQUEST['name'];
						$entity_type_id = (int) $_REQUEST['entity_type_id'];
						
						$q = "SELECT * FROM entity_types WHERE entity_type_id='".$entity_type_id."';";
						$r = $app->run_query($q);
						
						if ($r->rowCount() > 0) {
							$entity_type = $r->fetch();
							
							if (!empty($name)) {
								$entity = $app->check_set_entity($entity_type['entity_type_id'], $name);
								
								$game->check_set_game_definition("defined");
								
								$q = "SELECT COUNT(*), MAX(option_index) FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$gde['event_index']."';";
								$r = $app->run_query($q);
								$info = $r->fetch();
								if ($info['COUNT(*)'] > 0) $option_index = $info['COUNT(*)'];
								else $option_index = 0;
								
								$q = "INSERT INTO game_defined_options SET game_id='".$game->db_game['game_id']."', event_index='".$gde['event_index']."', entity_id='".$entity['entity_id']."', name=".$app->quote_escape($name).", option_index='".$option_index."';";
								$r = $app->run_query($q);
								$gdo_id = $app->last_insert_id();
								
								$game->check_set_game_definition("defined");
								
								$app->output_message(1, "Changed the game definition.", false);
							}
							else $app->output_message(9, "Invalid name.", false);
						}
						else $app->output_message(8, "Invalid entity type ID.", false);
					}
					else $app->output_message(7, "Invalid game defined event ID.", false);
				}
				else if ($action == "delete_gdo") {
					$gdo_id = (int) $_REQUEST['gdo_id'];
					
					$q = "SELECT * FROM game_defined_options WHERE game_id='".$game->db_game['game_id']."' AND game_defined_option_id='".$gdo_id."';";
					$r = $app->run_query($q);
					
					if ($r->rowCount() > 0) {
						$gdo = $r->fetch();
						
						$q = "DELETE FROM game_defined_options WHERE game_defined_option_id='".$gdo['game_defined_option_id']."';";
						$r = $app->run_query($q);
						
						$q = "UPDATE game_defined_options SET option_index=option_index-1 WHERE game_id='".$game->db_game['game_id']."' AND event_index='".$gdo['event_index']."' AND option_index>".$gdo['option_index'].";";
						$r = $app->run_query($q);
						
						$app->output_message(1, "Deleting...", false);
					}
					else $app->output_message(7, "Invalid game defined option ID.", false);
				}
				else $app->output_message(6, "Invalid action.", false);
			}
			else $app->output_message(5, "You don't have permission to perform this action.", false);
		}
		else $app->output_message(4, "Invalid game ID.", false);
	}
	else $app->output_message(3, "Bad URL", false);
	/*else if ($action == "reset" || $action == "delete") {
		$q = "SELECT * FROM game_types WHERE game_id='".$game_id."';";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$this_game = $r->fetch();
			if ($this_game['p2p_mode'] != "rpc" && $this_game['creator_id'] == $thisuser->db_user['user_id']) {
				$success = delete_reset_game($action, $game_id);
				
				if ($success) {
					$output_obj['redirect_url'] = '/wallet/'.$game->db_game['url_identifier'];
					
					output_message(1, "", $output_obj);
				}
				else output_message(2, "Error, the game couldn't be reset.", false);
			}
			else output_message(2, "You can't modify this game.", false);
		}
		else output_message(2, "You can't modify this game.", false);
	}*/
}
else $app->output_message(2, "Please log in.", false);
?>