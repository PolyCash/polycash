<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$nav_tab_selected = "manage_blockchains";
$pagetitle = AppSettings::getParam('site_name')." - Manage Blockchains";

AppSettings::addJsDependency("jquery.datatables.js");

include(AppSettings::srcPath()."/includes/html_start.php");
?>
<div class="container-fluid">
	<?php
	if (!$thisuser) {
		$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
		$redirect_key = $redirect_url['redirect_key'];
		
		include(AppSettings::srcPath()."/includes/html_login.php");
	}
	else if (!$app->user_is_admin($thisuser)) {
		?>
		You must be logged in as admin to manage blockchains.
		<?php
	}
	else {
		if (!empty($_REQUEST['action']) && $app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
			if ($_REQUEST['action'] == "new_blockchain") {
				$p2p_mode = $_REQUEST['p2p_mode'];
				if (!in_array($p2p_mode, ['rpc', 'none', 'web'])) $p2p_mode = "none";
				
				$blockchain_name = $_REQUEST['blockchain_name'];
				$url_identifier = $_REQUEST['url_identifier'];
				$coin_name = $_REQUEST['coin_name'];
				$coin_name_plural = $_REQUEST['coin_name_plural'];
				
				$seconds_per_block = (int) $_REQUEST['seconds_per_block'];
				$decimal_places = (int) $_REQUEST['decimal_places'];
				$first_required_block = (int) $_REQUEST['first_required_block'];
				$initial_pow_reward = ((float) $_REQUEST['initial_pow_reward'])*pow(10, $decimal_places);
				
				$new_blockchain_def = json_encode([
					'peer' => 'none',
					'p2p_mode' => $p2p_mode,
					'blockchain_name' => $blockchain_name,
					'url_identifier' => $url_identifier,
					'coin_name' => $coin_name,
					'coin_name_plural' => $coin_name_plural,
					'seconds_per_block' => $seconds_per_block,
					'decimal_places' => $decimal_places,
					'initial_pow_reward' => $initial_pow_reward
				], JSON_PRETTY_PRINT);
				
				$new_blockchain_message = "";
				$new_blockchain_id = $app->create_blockchain_from_definition($new_blockchain_def, $thisuser, $new_blockchain_message);
				$app->blockchain_ensure_currencies();
				
				if ($new_blockchain_id) {
					echo "Great, your blockchain has been created!<br/>\n";
				}
				else echo $new_blockchain_message."<br/>\n";
			}
			else {
				$blockchain_id = (int) $_REQUEST['blockchain_id'];
				$existing_blockchain = $app->fetch_blockchain_by_id($blockchain_id);
				
				if ($existing_blockchain) {
					if ($_REQUEST['action'] == "save_blockchain_params") {
						$app->run_query("UPDATE blockchains SET rpc_host=:rpc_host, rpc_username=:rpc_username, rpc_password=:rpc_password, rpc_port=:rpc_port, first_required_block=:first_required_block WHERE blockchain_id=:blockchain_id;", [
							'rpc_host' => $_REQUEST['rpc_host'],
							'rpc_username' => $_REQUEST['rpc_username'],
							'rpc_password' => $_REQUEST['rpc_password'],
							'rpc_port' => $_REQUEST['rpc_port'],
							'first_required_block' => $_REQUEST['first_required_block'],
							'blockchain_id' => $existing_blockchain['blockchain_id']
						]);
					}
					else if (in_array($_REQUEST['action'], ['enable','disable'])) {
						$online = $_REQUEST['action'] == "enable" ? 1 : 0;
						
						$app->run_query("UPDATE blockchains SET online=:online WHERE blockchain_id=:blockchain_id;", [
							'online' => $online,
							'blockchain_id' => $existing_blockchain['blockchain_id']
						]);
					}
					else die("Invalid action supplied.");
				}
				else die("Error: invalid blockchain ID.");
			}
		}
		
		$blockchain_r = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_id ASC;");
		?>
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">
					Manage Blockchains
				</div>
			</div>
			<div class="panel-body">
				<button class="btn btn-success btn-sm" style="margin-bottom: 15px; float: right;" onclick="$('#new_blockchain_modal').modal('show');">+ New Blockchain</button>
				
				<p><?php echo $blockchain_r->rowCount(); ?> blockchains are installed.</p>
				
				<table style="width: 100%;" class="table table-bordered">
					<thead style="background-color: #f6f6f6;">
						<tr>
							<th style="width: 180px;">P2P Type</th>
							<th>Name</th>
							<th>Enabled?</th>
							<th>Connection Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ($db_blockchain = $blockchain_r->fetch()) {
							$blockchain = new Blockchain($app, $db_blockchain['blockchain_id']);
							?>
							<tr>
								<td><?php
								if ($blockchain->db_blockchain['p2p_mode'] == "rpc") echo "RPC / Daemon";
								else {
									echo "Web &nbsp; ";
									if ($blockchain->db_blockchain['p2p_mode'] == "none") echo "(Authoritative)";
									else echo "(".$blockchain->authoritative_peer['base_url'].")";
								}
								?></td>
								<td><?php echo $blockchain->db_blockchain['blockchain_name']; ?></td>
								<td><?php
								if ($blockchain->db_blockchain['online']) echo '<font class="text-success">Enabled</font>';
								else echo '<font class="text-danger">Disabled</font>';
								?></td>
								<td>
									<?php
									$last_active_time = $blockchain->last_active_time();
									if ($last_active_time > time()-(60*60)) {
										echo '<font class="text-success">Online</font>';
									}
									else {
										echo '<font class="text-danger">Offline &nbsp;&nbsp; ';
										if ($blockchain->db_blockchain['p2p_mode'] == "rpc" && empty($blockchain->db_blockchain['rpc_username'].$blockchain->db_blockchain['rpc_password'])) echo '(Please set RPC credentials)';
										else if ($last_active_time) echo '(Last active '.$app->format_seconds(time()-$last_active_time).')';
										else echo '(Never Connected)';
										echo '</font>';
									}
									?>
								</td>
								<td>
									<select class="form-control input-sm" onchange='thisBlockchainManager.actionSelected(<?php
										echo $blockchain->db_blockchain['blockchain_id'];
										echo ','.json_encode($blockchain->db_blockchain['blockchain_name']);
										echo ','.json_encode($blockchain->db_blockchain['url_identifier']);
										?>, this);'>
										<option value="">-- Please Select --</option>
										<option value="see_definition">See Definition</option>
										<?php if ($blockchain->db_blockchain['p2p_mode'] != "none") { ?>
											<option value="reset_synchronize">Reset &amp; Synchronize</option>
											<?php
										}
										if ($blockchain->db_blockchain['p2p_mode'] == "rpc") { ?>
											<option value="set_rpc_credentials"><?php echo empty($blockchain->db_blockchain['rpc_username'].$blockchain->db_blockchain['rpc_password']) ? "Set" : "Change"; ?> RPC Credentials</option>
											<?php
										}
										if ($blockchain->db_blockchain['online']) {
											?>
											<option value="disable">Disable</option>
											<?php
										}
										else {
											?>
											<option value="enable">Enable</option>
											<?php
										}
										if ($blockchain->db_blockchain['p2p_mode'] == "none") { ?>
											<option value="claim_coinbase">Transfer mined coins to address</option>
											<?php
										}
										?>
									</select>
									
									<div style="display: none;" class="modal fade" id="event_modal">
										<div class="modal-dialog">
											<div class="modal-content" id="event_modal_content">
											<div class="modal-body">
												
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-primary" id="event_form_save_btn">Save changes</button>
												<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
											</div>
											</div>
										</div>
									</div>
									
									<div class="modal fade" id="set_rpc_<?php echo $db_blockchain['blockchain_id']; ?>" style="display: none;">
										<div class="modal-dialog">
											<div class="modal-content">
												<form method="post" action="/manage_blockchains/">
													<div class="modal-body">
														<p>Please enter the RPC username and password for connecting to the <b><?php echo $db_blockchain['blockchain_name']; ?></b> daemon:</p>
														<input type="hidden" name="action" value="save_blockchain_params" />
														<input type="hidden" name="blockchain_id" value="<?php echo $blockchain->db_blockchain['blockchain_id']; ?>" />
														<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
														<input class="form-control" name="rpc_host" placeholder="RPC hostname (default 127.0.0.1)" />
														<input class="form-control" name="rpc_username" placeholder="RPC username" />
														<input class="form-control" name="rpc_password" placeholder="RPC password" autocomplete="off" />
														<input class="form-control" name="rpc_port" value="<?php echo $blockchain->db_blockchain['default_rpc_port']; ?>" placeholder="RPC port" />
														<input class="form-control" name="first_required_block" value="" placeholder="First required block" />
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-warning" data-dismiss="modal">Cancel</button>
														 &nbsp;&nbsp; or &nbsp; 
														<input type="submit" class="btn btn-success" value="Save RPC Credentials" />
													</div>
												</form>
											</div>
										</div>
									</div>
									
									<form method="post" action="/manage_blockchains/" id="disable_<?php echo $blockchain->db_blockchain['blockchain_id']; ?>">
										<input type="hidden" name="action" value="disable" />
										<input type="hidden" name="blockchain_id" value="<?php echo $blockchain->db_blockchain['blockchain_id']; ?>" />
										<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
									</form>
									
									<form method="post" action="/manage_blockchains/" id="enable_<?php echo $blockchain->db_blockchain['blockchain_id']; ?>">
										<input type="hidden" name="action" value="enable" />
										<input type="hidden" name="blockchain_id" value="<?php echo $blockchain->db_blockchain['blockchain_id']; ?>" />
										<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
									</form>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				
				<div class="modal fade" id="new_blockchain_modal" style="display: none;">
					<div class="modal-dialog">
						<div class="modal-content">
							<form method="post" action="/manage_blockchains/">
								<div class="modal-header">
									Create a new blockchain
								</div>
								<div class="modal-body">
									<input type="hidden" name="action" value="new_blockchain" />
									<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
									
									<div class="form-group">
										<label for="new_p2p_mode">What P2P mode will this blockchain use?</label>
										<select class="form-control" name="p2p_mode" id="new_p2p_mode" required="required">
											<option value="">-- Please Select --</option>
											<option value="rpc">Use a coin daemon and connect via RPC</option>
											<option value="web">Read blocks from a trusted peer via web api</option>
											<option value="none" selected="selected">Mine all my own blocks &amp; allow peers to read via web api</option>
										</select>
									</div>
									
									<div class="form-group">
										<label for="new_blockchain_name">Please enter a name for this blockchain:</label>
										<input class="form-control" name="blockchain_name" id="new_blockchain_name" placeholder="Bitcoin" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_url_identifier">Please enter a URL identifier to uniquely identify this blockchain:</label>
										<input class="form-control" name="url_identifier" id="new_url_identifier" placeholder="bitcoin" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_coin_name">What is a single coin called on this blockchain?</label>
										<input class="form-control" name="coin_name" id="new_coin_name" placeholder="bitcoin" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_coin_name_plural">What are multiple coins called on this blockchain?</label>
										<input class="form-control" name="coin_name_plural" id="new_coin_name_plural" placeholder="bitcoins" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_seconds_per_block">How many seconds does it take to mine a block on average?</label>
										<input class="form-control" name="seconds_per_block" id="new_seconds_per_block" placeholder="600" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_seconds_per_block">How many decimal places can a coin be divided into?</label>
										<input class="form-control" name="decimal_places" id="new_decimal_places" placeholder="8" value="8" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_first_required_block">What block do you want to load this blockchain from?</label>
										<input class="form-control" name="first_required_block" id="new_first_required_block" placeholder="1" value="1" required="required" />
									</div>
									
									<div class="form-group">
										<label for="new_initial_pow_reward">How many coins do miners receive per block? (initially)</label>
										<input class="form-control" name="initial_pow_reward" id="new_initial_pow_reward" placeholder="50" required="required" />
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-warning btn-sm" data-dismiss="modal">Cancel</button>
									 &nbsp;&nbsp; or &nbsp; 
									<input type="submit" class="btn btn-success btn-sm" value="Create new blockchain" />
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
		var BlockchainManager = function(synchronizer_token) {
			this.synchronizer_token = synchronizer_token;
			
			this.actionSelected = function(blockchain_id, blockchain_name, blockchain_identifier, selectElement) {
				var action = selectElement.value;
				selectElement.value = "";
				
				var confirm_ok = false;
				
				if (action == "set_rpc_credentials" || action == "see_definition") confirm_ok = true;
				else {
					var confirm_message = "Are you sure you want to ";
					if (action == "reset_synchronize") confirm_message += "reset & synchronize "+blockchain_name+"?";
					else if (action == "claim_coinbase") confirm_message += "move mined coins to an address?";
					else confirm_message += action+" "+blockchain_name+"?";
					confirm_ok = confirm(confirm_message);
				}
				
				if (confirm_ok) {
					if (action == "reset_synchronize") {
						window.open('/scripts/sync_blockchain_initial.php?&blockchain_id='+blockchain_id, '_blank');
					}
					else if (action == "set_rpc_credentials") {
						$('#set_rpc_'+blockchain_id).modal('show');
					}
					else if (action == "see_definition") {
						window.open('/explorer/blockchains/'+blockchain_identifier+'/definition/', '_blank');
					}
					else if (action == "claim_coinbase") {
						var coinbase_quantity = prompt("How many "+blockchain_name+" coinbase outputs do you want to claim?");
						if (coinbase_quantity) {
							var to_address = prompt("Please enter the address where these coins should be deposited:");
							if (to_address) {
								$.ajax({
									url: "/ajax/claim_coinbase.php",
									dataType: "json",
									data: {
										blockchain_id: blockchain_id,
										synchronizer_token: this.synchronizer_token,
										quantity: coinbase_quantity,
										to_address: to_address
									},
									success: function(claim_response) {
										if (claim_response.status_code == 1) window.location = claim_response.message;
										else alert(claim_response.message);
									}
								});
							}
						}
					}
					else {
						$('#'+action+'_'+blockchain_id).submit();
					}
				}
			};
		};
		
		var thisBlockchainManager;
		
		window.onload = function() {
			thisBlockchainManager = new BlockchainManager('<?php echo $thisuser->get_synchronizer_token(); ?>');
		};
		</script>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
