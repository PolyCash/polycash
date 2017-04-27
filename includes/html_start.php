<?php
if (empty($nav_tab_selected)) $nav_tab_selected = "";
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	
	<title><?php if (!empty($pagetitle)) echo $pagetitle; ?></title>
	
	<link rel="stylesheet" type="text/css" href="/css/bootstrap.min.css" media="screen" />
	<link rel="stylesheet" type="text/css" href="/css/style.css<?php if (!empty($GLOBALS['cachebuster'])) echo '?v='.$GLOBALS['cachebuster']; ?>" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.ui.css" />
	<link rel="stylesheet" type="text/css" href="/css/jquery.nouislider.css" />
	
	<script type="text/javascript" src="/js/jquery-1.11.3.js"></script>
	<script type="text/javascript" src="/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/js/jquery.ui.js"></script>
	<script type="text/javascript" src="/js/jquery.nouislider.js"></script>
	<script type="text/javascript" src="/js/sha256.js"></script>
	<script type="text/javascript" src="/js/main.js<?php if (!empty($GLOBALS['cachebuster'])) echo '?v='.$GLOBALS['cachebuster']; ?>"></script>
	<?php
	if ($nav_tab_selected == "home" && $GLOBALS['site_domain'] != $_SERVER['HTTP_HOST']) {
		echo '<link rel="canonical" href="http://coinblock.org">'."\n";
	}
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
	<meta property="og:image" content="<?php echo $GLOBALS['base_url']; ?>/images/logo/icon-150x150.png"/>
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
				<a class="navbar-brand" href="/"><?php if (!empty($GLOBALS['navbar_icon_path'])) echo '<img alt="'.$GLOBALS['coin_brand_name'].' Logo" id="nav_logo" src="'.$GLOBALS['navbar_icon_path'].'" />'; ?><?php echo $GLOBALS['coin_brand_name']; ?></a>
			</div>
			<div id="navbar" class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['action'] != "logout") {
						echo ' class="active"';
					}
					?>><a href="/wallet/<?php
					if ($nav_tab_selected == "wallet") {}
					else if (!empty($game)) echo $game->db_game['url_identifier']."/";
					?>"><?php if (!empty($thisuser)) echo "Wallet"; else echo "Log In"; ?></a></li>
					<?php if (!empty($game)) { ?><li<?php if ($nav_tab_selected == "game_page") echo ' class="active"'; ?>><a href="/<?php echo $game->db_game['url_identifier']; ?>/">About</a></li><?php } ?>
					<li<?php if ($nav_tab_selected == "explorer") echo ' class="active"'; ?>><a href="/explorer/<?php if (!empty($game)) echo "games/".$game->db_game['url_identifier']."/blocks/"; ?>">Explorer</a></li>
					<li<?php if ($nav_tab_selected == "accounts") echo ' class="active"'; ?>><a href="/accounts/">My Accounts</a></li>
					<li<?php if ($nav_tab_selected == "api") echo ' class="active"'; ?>><a href="/api/">API</a></li>
					<li<?php if ($nav_tab_selected == "download") echo ' class="active"'; ?>><a href="/download/">Download</a></li>
					<?php
					if (!empty($thisuser) || (isset($_REQUEST['action']) && $_REQUEST['action'] == "logout")) { ?>
						<li<?php if ($nav_tab_selected == "wallet" && $_REQUEST['action'] == "logout") echo ' class="active"'; ?>><a href="/wallet/?action=logout">Log Out</a></li>
						<?php
					}
					?>
				</ul>
			</div>
		</div>
	</nav>
