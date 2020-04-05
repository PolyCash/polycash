<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

if ($thisuser && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
	$action = $_REQUEST['action'];
	
	if ($action == "new") {
		$new_game_perm = $thisuser->new_game_permission();
		
		if ($new_game_perm) {
			$default_game_def_txt = '{
				"option_group": null,
				"protocol_version": 0,
				"name": "",
				"url_identifier": "",
				"module": null,
				"category_id": null,
				"decimal_places": 4,
				"finite_events": true,
				"event_type_name": "event",
				"event_type_name_plural": "events",
				"event_rule": "game_definition",
				"event_winning_rule": "game_definition",
				"event_entity_type_id": null,
				"events_per_round": 1,
				"inflation": "exponential",
				"exponential_inflation_rate": 0,
				"pos_reward": 0,
				"round_length": 1,
				"maturity": 0,
				"payout_weight": "coin_round",
				"final_round": null,
				"buyin_policy": "for_sale",
				"game_buyin_cap": 0,
				"sellout_policy": "on",
				"sellout_confirmations": 0,
				"coin_name": "coin",
				"coin_name_plural": "coins",
				"coin_abbreviation": "COIN",
				"escrow_address": "",
				"genesis_tx_hash": "",
				"genesis_amount": 10000000000,
				"game_starting_block": 1,
				"game_winning_rule": "none",
				"game_winning_field": "",
				"game_winning_inflation": 0,
				"default_payout_rate": 1,
				"default_vote_effectiveness_function": "constant",
				"default_effectiveness_param1": 0,
				"default_max_voting_fraction": 1,
				"default_option_max_width": 200,
				"default_payout_block_delay": 0,
				"view_mode": "default"
			}';
			
			$setup_error = false;
			$setup_error_message = "";
			
			$game_index = (int)($app->run_query("SELECT MAX(creator_game_index) FROM games WHERE creator_id=:user_id;", ['user_id'=>$thisuser->db_user['user_id']])->fetch(PDO::FETCH_NUM))+1;
			
			$blockchain_id = (int) $_REQUEST['blockchain_id'];
			$blockchain = new Blockchain($app, $blockchain_id);
			
			$module_ok = true;
			$game_module = "";
			if (!empty($_REQUEST['module'])) {
				$existing_module = $app->check_module($_REQUEST['module']);
				if ($existing_module) $game_module = $existing_module['module_name'];
				else $module_ok = false;
			}
			
			if ($module_ok) {
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
					$db_group = $app->fetch_group_by_description($initial_game_def->option_group);
					
					if (!$db_group) {
						$import_error = "";
						$app->import_group_from_file($initial_game_def->option_group, $import_error);
						
						$db_group = $app->fetch_group_by_description($initial_game_def->option_group);
						
						if (!$db_group) {
							$setup_error = true;
							$setup_error_message = "Error: the \"".$initial_game_def->option_group."\" group does not exist. Please visit /groups/ to add this group and then try again.";
						}
					}
				}
				
				if (!$setup_error) {
					$verbatim_vars = $app->game_definition_verbatim_vars();
					
					$new_game_params = [
						'creator_id' => $thisuser->db_user['user_id'],
						'game_status' => 'editable',
						'featured' => 0,
						'option_group_id' => $db_group ? $db_group['group_id'] : null,
					];
					
					for ($i=0; $i<count($verbatim_vars); $i++) {
						$var_type = $verbatim_vars[$i][0];
						$var_name = $verbatim_vars[$i][1];
						
						if ($initial_game_def->$var_name != "") {
							$new_game_params[$var_name] = $initial_game_def->$var_name;
						}
					}
					$game = Game::create_game($blockchain, $new_game_params);
					
					$show_internal_params = false;
					list($initial_game_def_hash, $initial_game_def) = GameDefinition::fetch_game_definition($game, "actual", $show_internal_params, false);
					GameDefinition::check_set_game_definition($app, $initial_game_def_hash, $initial_game_def);
					
					$user_game = $thisuser->ensure_user_in_game($game, false);
					$user_strategy = $app->fetch_strategy_by_id($user_game['strategy_id']);
					
					$genesis_tx_hash = "";
					$escrow_address = "";
					
					if ($_REQUEST['genesis_type'] == "existing") {
						$genesis_tx_hash = $_REQUEST['genesis_tx_hash'];
						
						$genesis_first_address_r = $app->run_query("SELECT * FROM transactions t JOIN transaction_ios io ON t.transaction_id=io.create_transaction_id JOIN addresses a ON io.address_id=a.address_id WHERE t.tx_hash=:genesis_tx_hash AND io.out_index=0;", [
							'genesis_tx_hash' => $genesis_tx_hash
						]);
						if ($genesis_first_address_r->rowCount() == 1) {
							$genesis_first_address = $genesis_first_address_r->fetch();
							$escrow_address = $genesis_first_address['address'];
						}
					}
					else {
						$genesis_io_id = (int) $_REQUEST['genesis_io_id'];
						$escrow_amount = (float) $_REQUEST['escrow_amount'];
						
						$genesis_io = $app->run_query("SELECT * FROM transaction_ios io JOIN address_keys k ON io.address_id=k.address_id JOIN currency_accounts ca ON k.account_id=ca.account_id WHERE io.io_id=:genesis_io_id AND ca.user_id=:user_id;", [
							'genesis_io_id' => $genesis_io_id,
							'user_id' => $thisuser->db_user['user_id']
						])->fetch();
						
						if ($genesis_io) {
							$escrow_amount = $escrow_amount*pow(10, $blockchain->db_blockchain['decimal_places']);
							$fee_amount = $user_strategy['transaction_fee']*pow(10, $blockchain->db_blockchain['decimal_places']);
							$genesis_remainder = $genesis_io['amount']-$escrow_amount-$fee_amount;
							
							if ($escrow_amount > 0) {
								if ($genesis_remainder > 0) {
									$genesis_account = $app->create_new_account([
										'currency_id' => $blockchain->currency_id(),
										'user_id' => $thisuser->db_user['user_id'],
										'is_escrow_account' => 1,
										'account_name' => "Escrow account for ".$new_game_params['name']
									]);
									
									$db_genesis_address = $app->new_address_key($blockchain->currency_id(), $genesis_account);
									$escrow_address = $db_genesis_address['pub_key'];
									
									$my_address = $app->any_normal_address_in_account($user_game['account_id']);
									
									$error_message = false;
									$transaction_id = $blockchain->create_transaction('transaction', [$escrow_amount, $genesis_remainder], false, [$genesis_io['io_id']], [$db_genesis_address['address_id'], $my_address['address_id']], $fee_amount, $error_message);
									
									$transaction = $app->fetch_transaction_by_id($transaction_id);
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
					
					$app->run_query("UPDATE games SET escrow_address=:escrow_address, genesis_tx_hash=:genesis_tx_hash WHERE game_id=:game_id;", [
						'escrow_address' => $escrow_address,
						'genesis_tx_hash' => $genesis_tx_hash,
						'game_id' => $game->db_game['game_id']
					]);
					
					list($final_game_def_hash, $final_game_def) = GameDefinition::fetch_game_definition($game, "actual", $show_internal_params, false);
					GameDefinition::check_set_game_definition($app, $final_game_def_hash, $final_game_def);
					
					GameDefinition::record_migration($game, $thisuser->db_user['user_id'], "create_game_by_ui", $show_internal_params, $initial_defined_game_def, $final_defined_game_def);
					
					GameDefinition::set_cached_definition_hashes($game);
					
					$app->output_message(1, $game->db_game['url_identifier'], false);
				}
				else $app->output_message(5, $setup_error_message, false);
			}
			else $app->output_message(6, "Invalid module.", false);
		}
		else $app->output_message(6, "You don't have permission to create a new game.", false);
	}
	else {
		$game_id = (int) $_REQUEST['game_id'];
		$db_game = $app->fetch_game_by_id($game_id);
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
		
		if ($action == "switch") {
			$game = new Game($app, $game_id);
			
			if ($game) {
				$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $game->db_game['game_id']);
				
				if ($user_game) {
					$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
				}
				else {
					if ($game->db_game['creator_id'] > 0) {
						$app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
					}
					else {
						$user_game = $thisuser->ensure_user_in_game($game, false);
						
						$app->run_query("UPDATE users SET game_id=:game_id WHERE user_id=:user_id;", [
							'game_id' => $game->db_game['game_id'],
							'user_id' => $thisuser->db_user['user_id']
						]);
						
						$app->output_message(1, "", array('redirect_url'=>'/wallet/'.$game->db_game['url_identifier']));
					}
				}
			}
			else $app->output_message(2, "That game doesn't exist or you don't have permission to join it.");
		}
		else if ($app->user_can_edit_game($thisuser, $game)) {
			if ($action == "fetch") {
				$switch_game = $app->run_query("SELECT game_id, blockchain_id, module, creator_id, event_rule, option_group_id, event_entity_type_id, events_per_round, event_type_name, game_status, block_timing, giveaway_status, giveaway_amount, maturity, name, payout_weight, round_length, pos_reward, pow_reward, inflation, exponential_inflation_rate, exponential_inflation_minershare, final_round, invite_cost, invite_currency, coin_name, coin_name_plural, coin_abbreviation, start_condition, start_datetime, buyin_policy, game_buyin_cap, default_vote_effectiveness_function, default_effectiveness_param1, default_max_voting_fraction, game_starting_block, escrow_address, genesis_tx_hash, genesis_amount, default_betting_mode, finite_events FROM games WHERE game_id=:game_id;", [
					'game_id' => $game->db_game['game_id']
				])->fetch(PDO::FETCH_ASSOC);
				
				if ($switch_game) {
					$user_game = $app->fetch_user_game($thisuser->db_user['user_id'], $switch_game['game_id']);
					
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
			else if (in_array($action, ["load_gde","save_gde","manage_gdos","add_new_gdo","delete_gdo"])) {
				if ($action == "load_gde") {
					$gde_id = $_REQUEST['gde_id'];
					$gde = false;
					
					if ($gde_id == "new") {
						$gde_info = $app->run_query("SELECT COUNT(*), MAX(event_index) FROM game_defined_events WHERE game_id=:game_id;", [
							'game_id' => $game->db_game['game_id']
						])->fetch();
						
						if ($gde_info) {
							if ($gde_info['COUNT(*)'] > 0) $new_event_index = $gde_info['MAX(event_index)']+1;
							else $new_event_index = 0;
						}
						else $new_event_index = 0;
						
						$gde['event_index'] = $new_event_index;
					}
					else {
						$gde = $app->fetch_game_defined_event_by_id($game->db_game['game_id'], $gde_id);
					}
					
					$verbatim_vars = $app->event_verbatim_vars();
					
					$form_data = [];
					
					for ($i=0; $i<count($verbatim_vars); $i++) {
						if (isset($gde[$verbatim_vars[$i][1]])) $form_data[$verbatim_vars[$i][1]] = $gde[$verbatim_vars[$i][1]];
					}
					
					$output_obj['form_data'] = $form_data;
					
					$app->output_message(1, "", $output_obj);
				}
				else if ($action == "save_gde") {
					$show_internal_params = true;
					
					list($initial_defined_game_def_hash, $initial_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
					GameDefinition::check_set_game_definition($app, $initial_defined_game_def_hash, $initial_defined_game_def);
					
					$verbatim_vars = $app->event_verbatim_vars();
					
					$gde_id = $_REQUEST['gde_id'];
					
					$change_gde_params = [];
					
					if ($gde_id == "new") {
						$change_gde_q = "INSERT INTO game_defined_events SET game_id=:game_id, ";
						$change_gde_params['game_id'] = $game->db_game['game_id'];
					}
					else {
						$gde = $app->fetch_game_defined_event_by_id($game->db_game['game_id'], $gde_id);
						
						if ($gde) {
							$change_gde_q = "UPDATE game_defined_events SET ";
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
						if (!isset($val) || $val === "") $val = null;
						
						$change_gde_q .= $var."=:".$var.", ";
						$change_gde_params[$var] = $val;
					}
					$change_gde_q = substr($change_gde_q, 0, strlen($change_gde_q)-2);
					
					if ($gde_id == "new") $change_gde_q .= ";";
					else {
						$change_gde_q .= " WHERE game_defined_event_id=:game_defined_event_id;";
						$change_gde_params['game_defined_event_id'] = $gde['game_defined_event_id'];
					}
					$app->run_query($change_gde_q, $change_gde_params);
					
					list($final_defined_game_def_hash, $final_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
					GameDefinition::check_set_game_definition($app, $final_defined_game_def_hash, $final_defined_game_def);
					
					GameDefinition::record_migration($game, $thisuser->db_user['user_id'], "change_event_by_ui", $show_internal_params, $initial_defined_game_def, $final_defined_game_def);
					
					GameDefinition::set_cached_definition_hashes($game);
					
					$app->output_message(1, "Changed the game definition.", false);
				}
				else if ($action == "manage_gdos") {
					$gde = $app->fetch_game_defined_event_by_id($game->db_game['game_id'], $_REQUEST['gde_id']);
					
					if ($gde) {
						$html = '<div class="modal-body"><p><b>'.$gde['event_name'].':</b></p>'."\n";
						
						$gdos = $app->fetch_game_defined_options($game->db_game['game_id'], $gde['event_index'], false, true);
						
						while ($gdo = $gdos->fetch()) {
							$html .= '<div class="row">';
							
							$html .= '<div class="col-md-6">';
							$html .= $gdo['option_index'].". ".$gdo['name'];
							$html .= '</div>'."\n";
							
							$html .= '<div class="col-md-3">';
							$html .= ucfirst($gdo['entity_name']);
							$html .= '</div>'."\n";
							
							$html .= '<div class="col-md-1">';
							$html .= '<i class="fa fa-times redtext" aria-hidden="true" style="cursor: pointer;" title="Delete this option" onclick="thisPageManager.delete_game_defined_option('.$gde['game_defined_event_id'].', '.$gdo['game_defined_option_id'].');"></i>';
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
						
						$entity_types = $app->run_query("SELECT * FROM entity_types ORDER BY entity_name='general entity' DESC, entity_name ASC;");
						
						while ($entity_type = $entity_types->fetch()) {
							$html .= '<option value="'.$entity_type['entity_type_id'].'">'.$entity_type['entity_name'].'</option>'."\n";
						}
						$html .= '</select>'."\n";
						$html .= "</div>\n";
						
						$html .= '<button type="button" class="btn btn-primary" onclick="thisPageManager.add_game_defined_option(\''.$gde['game_defined_event_id'].'\');">Add option</button>'."\n";
						
						$html .= "</div>\n";
						
						$html .= "</div>\n";
						
						$output_obj = [
							'html' => $html
						];
						
						$app->output_message(1, "", $output_obj);
					}
					else $app->output_message(7, "Invalid game defined event ID.", false);
				}
				else if ($action == "add_new_gdo") {
					$gde = $app->fetch_game_defined_event_by_id($game->db_game['game_id'], $_REQUEST['gde_id']);
					
					if ($gde) {
						$name = $_REQUEST['name'];
						
						$entity_type = $app->fetch_entity_type_by_id($_REQUEST['entity_type_id']);
						
						if ($entity_type) {
							if (!empty($name)) {
								$show_internal_params = true;
								
								$entity = $app->check_set_entity($entity_type['entity_type_id'], $name);
								
								list($initial_defined_game_def_hash, $initial_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
								GameDefinition::check_set_game_definition($app, $initial_defined_game_def_hash, $initial_defined_game_def);
								
								$option_index = (int)($app->run_query("SELECT COUNT(*) FROM game_defined_options WHERE game_id=:game_id AND event_index=:event_index;", [
									'game_id' => $game->db_game['game_id'],
									'event_index' => $gde['event_index']
								])->fetch()['COUNT(*)']);
								
								$app->run_query("INSERT INTO game_defined_options SET game_id=:game_id, event_index=:event_index, entity_id=:entity_id, name=:name, option_index=:option_index;", [
									'game_id' => $game->db_game['game_id'],
									'event_index' => $gde['event_index'],
									'entity_id' => $entity['entity_id'],
									'name' => $name,
									'option_index' => $option_index
								]);
								
								list($final_defined_game_def_hash, $final_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
								GameDefinition::check_set_game_definition($app, $final_defined_game_def_hash, $final_defined_game_def);
								
								GameDefinition::record_migration($game, $thisuser->db_user['user_id'], "create_event_by_ui", $show_internal_params, $initial_defined_game_def, $final_defined_game_def);
								
								GameDefinition::set_cached_definition_hashes($game);
								
								$app->output_message(1, "Changed the game definition.", false);
							}
							else $app->output_message(9, "Invalid name.", false);
						}
						else $app->output_message(8, "Invalid entity type ID.", false);
					}
					else $app->output_message(7, "Invalid game defined event ID.", false);
				}
				else if ($action == "delete_gdo") {
					$gdo = $app->fetch_game_defined_option_by_id($game->db_game['game_id'], $_REQUEST['gdo_id']);
					
					if ($gdo) {
						list($initial_defined_game_def_hash, $initial_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
						GameDefinition::check_set_game_definition($app, $initial_defined_game_def_hash, $initial_defined_game_def);
						
						$app->run_query("DELETE FROM game_defined_options WHERE game_defined_option_id=:game_defined_option_id;", [
							'game_defined_option_id' => $gdo['game_defined_option_id']
						]);
						$app->run_query("UPDATE game_defined_options SET option_index=option_index-1 WHERE game_id=:game_id AND event_index=:event_index AND option_index>:option_index;", [
							'game_id' => $game->db_game['game_id'],
							'event_index' => $gdo['event_index'],
							'option_index' => $gdo['option_index']
						]);
						
						list($final_defined_game_def_hash, $final_defined_game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
						GameDefinition::check_set_game_definition($app, $final_defined_game_def_hash, $final_defined_game_def);
						
						GameDefinition::record_migration($game, $thisuser->db_user['user_id'], "delete_option_by_ui", $show_internal_params, $initial_defined_game_def, $final_defined_game_def);
						
						GameDefinition::set_cached_definition_hashes($game);
						
						$app->output_message(1, "Deleting...", false);
					}
					else $app->output_message(7, "Invalid game defined option ID.", false);
				}
				else $app->output_message(6, "Invalid action.", false);
			}
			else if (in_array($action, ['start','publish','unpublish','complete','delete','reset'])) {
				if ($action == "delete") $app->output_message(2, "This function is disabled", false);
				else if ($action == "reset") {
					if (!empty($_REQUEST['from_block'])) {
						$from_block = (int) $_REQUEST['from_block'];
						
						$game->reset_blocks_from_block($from_block);
						
						$reset_from_event_index = $game->reset_block_to_event_index($from_block);
						
						if ($reset_from_event_index !== false) {
							$game->reset_events_from_index($reset_from_event_index);
						}
					}
					else {
						$game->delete_reset_game('reset');
					}
					$app->output_message(2, "This game has been reset.", false);
				}
				else if ($action == "start") {
					$game->start_game();
					$app->output_message(2, "Successfully started the game.", false);
				}
				else {
					if ($action == "unpublish") $new_status = "editable";
					else if ($action == "publish") $new_status = "published";
					else if ($action == "complete") $new_status = "completed";
					
					$error_message = $game->set_game_status($new_status);
					if (empty($error_message)) $error_message = "Game status was successfully changed.";
					
					$app->output_message(2, $error_message, false);
				}
			}
		}
		else $app->output_message(5, "You don't have permission to perform this action.", false);
	}
}
else $app->output_message(2, "Please log in.", false);
?>