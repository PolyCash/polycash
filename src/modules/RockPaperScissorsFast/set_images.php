<?php
include(dirname(dirname(dirname(__FILE__)))."/includes/connect.php");

$allowed_params = ['game_id'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

$game_id = (int) $_REQUEST['game_id'];
$db_game = $app->fetch_game_by_id($game_id);
$blockchain = new Blockchain($app, $db_game['blockchain_id']);
$game = new Game($blockchain, $db_game['game_id']);

if ($game->db_game['module'] == "RockPaperScissorsFast") {
	$peer_host_name = "https://poly.cash";
	
	for ($option_index=0; $option_index<3; $option_index++) {
		$api_url = $peer_host_name."/api/".$game->db_game['url_identifier']."/events/1/options/".$option_index;
		$api_response = json_decode($app->safe_fetch_url($api_url));
		echo $api_url."\n";
		
		if ($api_response->status_code == 1) {
			$recommended_entity_type = $app->check_set_entity_type($api_response->option->entity_type);
			$recommended_entity = $app->check_set_entity($recommended_entity_type['entity_type_id'], $api_response->option->entity);
			
			if (empty($recommended_entity['default_image_id'])) {
				$db_image = $app->set_entity_image_from_url($api_response->option->image_url, $recommended_entity['entity_id'], $error_message);
			}
			else $error_message .= $imageless_option['name']." already has an image.\n";
		}
		else $error_message .= "Failed to set image for ".$imageless_option['name'].": ".$api_url."\n";
	}
}
else $error_message = "Invalid game ID.\n";

echo $error_message."\n";
