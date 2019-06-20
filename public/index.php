<?php
$src_path = realpath(dirname(dirname(__FILE__)))."/src";
require($src_path."/classes/Router.php");
require($src_path."/classes/AppSettings.php");
AppSettings::load();

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

$extension_pos = strpos($uri, ".php");
if ($extension_pos !== false) {
	$requested_filename = substr($uri, 0, $extension_pos).".php";
}
else $requested_filename = false;

if (count($uri_parts) < 2 || $uri_parts[1] == "") {
	// App home page
	include($src_path."/includes/connect.php");
	include($src_path."/includes/get_session.php");

	if (!empty(AppSettings::getParam('homepage_fname'))) include($src_path."/pages/".AppSettings::getParam('homepage_fname'));
	else include($src_path."/pages/default.php");
}
else if ($requested_filename && is_file($src_path.$requested_filename)) {
	include($src_path.$requested_filename);
}
else if ($uri_parts[1] == "reset_password") {
	include($src_path."/reset_password.php");
}
else if ($uri_parts[1] == "about") {
	include($src_path."/pages/about.php");
}
else if ($uri_parts[1] == "faq") {
	include($src_path."/pages/faq.php");
}
else if ($uri_parts[1] == "unsubscribe") {
	include($src_path."/unsubscribe.php");
}
else if ($uri_parts[1] == "wallet") {
	include($src_path."/wallet.php");
}
else if ($uri_parts[1] == "accounts") {
	include($src_path."/accounts.php");
}
else if ($uri_parts[1] == "profile") {
	include($src_path."/manage_profile.php");
}
else if ($uri_parts[1] == "cards") {
	include($src_path."/cards.php");
}
else if ($uri_parts[1] == "redeem" || $uri_parts[1] == "check") {
	include($src_path."/redeem_card.php");
}
else if ($uri_parts[1] == "event_types") {
	include($src_path."/matches.php");
}
else if ($uri_parts[1] == "api") {
	include($src_path."/api.php");
}
else if ($uri_parts[1] == "explorer") {
	include($src_path."/explorer.php");
}
else if ($uri_parts[1] == "download") {
	include($src_path."/download.php");
}
else if ($uri_parts[1] == "import") {
	include($src_path."/import_game.php");
}
else if ($uri_parts[1] == "directory") {
	include($src_path."/directory.php");
}
else if ($uri_parts[1] == "manage") {
	include($src_path."/manage_game.php");
}
else if ($uri_parts[1] == "groups") {
	include($src_path."/manage_groups.php");
}
else {
	include($src_path."/includes/connect.php");
	include($src_path."/includes/get_session.php");
	
	$selected_category = $app->run_query("SELECT * FROM categories WHERE category_level=0 AND url_identifier=".$app->quote_escape($uri_parts[1]).";")->fetch();
	
	if ($selected_category) {
		include($src_path."/directory.php");
	}
	else {
		$db_game = $app->fetch_db_game_by_identifier($uri_parts[1]);
		
		if ($db_game) {
			if (in_array($db_game['game_status'], ["running","published","completed"])) {
				$blockchain = new Blockchain($app, $db_game['blockchain_id']);
				$game = new Game($blockchain, $db_game['game_id']);
				include($src_path."/game_page.php");
			}
			else Router::Send404();
		}
		else Router::Send404();
	}
}
?>