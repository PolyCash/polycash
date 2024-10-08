<?php
$src_path = realpath(dirname(__DIR__));
require($src_path."/models/Router.php");
require($src_path."/models/AppSettings.php");
AppSettings::load();

$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if (count($uri_parts) < 2 || $uri_parts[1] == "") {
	// App home page
	require($src_path."/includes/connect.php");
	require($src_path."/includes/get_session.php");

	if (!empty(AppSettings::getParam('homepage_fname'))) include($src_path."/pages/".AppSettings::getParam('homepage_fname'));
	else include($src_path."/pages/default.php");
}
else if ($uri_parts[1] == "unsubscribe") {
	include($src_path."/unsubscribe.php");
}
else if ($uri_parts[1] == "privacy-policy") {
	include($src_path."/privacy-policy.php");
}
else if ($uri_parts[1] == "terms-of-use") {
	include($src_path."/terms-of-use.php");
}
else if ($uri_parts[1] == "register") {
	include($src_path."/register.php");
}
else if ($uri_parts[1] == "login") {
	include($src_path."/login.php");
}
else if ($uri_parts[1] == "wallet") {
	include($src_path."/wallet.php");
}
else if ($uri_parts[1] == "accounts" && !empty($uri_parts[2]) && $uri_parts[2] == "backups") {
	include($src_path."/backup_history.php");
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
else if ($uri_parts[1] == "api") {
	include($src_path."/api.php");
}
else if ($uri_parts[1] == "explorer") {
	include($src_path."/explorer.php");
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
else if ($uri_parts[1] == "manage_blockchains") {
	include($src_path."/manage_blockchains.php");
}
else if ($uri_parts[1] == "manage_currencies") {
	include($src_path."/manage_currencies.php");
}
else if ($uri_parts[1] == "analytics") {
	include($src_path."/analytics.php");
}
else if ($uri_parts[1] == "peers") {
	include($src_path."/peers.php");
}
else if ($uri_parts[1] == "groups") {
	include($src_path."/manage_groups.php");
}
else {
	$extension_pos = strpos($uri, ".php");
	if ($extension_pos !== false) {
		$requested_filename = substr($uri, 0, $extension_pos).".php";
	}
	else $requested_filename = false;

	if ($requested_filename && is_file($src_path.$requested_filename)) {
		$whitelisted_scripts = [
			$src_path."/scripts/main.php",
			$src_path."/scripts/reset_blockchain.php",
			$src_path."/scripts/verify_api.php",
		];

		$whitelisted_directories = [
			$src_path,
			$src_path."/ajax",
			$src_path."/cron",
			$src_path."/strategies",
		];

		if (AppSettings::allowRunScriptsByUrl()) array_push($whitelisted_directories, $src_path."/scripts");

		if (in_array(dirname($src_path.$requested_filename), $whitelisted_directories) || in_array($src_path.$requested_filename, $whitelisted_scripts) || dirname(dirname($src_path.$requested_filename)) == $src_path."/modules") {
			include($src_path.$requested_filename);
		}
		else Router::Send404();
	}
	else {
		require($src_path."/includes/connect.php");
		require($src_path."/includes/get_session.php");
		
		$selected_category = $app->run_query("SELECT * FROM categories WHERE category_level=0 AND url_identifier=:url_identifier;", [
			'url_identifier' => $uri_parts[1]
		])->fetch();
		
		if ($selected_category) {
			include($src_path."/directory.php");
		}
		else {
			$db_game = $app->fetch_game_by_identifier($uri_parts[1]);
			
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
}
