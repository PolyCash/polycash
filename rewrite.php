<?php
$uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode("/", $uri);

if ($uri_parts[1] == "reset_password") {
	include("reset_password.php");
}
else if ($uri_parts[1] == "about") {
	include("pages/about.php");
}
else if ($uri_parts[1] == "faq") {
	include("pages/faq.php");
}
else if ($uri_parts[1] == "unsubscribe") {
	include("unsubscribe.php");
}
else if ($uri_parts[1] == "wallet") {
	include("wallet.php");
}
else if ($uri_parts[1] == "accounts") {
	include("accounts.php");
}
else if ($uri_parts[1] == "profile") {
	include("manage_profile.php");
}
else if ($uri_parts[1] == "cards") {
	include("cards.php");
}
else if ($uri_parts[1] == "redeem" || $uri_parts[1] == "check") {
	include("redeem_card.php");
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
else if ($uri_parts[1] == "import") {
	include("import_game.php");
}
else if ($uri_parts[1] == "directory") {
	include("directory.php");
}
else if ($uri_parts[1] == "manage") {
	include("manage_game.php");
}
else if ($uri_parts[1] == "groups") {
	include("manage_groups.php");
}
else {
	include("includes/connect.php");
	include("includes/get_session.php");
	
	$q = "SELECT * FROM categories WHERE category_level=0 AND url_identifier=".$app->quote_escape($uri_parts[1]).";";
	$r = $app->run_query($q);
	
	if ($r->rowCount() == 1) {
		$selected_category = $r->fetch();
		include("directory.php");
	}
	else {
		$q = "SELECT * FROM games WHERE url_identifier=".$app->quote_escape($uri_parts[1]).";";
		$r = $app->run_query($q);
		
		if ($r->rowCount() == 1) {
			$db_game = $r->fetch();
			
			if (in_array($db_game['game_status'], array("running","published","completed"))) {
				$blockchain = new Blockchain($app, $db_game['blockchain_id']);
				$game = new Game($blockchain, $db_game['game_id']);
				include("game_page.php");
			}
			else echo "404 - Page not found";
		}
		else echo "404 - Page not found";
	}
}
?>