<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Import Game Definition - ".$GLOBALS['coin_brand_name'];
include('includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	$definition_mode = "game";
	$toggle_mode = "blockchain";
	
	if ($_REQUEST['definition_mode'] == "blockchain") {
		$definition_mode = $_REQUEST['definition_mode'];
		$toggle_mode = "game";
	}
	
	if ($thisuser) {
		?>
		<div class="panel panel-info" style="margin-top: 10px;">
			<div class="panel-heading">
				<div class="panel-title">Import a game or blockchain</div>
			</div>
			<div class="panel-body">
			<?php
				if ($app->user_is_admin($thisuser)) {
					if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "import_definition") {
						$db_new_game = false;
						$error_message = false;
						
						$definition = $_REQUEST['definition'];
						
						if ($definition_mode == "game") {
							$app->create_game_from_definition($definition, $thisuser, $error_message, $db_new_game);
							
							if (!empty($error_message)) echo "<p>".$error_message."</p>\n";
							
							echo '<p>Successfully created <a href="/'.$db_new_game['url_identifier'].'/">'.$db_new_game['name'].'</a></p>';
						
							if ($db_new_game) {
								echo "<p>Your ".$definition_mode." definition was successfully imported!<br/>\n";
								echo "Please be patient as it may take several minutes for this game to sync.<br/>\n";
								echo "Please <a href=\"/".$db_new_game['url_identifier']."/\">click here</a> to join the game.</p>\n";
							}
							else {
								echo "<p><a href=\"/import/?definition_mode=".$definition_mode."\">Try again</a></p>\n";
							}
						}
						else {
							$app->create_blockchain_from_definition($definition, $thisuser, $error_message, $db_new_blockchain);
							
							if (!empty($error_message)) echo "<p>".$error_message."</p>\n";
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
							<input type="hidden" name="action" value="import_definition" />
							<input type="hidden" name="definition_mode" value="<?php echo $definition_mode; ?>" />
							<p>
								<a href="/import/?definition_mode=<?php echo $toggle_mode; ?>">Switch to importing <?php echo $toggle_mode."s"; ?></a>
							</p>
							<p>
								Definition:<br/>
								<textarea id="definition" name="definition" class="form-control" rows="10" style="margin: 10px 0px;"></textarea>
							</p>
							<p>
								<button class="btn btn-primary">Import <?php echo ucwords($definition_mode); ?></button>
							</p>
						</form>
						<?php
					}
				}
				else echo "You don't have permission to complete this action.";
				?>
			</div>
		</div>
		<?php
	}
	else {
		$redirect_url = $app->get_redirect_url("/import/?definition_mode=".$definition_mode);
		$redirect_key = $redirect_url['redirect_key'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>