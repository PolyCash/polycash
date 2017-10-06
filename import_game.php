<?php
include("includes/connect.php");
include("includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$pagetitle = "Import Game Definition - ".$GLOBALS['coin_brand_name'];
include('includes/html_start.php');
?>
<div class="container-fluid">
	<?php
	if ($thisuser) {
		if (!empty($_REQUEST['action']) && $_REQUEST['action'] == "import_game_definition") {
			if (empty($GLOBALS['cron_key_string']) || $_REQUEST['key'] == $GLOBALS['cron_key_string']) {
				$game_definition = $_REQUEST['game_definition'];

				$error_message = false;
				$new_game = $app->create_game_from_definition($game_definition, $thisuser, false, $error_message);
			}
			else $error_message = "Error, you didn't enter the right site administrator key.\n";
			
			if (!$error_message) {
				echo "Your game definition was successfully imported!<br/>\n";
				echo "Please be patient as it may take several minutes for this game to sync.<br/>\n";
				echo "Next please <a href=\"/".$new_game->db_game['url_identifier']."/\">click here</a> to join the game.<br/>\n";
			}
			else {
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
		$redirect_key = $redirect_url['redirect_key'];
		include("includes/html_login.php");
	}
	?>
</div>
<?php
include('includes/html_stop.php');
?>