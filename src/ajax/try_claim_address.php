<?php
require(AppSettings::srcPath()."/includes/connect.php");
require(AppSettings::srcPath()."/includes/get_session.php");

$game_id = false;
if (!empty($_REQUEST['game_id'])) $game_id = (int) $_REQUEST['game_id'];

$blockchain_id = (int) $_REQUEST['blockchain_id'];
$address_id = (int) $_REQUEST['address_id'];
$permission_to_claim_address = false;

$blockchain = new Blockchain($app, $blockchain_id);
if ($game_id) $game = new Game($blockchain, $game_id);
else $game = false;

$db_address = $app->fetch_address_by_id($address_id);

if ($db_address) {
	$permission_to_claim_address = $app->permission_to_claim_address($thisuser, $blockchain, $db_address);
	
	if ($permission_to_claim_address) {
		if ($thisuser) {
			$app->give_address_to_user($game, $thisuser, $db_address);
			$app->output_message(1, "successful!", false);
		}
		else {
			if ($game) $url = "/explorer/games/".$game->db_game['url_identifier']."/addresses/".$db_address['address']."/?action=claim";
			else $url = "/explorer/blockchains/".$blockchain->db_blockchain['url_identifier']."/addresses/".$db_address['address']."/?action=claim";
			
			$redirect_url = $app->get_redirect_url($url);
			$app->output_message(2, $redirect_url['redirect_key'], false);
		}
	}
	else $app->output_message(3, "Permission denied.", false);
}
else $app->output_message(4, "Invalid address ID.", false);
?>