<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$nav_tab_selected = "manage";

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	$redirect_key = $redirect_url['redirect_key'];
	
	include(AppSettings::srcPath()."/includes/html_start.php");
	?>
	<div class="container-fluid">
	<?php
	include(AppSettings::srcPath()."/includes/html_login.php");
	?>
	</div>
	<?php
	include(AppSettings::srcPath().'/includes/html_stop.php');
}
else {
	$db_game = $app->fetch_game_from_url();

	if (empty($db_game)) {
		$pagetitle = "Create a new game";
		include(AppSettings::srcPath().'/includes/html_start.php');
		?>
		<div class="container-fluid">
			<div class="panel panel-info" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title">Would you like to create a new game?</div>
				</div>
				<div class="panel-body">
					<?php
					$new_game_perm = $thisuser->new_game_permission();
					
					if ($new_game_perm) {
						?>
						<form action="/ajax/manage_game.php" onsubmit="thisPageManager.create_new_game(); return false;">
							<div class="form-group">
								<label for="new_game_name">Please enter a title for this game:</label>
								<input class="form-control" id="new_game_name" />
							</div>
							<div class="form-group">
								<label for="new_game_module">Please select a module for this game:</label>
								<select id="new_game_module" class="form-control">
									<option value="">None</option>
									<?php
									$all_modules = $app->run_query("SELECT * FROM modules ORDER BY module_name ASC;");
									while ($db_module = $all_modules->fetch()) {
										echo "<option value=\"".$db_module['module_name']."\">".$db_module['module_name']."</option>\n";
									}
									?>
								</select>
							</div>
							<div class="form-group">
								<label for="new_game_blockchain_id">Which blockchain should the game run on?</label>
								<select id="new_game_blockchain_id" class="form-control">
									<option value="">-- Please Select --</option>
									<?php
									$all_blockchains = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_name ASC;");
									while ($db_blockchain = $all_blockchains->fetch()) {
										echo "<option value=\"".$db_blockchain['blockchain_id']."\">".$db_blockchain['blockchain_name']."</option>\n";
									}
									?>
								</select>
							</div>
							<div class="form-group">
								<label for="new_game_genesis_tx_type">
									Do you want to use an existing transaction as the genesis for this game, or create a new genesis transaction?
								</label>
								<select id="new_game_genesis_type" class="form-control" onchange="thisPageManager.new_game_genesis_type_changed();">
									<option value="">-- Please Select --</option>
									<option value="existing">Use an existing transaction</option>
									<option value="new">Create a new transaction</option>
								</select>
							</div>
							<div class="form-group" id="new_game_genesis_tx_hash_holder" style="display: none;">
								<label for="new_game_genesis_tx_hash">
									Please enter the genesis transaction ID:
								</label>
								<input type="text" class="form-control" id="new_game_genesis_tx_hash" />
							</div>
							<div id="new_game_existing_genesis" style="display: none;">
								<div class="form-group">
									<label for="new_game_genesis_account_id">Please select an account to pay for the new transaction:</label>
									<select id="new_game_genesis_account_id" class="form-control" onchange="thisPageManager.new_game_genesis_account_changed();"></select>
								</div>
								<div class="form-group">
									<label for="new_game_genesis_io_id">Please select coins for your new genesis transaction:</label>
									<select id="new_game_genesis_io_id" class="form-control"></select>
								</div>
								<div class="form-group">
									<label for="new_game_genesis_escrow_amount">For the UTXO you selected, how many coins should be deposited to escrow?</label>
									<input type="text" id="new_game_genesis_escrow_amount" class="form-control" />
								</div>
								<div class="form-group">
									<label for="new_game_genesis_fee">How much do you want to pay in fees for the genesis TX?</label>
									<input type="text" id="new_game_genesis_fee" class="form-control" value="0.0001" />
								</div>
							</div>
							<div class="form-group">
								<button id="new_game_save_btn" class="btn btn-primary">Save &amp; Continue</button>
							</div>
						</form>
						<?php
					}
					else {
						?>
						Sorry, you don't have permission to create a new game.
						<?php
					}
					?>
				</div>
			</div>
		</div>
		<?php
		include(AppSettings::srcPath().'/includes/html_stop.php');
	}
	else {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$next_action = "params";
			$last_action = "";
			$messages = "";
			
			if (!empty($_REQUEST['next'])) $next_action = $_REQUEST['next'];
			if (!empty($_REQUEST['last'])) $last_action = $_REQUEST['last'];
			
			if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
				if ($last_action == "description" && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
					$game_description = $_REQUEST['game_description'];
					
					$app->run_query("UPDATE games SET short_description=:short_description WHERE game_id=:game_id;", [
						'short_description' => $game_description,
						'game_id' => $game->db_game['game_id']
					]);
					
					$game->db_game['short_description'] = $game_description;
				}
				else if ($last_action == "invite_upload_csv") {
					$csv_content = file_get_contents($_FILES['csv_file']['tmp_name']);
					$csv_lines = explode("\n", $csv_content);
					$header_vars = explode(",", trim(strtolower($csv_lines[0])));
					$id_col = array_search("user_id", $header_vars);
					
					if ($id_col === false) {
						$messages .= "Required column user_id was missing.<br/>\n";
					}
					else {
						$invite_count = 0;
						
						for ($line_i=1; $line_i<count($csv_lines); $line_i++) {
							$line_vals = explode(",", trim($csv_lines[$line_i]));
							$user_id = (int) str_replace('"', '', $line_vals[$id_col]);
							
							if ($user_id > 0) {
								$send_to_user = $app->fetch_user_by_id($user_id);
								
								if ($send_to_user) {
									$send_user_game = $app->fetch_user_game($send_to_user['user_id'], $game->db_game['game_id']);
									
									if (!$send_user_game) {
										$invitation = false;
										$game->generate_invitation($user_id, $invitation, false);
										$app->send_apply_invitation($send_to_user, $invitation);
										$invite_count++;
									}
								}
							}
						}
						
						$messages .= $invite_count." invitations have been sent & applied.<br/>\n";
					}
				}
				else if ($last_action == "upload_csv") {
					$csv_content = file_get_contents($_FILES['csv_file']['tmp_name']);
					$csv_lines = explode("\n", $csv_content);
					$header_vars = explode(",", trim(strtolower($csv_lines[0])));
					
					$ties_allowed = (int) $_REQUEST['ties_allowed'];
					
					$home_col = array_search("home", $header_vars);
					$away_col = array_search("away", $header_vars);
					$name_col = array_search("event name", $header_vars);
					$start_time_col = array_search("start time utc", $header_vars);
					$time_col = array_search("datetime utc", $header_vars);
					$payout_time_col = array_search("payout utc", $header_vars);
					$fee_col = array_search("fee percentage", $header_vars);
					
					$home_odds_col = array_search("home odds", $header_vars);
					$away_odds_col = array_search("away odds", $header_vars);
					$sport_col = array_search("sport", $header_vars);
					$league_col = array_search("league", $header_vars);
					$external_id_col = array_search("external identifier", $header_vars);
					$outcome_col = array_search("outcome", $header_vars);
					
					if ($home_col === false || $away_col === false || $name_col === false || $time_col === false || $start_time_col === false) {
						$messages .= "A required column was missing in the file you uploaded. Required fields are 'Home', 'Away', 'Event Name', 'Start Time UTC' and 'Datetime UTC'<br/>\n";
					}
					else {
						$sports_entity_type = $app->check_set_entity_type("sports");
						$leagues_entity_type = $app->check_set_entity_type("leagues");
						$general_entity_type = $app->check_set_entity_type("general entity");
						
						$game_max_event_index = (int) $app->run_query("SELECT MAX(event_index) FROM game_defined_events WHERE game_id=:game_id;", ['game_id' => $game->db_game['game_id']])->fetch()['MAX(event_index)'];
						$game_event_index_offset = 0;
						$skip_count = 0;
						
						for ($line_i=1; $line_i<count($csv_lines); $line_i++) {
							if (!empty(trim($csv_lines[$line_i]))) {
								$line_vals = explode(",", trim($csv_lines[$line_i]));
								$home = $line_vals[$home_col];
								$away = $line_vals[$away_col];
								$event_name = $line_vals[$name_col];
								$event_time = str_replace("'", "", $line_vals[$time_col]);
								$event_start_time = str_replace("'", "", $line_vals[$start_time_col]);
								
								$payout_rate = 1;
								if ((string)$line_vals[$fee_col] != "") $payout_rate = (100-$line_vals[$fee_col])/100;
								
								if ($payout_time_col === false || (string)$line_vals[$payout_time_col] == "") $event_payout_time = $event_time;
								else $event_payout_time = str_replace("'", "", $line_vals[$payout_time_col]);
								
								if (!empty($home) && !empty($away) && !empty($event_name) && !empty($event_time)) {
									$event_starting_time = date("Y-m-d G:i:s", strtotime($event_start_time));
									$event_final_time = date("Y-m-d G:i:s", strtotime($event_time));
									$event_payout_time = date("Y-m-d G:i:s", strtotime($event_payout_time));
									
									$event_index = $game_max_event_index+$game_event_index_offset+1;
									
									$existing_gde = $app->run_query("SELECT * FROM game_defined_events WHERE game_id=:game_id AND event_name=:event_name AND event_final_time=:event_final_time;", [
										'game_id' => $game->db_game['game_id'],
										'event_name' => $event_name,
										'event_final_time' => $event_final_time
									])->fetch();
									
									if (!$existing_gde) {
										$home_target_prob = false;
										$away_target_prob = false;
										$tie_target_prob = false;
										
										if ($home_odds_col !== false) {
											$home_odds = $line_vals[$home_odds_col];
											$away_odds = $line_vals[$away_odds_col];
											
											if ((string)$line_vals[$home_odds_col] != "" || (string)$line_vals[$away_odds_col] != "") {
												$prob_sum = 1/$home_odds + 1/$away_odds;
												
												$home_target_prob = (1/$home_odds)/$prob_sum;
												$away_target_prob = (1/$away_odds)/$prob_sum;
												$target_prob_sum = $home_target_prob+$away_target_prob;
												$tie_target_prob = 1-$target_prob_sum;
											}
										}
										
										if ($sport_col !== false) {
											$sport_name = $line_vals[$sport_col];
											if ($sport_name != "") {
												$this_sport_entity = $app->check_set_entity($sports_entity_type['entity_type_id'], $sport_name);
											}
											else $this_sport_entity = false;
										}
										else $this_sport_entity = false;
										
										if ($external_id_col !== false) {
											$external_identifier = $line_vals[$external_id_col];
											if (empty($external_identifier)) $external_identifier = false;
										}
										else $external_identifier = false;
										
										$home_entity = $app->check_set_entity($general_entity_type['entity_type_id'], $home);
										$away_entity = $app->check_set_entity($general_entity_type['entity_type_id'], $away);
										
										if ($league_col !== false) {
											$league_name = $line_vals[$league_col];
											if ($league_name != "") {
												$this_league_entity = $app->check_set_entity($leagues_entity_type['entity_type_id'], $league_name);
											}
											else $this_league_entity = false;
										}
										else $this_league_entity = false;
										
										$outcome_index = null;
										if ($outcome_col !== false) {
											if ($line_vals[$outcome_col] != "") $outcome_index = (int)$line_vals[$outcome_col];
										}
										
										$gde_ins_params = [
											'game_id' => $game->db_game['game_id'],
											'sport_entity_id' => $this_sport_entity ? $this_sport_entity['entity_id'] : null,
											'league_entity_id' => $this_league_entity ? $this_league_entity['entity_id'] : null,
											'external_identifier' => $external_identifier ? $external_identifier : null,
											'payout_rate' => $payout_rate,
											'outcome_index' => $outcome_index,
											'event_index' => $event_index,
											'event_name' => $event_name,
											'event_starting_time' => $event_starting_time,
											'event_final_time' => $event_final_time,
											'event_payout_time' => $event_payout_time,
										];
										$gde_ins_q = "INSERT INTO game_defined_events SET game_id=:game_id, sport_entity_id=:sport_entity_id, league_entity_id=:league_entity_id, external_identifier=:external_identifier, payout_rate=:payout_rate, outcome_index=:outcome_index, event_index=:event_index, event_name=:event_name, event_starting_time=:event_starting_time, event_final_time=:event_final_time, event_payout_time=:event_payout_time, option_name='team', option_name_plural='teams';";
										$app->run_query($gde_ins_q, $gde_ins_params);
										
										$gdo_home_params = [
											'game_id' => $game->db_game['game_id'],
											'event_index' => $event_index,
											'option_index' => 0,
											'name' => $home,
											'entity_id' => $home_entity['entity_id'],
											'target_probability' => $home_target_prob ? $home_target_prob : null
										];
										$gdo_away_params = [
											'game_id' => $game->db_game['game_id'],
											'event_index' => $event_index,
											'option_index' => 1,
											'name' => $away,
											'entity_id' => $away_entity['entity_id'],
											'target_probability' => $away_target_prob ? $away_target_prob : null
										];
										$gdo_ins_q = "INSERT INTO game_defined_options SET game_id=:game_id, event_index=:event_index, option_index=:option_index, name=:name, entity_id=:entity_id, target_probability=:target_probability;";
										
										$app->run_query($gdo_ins_q, $gdo_home_params);
										$app->run_query($gdo_ins_q, $gdo_away_params);
										
										if ($ties_allowed) {
											$gdo_tie_params = [
												'game_id' => $game->db_game['game_id'],
												'event_index' => $event_index,
												'option_index' => 2,
												'name' => "Tie",
												'entity_id' => null,
												'target_probability' => $tie_target_prob ? $tie_target_prob : null
											];
											$app->run_query($gdo_ins_q, $gdo_tie_params);
										}
										
										$game_event_index_offset++;
									}
									else $skip_count++;
								}
							}
						}
						
						$messages .= "Added ".$game_event_index_offset." events.<br/>\n";
						if ($skip_count > 0) $messages .= "Skipped ".$skip_count." events that already exist.<br/>\n";
					}
				}
				else if ($last_action == "internal_settings") {
					$featured = (int)$_REQUEST['featured'];
					$public_players = (int)$_REQUEST['public_players'];
					$faucet_policy = $_REQUEST['faucet_policy'];
					$hide_module = (int)$_REQUEST['hide_module'];
					$every_event_bet_reminder_minutes = (int) $_REQUEST['every_event_bet_reminder_minutes'];
					$sec_per_faucet_claim = (int) $_REQUEST['sec_per_faucet_claim'];
					$min_sec_between_claims = (int) $_REQUEST['min_sec_between_claims'];
					$bonus_claims = (int) $_REQUEST['bonus_claims'];
					$default_transaction_fee = (float) $_REQUEST['default_transaction_fee'];
					
					$definitive_game_peer_on = $_REQUEST['definitive_game_peer_on'];
					if ($definitive_game_peer_on == 1) {
						$definitive_game_peer_url = $_REQUEST['definitive_game_peer'];
					}
					else $definitive_game_peer_url = "";
					
					if ($faucet_policy != "on") $faucet_policy = "off";
					
					$definitive_game_peer_id = "NULL";
					$game->db_game['definitive_game_peer_id'] = "";
					
					$definitive_game_peer = false;
					
					if (!empty($definitive_game_peer_url)) {
						$definitive_game_peer = $game->get_game_peer_by_server_name($definitive_game_peer_url);
						
						if ($definitive_game_peer) {
							$game->db_game['definitive_game_peer_id'] = $definitive_game_peer['game_peer_id'];
						}
					}
					
					$app->run_query("UPDATE games SET featured=:featured, public_players=:public_players, faucet_policy=:faucet_policy, hide_module=:hide_module, definitive_game_peer_id=:definitive_game_peer_id, every_event_bet_reminder_minutes=:every_event_bet_reminder_minutes, sec_per_faucet_claim=:sec_per_faucet_claim, min_sec_between_claims=:min_sec_between_claims, bonus_claims=:bonus_claims, default_transaction_fee=:default_transaction_fee WHERE game_id=:game_id;", [
						'featured' => $featured,
						'public_players' => $public_players,
						'faucet_policy' => $faucet_policy,
						'hide_module' => $hide_module,
						'definitive_game_peer_id' => $definitive_game_peer ? $definitive_game_peer['game_peer_id'] : null,
						'game_id' => $game->db_game['game_id'],
						'every_event_bet_reminder_minutes' => $every_event_bet_reminder_minutes,
						'sec_per_faucet_claim' => $sec_per_faucet_claim,
						'min_sec_between_claims' => $min_sec_between_claims,
						'bonus_claims' => $bonus_claims,
						'default_transaction_fee' => $default_transaction_fee
					]);
					
					$game->db_game['featured'] = $featured;
					$game->db_game['public_players'] = $public_players;
					$game->db_game['faucet_policy'] = $faucet_policy;
					$game->db_game['hide_module'] = $hide_module;
					$game->db_game['every_event_bet_reminder_minutes'] = $every_event_bet_reminder_minutes;
					$game->db_game['min_sec_between_claims'] = $min_sec_between_claims;
					$game->db_game['sec_per_faucet_claim'] = $sec_per_faucet_claim;
					$game->db_game['bonus_claims'] = $bonus_claims;
					$game->db_game['default_transaction_fee'] = $default_transaction_fee;
					
					$messages .= "Game internal settings have been updated.<br/>\n";
				}
				else if ($last_action == "claim_account") {
					$account_id = (int) $_REQUEST['account_id'];
					
					$claim_account = $app->fetch_account_by_id($account_id);
					
					if (empty($claim_account['user_id']) && $claim_account['game_id'] == $game->db_game['game_id'] && ($claim_account['is_game_sale_account'] || $claim_account['is_blockchain_sale_account'])) {
						$app->run_query("UPDATE currency_accounts SET user_id=:user_id WHERE account_id=:account_id;", [
							'user_id' => $thisuser->db_user['user_id'],
							'account_id' => $claim_account['account_id']
						]);
					}
				}
			}
			
			$manage_game_action = "";
			if ($next_action == "params") $manage_game_action = "fetch";
			
			$nav_tab_selected = "manage_game";
			$pagetitle = "Manage game: ".$game->db_game['name'];
			include(AppSettings::srcPath().'/includes/html_start.php');
			
			if (!empty($messages)) echo $messages;
			
			$actions = array("params", "internal_settings", "events", "description", "currency_conversions", "game_definition");
			$action_labels = array("Public Parameters", "Internal Settings", "Manage Events", "Description", "Currency Conversions", "Game Definition");
			?>
			<script type="text/javascript">
			var editor;
			
			window.onload = function() {
				games.push(new Game(thisPageManager, <?php
					echo $game->db_game['game_id'];
					echo ', false';
					echo ', false';
					echo ', false';
					echo ', "'.$game->db_game['payout_weight'].'"';
					echo ', '.$game->db_game['round_length'];
					echo ', 0';
					echo ', "'.$game->db_game['url_identifier'].'"';
					echo ', "'.$game->db_game['coin_name'].'"';
					echo ', "'.$game->db_game['coin_name_plural'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
					echo ', "explorer", false';
					echo ', "'.$game->logo_image_url().'"';
					echo ', "'.$game->vote_effectiveness_function().'"';
					echo ', "'.$game->effectiveness_param1().'"';
					echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
					echo ', "'.$game->db_game['inflation'].'"';
					echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
					echo ', false';
					echo ', "'.$game->db_game['decimal_places'].'"';
					echo ', "'.$game->blockchain->db_blockchain['decimal_places'].'"';
					echo ', "'.$game->db_game['view_mode'].'"';
					echo ', 0';
					echo ', false';
					echo ', "'.$game->db_game['default_betting_mode'].'"';
					echo ', false';
				?>));
				
				<?php
				if ($manage_game_action) echo "thisPageManager.manage_game(".$game->db_game['game_id'].", '".$manage_game_action."');\n";
				
				if ($next_action == "description") {
					AppSettings::addJsDependency("tiny.editor.js");
					?>
					editor = new TINY.editor.edit('editor', {
						id: 'game_description',
						width: "100%",
						height: 330,
						cssclass: 'tinyeditor',
						controlclass: 'tinyeditor-control',
						rowclass: 'tinyeditor-header',
						dividerclass: 'tinyeditor-divider',
						controls: ['bold', 'italic', 'underline', 'strikethrough', '|', 'subscript', 'superscript', '|',
							'orderedlist', 'unorderedlist', '|', 'outdent', 'indent', '|', 'leftalign',
							'centeralign', 'rightalign', 'blockjustify', '|', 'unformat', '|', 'undo', 'redo', 'n',
							'font', 'size', 'style', '|', 'image', 'hr', 'link', 'unlink', '|', 'print'],
						footer: true,
						fonts: ['Verdana','Arial','Georgia','Trebuchet MS'],
						xhtml: true,
						bodyid: 'editor',
						footerclass: 'tinyeditor-footer',
						toggle: {text: 'source', activetext: 'wysiwyg', cssclass: 'toggle'},
						resize: {cssclass: 'resize'}
					});
					<?php
				}
				?>
			};
			</script>
			
			<div class="container-fluid">
				<div class="row game_tabs">
					<?php
					for ($i=0; $i<count($actions); $i++) {
						echo '<div class="col-sm-2 game_tab';
						if ($next_action == $actions[$i]) echo ' game_tab_sel';
						echo '"><a id="manage_game_tab_'.$actions[$i].'" ';
						echo 'href="/manage/'.$game->db_game['url_identifier'].'/?next='.$actions[$i];
						echo '">'.($i+1).'. '.$action_labels[$i].'</a></div>'."\n";
					}
					?>
				</div>
				<?php
				if ($next_action == "params") {
					?>
					<div class="row">
						<div class="col-lg-6">
							<div class="panel panel-info">
								<div class="panel-heading">
									<div class="panel-title">Manage game parameters: <?php echo $game->db_game['name']." (#".$game->db_game['game_id'].")"; ?></div>
								</div>
								<div class="panel-body">
									<form onsubmit="thisPageManager.save_game(); return false;">
										<div class="form-group">
											<label for="game_form_name">Game title:</label>
											<input class="form-control" type="text" id="game_form_name" />
										</div>
										<div class="form-group">
											<label for="game_form_blockchain_id">Runs on Blockchain:</label>
											<select id="game_form_blockchain_id" class="form-control">
												<option value="">-- Please Select --</option>
												<?php
												$all_blockchains = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_name ASC;");
												while ($db_blockchain = $all_blockchains->fetch()) {
													echo "<option value=\"".$db_blockchain['blockchain_id']."\">".$db_blockchain['blockchain_name']."</option>\n";
												}
												?>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_coin_name">Module:</label>
											<input class="form-control" type="text" id="game_form_module" />
										</div>
										<div class="form-group">
											<label for="game_form_coin_name">Each coin is called a(n):</label>
											<input class="form-control" type="text" id="game_form_coin_name" />
										</div>
										<div class="form-group">
											<label for="game_form_coin_name_plural">Coins (plural) are called:</label>
											<input class="form-control" type="text" id="game_form_coin_name_plural" />
										</div>
										<div class="form-group">
											<label for="game_form_coin_abbreviation">Currency abbreviation:</label>
											<input class="form-control" type="text" id="game_form_coin_abbreviation" />
										</div>
										<div class="form-group">
											<label for="game_form_has_final_round">Game ends?</label>
											<select class="form-control" id="game_form_has_final_round" onchange="thisPageManager.game_form_final_round_changed();">
												<option value="0">No</option>
												<option value="1">Yes</option>
											</select>
										</div>
										<div id="game_form_final_round_disp">
											<div class="form-group">
												<label for="game_form_final_round">Number of rounds in the game:</label>
												<input type="text" class="form-control" id="game_form_final_round" placeholder="0" />
											</div>
										</div>
										<div class="form-group">
											<label for="game_form_game_starting_block">Game starts on block:</label>
											<input class="form-control" type="text" style="text-align: right;" id="game_form_game_starting_block" />
										</div>
										<div class="form-group">
											<label for="game_form_round_length">Blocks per round:</label>
											<input class="form-control" style="text-align: right;" type="text" id="game_form_round_length" />
										</div>
										<div class="form-group">
											<label for="game_form_escrow_address">Escrow address:</label>
											<input class="form-control" type="text" id="game_form_escrow_address" />
										</div>
										<div class="form-group">
											<label for="game_form_genesis_tx_hash">Genesis transaction:</label>
											<input class="form-control" type="text" id="game_form_genesis_tx_hash" />
										</div>
										<div class="form-group">
											<label for="game_form_genesis_amount">Coins created by genesis tx:</label>
											<input class="form-control" type="text" id="game_form_genesis_amount" style="text-align: right;" />
										</div>
										<div class="form-group">
											<label for="game_form_payout_weight">Definition of a vote:</label>
											<select class="form-control" id="game_form_payout_weight">
												<option value="coin">Coins staked</option>
												<option value="coin_block">Coins over time. 1 vote per block</option>
												<option value="coin_round">Coins over time. 1 vote per round</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_default_vote_effectiveness_function">Vote effectiveness:</label>
											<select class="form-control" id="game_form_default_vote_effectiveness_function">
												<option value="constant">Votes count equally through the round</option>
												<option value="linear_decrease">Linearly decreasing vote effectiveness</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_maturity">Transaction lock time (blocks):</label>
											<input class="form-control" style="text-align: right;" type="text" id="game_form_maturity" />
										</div>
										<div class="form-group">
											<label for="game_form_buyin_policy">Buy-in policy:</label>
											<select class="form-control" id="game_form_buyin_policy" onchange="thisPageManager.game_form_buyin_policy_changed();">
												<option value="none">No additional buy-ins</option>
												<option value="unlimited">Unlimited buy-ins</option>
												<option value="game_cap">Buy-in cap for the whole game</option>
												<option value="for_sale">Allow each node to sell their own coins</option>
											</select>
										</div>
										<div id="game_form_game_buyin_cap_disp">
											<label for="game_form_game_buyin_cap">Game-wide buy-in cap:</label>
											<input class="form-control" style="text-align: right;" type="text" id="game_form_game_buyin_cap" />
										</div>
										<div class="form-group">
											<label for="game_form_inflation">Inflation:</label>
											<select id="game_form_inflation" class="form-control" onchange="thisPageManager.game_form_inflation_changed();">
												<option value="linear">Linear</option>
												<option value="fixed_exponential">Fixed Exponential</option>
												<option value="exponential">Exponential</option>
											</select>
										</div>
										<div id="game_form_inflation_exponential">
											<div class="form-group">
												<label for="game_form_exponential_inflation_rate">Inflation per round:</label>
												<input class="form-control" style="text-align: right;" type="text" id="game_form_exponential_inflation_rate" placeholder="100" />
											</div>
										</div>
										<div id="game_form_inflation_linear">
											<div class="form-group">
												<label for="game_form_pos_reward">Voting payout reward:</label>
												<input class="form-control" style="text-align: right;" type="text" id="game_form_pos_reward" />
											</div>
										</div>
										<div class="form-group">
											<label for="game_form_event_rule">Event rule:</label>
											<select id="game_form_event_rule" class="form-control" onchange="thisPageManager.game_form_event_rule_changed();">
												<option value="game_definition">Game definition</option>
												<option value="single_event_series">Single, repeating event</option>
												<option value="entity_type_option_group">One event for each item in a group</option>
												<option value="all_pairs">Head to head between all options</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_finite_events">Finite events?</label>
											<select id="game_form_finite_events" class="form-control">
												<option value="1">Yes</option>
												<option value="0">No</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_option_group_id">Voting options:</label>
											&nbsp;&nbsp; <a href="/groups/">Manage Groups</a>
											<select id="game_form_option_group_id" class="form-control">
												<?php
												$all_option_groups = $app->run_query("SELECT * FROM option_groups ORDER BY description ASC;");
												while ($option_group = $all_option_groups->fetch()) {
													echo '<option value="'.$option_group['group_id'].'">'.$option_group['description']."</option>\n";
												}
												?>
											</select>
										</div>
										<div id="game_form_event_rule_entity_type_option_group">
											<div class="form-group">
												<label for="game_form_events_per_round">Events per round:</label>
												<input class="form-control" type="text" id="game_form_events_per_round" style="text-align: right" />
											</div>
											<div class="form-group">
												<label for="game_form_event_entity_type_id">One event for each of these:</label>
												<select id="game_form_event_entity_type_id" class="form-control">
													<?php
													$all_entity_types = $app->run_query("SELECT * FROM entity_types ORDER BY entity_name ASC;");
													while ($entity_type = $all_entity_types->fetch()) {
														echo '<option value="'.$entity_type['entity_type_id'].'">'.$entity_type['entity_name']."</option>\n";
													}
													?>
												</select>
											</div>
										</div>
										<div class="form-group">
											<label for="game_form_default_betting_mode">Default betting mode:</label>
											<select class="form-control" id="game_form_default_betting_mode">
												<option value="">-- Please Select --</option>
												<option value="inflationary">Inflationary Betting</option>
												<option value="principal">Principal Betting</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_event_type_name">Each event is called:</label>
											<input class="form-control" type="text" id="game_form_event_type_name" />
										</div>
										<div class="form-group">
											<label for="game_form_default_max_voting_fraction">Voting cap:</label>
											<input class="form-control" type="text" style="text-align: right;" id="game_form_default_max_voting_fraction" placeholder="100" />
										</div>
										
										<div class="form-group">
											<button id="save_game_btn" type="button" class="btn btn-success" onclick="thisPageManager.save_game('save');">Save Settings</button>
											<button id="publish_game_btn" type="button" class="btn btn-primary" onclick="thisPageManager.save_game('publish');">Save &amp; Publish</button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "internal_settings") {
					$publish_action = "unpublish";
					if ($game->db_game['game_status'] == "editable") $publish_action = "publish";
					?>
					<div class="row">
						<div class="col-lg-6">
							<div class="panel panel-info">
								<div class="panel-heading">
									<div class="panel-title">Internal settings for <?php echo $game->db_game['name']." (#".$game->db_game['game_id'].")"; ?></div>
								</div>
								<div class="panel-body">
									<div class="form-group">
										<b>Game status:</b> &nbsp; <?php echo ucwords($game->db_game['game_status']); ?>
									</div>
									<div class="form-group">
										<button id="start_game_btn" class="btn btn-success" onclick="thisPageManager.manage_game(<?php echo $game->db_game['game_id']; ?>, 'start'); return false;">Start Game</button>
										<button id="unpublish_game_btn" class="btn btn-info" onclick="thisPageManager.manage_game(<?php echo $game->db_game['game_id']; ?>, '<?php echo $publish_action; ?>'); return false;"><?php echo ucwords($publish_action); ?></button>
										<button id="complete_game_btn" class="btn btn-primary" onclick="thisPageManager.manage_game(<?php echo $game->db_game['game_id']; ?>, 'complete'); return false;">Mark Completed</button>
										<button id="delete_game_btn" class="btn btn-danger" style="display: none;" onclick="thisPageManager.manage_game(<?php echo $game->db_game['game_id']; ?>, 'delete'); return false;">Delete</button>
										<button id="reset_game_btn" class="btn btn-warning" onclick="thisPageManager.manage_game(<?php echo $game->db_game['game_id']; ?>, 'reset'); return false;">Reset</button>
									</div>
									<?php
									$sale_accounts = $app->run_query("SELECT * FROM currency_accounts ca JOIN currencies c ON ca.currency_id=c.currency_id LEFT JOIN users u ON ca.user_id=u.user_id WHERE ca.game_id=:game_id AND (ca.is_game_sale_account=1 OR ca.is_blockchain_sale_account=1);", ['game_id' => $game->db_game['game_id']]);
									
									echo '<p>This game has '.$sale_accounts->rowCount()." sale accounts.</p>\n";
									
									echo "<table style=\"width: 100%;\">\n";
									
									while ($sale_account = $sale_accounts->fetch()) {
										echo "<tr>\n";
										
										echo "<td>";
										if ($sale_account['user_id'] == $thisuser->db_user['user_id']) echo '<a href="/accounts/?account_id='.$sale_account['account_id'].'">';
										echo $sale_account['account_name'];
										if ($sale_account['user_id'] == $thisuser->db_user['user_id']) echo '</a>';
										echo "</td>\n";
										
										echo "<td>";
										if (empty($sale_account['username'])) {
											echo "Not owned";
											echo " &nbsp; <a href=\"/manage/".$game->db_game['url_identifier']."/?next=internal_settings&last=claim_account&account_id=".$sale_account['account_id']."&synchronizer_token=".$thisuser->get_synchronizer_token()."\">Claim</a>";
										}
										else echo $sale_account['username'];
										echo "</td>\n";
										
										echo "</tr>\n";
									}
									
									echo "</table>\n";
									
									echo "<br/>\n";
									?>
									<form method="get" action="/manage/<?php echo $game->db_game['url_identifier']; ?>/">
										<input type="hidden" name="next" value="internal_settings" />
										<input type="hidden" name="last" value="internal_settings" />
										<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
										
										<div class="form-group">
											<label for="featured">Featured?</label>
											<select class="form-control" name="featured" id="featured">
												<option value="1">Yes</option>
												<option value="0"<?php if ($game->db_game['featured'] == 0) echo ' selected="selected"'; ?>>No</option>
											</select>
										</div>
										<div class="form-group">
											<label for="faucet_policy">Faucet?</label>
											<select class="form-control" name="faucet_policy" id="faucet_policy">
												<option value="on">On</option>
												<option value="off"<?php if ($game->db_game['faucet_policy'] == "off") echo ' selected="selected"'; ?>>Off</option>
											</select>
										</div>
										<div class="form-group">
											<label for="sec_per_faucet_claim">How often should users be granted coins from the faucet? Please answer in seconds.</label>
											<input type="text" class="form-control" name="sec_per_faucet_claim" id="sec_per_faucet_claim" value="<?php echo $game->db_game['sec_per_faucet_claim']; ?>" />
										</div>
										<div class="form-group">
											<label for="min_sec_between_claims">If users go for a long time without claiming from the faucet, they may be eligible to claim many times.  How many seconds must these users wait between claims?</label>
											<input type="text" class="form-control" name="min_sec_between_claims" id="min_sec_between_claims" value="<?php echo $game->db_game['min_sec_between_claims']; ?>" />
										</div>
										<div class="form-group">
											<label for="bonus_claims">How many extra faucet claims should users get upon signing up?</label>
											<input type="text" class="form-control" name="bonus_claims" id="bonus_claims" value="<?php echo $game->db_game['bonus_claims']; ?>" />
										</div>
										<div class="form-group">
											<label for="hide_module">Should the game's module be hidden when displaying game definition to the public?</label>
											<select class="form-control" name="hide_module" id="hide_module">
												<option value="0">No</option>
												<option value="1"<?php if ($game->db_game['hide_module']) echo ' selected="selected"'; ?>>Yes</option>
											</select>
										</div>
										<div class="form-group">
											<?php
											$definitive_game_peer = $game->get_definitive_peer();
											?>
											<label for="definitive_game_peer">Does this game have a definitive peer?</label>
											<select class="form-control" name="definitive_game_peer_on" id="definitive_game_peer_on" onchange="thisPageManager.toggle_definitive_game_peer();">
												<option value="0">No</option>
												<option value="1"<?php if ($definitive_game_peer) echo ' selected="selected"'; ?>>Yes</option>
											</select>
											<input type="text" class="form-control" name="definitive_game_peer" id="definitive_game_peer" placeholder="http://" value="<?php if ($definitive_game_peer) echo $definitive_game_peer['base_url']; ?>"<?php if (!$definitive_game_peer) echo ' style="display: none;"'; ?> />
										</div>
										<div class="form-group">
											<label for="public_players">Can players see balances and contact each other?</label>
											<select class="form-control" name="public_players" id="public_players">
												<option value="0">No</option>
												<option value="1"<?php if ($game->db_game['public_players'] == 1) echo ' selected="selected"'; ?>>Yes</option>
											</select>
										</div>
										<div class="form-group">
											<label for="every_event_bet_reminder">Do you want to remind players to bet near the end of every event?</label>
											<select class="form-control" name="every_event_bet_reminder" id="every_event_bet_reminder" onchange="thisPageManager.toggle_bet_reminder_minutes();">
												<option value="0">No</option>
												<option value="1"<?php if ($game->db_game['every_event_bet_reminder_minutes'] > 0) echo ' selected="selected"'; ?>>Yes</option>
											</select>
										</div>
										<div class="form-group" id="bet_reminder_minutes"<?php if (empty($game->db_game['every_event_bet_reminder_minutes'])) echo ' style="display:none;"'; ?>>
											<label for="every_event_bet_reminder_minutes">How many minutes before each event ends should the reminder be sent?</label>
											<input class="form-control" name="every_event_bet_reminder_minutes" id="every_event_bet_reminder_minutes" value="<?php echo $game->db_game['every_event_bet_reminder_minutes']; ?>" />
										</div>
										<div class="form-group">
											<label for="default_transaction_fee">When users join a game what should their default transaction fee be? (<?php echo $game->blockchain->db_blockchain['coin_name_plural']; ?>)</label>
											<input type="text" class="form-control" name="default_transaction_fee" id="default_transaction_fee" value="<?php echo $game->db_game['default_transaction_fee']; ?>" />
										</div>
										
										<input type="submit" class="btn btn-success" value="Save Settings" />
									</form>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "events") {
					if (isset($_REQUEST['event_filter'])) $event_filter = $_REQUEST['event_filter'];
					else $event_filter = "";
					?>
					<div class="panel panel-info">
						<div class="panel-heading">
							<div class="panel-title"><?php echo $game->db_game['name']; ?>: Manage Events</div>
						</div>
						<div class="panel-body">
							<?php
							if ($game->get_definitive_peer()) { ?>
								<p><font class="redtext">This game is set to update automatically from a definitive peer. Any changes you make here will be overwritten.</font></p>
								<?php
							}
							?>
							<p>
								<button class="btn btn-sm btn-success" onclick="thisPageManager.manage_game_load_event('new');">New Event</button>
								<button class="btn btn-sm btn-primary" onclick="thisPageManager.manage_game_set_event_blocks(false);">Set Event Blocks</button>
								<button class="btn btn-sm btn-info" onclick="$('#import_csv_modal').modal('show');">Import Events from CSV</button>
							</p>
							<p>
								<select class="form-control" id="manage_game_event_filter" onchange="thisPageManager.manage_game_event_filter_changed();">
									<option value="">View all events</option>
									<option <?php if ($event_filter == "past_due") echo "selected=\"selected\" "; ?>value="past_due">Past due unresolved events</option>
								</select>
							</p>
							<?php
							$manage_gdes_params = [
								'game_id' => $game->db_game['game_id']
							];
							$manage_gdes_q = "SELECT * FROM game_defined_events WHERE game_id=:game_id";
							if ($event_filter == "past_due") {
								$manage_gdes_q .= " AND outcome_index IS NULL AND event_payout_block <= :ref_block";
								$manage_gdes_params['ref_block'] = $game->blockchain->last_block_id();
							}
							$manage_gdes_q .= " ORDER BY event_index DESC;";
							$manage_gdes = $app->run_query($manage_gdes_q, $manage_gdes_params);
							while ($gde = $manage_gdes->fetch()) {
								?>
								<div class="row">
									<div class="col-lg-4" style="text-align: center;">
										<a href="/explorer/games/<?php echo $game->db_game['url_identifier']; ?>/events/<?php echo $gde['event_index']; ?>">View</a> &nbsp;&nbsp; 
										<a href="" onclick="thisPageManager.manage_game_load_event(<?php echo $gde['game_defined_event_id']; ?>); return false;">Edit</a> &nbsp;&nbsp; 
										<a href="" onclick="thisPageManager.manage_game_event_options(<?php echo $gde['game_defined_event_id']; ?>); return false;">Edit Options</a> &nbsp;&nbsp;
										<a href="" onclick="thisPageManager.manage_game_set_event_blocks(<?php echo $gde['game_defined_event_id']; ?>); return false;">Set Event Blocks</a>
									</div>
									<div class="col-lg-6"><?php
										echo $gde['event_index'].'. '.$gde['event_name'];
										?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					
					<div style="display: none;" class="modal fade" id="event_modal">
						<div class="modal-dialog">
							<div class="modal-content" id="event_modal_content">
							<div class="modal-body">
								<div class="form-group">
									<label for="event_form_event_index">Event index:</label>
									<input class="form-control" id="event_form_event_index" />
								</div>
								<div class="form-group">
									<label for="event_form_next_event_index">Next event index:</label>
									<input class="form-control" id="event_form_next_event_index" />
								</div>
								<div class="form-group">
									<label for="event_form_payout_rule">Payout rule:</label>
									<select class="form-control" id="event_form_payout_rule">
										<option value="binary">Binary Options</option>
										<option value="linear">Track an Asset</option>
									</select>
								</div>
								<div class="form-group">
									<label for="event_form_payout_rate">Payout rate (betting fees):</label>
									<input class="form-control" id="event_form_payout_rate" />
								</div>
								<div id="event_form_event_blocks">
									<p><a href="" onclick="$('#event_form_event_times').show(); $('#event_form_event_blocks').hide(); return false;">Specify times</a></p>
									<div class="form-group">
										<label for="event_form_event_starting_block">Event starting block:</label>
										<input class="form-control" id="event_form_event_starting_block" />
									</div>
									<div class="form-group">
										<label for="event_form_event_final_block">Event final block:</label>
										<input class="form-control" id="event_form_event_final_block" />
									</div>
									<div class="form-group">
										<label for="event_form_event_payout_block">Event payout block:</label>
										<input class="form-control" id="event_form_event_payout_block" />
									</div>
								</div>
								<div id="event_form_event_times">
									<p><a href="" onclick="$('#event_form_event_blocks').show(); $('#event_form_event_times').hide(); return false;">Specify block numbers</a></p>
									<div class="form-group">
										<label for="event_form_event_starting_block">Event starting time:</label>
										<input class="form-control" id="event_form_event_starting_time" />
									</div>
									<div class="form-group">
										<label for="event_form_event_final_block">Event final time:</label>
										<input class="form-control" id="event_form_event_final_time" />
									</div>
									<div class="form-group">
										<label for="event_form_event_payout_block">Event payout time:</label>
										<input class="form-control" id="event_form_event_payout_time" />
									</div>
								</div>
								<div class="form-group">
									<label for="event_form_event_name">Event name:</label>
									<input class="form-control" id="event_form_event_name" />
								</div>
								<div class="form-group">
									<label for="event_form_track_max_price">Track min price:</label>
									<input class="form-control" id="event_form_track_min_price" />
								</div>
								<div class="form-group">
									<label for="event_form_track_max_price">Track max price:</label>
									<input class="form-control" id="event_form_track_max_price" />
								</div>
								<div class="form-group">
									<label for="event_form_track_max_price">Track payout price:</label>
									<input class="form-control" id="event_form_track_payout_price" />
								</div>
								<div class="form-group">
									<label for="event_form_track_name_short">Abbreviation for tracked asset:</label>
									<input class="form-control" id="event_form_track_name_short" />
								</div>
								<div class="form-group">
									<label for="event_form_option_block_rule">Option block rule:</label>
									<input class="form-control" id="event_form_option_block_rule" />
								</div>
								<div class="form-group">
									<label for="event_form_option_name">Option name:</label>
									<input class="form-control" id="event_form_option_name" />
								</div>
								<div class="form-group">
									<label for="event_form_option_name_plural">Option name plural:</label>
									<input class="form-control" id="event_form_option_name_plural" />
								</div>
								<div class="form-group">
									<label for="event_form_outcome_index">Outcome index:</label>
									<input class="form-control" id="event_form_outcome_index" />
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-primary" id="event_form_save_btn">Save changes</button>
								<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
							</div>
							</div>
						</div>
					</div>
					
					<div style="display: none;" class="modal fade" id="options_modal">
						<div class="modal-dialog">
							<div class="modal-content" id="options_modal_content">
							</div>
						</div>
					</div>
					
					<div style="display: none;" class="modal fade" id="import_csv_modal">
						<div class="modal-dialog">
							<div class="modal-content">
								<form action="/manage/<?php echo $game->db_game['url_identifier']; ?>/" method="post" enctype="multipart/form-data">
									<input type="hidden" name="last" value="upload_csv" />
									<input type="hidden" name="next" value="events" />
									<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
									
									<div class="modal-body">
										<div class="form-group">
											<label for="ties_allowed">Can the teams tie?</label>
											<select class="form-control" name="ties_allowed">
												<option value="1">Yes, ties are allowed</option>
												<option value="0">No ties</option>
											</select>
										</div>
										<div class="form-group">
											<label for="csv_file">Please select a file to upload</label>
											<input type="file" name="csv_file" class="btn btn-warning" />
										</div>
									</div>
									<div class="modal-footer">
										<input type="submit" value="Upload CSV" class="btn btn-primary" />
										<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
									</div>
								</form>
							</div>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "description") {
					?>
					<div class="panel panel-info">
						<div class="panel-heading">
							<div class="panel-title"><?php echo $game->db_game['name']; ?>: Description</div>
						</div>
						<div class="panel-body">
							<form action="/manage/<?php echo $game->db_game['url_identifier']; ?>/" method="get" onsubmit="editor.post();">
								<input type="hidden" name="last" value="description" />
								<input type="hidden" name="next" value="description" />
								<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
								<textarea name="game_description" id="game_description" cols="90" rows="14"><?php echo $game->db_game['short_description']; ?></textarea>
								<input class="btn btn-primary" type="submit" value="Save Description" />
							</form>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "currency_conversions") {
					$currency_conversions = $app->run_query("SELECT i.invoice_id, i.invoice_type, i.confirmed_amount_paid, c.blockchain_id, b.decimal_places, b.url_identifier, b.coin_name_plural, c.abbreviation, u.username FROM currency_invoices i JOIN user_games ug ON i.user_game_id=ug.user_game_id JOIN users u ON ug.user_id=u.user_id JOIN currencies c ON i.pay_currency_id=c.currency_id JOIN blockchains b ON c.blockchain_id=b.blockchain_id WHERE i.invoice_type IN ('sale_buyin', 'sellout') AND i.status != 'unpaid' AND ug.game_id=:game_id;", ['game_id' => $game->db_game['game_id']])->fetchAll();
					?>
					<div class="row">
						<div class="col-lg-12">
							<div class="panel panel-info">
								<div class="panel-heading">
									<div class="panel-title">Currency conversions for <?php echo $game->db_game['name']." (#".$game->db_game['game_id'].")"; ?></div>
								</div>
								<div class="panel-body">
									<?php
									foreach ($currency_conversions as $currency_conversion) {
										$invoice_ios = $app->invoice_ios_by_invoice($currency_conversion['invoice_id']);
										$received_utxo_html = "";
										$conversion_game_amount_float = 0;
										$conversion_blockchain_amount_float = 0;
										
										foreach ($invoice_ios as $invoice_io) {
											$invoice_io_blockchain_id = $currency_conversion['invoice_type'] == "sale_buyin" ? $game->blockchain->db_blockchain['blockchain_id'] : $currency_conversion['blockchain_id'];
											
											$io = $app->fetch_io_by_hash_out_index($invoice_io_blockchain_id, $invoice_io['tx_hash'], $invoice_io['out_index']);
											
											if ($currency_conversion['invoice_type'] == "sale_buyin") {
												$game_amount = $game->game_amount_by_io($io['io_id']);
												
												$received_utxo_html .= '<a href="/explorer/games/'.$game->db_game['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['game_out_index'].'/">'.$app->format_bignum($game_amount/pow(10, $game->db_game['decimal_places']))." ".$game->db_game['coin_name_plural']."</a> ";
												
												$conversion_game_amount_float += $game_amount/pow(10, $game->db_game['decimal_places']);
											}
											else {
												$received_utxo_html .= '<a href="/explorer/blockchains/'.$currency_conversion['url_identifier']."/utxo/".$invoice_io['tx_hash']."/".$invoice_io['out_index'].'/">'.$app->format_bignum($io['amount']/pow(10, $currency_conversion['decimal_places']))." ".$currency_conversion['coin_name_plural']."</a><br/>\n";
												
												$conversion_blockchain_amount_float += $io['amount']/pow(10, $currency_conversion['decimal_places']);
											}
										}
										
										if ($currency_conversion['invoice_type'] == "sale_buyin") $exchange_rate = $conversion_game_amount_float/$currency_conversion['confirmed_amount_paid'];
										else $exchange_rate = $currency_conversion['confirmed_amount_paid']/$conversion_blockchain_amount_float;
										
										echo '<div class="row">';
										echo '<div class="col-sm-2">'.$currency_conversion['username']."</div>";
										echo '<div class="col-sm-1">'.($currency_conversion['invoice_type'] == "sale_buyin" ? "Buyin" : "Sellout")."</div>";
										echo '<div class="col-sm-3">'.(float) $currency_conversion['confirmed_amount_paid'].' ';
										if ($currency_conversion['invoice_type'] == "sale_buyin") echo $currency_conversion['abbreviation'];
										else echo $game->db_game['coin_abbreviation'];
										echo " &rarr; ".$received_utxo_html."</div>";
										echo '<div class="col-sm-3">'.$app->format_bignum($exchange_rate).' '.$game->db_game['coin_abbreviation']."/".$currency_conversion['abbreviation']."</div>\n";
										
										echo "</div>\n";
									}
									?>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "game_definition") {
					$show_internal_params = false;
					list($game_def_hash, $game_def) = GameDefinition::fetch_game_definition($game, "defined", $show_internal_params, false);
					?>
					<div class="panel panel-info">
						<div class="panel-heading">
							<div class="panel-title">Game definition for <?php echo $game->db_game['name']." (#".$game->db_game['game_id'].")"; ?></div>
						</div>
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-2"><label class="form-control-static" for="game_definition_hash">Definition hash:</label></div>
								<div class="col-sm-10"><input type="text" class="form-control" id="game_definition_hash" value="<?php echo $game_def_hash; ?>" /></div>
							</div>
							
							<textarea id="game_definition" style="width: 100%; min-height: 400px; background-color: #f5f5f5; border: 1px solid #cccccc; margin-top: 10px;"><?php echo json_encode($game_def, JSON_PRETTY_PRINT); ?></textarea>
						</div>
					</div>
					<script type="text/javascript">
					window.onload = function() {
						$('#game_definition').dblclick(function() {
							$('#game_definition').focus().select();
						});
					};
					</script>
					<?php
				}
				?>
			</div>
			<?php
			include(AppSettings::srcPath().'/includes/html_stop.php');
		}
		else {
			include(AppSettings::srcPath()."/includes/html_start.php");
			?>
			<div class="container-fluid">
				Sorry, you do not have permission to view this page.
			</div>
			<?php
			include(AppSettings::srcPath().'/includes/html_stop.php');
		}
	}
}
?>