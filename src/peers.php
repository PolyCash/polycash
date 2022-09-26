<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");
$nav_tab_selected = "peers";
$pagetitle = AppSettings::getParam('site_name')." - Manage Peers";

if (!$thisuser) {
	$redirect_url = $app->get_redirect_url($_SERVER['REQUEST_URI']);
	$redirect_key = $redirect_url['redirect_key'];
	
	?>
	<div class="container-fluid">
		<?php
		include(AppSettings::srcPath()."/includes/html_login.php");
		?>
	</div>
	<?php
}
else {
	$game = null;
	$uri_parts = explode("/", $_SERVER['REQUEST_URI']);
	if (!empty($uri_parts[2])) {
		$db_game = $app->fetch_game_by_identifier($uri_parts[2]);
		if ($db_game && $db_game['creator_id'] == $thisuser->db_user['user_id']) {
			$blockchain = new Blockchain($app, $db_game['blockchain_id']);
			$game = new Game($blockchain, $db_game['game_id']);
		}
		else Router::Send404();
	}
	
	if (empty($game)) {
		$owned_games = $app->games_owned_by_user($thisuser);
		
		include(AppSettings::srcPath()."/includes/html_start.php");
		?>
		<div class="container-fluid">
			<div class="panel panel-default" style="margin-top: 15px;">
				<div class="panel-heading">
					<div class="panel-title">Please select a game:</div>
				</div>
				<div class="panel-body">
					<?php
					foreach ($owned_games as $owned_game) {
						?>
						<a href="/peers/<?php echo $owned_game['url_identifier']; ?>/"><?php echo $owned_game['name']; ?></a><br/>
						<?php
					}
					?>
				</div>
			</div>
		</div>
		<?php
		include(AppSettings::srcPath()."/includes/html_stop.php");
	}
	else {
		$messages = [];
		
		if (!empty($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
			if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
				if ($action == "add_peer") {
					if (!empty($_REQUEST['base_url'])) {
						$peer = $app->get_peer_by_server_name($_REQUEST['base_url'], false);
						
						$added_peer = false;
						
						if (empty($peer)) {
							list($base_url, $server_name) = $app->peer_base_url_to_server_name($_REQUEST['base_url']);
							
							$peer = $app->create_peer([
								'peer_identifier' => $server_name,
								'peer_name' => $server_name,
								'base_url' => $base_url
							]);
							
							$added_peer = true;
						}
						
						$game_peer = $game->get_game_peer_by_peer($peer);
						
						if (empty($game_peer)) {
							$game_peer = $game->create_game_peer($peer);
							$added_peer = true;
						}
						
						$enabled_peer = false;
						
						if (!empty($game_peer['disabled_at'])) {
							list($successful, $enable_error_message) = $app->enable_game_peer($game_peer);
							
							if ($successful) $enabled_peer = true;
						}
						
						if ($added_peer || $enabled_peer) {
							array_push($messages, [
								'type' => 'success',
								'message' => 'Successfully created a new peer.',
							]);
						}
						else {
							array_push($messages, [
								'type' => 'warning',
								'message' => 'That peer has already been added.',
							]);
						}
					}
					else {
						array_push($messages, [
							'type' => 'warning',
							'message' => 'Please supply a valid peer URL.',
						]);
					}
				}
				else {
					array_push($messages, [
						'type' => 'warning',
						'message' => 'The action you supplied is invalid.',
					]);
				}
			}
		}
		
		$game_peers = $game->fetch_all_peers();
		
		include(AppSettings::srcPath()."/includes/html_start.php");
		?>
		<div class="container-fluid" style="padding-top: 15px;">
			<?php
			foreach ($messages as $message) {
				?>
				<div class="alert alert-<?php echo $message['type']; ?>" role="alert">
					<?php echo $message['message']; ?>
				</div>
				<?php
			}
			?>
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="panel-title">Manage peers for <?php echo $game->db_game['name']; ?></div>
				</div>
				<div class="panel-body">
					<a href="" class="btn btn-sm btn-success" style="float: right;" onClick="$('#new_peer_modal').modal('show');return false;"><i class="fas fa-plus"></i> &nbsp; Add a Peer</a>
					<p>
						<?php echo $game->db_game['name']; ?> has <?php echo count($game_peers)." peer".(count($game_peers) == 1 ? "": "s"); ?>.
					</p>
					<?php
					foreach ($game_peers as $game_peer) {
						?>
						<div class="row">
							<div class="col-sm-1">
								<font class="text-danger" onClick="thisPageManager.remove_game_peer(<?php echo $game_peer['game_peer_id']; ?>);" style="cursor: pointer;">&times;</font>
							</div>
							<div class="col-sm-4">
								<?php echo $game_peer['peer_name']; ?>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>
		</div>
		<div style="display: none;" class="modal fade" id="new_peer_modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<form method="post" action="/peers/<?php echo $game->db_game['url_identifier']; ?>" />
						<input type="hidden" name="action" value="add_peer" />
						<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
						
						<div class="modal-header">
							<b class="modal-title">Add a peer for <?php echo $game->db_game['name']; ?></b>
							
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<div class="form-group">
								<label for="base_url">Please enter the IP address or host name for this peer:</label>
								<input id="base_url" name="base_url" type="text" class="form-control" />
							</div>
						</div>
						<div class="modal-footer">
							<a href="" data-dismiss="modal">Cancel</a> &nbsp;&nbsp;&nbsp;&nbsp;
							<button class="btn btn-sm btn-success" onclick=""><i class="fas fa-plus"></i> &nbsp; Add Peer</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
		include(AppSettings::srcPath()."/includes/html_stop.php");
	}
}
