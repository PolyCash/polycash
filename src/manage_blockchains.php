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
		
		$blockchain_r = $app->run_query("SELECT * FROM blockchains ORDER BY blockchain_id ASC;");
		?>
		<div class="panel panel-default" style="margin-top: 15px;">
			<div class="panel-heading">
				<div class="panel-title">
					Manage Blockchains
				</div>
			</div>
			<div class="panel-body">
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
									echo "Web";
									if ($blockchain->db_blockchain['p2p_mode'] == "none") echo " (Authoritative)";
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
			</div>
		</div>
		<script type="text/javascript">
		var BlockchainManager = function() {
			this.actionSelected = function(blockchain_id, blockchain_name, blockchain_identifier, selectElement) {
				var action = selectElement.value;
				selectElement.value = "";
				
				var confirm_ok = false;
				
				if (action == "set_rpc_credentials" || action == "see_definition") confirm_ok = true;
				else {
					var confirm_message = "Are you sure you want to ";
					if (action == "reset_synchronize") confirm_message += "reset & synchronize";
					else confirm_message += action;
					confirm_message += " "+blockchain_name+"?";
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
					else {
						$('#'+action+'_'+blockchain_id).submit();
					}
				}
			};
		};
		
		var thisBlockchainManager;
		
		window.onload = function() {
			thisBlockchainManager = new BlockchainManager();
		};
		</script>
		<?php
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
