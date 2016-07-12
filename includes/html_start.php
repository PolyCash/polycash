<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	
	<title><?php if (!empty($pagetitle)) echo $pagetitle; ?></title>
	
	<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="/css/style.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.ui.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.nouislider.css" />
	
	<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
	<script type="text/javascript" src="/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/js/jquery.ui.js"></script>
	<script type="text/javascript" src="/js/jquery.nouislider.js"></script>
	<script type="text/javascript" src="/js/sha256.js"></script>
	<script type="text/javascript" src="/js/main.js"></script>

	<?php
	if (!empty($include_crypto_js)) { ?>
	<script type="text/javascript" src="/js/base64.lib.js" ></script>
	<script type="text/javascript" src="/js/rsa/prng4.js"></script>
	<script type="text/javascript" src="/js/rsa/rng.js"></script>
	<script type="text/javascript" src="/js/rsa/rsa.js"></script>
	<script type="text/javascript" src="/js/rsa/rsa2.js"></script>
	<script type="text/javascript" src="/js/rsa/base64.js"></script>
	<script type="text/javascript" src="/js/rsa/jsbn.js"></script>
	<script type="text/javascript" src="/js/rsa/jsbn2.js"></script>
	<?php
	}
	if ($GLOBALS['signup_captcha_required']) { ?>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	<?php
	}
	?>
	<meta property="og:image" content="http://empirecoin.org/img/logo/icon-150x150.png"/>
</head>
<body>
	<nav class="navbar navbar-default navbar-fixed-top">
		<div class="container-fluid">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="/"><img alt="EmpireCoin Logo" id="nav_logo" src="/img/logo/icon-35x35.png" />EmpireCoin</a>
			</div>
			<div id="navbar" class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['action'] != "logout") {
						echo ' class="active"';
					}
					?>><a href="/wallet/<?php
					if ($nav_tab_selected == "wallet") {}
					else if ($game) echo $game->db_game['url_identifier']."/";
					else {
						$primary_game = new Game($app, $app->get_site_constant('primary_game_id'));
						echo $primary_game->db_game['url_identifier']."/";
					}
					?>"><?php if ($thisuser) echo "Wallet"; else echo "Log In"; ?></a></li>
					<?php if ($game) { ?><li<?php if ($nav_tab_selected == "game_homepage") echo ' class="active"'; ?>><a href="/<?php echo $game->db_game['url_identifier']; ?>/">About</a></li><?php } ?>
					<li<?php if ($nav_tab_selected == "explorer") echo ' class="active"'; ?>><a href="/explorer/<?php if ($game) echo $game->db_game['url_identifier']."/"; ?>">Explorer</a></li>
					<?php
					if ($thisuser || (isset($_REQUEST['action']) && $_REQUEST['action'] == "logout")) { ?>
						<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['action'] == "logout") echo ' class="active"'; ?>><a href="/wallet/?action=logout">Log Out</a></li>
						<?php
					}
					?>
				</ul>
			</div>
		</div>
	</nav>
