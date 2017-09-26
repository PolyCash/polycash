<?php
include("../includes/connect.php");
include("../includes/get_session.php");
if ($GLOBALS['pageview_tracking_enabled']) $viewer_id = $pageview_controller->insert_pageview($thisuser);

$game_id = (int) $_REQUEST['game_id'];
$address_id = (int) $_REQUEST['address_id'];
$permission_to_claim_address = false;

$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $db_game['game_id']);

$q = "SELECT * FROM addresses WHERE address_id='".$address_id."';";
$r = $app->run_query($q);

if ($r->rowCount() > 0) {
	$db_address = $r->fetch();
	$permission_to_claim_address = $app->permission_to_claim_address($game, $thisuser, $db_address);
	
	if ($permission_to_claim_address) {
		if ($thisuser) {
			$app->give_address_to_user($game, $thisuser, $db_address);
			$app->output_message(1, "successful!", false);
		}
		else {
			$redirect_url = $app->get_redirect_url("/explorer/games/".$game->db_game['url_identifier']."/addresses/".$db_address['address']."/?action=claim");
			$app->output_message(2, $redirect_url['redirect_url_id'], false);
		}
	}
	else $app->output_message(3, "Permission denied.", false);
}
else $app->output_message(4, "Invalid address ID.", false);
?>