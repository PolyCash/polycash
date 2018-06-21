<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$db_game = $app->fetch_game_from_url();

if (empty($db_game)) {
	$nav_tab_selected = "manage_game";
	$pagetitle = "Create a new game?";
	include('includes/html_start.php');
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
					<form action="/ajax/manage_game.php" onsubmit="create_new_game(); return false;">
						<div class="form-group">
							<label for="new_game_name">Please enter a title for the game:</label>
							<input class="form-control" id="new_game_name" />
						</div>
						<div class="form-group">
							<label for="new_game_blockchain_id">Which blockchain should the game run on?</label>
							<select id="new_game_blockchain_id" class="form-control">
								<option value="">-- Please Select --</option>
								<?php
								$q = "SELECT * FROM blockchains ORDER BY blockchain_name ASC;";
								$r = $app->run_query($q);
								while ($db_blockchain = $r->fetch()) {
									echo "<option value=\"".$db_blockchain['blockchain_id']."\">".$db_blockchain['blockchain_name']."</option>\n";
								}
								?>
							</select>
						</div>
						<div class="form-group">
							<label for="new_game_genesis_tx_type">
								Do you want to use an existing transaction as the genesis for this game, or create a new genesis transaction?
							</label>
							<select id="new_game_genesis_type" class="form-control" onchange="new_game_genesis_type_changed();">
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
								<select id="new_game_genesis_account_id" class="form-control" onchange="new_game_genesis_account_changed();"></select>
							</div>
							<div class="form-group">
								<label for="new_game_genesis_io_id">Please select coins for your new genesis transaction:</label>
								<select id="new_game_genesis_io_id" class="form-control"></select>
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
	include('includes/html_stop.php');
}
else {
	if ($thisuser) {
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $db_game['game_id']);
		
		if ($app->user_can_edit_game($thisuser, $game)) {
			$next_action = "params";
			$last_action = "";
			
			if (!empty($_REQUEST['next'])) $next_action = $_REQUEST['next'];
			if (!empty($_REQUEST['last'])) $last_action = $_REQUEST['last'];
			
			if ($last_action == "description") {
				$game_description = $_REQUEST['game_description'];
				
				$q = "UPDATE games SET short_description=".$app->quote_escape($game_description)." WHERE game_id='".$game->db_game['game_id']."';";
				$r = $app->run_query($q);
				
				$game->db_game['short_description'] = $game_description;
			}
			
			$nav_tab_selected = "manage_game";
			$pagetitle = "Manage game: ".$game->db_game['name'];
			include('includes/html_start.php');
			
			$actions = array("params", "events", "description");
			$action_labels = array("Game Parameters", "Manage Events", "Description");
			?>
			<script type="text/javascript">
			var games = new Array();
			$(document).ready(function() {
				games.push(new Game(<?php
					echo $game->db_game['game_id'];
					echo ', false';
					echo ', false';
					echo ', ""';
					echo ', "'.$game->db_game['payout_weight'].'"';
					echo ', '.$game->db_game['round_length'];
					echo ', 0';
					echo ', "'.$game->db_game['url_identifier'].'"';
					echo ', "'.$game->db_game['coin_name'].'"';
					echo ', "'.$game->db_game['coin_name_plural'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name'].'"';
					echo ', "'.$game->blockchain->db_blockchain['coin_name_plural'].'"';
					echo ', "wallet", "'.$game->event_ids().'"';
					echo ', "'.$game->logo_image_url().'"';
					echo ', "'.$game->vote_effectiveness_function().'"';
					echo ', "'.$game->effectiveness_param1().'"';
					echo ', "'.$game->blockchain->db_blockchain['seconds_per_block'].'"';
					echo ', "'.$game->db_game['inflation'].'"';
					echo ', "'.$game->db_game['exponential_inflation_rate'].'"';
					echo ', false';
					echo ', "'.$game->db_game['decimal_places'].'"';
				?>));
				
				manage_game(<?php echo $game->db_game['game_id']; ?>, 'fetch');
			});
			</script>
			
			<div class="container-fluid">
				<div class="row game_tabs">
					<?php
					for ($i=0; $i<count($actions); $i++) {
						echo '<div class="col-sm-2 game_tab';
						if ($next_action == $actions[$i]) echo ' game_tab_sel';
						echo '"><a id="manage_game_tab_'.$actions[$i].'" ';
						//if ($statuses[$i] == "deselected") echo ' onclick="return false;"';
						echo 'href="/manage/'.$game->db_game['url_identifier'].'/?next='.$actions[$i];
						echo '">'.($i+1).'. '.$action_labels[$i].'</a></div>'."\n";
					}
					?>
				</div>
				<?php
				if ($next_action == "params") {
					?>
					<div class="row">
						<div class="col-md-6">
							<div class="panel panel-info">
								<div class="panel-heading">
									<div class="panel-title">Manage game parameters: <?php echo $game->db_game['name']; ?></div>
								</div>
								<div class="panel-body">
									<form onsubmit="save_game();">
										<div class="form-group">
											<label for="game_form_name">Game title:</label>
											<input class="form-control" type="text" id="game_form_name" />
										</div>
										<div class="form-group">
											<label for="game_form_blockchain_id">Runs on Blockchain:</label>
											<select id="game_form_blockchain_id" class="form-control">
												<option value="">-- Please Select --</option>
												<?php
												$q = "SELECT * FROM blockchains ORDER BY blockchain_name ASC;";
												$r = $app->run_query($q);
												while ($db_blockchain = $r->fetch()) {
													echo "<option value=\"".$db_blockchain['blockchain_id']."\">".$db_blockchain['blockchain_name']."</option>\n";
												}
												?>
											</select>
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
											<label for="game_form_game_status">Game status:</label>
											<div id="game_form_game_status" class="form-control-static"></div>
										</div>
										<div class="form-group">
											<button id="start_game_btn" class="btn btn-info" style="display: none;" onclick="manage_game(editing_game_id, 'running'); return false;">Start Game</button>
											<button id="pause_game_btn" class="btn btn-info" style="display: none;" onclick="manage_game(editing_game_id, 'paused'); return false;">Pause Game</button>

											<button id="delete_game_btn" class="btn btn-danger" style="display: none;" onclick="manage_game(editing_game_id, 'delete'); return false;">Delete Game</button>
											<button id="reset_game_btn" class="btn btn-warning" style="display: none;" onclick="manage_game(editing_game_id, 'reset'); return false;">Reset Game</button>
										</div>
										<div class="form-group">
											<label for="game_form_has_final_round">Game ends?</label>
											<select class="form-control" id="game_form_has_final_round" onchange="game_form_final_round_changed();">
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
											<select class="form-control" id="game_form_buyin_policy" onchange="game_form_buyin_policy_changed();">
												<option value="none">No additional buy-ins</option>
												<option value="unlimited">Unlimited buy-ins</option>
												<option value="game_cap">Buy-in cap for the whole game</option>
											</select>
										</div>
										<div id="game_form_game_buyin_cap_disp">
											<label for="game_form_game_buyin_cap">Game-wide buy-in cap:</label>
											<input class="form-control" style="text-align: right;" type="text" id="game_form_game_buyin_cap" />
										</div>
										<div class="form-group">
											<label for="game_form_inflation">Inflation:</label>
											<select id="game_form_inflation" class="form-control" onchange="game_form_inflation_changed();">
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
											<select id="game_form_event_rule" class="form-control" onchange="game_form_event_rule_changed();">
												<option value="single_event_series">Single, repeating event</option>
												<option value="entity_type_option_group">One event for each item in a group</option>
												<option value="all_pairs">Head to head between all options</option>
											</select>
										</div>
										<div class="form-group">
											<label for="game_form_option_group_id">Voting options:</label>
											<select id="game_form_option_group_id" class="form-control">
												<?php
												$q = "SELECT * FROM option_groups ORDER BY description ASC;";
												$r = $app->run_query($q);
												while ($option_group = $r->fetch()) {
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
													$q = "SELECT * FROM entity_types ORDER BY entity_name ASC;";
													$r = $app->run_query($q);
													while ($entity_type = $r->fetch()) {
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
											<button id="save_game_btn" type="button" class="btn btn-success" onclick="save_game('save');">Save Settings</button>
											<button id="publish_game_btn" type="button" class="btn btn-primary" onclick="save_game('publish');">Save &amp; Publish</button>
										</div>
										<?php /*<button id="game_invitations_game_btn" type="button" class="btn btn-info" data-dismiss="modal" onclick="manage_game_invitations(editing_game_id);">Invite People</button> */ ?>
									</form>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				else if ($next_action == "events") {
					if ($game->db_game['event_rule'] == "game_definition") {
						?>
						<div class="panel panel-info">
							<div class="panel-heading">
								<div class="panel-title"><?php echo $game->db_game['name']; ?>: Manage Events</div>
							</div>
							<div class="panel-body">
								<p>
									<a href="" onclick="manage_game_load_event('new'); return false;">Create a new Event</a>
								</p>
								<?php
								$q = "SELECT * FROM game_defined_events WHERE game_id='".$game->db_game['game_id']."' ORDER BY event_index DESC;";
								$r = $app->run_query($q);
								while ($gde = $r->fetch()) {
									echo '<div class="row"><div class="col-md-2" style="text-align: center;"><a href="" onclick="manage_game_load_event('.$gde['game_defined_event_id'].'); return false;">Edit</a> &nbsp;&nbsp; <a href="" onclick="manage_game_event_options('.$gde['game_defined_event_id'].'); return false;">Edit Options</a></div><div class="col-md-6">'.$gde['event_index'].'. '.$gde['event_name'].'</a></div></div>'."\n";
								}
								?>
							</div>
						</div>
						
						<div style="display: none;" class="modal fade" id="event_modal">
							<div class="modal-dialog">
								<div class="modal-content" id="event_modal_content">
								</div>
							</div>
						</div>
						
						<div style="display: none;" class="modal fade" id="options_modal">
							<div class="modal-dialog">
								<div class="modal-content" id="options_modal_content">
								</div>
							</div>
						</div>
						<?php
					}
					else echo "Events are determined automatically for this game. You cannot add events manually.";
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
								<textarea name="game_description" id="game_description" cols="90" rows="14"><?php echo $game->db_game['short_description']; ?></textarea>
								<input class="btn btn-primary" type="submit" value="Save Description" />
							</form>
						</div>
					</div>
					<script>
					var editor;
					$(document).ready(function() {
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
					});
					</script>
					<?php
				}
				?>
			</div>
			<?php
			include('includes/html_stop.php');
		}
		else {
			include("includes/html_start.php");
			?>
			<div class="container-fluid">
				Sorry, you do not have permission to view this page.
			</div>
			<?php
			include('includes/html_stop.php');
		}
	}
	else {
		$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
		$redirect_key = $redirect_url['redirect_key'];
		
		include("includes/html_start.php");
		?>
		<div class="container-fluid">
		<?php
		include("includes/html_login.php");
		?>
		</div>
		<?php
		include('includes/html_stop.php');
	}
}
?>