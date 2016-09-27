<?php
$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "reset_password") {
	include("reset_password.php");
}
else if ($uri_parts[1] == "wallet") {
	include("wallet.php");
}
else if ($uri_parts[1] == "accounts") {
	include("accounts.php");
}
else if ($uri_parts[1] == "event_types") {
	include("matches.php");
}
else if ($uri_parts[1] == "api") {
	include("api.php");
}
else if ($uri_parts[1] == "explorer") {
	include("explorer.php");
}
else if ($uri_parts[1] == "download") {
	include("download.php");
}
else {
	include("includes/connect.php");
	include("includes/get_session.php");
	
	$q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($uri_parts[1]).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$db_game = $r->fetch();
		
		if (in_array($db_game['game_status'], array("running","published","completed"))) {
			$game = new Game($app, $db_game['game_id']);
			include("game_page.php");
		}
		else echo "404 - Page not found";
	}
	else echo "404 - Page not found";
}
?>