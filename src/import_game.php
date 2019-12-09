<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$import_mode = "game";
$toggle_mode = "blockchain";

if (!empty($_REQUEST['import_mode']) && $_REQUEST['import_mode'] == "blockchain") {
	$import_mode = "blockchain";
	$toggle_mode = "game";
}

$pagetitle = "Import a ";
if ($import_mode == "game") $pagetitle .= "Game Definition";
else $pagetitle .= "Blockchain";
$pagetitle .= " - ".AppSettings::getParam('coin_brand_name');

$nav_tab_selected = "import";

include(AppSettings::srcPath().'/includes/html_start.php');
?>
<div class="container-fluid">
	<?php
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
						if ($app->synchronizer_ok($thisuser, $_REQUEST['synchronizer_token'])) {
							$db_new_game = false;
							$error_message = false;
							
							$definition = $_REQUEST['definition'];
							
							if ($import_mode == "game") {
								$app->set_game_from_definition($definition, $thisuser, $error_message, $db_new_game, false);
								
								if (!empty($error_message)) echo "<p>".$error_message."</p>\n";
								
								if ($db_new_game) {
									echo "<p>Your ".$import_mode." definition was successfully imported!<br/>\n";
									echo "Next please <a href=\"/manage/".$db_new_game['url_identifier']."/?next=internal_settings\">click here</a> and then start the game.</p>\n";
								}
								else {
									echo "<p><a href=\"/import/?import_mode=".$import_mode."\">Try again</a></p>\n";
								}
							}
							else {
								$app->create_blockchain_from_definition($definition, $thisuser, $error_message, $db_new_blockchain);
								
								if (!empty($error_message)) echo "<p>".$error_message."</p>\n";
							}
						}
						else echo "<p>CSRF error</p>\n";
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
							<input type="hidden" name="import_mode" value="<?php echo $import_mode; ?>" />
							<input type="hidden" name="synchronizer_token" value="<?php echo $thisuser->get_synchronizer_token(); ?>" />
							<p>
								<b>Import a new <?php echo $import_mode; ?></b></a>
							</p>
							<p>
								<a href="/import/?import_mode=<?php echo $toggle_mode; ?>">Switch to importing <?php echo $toggle_mode."s"; ?></a>
							</p>
							<p>
								Paste the <?php echo $import_mode; ?> definition here:<br/>
								<textarea id="definition" name="definition" class="form-control" rows="10" style="margin: 10px 0px;"></textarea>
							</p>
							<p>
								<button class="btn btn-primary">Import <?php echo ucwords($import_mode); ?></button>
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
		$redirect_url = $app->get_redirect_url("/import/?import_mode=".$import_mode);
		$redirect_key = $redirect_url['redirect_key'];
		include(AppSettings::srcPath()."/includes/html_login.php");
	}
	?>
</div>
<?php
include(AppSettings::srcPath().'/includes/html_stop.php');
?>