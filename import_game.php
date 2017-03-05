<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Import Game Definition - ".$GLOBALS['coin_brand_name'];
include('includes/html_start.php');
?>
<div class="container" style="max-width: 1000px; padding-top: 10px; padding-bottom: 10px; margin-bottom: 10px;">
	<?php
	if ($thisuser) {
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "import_game_definition") {
			if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
				$game_definition = $_REQUEST['game_definition'];
				$game_def = json_decode($game_definition) or die("Error: the game definition you entered could not be imported.<br/>Please make sure to enter properly formatted JSON.<br/><a href=\"/import/\">Try again</a>");
				
				$error_message = "";
				
				if ($game_def->blockchain_identifier != "") {
					if ($game_def->blockchain_identifier == "private") {
						$chain_id = $app->random_string(6);
						$url_identifier = "private-chain-".$chain_id;
						$chain_pow_reward = 25*pow(10,8);
						$q = "INSERT INTO blockchains SET online=1, p2p_mode='none', blockchain_name='Private Chain', url_identifier='".$url_identifier."', coin_name='chaincoin', coin_name_plural='chaincoins', seconds_per_block=30, first_required_block=1, initial_pow_reward=".$chain_pow_reward.";";
						$r = $app->run_query($q);
						$blockchain_id = $app->last_insert_id();
						$new_blockchain = new Blockchain($app, $blockchain_id);
						if ($thisuser) $new_blockchain->set_blockchain_creator($thisuser);
						$game_def->blockchain_identifier = $url_identifier;
					}
					
					$q = "SELECT * FROM blockchains WHERE url_identifier=".$app->quote_escape($game_def->blockchain_identifier).";";
					$r = $app->run_query($q);
					
					if ($r->rowCount() == 1) {
						$db_blockchain = $r->fetch();
						$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
						
						$game_def->url_identifier = $app->normalize_uri_part($game_def->url_identifier);
						
						if ($game_def->url_identifier != "") {
							$q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($game_def->url_identifier).";";
							$r = $app->run_query($q);
							
							if ($r->rowCount() == 0) {
								$verbatim_vars = $app->game_definition_verbatim_vars();
								
								$q = "INSERT INTO games SET ";
								if ($thisuser) $q .= "creator_id='".$thisuser->db_user['user_id']."', ";
								$q .= "blockchain_id='".$db_blockchain['blockchain_id']."', game_status='published', featured=1, seconds_per_block='".$db_blockchain['seconds_per_block']."', start_condition='fixed_block', giveaway_status='public_free', invite_currency='".$blockchain->currency_id()."', logo_image_id=34";
								for ($i=0; $i<count($verbatim_vars); $i++) {
									$var_type = $verbatim_vars[$i][0];
									$var_name = $verbatim_vars[$i][1];
									$q .= ", ".$var_name."=".$app->quote_escape($game_def->$var_name);
								}
								$q .= ";";
								$r = $app->run_query($q);
								$new_game_id = $app->last_insert_id();
								
								$new_game = new Game($blockchain, $new_game_id);
								
								if ($new_game->db_game['p2p_mode'] == "none") {
									if ($thisuser) $user_game = $thisuser->ensure_user_in_game($new_game);
									
									if (empty($new_game->db_game['genesis_tx_hash'])) {
										$game_genesis_tx_hash = $app->random_string(64);
										
										$q = "UPDATE games SET genesis_tx_hash=".$app->quote_escape($game_genesis_tx_hash)." WHERE game_id='".$new_game->db_game['game_id']."';";
										$r = $app->run_query($q);
										$new_game->db_game['genesis_tx_hash'] = $game_genesis_tx_hash;
									}
									else $game_genesis_tx_hash = $new_game->db_game['genesis_tx_hash'];
									
									$new_game->genesis_hash = $game_genesis_tx_hash;
									if ($thisuser) $new_game->user_game = $user_game;
									
									$blockchain->add_genesis_block($new_game);
									
									$block_hash = $app->random_string(64);
									$blockchain->private_add_block($new_game, $block_hash, 1);
								}
								
								if ($new_game->db_game['event_rule'] == "game_definition") {
									$game_defined_events = $game_def->events;
									$game_event_params = $app->event_verbatim_vars();
									
									for ($i=0; $i<count($game_defined_events); $i++) {
										$q = "INSERT INTO game_defined_events SET game_id='".$new_game->db_game['game_id']."', event_index='".$i."'";
										
										for ($j=0; $j<count($game_event_params); $j++) {
											$var_type = $game_event_params[$j][0];
											$var_val = (string) $game_defined_events[$i]->$game_event_params[$j][1];
											
											if ($var_val === "" || strtolower($var_val) == "null") $escaped_var_val = "NULL";
											else $escaped_var_val = $app->quote_escape($var_val);
											
											$q .= ", ".$game_event_params[$j][1]."=".$escaped_var_val;
										}
										$q .= ";";
										$r = $app->run_query($q);
										
										$possible_outcomes = $game_defined_events[$i]->possible_outcomes;
										
										for ($k=0; $k<count($game_defined_events[$i]->possible_outcomes); $k++) {
											$q = "INSERT INTO game_defined_options SET game_id='".$new_game->db_game['game_id']."', event_index='".$i."', option_index='".$k."', name=".$app->quote_escape($possible_outcomes[$k]->title).";";
											$r = $app->run_query($q);
										}
									}
								}
								
								$new_game->check_set_game_definition();
								
								echo "Your game definition was successfully imported!<br/>\n";
								echo "Please be patient as it may take several minutes for this game to sync.<br/>\n";
								echo "Next please <a href=\"/".$new_game->db_game['url_identifier']."/\">click here</a> to join the game.<br/>\n";
							}
							else $error_message = "Error, a game already exists with that URL identifier.";
						}
						else $error_message = "Error, invalid game URL identifier.";
					}
					else $error_message = "Error, failed to identify the right blockchain.";
				}
				else $error_message = "Error, failed to identify the right blockchain.";
			}
			else $error_message = "Error, you didn't enter the right site administrator key.\n";
			
			if ($error_message != "") {
				echo $error_message."<br/><a href=\"/import/\">Try again</a><br/>\n";
			}
		}
		else {
			?>
			<script type="text/javascript">
			$(document).ready(function() {
				$('#key').focus();
			});
			</script>
			<form action="/import/" method="post">
				<input type="hidden" name="action" value="import_game_definition" />
				<p>
					To import a new game, please enter the site administrator key and game definition below:
				</p>
				<div class="row">
					<div class="col-md-3 form-control-static">Site administrator key:</div>
					<div class="col-md-9">
						<input type="text" id="key" name="key" class="form-control" autocomplete="off" />
					</div>
				</div>
				<p>
					Game definition:<br/>
					<textarea id="game_definition" name="game_definition" class="form-control" rows="10" style="margin: 10px 0px;"></textarea>
				</p>
				<p>
					<button class="btn btn-primary">Create Game</button>
				</p>
			</form>
			<?php
		}
	}
	else {
		$redirect_url = $app->get_redirect_url("/import/");
		$redirect_id = $redirect_url['redirect_url_id'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>